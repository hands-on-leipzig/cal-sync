<?php
/**
 * Microsoft Graph API client for calendar operations
 */

use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Model\ScheduleInformation;

class MicrosoftGraphClient {
    private $graph;
    private $tenantId;
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $tokenExpiresAt;
    
    public function __construct() {
        $this->tenantId = $_ENV['MICROSOFT_TENANT_ID'];
        $this->clientId = $_ENV['MICROSOFT_CLIENT_ID'];
        $this->clientSecret = $_ENV['MICROSOFT_CLIENT_SECRET'];
        
        if (!$this->tenantId || !$this->clientId || !$this->clientSecret) {
            throw new Exception('Microsoft Graph credentials not configured');
        }
        
        $this->graph = new Graph();
    }
    
    private function authenticate() {
        // Check if we have a valid token
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            $this->graph->setAccessToken($this->accessToken);
            return;
        }
        
        // Get new token using client credentials flow
        $guzzle = new \GuzzleHttp\Client();
        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        
        try {
            $response = $guzzle->post($url, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);
            
            $tokenData = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($tokenData['access_token'])) {
                throw new Exception('Failed to obtain access token');
            }
            
            $this->accessToken = $tokenData['access_token'];
            $this->tokenExpiresAt = time() + ($tokenData['expires_in'] ?? 3600) - 60; // 60 second buffer
            
            $this->graph->setAccessToken($this->accessToken);
            
        } catch (Exception $e) {
            throw new Exception("Authentication failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get free/busy information for a user
     */
    public function getFreeBusy($userEmail, $startTime, $endTime) {
        $this->authenticate();
        
        $requestBody = [
            'schedules' => [$userEmail],
            'startTime' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => $_ENV['DEFAULT_TIMEZONE'] ?? 'UTC',
            ],
            'endTime' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => $_ENV['DEFAULT_TIMEZONE'] ?? 'UTC',
            ],
            'availabilityViewInterval' => 30,
        ];
        
        try {
            $response = $this->graph->createRequest('POST', '/me/calendar/getSchedule')
                ->attachBody($requestBody)
                ->setReturnType(ScheduleInformation::class)
                ->execute();
            
            return $response;
            
        } catch (Exception $e) {
            throw new Exception("Failed to get free/busy data: " . $e->getMessage());
        }
    }
    
    /**
     * Create a calendar event (busy time)
     */
    public function createEvent($userEmail, $subject, $startTime, $endTime, $isAllDay = false) {
        $this->authenticate();
        
        $eventData = [
            'subject' => $subject,
            'start' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => $_ENV['DEFAULT_TIMEZONE'] ?? 'UTC',
            ],
            'end' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => $_ENV['DEFAULT_TIMEZONE'] ?? 'UTC',
            ],
            'isAllDay' => $isAllDay,
            'showAs' => 'busy',
        ];
        
        try {
            $response = $this->graph->createRequest('POST', "/users/{$userEmail}/events")
                ->attachBody($eventData)
                ->setReturnType(\Microsoft\Graph\Model\Event::class)
                ->execute();
            
            return $response;
            
        } catch (Exception $e) {
            throw new Exception("Failed to create event: " . $e->getMessage());
        }
    }
    
    /**
     * Delete a calendar event
     */
    public function deleteEvent($userEmail, $eventId) {
        $this->authenticate();
        
        try {
            $this->graph->createRequest('DELETE', "/users/{$userEmail}/events/{$eventId}")
                ->execute();
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Failed to delete event: " . $e->getMessage());
        }
    }
    
    /**
     * Get calendar events for a user
     */
    public function getEvents($userEmail, $startTime, $endTime) {
        $this->authenticate();
        
        $startTimeStr = $startTime->format('c');
        $endTimeStr = $endTime->format('c');
        
        try {
            $response = $this->graph->createRequest('GET', "/users/{$userEmail}/events")
                ->addQuery('startDateTime', $startTimeStr)
                ->addQuery('endDateTime', $endTimeStr)
                ->setReturnType(\Microsoft\Graph\Model\Event::class)
                ->execute();
            
            return $response;
            
        } catch (Exception $e) {
            throw new Exception("Failed to get events: " . $e->getMessage());
        }
    }
}
