#!/usr/bin/env php
<?php
/**
 * Cron script for automated calendar syncing
 * Run this script via cron job for automated syncing
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/CalendarSyncService.php';

use CalSync\CalendarSyncService;

try {
    $syncService = new CalendarSyncService();
    $syncService->syncAll();
    echo "Sync completed successfully at " . date('Y-m-d H:i:s') . "\n";
} catch (Exception $e) {
    echo "Sync failed: " . $e->getMessage() . "\n";
    exit(1);
}
