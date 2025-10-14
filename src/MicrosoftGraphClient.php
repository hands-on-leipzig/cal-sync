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
    private $accessToken;
    private $tokenExpiresAt;
    
    public function __construct() {
        $this->tenantId = $_ENV['MICROSOFT_TENANT_ID'];
        $this->clientId = $_ENV['MICROSOFT_CLIENT_ID'];
        $this->clientSecret = $_ENV['MICROSOFT_CLIENT_SECRET'];
        
        if (!$this->tenantId || !$this->clientId || !$this->clientSecret) {
            throw new Exception('Microsoft Graph credentials not configured');
        }
        
        $this->graph = new \Microsoft\Graph\GraphServiceClient();
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
        
        // Note: The new Graph SDK v2 has a different API structure
        // For now, we'll implement a basic version that works with the new SDK
        // This would need to be updated based on the specific v2 API methods
        
        try {
            // Using the new GraphServiceClient API structure
            // This is a simplified implementation - you may need to adjust based on actual v2 API
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
            
            // This is a placeholder - the actual v2 API call would be different
            // You'll need to check the Microsoft Graph SDK v2 documentation for the correct method
            throw new Exception("Microsoft Graph SDK v2 API calls need to be updated - please check documentation");
            
        } catch (Exception $e) {
            throw new Exception("Failed to get free/busy data: " . $e->getMessage());
        }
    }
    
    /**
     * Create a calendar event (busy time)
     */
    public function createEvent($userEmail, $subject, $startTime, $endTime, $isAllDay = false) {
        $this->authenticate();
        
        // Note: Microsoft Graph SDK v2 API calls need to be updated
        // This is a placeholder implementation
        throw new Exception("Microsoft Graph SDK v2 API calls need to be updated - please check documentation");
    }
    
    /**
     * Delete a calendar event
     */
    public function deleteEvent($userEmail, $eventId) {
        $this->authenticate();
        
        // Note: Microsoft Graph SDK v2 API calls need to be updated
        // This is a placeholder implementation
        throw new Exception("Microsoft Graph SDK v2 API calls need to be updated - please check documentation");
    }
    
    /**
     * Get calendar events for a user
     */
    public function getEvents($userEmail, $startTime, $endTime) {
        $this->authenticate();
        
        // Note: Microsoft Graph SDK v2 API calls need to be updated
        // This is a placeholder implementation
        throw new Exception("Microsoft Graph SDK v2 API calls need to be updated - please check documentation");
    }
}
