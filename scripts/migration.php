<?php
/**
 * Database Migration Script
 * Handles schema updates for Google Calendar support
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use CalSync\Database;

class DatabaseMigration {
    private $db;
    private $version;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->version = $this->getCurrentVersion();
    }
    
    /**
     * Get current database version
     */
    private function getCurrentVersion() {
        try {
            $result = $this->db->fetchOne('SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1');
            return $result ? $result['version'] : '0.0.0';
        } catch (\Exception $e) {
            // Table doesn't exist, return initial version
            return '0.0.0';
        }
    }
    
    /**
     * Record migration version
     */
    private function recordMigration($version, $description) {
        try {
            $this->db->query(
                'INSERT INTO schema_migrations (version, description, executed_at) VALUES (?, ?, NOW())',
                [$version, $description]
            );
            echo "✓ Migration {$version}: {$description}\n";
        } catch (\Exception $e) {
            echo "✗ Failed to record migration {$version}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate() {
        echo "Current database version: {$this->version}\n";
        echo "Running migrations...\n\n";
        
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();
        
        // Run migrations in order
        $this->runMigration('1.0.0', 'Add Google Calendar support', function() {
            $this->addGoogleCalendarSupport();
        });
        
        $this->runMigration('1.0.1', 'Add calendar type indexes', function() {
            $this->addCalendarTypeIndexes();
        });
        
        echo "\nMigration completed!\n";
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        try {
            $this->db->query('
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    version VARCHAR(20) NOT NULL UNIQUE,
                    description TEXT,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ');
            echo "✓ Created schema_migrations table\n";
        } catch (\Exception $e) {
            echo "✗ Failed to create migrations table: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Run a specific migration
     */
    private function runMigration($version, $description, $callback) {
        if (version_compare($this->version, $version, '>=')) {
            echo "⏭ Skipping migration {$version} (already applied)\n";
            return;
        }
        
        try {
            echo "🔄 Running migration {$version}: {$description}\n";
            $callback();
            $this->recordMigration($version, $description);
        } catch (\Exception $e) {
            echo "✗ Migration {$version} failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Migration 1.0.0: Add Google Calendar support
     */
    private function addGoogleCalendarSupport() {
        // Check if columns already exist
        $columns = $this->db->fetchAll("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'sync_configurations' 
            AND TABLE_SCHEMA = DATABASE()
        ");
        
        $existingColumns = array_column($columns, 'COLUMN_NAME');
        
        // Add source_type column if it doesn't exist
        if (!in_array('source_type', $existingColumns)) {
            $this->db->query("
                ALTER TABLE sync_configurations 
                ADD COLUMN source_type ENUM('google', 'microsoft') DEFAULT 'microsoft' 
                AFTER target_email
            ");
            echo "  ✓ Added source_type column\n";
        }
        
        // Add target_type column if it doesn't exist
        if (!in_array('target_type', $existingColumns)) {
            $this->db->query("
                ALTER TABLE sync_configurations 
                ADD COLUMN target_type ENUM('google', 'microsoft') DEFAULT 'microsoft' 
                AFTER source_type
            ");
            echo "  ✓ Added target_type column\n";
        }
        
        // Update existing records to have default values
        $this->db->query("
            UPDATE sync_configurations 
            SET source_type = 'microsoft', target_type = 'microsoft' 
            WHERE source_type IS NULL OR target_type IS NULL
        ");
        echo "  ✓ Updated existing records with default values\n";
    }
    
    /**
     * Migration 1.0.1: Add calendar type indexes
     */
    private function addCalendarTypeIndexes() {
        // Check if indexes already exist
        $indexes = $this->db->fetchAll("
            SELECT INDEX_NAME 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_NAME = 'sync_configurations' 
            AND TABLE_SCHEMA = DATABASE()
        ");
        
        $existingIndexes = array_column($indexes, 'INDEX_NAME');
        
        // Add index for source_type if it doesn't exist
        if (!in_array('idx_source_type', $existingIndexes)) {
            $this->db->query("
                ALTER TABLE sync_configurations 
                ADD INDEX idx_source_type (source_type)
            ");
            echo "  ✓ Added source_type index\n";
        }
        
        // Add index for target_type if it doesn't exist
        if (!in_array('idx_target_type', $existingIndexes)) {
            $this->db->query("
                ALTER TABLE sync_configurations 
                ADD INDEX idx_target_type (target_type)
            ");
            echo "  ✓ Added target_type index\n";
        }
        
        // Add composite index for both types if it doesn't exist
        if (!in_array('idx_calendar_types', $existingIndexes)) {
            $this->db->query("
                ALTER TABLE sync_configurations 
                ADD INDEX idx_calendar_types (source_type, target_type)
            ");
            echo "  ✓ Added calendar types composite index\n";
        }
    }
    
    /**
     * Rollback migrations (if needed)
     */
    public function rollback($version = null) {
        if ($version === null) {
            $version = $this->version;
        }
        
        echo "Rolling back to version: {$version}\n";
        
        // Add rollback logic here if needed
        // For now, we'll just show what would be rolled back
        echo "Rollback functionality not implemented yet.\n";
        echo "If you need to rollback, you'll need to manually:\n";
        echo "1. Drop the new columns: ALTER TABLE sync_configurations DROP COLUMN source_type, DROP COLUMN target_type;\n";
        echo "2. Update schema_migrations table to remove the migration records\n";
    }
    
    /**
     * Show migration status
     */
    public function status() {
        echo "Database Migration Status\n";
        echo "========================\n";
        echo "Current version: {$this->version}\n\n";
        
        $migrations = $this->db->fetchAll('
            SELECT version, description, executed_at 
            FROM schema_migrations 
            ORDER BY version DESC
        ');
        
        if (empty($migrations)) {
            echo "No migrations have been applied yet.\n";
        } else {
            echo "Applied migrations:\n";
            foreach ($migrations as $migration) {
                echo "  {$migration['version']}: {$migration['description']} ({$migration['executed_at']})\n";
            }
        }
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $migration = new DatabaseMigration();
    
    $command = $argv[1] ?? 'migrate';
    
    switch ($command) {
        case 'migrate':
            $migration->migrate();
            break;
        case 'status':
            $migration->status();
            break;
        case 'rollback':
            $version = $argv[2] ?? null;
            $migration->rollback($version);
            break;
        default:
            echo "Usage: php migration.php [migrate|status|rollback]\n";
            echo "  migrate  - Run pending migrations\n";
            echo "  status   - Show migration status\n";
            echo "  rollback - Rollback to specific version\n";
            break;
    }
}
