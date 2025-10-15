<?php
/**
 * Microsoft Graph API client for calendar operations
 */

namespace CalSync;

class MicrosoftGraphClient {
    private $graph;
    private $tenantId;
    private $clientId;
    private $clientSecret;
    
    public function __construct() {
        $this->tenantId = $_ENV['MICROSOFT_TENANT_ID'];
        $this->clientId = $_ENV['MICROSOFT_CLIENT_ID'];
        $this->clientSecret = $_ENV['MICROSOFT_CLIENT_SECRET'];
        
        if (!$this->tenantId || !$this->clientId || !$this->clientSecret) {
            throw new \Exception('Microsoft Graph credentials not configured');
        }
        
        $this->graph = new \Microsoft\Graph\GraphServiceClient(
            new \Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext(
                $this->tenantId,
                $this->clientId,
                $this->clientSecret
            ),
            ['https://graph.microsoft.com/.default']
        );
    }
    
    /**
     * Get free/busy information for a user
     */
    public function getFreeBusy($userEmail, $startTime, $endTime) {
        try {
            // Use the getSchedule API for free/busy information
            $scheduleRequest = new \Microsoft\Graph\Generated\Users\Item\Calendar\GetSchedule\GetSchedulePostRequestBody();
            $scheduleRequest->setSchedules([$userEmail]);
            
            $startDateTime = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
            $startDateTime->setDateTime($startTime->format('c'));
            $startDateTime->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
            $scheduleRequest->setStartTime($startDateTime);
            
            $endDateTime = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
            $endDateTime->setDateTime($endTime->format('c'));
            $endDateTime->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
            $scheduleRequest->setEndTime($endDateTime);
            
            $scheduleRequest->setAvailabilityViewInterval(30);
            
            $promise = $this->graph->users()->byUserId($userEmail)->calendar()->getSchedule()->post($scheduleRequest);
            $result = $promise->wait();
            
            return $result;
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to get free/busy data: " . $e->getMessage());
        }
    }
    
    /**
     * Create a calendar event (busy time)
     */
    public function createEvent($userEmail, $subject, $startTime, $endTime, $isAllDay = false) {
        try {
            $event = new \Microsoft\Graph\Generated\Models\Event();
            $event->setSubject('[SYNC] ' . $subject);
            $event->setBody(new \Microsoft\Graph\Generated\Models\ItemBody());
            $event->getBody()->setContent('Synced from external calendar');
            $event->getBody()->setContentType(\Microsoft\Graph\Generated\Models\BodyType::TEXT);
            
            if ($isAllDay) {
                $start = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
                $start->setDateTime($startTime->format('Y-m-d'));
                $start->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setStart($start);
                
                $end = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
                $end->setDateTime($endTime->format('Y-m-d'));
                $end->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setEnd($end);
                
                $event->setIsAllDay(true);
            } else {
                $start = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
                $start->setDateTime($startTime->format('c'));
                $start->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setStart($start);
                
                $end = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
                $end->setDateTime($endTime->format('c'));
                $end->setTimeZone($_ENV['DEFAULT_TIMEZONE'] ?? 'UTC');
                $event->setEnd($end);
                
                $event->setIsAllDay(false);
            }
            
            $promise = $this->graph->users()->byUserId($userEmail)->calendar()->events()->post($event);
            $createdEvent = $promise->wait();
            
            return $createdEvent;
            
        } catch (\Exception $e) {
            // Enhanced error logging for debugging
            $errorDetails = [
                'userEmail' => $userEmail,
                'subject' => $subject,
                'startTime' => $startTime->format('c'),
                'endTime' => $endTime->format('c'),
                'isAllDay' => $isAllDay,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            
            throw new \Exception("Failed to create Microsoft Calendar event: " . $e->getMessage() . " | Details: " . json_encode($errorDetails));
        }
    }
    
    /**
     * Delete a calendar event
     */
    public function deleteEvent($userEmail, $eventId) {
        try {
            $promise = $this->graph->users()->byUserId($userEmail)->calendar()->events()->byEventId($eventId)->delete();
            $promise->wait();
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete Microsoft Calendar event: " . $e->getMessage());
        }
    }
    
    /**
     * Get calendar events for a user
     */
    public function getEvents($userEmail, $startTime, $endTime) {
        try {
            // Use the calendarView API which is designed for date range queries
            $requestConfiguration = new \Microsoft\Graph\Generated\Users\Item\Calendar\CalendarView\CalendarViewRequestBuilderGetRequestConfiguration();
            $queryParameters = new \Microsoft\Graph\Generated\Users\Item\Calendar\CalendarView\CalendarViewRequestBuilderGetQueryParameters();
            
            // Set the date range using direct property assignment
            $queryParameters->startDateTime = $startTime->format('c');
            $queryParameters->endDateTime = $endTime->format('c');
            $queryParameters->orderby = ['start/dateTime'];
            
            // Set query parameters on request configuration
            $requestConfiguration->queryParameters = $queryParameters;
            
            $promise = $this->graph->users()->byUserId($userEmail)->calendar()->calendarView()->get($requestConfiguration);
            $events = $promise->wait();
            
            return $events->getValue();
            
        } catch (\Exception $e) {
            // Enhanced error logging for debugging
            $errorDetails = [
                'userEmail' => $userEmail,
                'startTime' => $startTime->format('c'),
                'endTime' => $endTime->format('c'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            
            throw new \Exception("Failed to get Microsoft Calendar events: " . $e->getMessage() . " | Details: " . json_encode($errorDetails));
        }
    }
}
