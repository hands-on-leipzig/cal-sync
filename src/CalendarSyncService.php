<?php
/**
 * Calendar Sync Service - Main sync logic
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MicrosoftGraphClient.php';

use CalSync\Database;
use CalSync\MicrosoftGraphClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CalendarSyncService {
    private $db;
    private $graphClient;
    private $logger;
    
    public function __construct() {
        // Load environment variables
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }
        
        $this->db = Database::getInstance();
        $this->graphClient = new MicrosoftGraphClient();
        
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
            } catch (Exception $e) {
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
            
            // Sync based on direction
            switch ($config['sync_direction']) {
                case 'source_to_target':
                    $result = $this->syncSourceToTarget($config, $startTime, $endTime);
                    break;
                case 'target_to_source':
                    $result = $this->syncTargetToSource($config, $startTime, $endTime);
                    break;
                case 'bidirectional':
                    $result1 = $this->syncSourceToTarget($config, $startTime, $endTime);
                    $result2 = $this->syncTargetToSource($config, $startTime, $endTime);
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
            
        } catch (Exception $e) {
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
     * Sync from source to target
     */
    private function syncSourceToTarget($config, $startTime, $endTime) {
        $this->logger->info("Syncing from {$config['source_email']} to {$config['target_email']}");
        
        // Get events from source
        $sourceEvents = $this->graphClient->getEvents($config['source_email'], $startTime, $endTime);
        
        $processed = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        
        foreach ($sourceEvents as $event) {
            $processed++;
            
            // Check if event already exists in target
            $existingEvent = $this->db->fetchOne(
                'SELECT * FROM calendar_events WHERE microsoft_event_id = ? AND sync_config_id = ? AND sync_direction = ?',
                [$event->getId(), $config['id'], 'source_to_target']
            );
            
            if ($existingEvent) {
                // Update existing event
                $this->updateTargetEvent($config, $event, $existingEvent);
                $updated++;
            } else {
                // Create new event in target
                $this->createTargetEvent($config, $event);
                $created++;
            }
        }
        
        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }
    
    /**
     * Sync from target to source
     */
    private function syncTargetToSource($config, $startTime, $endTime) {
        $this->logger->info("Syncing from {$config['target_email']} to {$config['source_email']}");
        
        // Get events from target
        $targetEvents = $this->graphClient->getEvents($config['target_email'], $startTime, $endTime);
        
        $processed = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        
        foreach ($targetEvents as $event) {
            $processed++;
            
            // Check if event already exists in source
            $existingEvent = $this->db->fetchOne(
                'SELECT * FROM calendar_events WHERE microsoft_event_id = ? AND sync_config_id = ? AND sync_direction = ?',
                [$event->getId(), $config['id'], 'target_to_source']
            );
            
            if ($existingEvent) {
                // Update existing event
                $this->updateSourceEvent($config, $event, $existingEvent);
                $updated++;
            } else {
                // Create new event in source
                $this->createSourceEvent($config, $event);
                $created++;
            }
        }
        
        return ['processed' => $processed, 'created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }
    
    /**
     * Create event in target calendar
     */
    private function createTargetEvent($config, $sourceEvent) {
        $startTime = new DateTime($sourceEvent->getStart()->getDateTime());
        $endTime = new DateTime($sourceEvent->getEnd()->getDateTime());
        
        $newEvent = $this->graphClient->createEvent(
            $config['target_email'],
            '[SYNC] ' . $sourceEvent->getSubject(),
            $startTime,
            $endTime,
            $sourceEvent->getIsAllDay()
        );
        
        // Store in database
        $this->db->query(
            'INSERT INTO calendar_events (sync_config_id, microsoft_event_id, subject, start_time, end_time, is_all_day, show_as, source_email, target_email, sync_direction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $config['id'],
                $newEvent->getId(),
                $sourceEvent->getSubject(),
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $sourceEvent->getIsAllDay() ? 1 : 0,
                $sourceEvent->getShowAs(),
                $config['source_email'],
                $config['target_email'],
                'source_to_target'
            ]
        );
    }
    
    /**
     * Create event in source calendar
     */
    private function createSourceEvent($config, $targetEvent) {
        $startTime = new DateTime($targetEvent->getStart()->getDateTime());
        $endTime = new DateTime($targetEvent->getEnd()->getDateTime());
        
        $newEvent = $this->graphClient->createEvent(
            $config['source_email'],
            '[SYNC] ' . $targetEvent->getSubject(),
            $startTime,
            $endTime,
            $targetEvent->getIsAllDay()
        );
        
        // Store in database
        $this->db->query(
            'INSERT INTO calendar_events (sync_config_id, microsoft_event_id, subject, start_time, end_time, is_all_day, show_as, source_email, target_email, sync_direction) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $config['id'],
                $newEvent->getId(),
                $targetEvent->getSubject(),
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $targetEvent->getIsAllDay() ? 1 : 0,
                $targetEvent->getShowAs(),
                $config['source_email'],
                $config['target_email'],
                'target_to_source'
            ]
        );
    }
    
    /**
     * Update event in target calendar
     */
    private function updateTargetEvent($config, $sourceEvent, $existingEvent) {
        // For now, we'll just log that an update would happen
        // In a full implementation, you'd update the event via Graph API
        $this->logger->info("Would update event {$existingEvent['microsoft_event_id']} in target calendar");
    }
    
    /**
     * Update event in source calendar
     */
    private function updateSourceEvent($config, $targetEvent, $existingEvent) {
        // For now, we'll just log that an update would happen
        // In a full implementation, you'd update the event via Graph API
        $this->logger->info("Would update event {$existingEvent['microsoft_event_id']} in source calendar");
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
