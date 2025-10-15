<?php
/**
 * Google Calendar API client for calendar operations
 */

namespace CalSync;

class GoogleCalendarClient {
    private $client;
    private $service;
    private $credentialsPath;
    
    public function __construct() {
        $this->credentialsPath = $_ENV['GOOGLE_CREDENTIALS_PATH'] ?? __DIR__ . '/../storage/google-credentials.json';
        
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Google credentials file not found. Please set GOOGLE_CREDENTIALS_PATH or place credentials.json in storage/');
        }
        
        $this->client = new \Google\Client();
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->addScope(\Google\Service\Calendar::CALENDAR);
        
        $this->service = new \Google\Service\Calendar($this->client);
    }
    
    /**
     * Get events from a Google Calendar
     */
    public function getEvents($calendarId, $startTime, $endTime) {
        try {
            $optParams = [
                'timeMin' => $startTime->format('c'),
                'timeMax' => $endTime->format('c'),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];
            
            $results = $this->service->events->listEvents($calendarId, $optParams);
            $events = $results->getItems();
            
            return $events ?: [];
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to get Google Calendar events: " . $e->getMessage());
        }
    }
    
    /**
     * Create an event in Google Calendar
     */
    public function createEvent($calendarId, $subject, $startTime, $endTime, $isAllDay = false) {
        try {
            $event = new \Google\Service\Calendar\Event();
            $event->setSummary('[SYNC] ' . $subject);
            $event->setDescription('Synced from external calendar');
            
            if ($isAllDay) {
                $start = new \Google\Service\Calendar\EventDateTime();
                $start->setDate($startTime->format('Y-m-d'));
                $event->setStart($start);
                
                $end = new \Google\Service\Calendar\EventDateTime();
                $end->setDate($endTime->format('Y-m-d'));
                $event->setEnd($end);
            } else {
                $start = new \Google\Service\Calendar\EventDateTime();
                $start->setDateTime($startTime->format('c'));
                $start->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setStart($start);
                
                $end = new \Google\Service\Calendar\EventDateTime();
                $end->setDateTime($endTime->format('c'));
                $end->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setEnd($end);
            }
            
            $createdEvent = $this->service->events->insert($calendarId, $event);
            
            return $createdEvent;
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to create Google Calendar event: " . $e->getMessage());
        }
    }
    
    /**
     * Update an event in Google Calendar
     */
    public function updateEvent($calendarId, $eventId, $subject, $startTime, $endTime, $isAllDay = false) {
        try {
            // Get existing event
            $event = $this->service->events->get($calendarId, $eventId);
            
            $event->setSummary('[SYNC] ' . $subject);
            
            if ($isAllDay) {
                $start = new \Google\Service\Calendar\EventDateTime();
                $start->setDate($startTime->format('Y-m-d'));
                $event->setStart($start);
                
                $end = new \Google\Service\Calendar\EventDateTime();
                $end->setDate($endTime->format('Y-m-d'));
                $event->setEnd($end);
            } else {
                $start = new \Google\Service\Calendar\EventDateTime();
                $start->setDateTime($startTime->format('c'));
                $start->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setStart($start);
                
                $end = new \Google\Service\Calendar\EventDateTime();
                $end->setDateTime($endTime->format('c'));
                $end->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setEnd($end);
            }
            
            $updatedEvent = $this->service->events->update($calendarId, $eventId, $event);
            
            return $updatedEvent;
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to update Google Calendar event: " . $e->getMessage());
        }
    }
    
    /**
     * Delete an event from Google Calendar
     */
    public function deleteEvent($calendarId, $eventId) {
        try {
            $this->service->events->delete($calendarId, $eventId);
            return true;
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete Google Calendar event: " . $e->getMessage());
        }
    }
    
    /**
     * Get calendar list
     */
    public function getCalendarList() {
        try {
            $calendarList = $this->service->calendarList->listCalendarList();
            return $calendarList->getItems();
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to get Google Calendar list: " . $e->getMessage());
        }
    }
    
    /**
     * Check if calendar exists and is accessible
     */
    public function validateCalendar($calendarId) {
        try {
            $this->service->calendars->get($calendarId);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
