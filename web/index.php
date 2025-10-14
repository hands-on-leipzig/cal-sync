<?php
/**
 * Web interface for calendar sync configuration
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                try {
                    $db->query(
                        'INSERT INTO users (email, display_name) VALUES (?, ?)',
                        [$_POST['email'], $_POST['display_name']]
                    );
                    $success = 'User added successfully';
                } catch (Exception $e) {
                    $error = 'Failed to add user: ' . $e->getMessage();
                }
                break;
                
            case 'add_sync_config':
                try {
                    $db->query(
                        'INSERT INTO sync_configurations (user_id, source_email, target_email, source_type, target_type, sync_direction, sync_frequency_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            $_POST['user_id'],
                            $_POST['source_email'],
                            $_POST['target_email'],
                            $_POST['source_type'],
                            $_POST['target_type'],
                            $_POST['sync_direction'],
                            $_POST['sync_frequency_minutes']
                        ]
                    );
                    $success = 'Sync configuration added successfully';
                } catch (Exception $e) {
                    $error = 'Failed to add sync configuration: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_sync_config':
                try {
                    $db->query(
                        'UPDATE sync_configurations SET is_active = ? WHERE id = ?',
                        [$_POST['is_active'], $_POST['config_id']]
                    );
                    $success = 'Sync configuration updated successfully';
                } catch (Exception $e) {
                    $error = 'Failed to update sync configuration: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get data for display
$users = $db->fetchAll('SELECT * FROM users ORDER BY email');
$syncConfigs = $db->fetchAll('
    SELECT sc.*, u.display_name, u.email as user_email 
    FROM sync_configurations sc 
    JOIN users u ON sc.user_id = u.id 
    ORDER BY sc.created_at DESC
');
$recentLogs = $db->fetchAll('
    SELECT sl.*, sc.source_email, sc.target_email 
    FROM sync_logs sl 
    JOIN sync_configurations sc ON sl.sync_config_id = sc.id 
    ORDER BY sl.started_at DESC 
    LIMIT 20
');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microsoft Calendar Sync</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #0078d4;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
        }
        .section h2 {
            margin-top: 0;
            color: #323130;
            border-bottom: 2px solid #0078d4;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #323130;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d2d0ce;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #0078d4;
            box-shadow: 0 0 0 2px rgba(0,120,212,0.2);
        }
        .btn {
            background: #0078d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn:hover {
            background: #106ebe;
        }
        .btn-danger {
            background: #d13438;
        }
        .btn-danger:hover {
            background: #b71c1c;
        }
        .btn-success {
            background: #107c10;
        }
        .btn-success:hover {
            background: #0e6b0e;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #dff6dd;
            color: #107c10;
            border: 1px solid #107c10;
        }
        .alert-error {
            background: #fde7e9;
            color: #d13438;
            border: 1px solid #d13438;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #323130;
        }
        .status-active {
            color: #107c10;
            font-weight: 600;
        }
        .status-inactive {
            color: #d13438;
            font-weight: 600;
        }
        .status-success {
            color: #107c10;
        }
        .status-error {
            color: #d13438;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Microsoft Calendar Sync</h1>
            <p>Synchronize free/busy time between Microsoft calendars</p>
        </div>
        
        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="grid">
                <div class="section">
                    <h2>Add User</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" required>
                        </div>
                        <button type="submit" class="btn">Add User</button>
                    </form>
                </div>
                
                <div class="section">
                    <h2>Add Sync Configuration</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_sync_config">
                        <div class="form-group">
                            <label for="user_id">User</label>
                            <select id="user_id" name="user_id" required>
                                <option value="">Select a user</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['display_name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="source_email">Source Email/Calendar ID</label>
                            <input type="text" id="source_email" name="source_email" placeholder="user@company.com or calendar-id" required>
                            <small>For Google Calendar: use email address or calendar ID</small>
                        </div>
                        <div class="form-group">
                            <label for="source_type">Source Calendar Type</label>
                            <select id="source_type" name="source_type" required>
                                <option value="microsoft">Microsoft Calendar</option>
                                <option value="google">Google Calendar</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="target_email">Target Email/Calendar ID</label>
                            <input type="text" id="target_email" name="target_email" placeholder="user@company.com or calendar-id" required>
                            <small>For Google Calendar: use email address or calendar ID</small>
                        </div>
                        <div class="form-group">
                            <label for="target_type">Target Calendar Type</label>
                            <select id="target_type" name="target_type" required>
                                <option value="microsoft">Microsoft Calendar</option>
                                <option value="google">Google Calendar</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sync_direction">Sync Direction</label>
                            <select id="sync_direction" name="sync_direction" required>
                                <option value="bidirectional">Bidirectional</option>
                                <option value="source_to_target">Source → Target</option>
                                <option value="target_to_source">Target → Source</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sync_frequency_minutes">Sync Frequency (minutes)</label>
                            <input type="number" id="sync_frequency_minutes" name="sync_frequency_minutes" value="15" min="5" max="1440" required>
                        </div>
                        <button type="submit" class="btn">Add Sync Configuration</button>
                    </form>
                </div>
            </div>
            
            <div class="section">
                <h2>Sync Configurations</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Source Calendar</th>
                            <th>Target Calendar</th>
                            <th>Direction</th>
                            <th>Frequency</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($syncConfigs as $config): ?>
                            <tr>
                                <td><?= htmlspecialchars($config['display_name']) ?></td>
                                <td>
                                    <?= htmlspecialchars($config['source_email']) ?>
                                    <br><small class="status-<?= $config['source_type'] === 'google' ? 'success' : 'active' ?>">
                                        <?= ucfirst($config['source_type']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($config['target_email']) ?>
                                    <br><small class="status-<?= $config['target_type'] === 'google' ? 'success' : 'active' ?>">
                                        <?= ucfirst($config['target_type']) ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($config['sync_direction']) ?></td>
                                <td><?= $config['sync_frequency_minutes'] ?> min</td>
                                <td>
                                    <span class="<?= $config['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $config['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= $config['last_sync_at'] ? date('Y-m-d H:i:s', strtotime($config['last_sync_at'])) : 'Never' ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_sync_config">
                                        <input type="hidden" name="config_id" value="<?= $config['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $config['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn <?= $config['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                            <?= $config['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section">
                <h2>Recent Sync Logs</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Configuration</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Events</th>
                            <th>Started</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['source_email']) ?> ↔ <?= htmlspecialchars($log['target_email']) ?></td>
                                <td><?= htmlspecialchars($log['sync_type']) ?></td>
                                <td>
                                    <span class="<?= $log['status'] === 'success' ? 'status-success' : 'status-error' ?>">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $log['events_processed'] ?> processed
                                    (<?= $log['events_created'] ?> created, <?= $log['events_updated'] ?> updated, <?= $log['events_deleted'] ?> deleted)
                                </td>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['started_at'])) ?></td>
                                <td>
                                    <?php if ($log['completed_at']): ?>
                                        <?= strtotime($log['completed_at']) - strtotime($log['started_at']) ?>s
                                    <?php else: ?>
                                        Running...
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
