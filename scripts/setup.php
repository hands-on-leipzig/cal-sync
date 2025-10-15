#!/usr/bin/env php
<?php
/**
 * Setup script for initial configuration
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} else {
    echo "Error: .env file not found. Please copy .env.example to .env and configure it.\n";
    exit(1);
}

use CalSync\Database;

try {
    $db = Database::getInstance();
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->query($statement);
        }
    }
    
    echo "Database schema created successfully!\n";
    
    // Create storage directories
    $directories = [
        __DIR__ . '/../storage',
        __DIR__ . '/../storage/logs',
        __DIR__ . '/../storage/cache',
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "Created directory: $dir\n";
        }
    }
    
    echo "Setup completed successfully!\n";
    echo "Next steps:\n";
    echo "1. Configure your Microsoft Graph API credentials in .env\n";
    echo "2. Set up your cron job to run scripts/sync.php\n";
    echo "3. Access the web interface at web/index.php\n";
    
} catch (\Exception $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
