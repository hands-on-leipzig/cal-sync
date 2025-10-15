<?php
/**
 * Debug script for Microsoft Graph API issues
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

use CalSync\MicrosoftGraphClient;

try {
    echo "=== Microsoft Graph API Debug ===\n";
    
    // Check environment variables
    echo "1. Checking environment variables:\n";
    echo "   MICROSOFT_TENANT_ID: " . (isset($_ENV['MICROSOFT_TENANT_ID']) ? 'SET' : 'NOT SET') . "\n";
    echo "   MICROSOFT_CLIENT_ID: " . (isset($_ENV['MICROSOFT_CLIENT_ID']) ? 'SET' : 'NOT SET') . "\n";
    echo "   MICROSOFT_CLIENT_SECRET: " . (isset($_ENV['MICROSOFT_CLIENT_SECRET']) ? 'SET' : 'NOT SET') . "\n";
    echo "   DEFAULT_TIMEZONE: " . ($_ENV['DEFAULT_TIMEZONE'] ?? 'NOT SET') . "\n";
    
    if (!isset($_ENV['MICROSOFT_TENANT_ID']) || !isset($_ENV['MICROSOFT_CLIENT_ID']) || !isset($_ENV['MICROSOFT_CLIENT_SECRET'])) {
        echo "\n❌ ERROR: Microsoft Graph credentials not configured!\n";
        echo "Please set MICROSOFT_TENANT_ID, MICROSOFT_CLIENT_ID, and MICROSOFT_CLIENT_SECRET in your .env file.\n";
        exit(1);
    }
    
    echo "\n2. Testing Microsoft Graph client instantiation:\n";
    $client = new MicrosoftGraphClient();
    echo "   ✅ Microsoft Graph client created successfully\n";
    
    // Test with a sample user email (you'll need to replace this with an actual user)
    $testUserEmail = 'test@yourdomain.com'; // Replace with actual user email
    $startTime = new \DateTime();
    $endTime = new \DateTime('+1 day');
    
    echo "\n3. Testing getEvents API call:\n";
    echo "   User Email: $testUserEmail\n";
    echo "   Start Time: " . $startTime->format('c') . "\n";
    echo "   End Time: " . $endTime->format('c') . "\n";
    
    try {
        $events = $client->getEvents($testUserEmail, $startTime, $endTime);
        echo "   ✅ getEvents API call successful\n";
        echo "   Events found: " . count($events) . "\n";
    } catch (\Exception $e) {
        echo "   ❌ getEvents API call failed:\n";
        echo "   Error: " . $e->getMessage() . "\n";
        
        // Check for common issues
        if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
            echo "\n   🔍 DIAGNOSIS: Authentication issue\n";
            echo "   - Check if your Microsoft Graph credentials are correct\n";
            echo "   - Verify that your app registration has the correct permissions\n";
            echo "   - Ensure the user email exists in your tenant\n";
        } elseif (strpos($e->getMessage(), '403') !== false || strpos($e->getMessage(), 'Forbidden') !== false) {
            echo "\n   🔍 DIAGNOSIS: Permission issue\n";
            echo "   - Check if your app has the required permissions (Calendars.Read, Calendars.ReadWrite)\n";
            echo "   - Verify that admin consent has been granted\n";
            echo "   - Ensure the user has granted consent to your app\n";
        } elseif (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'Not Found') !== false) {
            echo "\n   🔍 DIAGNOSIS: User or resource not found\n";
            echo "   - Verify that the user email exists in your tenant\n";
            echo "   - Check if the user has a calendar\n";
            echo "   - Ensure the user email format is correct\n";
        } elseif (strpos($e->getMessage(), '400') !== false || strpos($e->getMessage(), 'Bad Request') !== false) {
            echo "\n   🔍 DIAGNOSIS: Request format issue\n";
            echo "   - Check if the date format is correct\n";
            echo "   - Verify that the request parameters are valid\n";
        }
    }
    
    echo "\n4. Common troubleshooting steps:\n";
    echo "   - Verify your .env file has correct Microsoft Graph credentials\n";
    echo "   - Check that your Azure app registration has the right permissions\n";
    echo "   - Ensure admin consent has been granted for your app\n";
    echo "   - Verify the user email exists in your Microsoft 365 tenant\n";
    echo "   - Check that the user has a calendar\n";
    
} catch (\Exception $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}
