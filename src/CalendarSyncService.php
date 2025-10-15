<?php
/**
 * Calendar Sync Service - Main sync logic
 */

namespace CalSync;

use CalSync\Database;
use CalSync\MicrosoftGraphClient;
use CalSync\GoogleCalendarClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CalendarSyncService {
    private $db;
    private $graphClient;
    private $googleClient;
    private $logger;
    
    public function __construct() {
        // Load environment variables
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }
        
        $this->db = Database::getInstance();
        $this->graphClient = new MicrosoftGraphClient();
        
        // Initialize Google client if credentials are available
        try {
            $this->googleClient = new GoogleCalendarClient();
        } catch (\Exception $e) {
            $this->googleClient = null;
            // Log warning but don't fail - Google sync is optional
        }
        
        // Setup logging
        $this->logger = new Logger('cal_sync');
        $logFile = __DIR__ . '/../storage/logs/sync_' . date('Y-m-d') . '.log';
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
    }
    
    /**
     * Run sync for all active configurations
     */
    public function syncAll() {
        $this->logger->info('Starting full sync process');
        
        $configs = $this->db->fetchAll(
            'SELECT * FROM sync_configurations WHERE is_active = 1'
        );
        
        foreach ($configs as $config) {
            try {
                $this->syncConfiguration($config);
            } catch (\Exception $e) {
                $this->logger->error("Sync failed for config {$config['id']}: " . $e->getMessage());
                $this->logSyncResult($config['id'], 'error', 0, 0, 0, 0, $e->getMessage());
            }
        }
        
        $this->logger->info('Full sync process completed');
    }
    
    /**
     * Sync a specific configuration
     */
    public function syncConfiguration($config) {
        $this->logger->info("Starting sync for config {$config['id']}: {$config['source_email']} <-> {$config['target_email']}");
        
        $syncLogId = $this->logSyncResult($config['id'], 'success', 0, 0, 0, 0, null, 'incremental');
        
        $eventsProcessed = 0;
        $eventsCreated = 0;
        $eventsUpdated = 0;
        $eventsDeleted = 0;
        
        try {
            // Determine sync range
            $startTime = new DateTime();
            $endTime = new DateTime('+' . ($_ENV['MAX_SYNC_RANGE_DAYS'] ?? 30) . ' days');
            
            // Determine calendar types and sync accordingly
            $sourceType = $this->getCalendarType($config['source_email']);
            $targetType = $this->getCalendarType($config['target_email']);
            
            // Sync based on direction and calendar types
            switch ($config['sync_direction']) {
                case 'source_to_target':
                    $result = $this->syncSourceToTarget($config, $startTime, $endTime, $sourceType, $targetType);
                    break;
                case 'target_to_source':
                    $result = $this->syncTargetToSource($config, $startTime, $endTime, $sourceType, $targetType);
                    break;
                case 'bidirectional':
                    $result1 = $this->syncSourceToTarget($config, $startTime, $endTime, $sourceType, $targetType);
                    $result2 = $this->syncTargetToSource($config, $startTime, $endTime, $sourceType, $targetType);
                    $result = [
                        'processed' => $result1['processed'] + $result2['processed'],
                        'created' => $result1['created'] + $result2['created'],
                        'updated' => $result1['updated'] + $result2['updated'],
                        'deleted' => $result1['deleted'] + $result2['deleted'],
                    ];
                    break;
            }
            
            $eventsProcessed = $result['processed'];
            $eventsCreated = $result['created'];
            $eventsUpdated = $result['updated'];
            $eventsDeleted = $result['deleted'];
            
            // Update last sync time
            $this->db->query(
                'UPDATE sync_configurations SET last_sync_at = NOW() WHERE id = ?',
                [$config['id']]
            );
            
            $this->logger->info("Sync completed for config {$config['id']}: {$eventsProcessed} processed, {$eventsCreated} created, {$eventsUpdated} updated, {$eventsDeleted} deleted");
            
        } catch (\Exception $e) {
            $this->logger->error("Sync error for config {$config['id']}: " . $e->getMessage());
            throw $e;
        } finally {
            // Update sync log
            $this->db->query(
                'UPDATE sync_logs SET status = ?, events_processed = ?, events_created = ?, events_updated = ?, events_deleted = ?, completed_at = NOW() WHERE id = ?',
                ['success', $eventsProcessed, $eventsCreated, $eventsUpdated, $eventsDeleted, $syncLogId]
            );
        }
    }
    
    /**
     * Determine calendar type (google or microsoft)
     */
    private function getCalendarType($email) {
        // Check if it's a Google calendar ID (contains @gmail.com or @googlemail.com or is a calendar ID)
        if (strpos($email, '@gmail.com') !== false || 
            strpos($email, '@googlemail.com') !== false ||
            strpos($email, '@') === false) { // Calendar ID without @
            return 'google';
        }
        return 'microsoft';
    }
    
    /**
     * Sync from source to target
     */
    private function syncSourceToTarget($config, $startTime, $endTime, $sourceType, $targetType) {
        $this->logger->info("Syncing from {$config['source_email']} ({$sourceType}) to {$config['target_email']} ({$targetType})");
        
        // Get events from source based on calendar type
        $sourceEvents = $this->getEventsFromCalendar($config['source_email'], $startTime, $endTime, $sourceType);
        
        $processed = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        
        foreach ($sourceEvents as $event) {
            $processed++;
            
            $eventId = $this->getEventId($event, $sourceType);
            
            // Check if event already exists in target
            $existingEvent = $this->db->fetchOne(
                'SELECT * FROM calendar_events WHERE microsoft_event_id = ? AND sync_config_id = ? AND sync_direction = ?',
                [$eventId, $config['id'], 'source_to_target']
            );
            
            if ($existingEvent) {
                // Update existing event
                $this->updateTargetEvent($config, $event, $existingEvent, $sourceType, $targetType);
                $updated++;
            } else {
                // Create new event in target
                $this->createTargetEvent($config, $event, $sourceType, $targetType);
                $created++;
            }
        }
        
        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }
    
    /**
     * Sync from target to source
     */
    private function syncTargetToSource($config, $startTime, $endTime, $sourceType, $targetType) {
        $this->logger->info("Syncing from {$config['target_email']} ({$targetType}) to {$config['source_email']} ({$sourceType})");
        
        // Get events from target based on calendar type
        $targetEvents = $this->getEventsFromCalendar($config['target_email'], $startTime, $endTime, $targetType);
        
        $processed = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        
        foreach ($targetEvents as $event) {
            $processed++;
            
            $eventId = $this->getEventId($event, $targetType);
            
            // Check if event already exists in source
            $existingEvent = $this->db->fetchOne(
                'SELECT * FROM calendar_events WHERE microsoft_event_id = ? AND sync_config_id = ? AND sync_direction = ?',
                [$eventId, $config['id'], 'target_to_source']
            );
            
            if ($existingEvent) {
                // Update existing event
                $this->updateSourceEvent($config, $event, $existingEvent, $sourceType, $targetType);
                $updated++;
            } else {
                // Create new event in source
                $this->createSourceEvent($config, $event, $sourceType, $targetType);
                $created++;
            }
        }
        
        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }
    
    /**
     * Get events from calendar based on type
     */
    private function getEventsFromCalendar($calendarId, $startTime, $endTime, $type) {
        if ($type === 'google') {
            if (!$this->googleClient) {
                throw new \Exception('Google Calendar client not available');
            }
            return $this->googleClient->getEvents($calendarId, $startTime, $endTime);
        } else {
            return $this->graphClient->getEvents($calendarId, $startTime, $endTime);
        }
    }
    
    /**
     * Get event ID based on calendar type
     */
    private function getEventId($event, $type) {
        if ($type === 'google') {
            return $event->getId();
        } else {
            return $event->getId();
        }
    }
    
    /**
     * Get event details based on calendar type
     */
    private function getEventDetails($event, $type) {
        if ($type === 'google') {
            return [
                'id' => $event->getId(),
                'subject' => $event->getSummary(),
                'start' => $event->getStart(),
                'end' => $event->getEnd(),
                'isAllDay' => $event->getStart()->getDate() !== null,
                'showAs' => 'busy'
            ];
        } else {
            return [
                'id' => $event->getId(),
                'subject' => $event->getSubject(),
                'start' => $event->getStart(),
                'end' => $event->getEnd(),
                'isAllDay' => $event->getIsAllDay(),
                'showAs' => $event->getShowAs()
            ];
        }
    }
    
    /**
     * Create event in target calendar
     */
    private function createTargetEvent($config, $sourceEvent, $sourceType, $targetType) {
        $eventDetails = $this->getEventDetails($sourceEvent, $sourceType);
        
        // Parse start and end times based on calendar type
        if ($eventDetails['isAllDay']) {
            $startTime = new DateTime($eventDetails['start']->getDate());
            $endTime = new DateTime($eventDetails['end']->getDate());
        } else {
            $startTime = new DateTime($eventDetails['start']->getDateTime());
            $endTime = new DateTime($eventDetails['end']->getDateTime());
        }
        
        // Create event in target calendar based on type
        if ($targetType === 'google') {
            if (!$this->googleClient) {
                throw new \Exception('Google Calendar client not available');
            }
            $newEvent = $this->googleClient->createEvent(
                $config['target_email'],
                $eventDetails['subject'],
                $startTime,
                $endTime,
                $eventDetails['isAllDay']
            );
        } else {
            $newEvent = $this->graphClient->createEvent(
                $config['target_email'],
                $eventDetails['subject'],
                $startTime,
                $endTime,
                $eventDetails['isAllDay']
            );
        }
        
        // Store in database
        $this->db->query(
            'INSERT INTO calendar_events (sync_config_id, microsoft_event_id, subject, start_time, end_time, is_all_day, show_as, source_email, target_email, sync_direction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $config['id'],
                $newEvent->getId(),
                $eventDetails['subject'],
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $eventDetails['isAllDay'] ? 1 : 0,
                $eventDetails['showAs'],
                $config['source_email'],
                $config['target_email'],
                'source_to_target'
            ]
        );
    }
    
    /**
     * Create event in source calendar
     */
    private function createSourceEvent($config, $targetEvent, $sourceType, $targetType) {
        $eventDetails = $this->getEventDetails($targetEvent, $targetType);
        
        // Parse start and end times based on calendar type
        if ($eventDetails['isAllDay']) {
            $startTime = new DateTime($eventDetails['start']->getDate());
            $endTime = new DateTime($eventDetails['end']->getDate());
        } else {
            $startTime = new DateTime($eventDetails['start']->getDateTime());
            $endTime = new DateTime($eventDetails['end']->getDateTime());
        }
        
        // Create event in source calendar based on type
        if ($sourceType === 'google') {
            if (!$this->googleClient) {
                throw new \Exception('Google Calendar client not available');
            }
            $newEvent = $this->googleClient->createEvent(
                $config['source_email'],
                $eventDetails['subject'],
                $startTime,
                $endTime,
                $eventDetails['isAllDay']
            );
        } else {
            $newEvent = $this->graphClient->createEvent(
                $config['source_email'],
                $eventDetails['subject'],
                $startTime,
                $endTime,
                $eventDetails['isAllDay']
            );
        }
        
        // Store in database
        $this->db->query(
            'INSERT INTO calendar_events (sync_config_id, microsoft_event_id, subject, start_time, end_time, is_all_day, show_as, source_email, target_email, sync_direction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $config['id'],
                $newEvent->getId(),
                $eventDetails['subject'],
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $eventDetails['isAllDay'] ? 1 : 0,
                $eventDetails['showAs'],
                $config['source_email'],
                $config['target_email'],
                'target_to_source'
            ]
        );
    }
    
    /**
     * Update event in target calendar
     */
    private function updateTargetEvent($config, $sourceEvent, $existingEvent, $sourceType, $targetType) {
        // For now, we'll just log that an update would happen
        // In a full implementation, you'd update the event via the appropriate API
        $this->logger->info("Would update event {$existingEvent['microsoft_event_id']} in target calendar ({$targetType})");
    }
    
    /**
     * Update event in source calendar
     */
    private function updateSourceEvent($config, $targetEvent, $existingEvent, $sourceType, $targetType) {
        // For now, we'll just log that an update would happen
        // In a full implementation, you'd update the event via the appropriate API
        $this->logger->info("Would update event {$existingEvent['microsoft_event_id']} in source calendar ({$sourceType})");
    }
    
    /**
     * Log sync result
     */
    private function logSyncResult($configId, $status, $processed, $created, $updated, $deleted, $errorMessage = null, $syncType = 'incremental') {
        $this->db->query(
            'INSERT INTO sync_logs (sync_config_id, sync_type, status, events_processed, events_created, events_updated, events_deleted, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$configId, $syncType, $status, $processed, $created, $updated, $deleted, $errorMessage]
        );
        
        return $this->db->lastInsertId();
    }
}
