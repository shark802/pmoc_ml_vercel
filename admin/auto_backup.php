<?php
// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

/**
 * Automatic Database Backup Script
 * This script works for both localhost (XAMPP) and production (Hostinger/Server)
 * 
 * Setup:
 * - Localhost: Use Windows Task Scheduler (see AUTOMATIC_BACKUP_SETUP_GUIDE.md)
 * - Production: Use cron job in hosting panel (see PRODUCTION_BACKUP_SETUP.md)
 * 
 * Cron examples:
 * Daily at 2 AM: 0 2 * * * php /path/to/admin/auto_backup.php
 * Weekly on Sunday at 2 AM: 0 2 * * 0 php /path/to/admin/auto_backup.php
 * Monthly on 1st at 2 AM: 0 2 1 * * php /path/to/admin/auto_backup.php
 * 
 * Note: This script uses absolute paths, so it works regardless of where it's called from
 */

// Get the directory where this script is located
$scriptDir = __DIR__; // admin/ directory
$baseDir = dirname($scriptDir); // caps2/ directory (or root on production)

// Backup directory - use absolute path to work from anywhere (cron, web, CLI)
$backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

// Ensure backup directory exists
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

require_once $baseDir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'conn.php';

// Get database credentials (conn.php already loaded $host, $username, $password, $database_name)
// These variables are available globally after require_once

// Ensure backups directory is not accessible via web
$htaccessFile = $backupDir . '.htaccess';
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, "deny from all\n");
}

// Check if auto backup is enabled
$autoBackupEnabled = false;
$backupFrequency = 'daily';
$lastBackup = null;

try {
    // Create settings table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    
    $result = $conn->query("SELECT setting_key, setting_value FROM backup_settings");
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'auto_backup_enabled') {
            $autoBackupEnabled = (bool)$row['setting_value'];
        } elseif ($row['setting_key'] === 'backup_frequency') {
            $backupFrequency = $row['setting_value'];
        } elseif ($row['setting_key'] === 'last_backup') {
            $lastBackup = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("Backup settings error: " . $e->getMessage());
    exit(1);
}

if (!$autoBackupEnabled) {
    exit(0); // Auto backup is disabled, exit silently
}

// Check if backup is needed based on frequency
$shouldBackup = false;
$now = time();

if ($lastBackup) {
    $lastBackupTime = strtotime($lastBackup);
    $daysSinceBackup = ($now - $lastBackupTime) / (24 * 60 * 60);
    
    switch ($backupFrequency) {
        case 'daily':
            $shouldBackup = $daysSinceBackup >= 1;
            break;
        case 'weekly':
            $shouldBackup = $daysSinceBackup >= 7;
            break;
        case 'monthly':
            $shouldBackup = $daysSinceBackup >= 30;
            break;
    }
} else {
    // No previous backup, create one
    $shouldBackup = true;
}

if (!$shouldBackup) {
    exit(0); // Backup not needed yet
}

// Helper function to format file size (for logging)
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Create backup
try {
    // Set longer timeout for remote database connections
    ini_set('max_execution_time', 600); // 10 minutes
    ini_set('memory_limit', '512M');
    
    // Database credentials are already loaded from conn.php (required at top)
    // Use the variables from conn.php
    global $host, $username, $password, $database_name;
    
    // If variables not available, get from connection
    if (!isset($host) || !isset($username) || !isset($database_name)) {
        // Try to get from mysqli connection
        if (isset($conn)) {
            $database = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'u520834156_DBpmoc25';
            // Get connection info from mysqli
            $host = $conn->host_info ?? 'srv1322.hstgr.io';
            // Note: mysqli doesn't expose username/password, so we rely on conn.php
        } else {
            // Fallback to remote database (production) - use environment variables
            require_once __DIR__ . '/../includes/env_loader.php';
            $host = getEnvVar('DB_HOST', 'srv1322.hstgr.io');
            $username = getEnvVar('DB_USER', 'u520834156_userPmoc');
            $password = getEnvVar('DB_PASSWORD');
            $database = getEnvVar('DB_NAME', 'u520834156_DBpmoc25');
            
            if (empty($password)) {
                error_log("CRITICAL: DB_PASSWORD environment variable is not set in production!");
                die("Database configuration error. Please contact system administrator.");
            }
        }
    } else {
        $database = $database_name;
    }
    
    // Increase MySQL connection timeout settings
    // Note: max_allowed_packet is read-only on shared hosting, so we skip it
    if (isset($conn)) {
        $conn->query("SET SESSION wait_timeout = 600");
        $conn->query("SET SESSION interactive_timeout = 600");
        // max_allowed_packet cannot be set at session level on shared hosting
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$database}_{$timestamp}.sql";
    $filepath = $backupDir . $filename;
    
    // For remote databases, use PHP method directly (more reliable)
    // mysqldump may not work well with remote connections
    $usePHPBackup = true; // Always use PHP method for remote databases
    
    if ($usePHPBackup) {
        // Use PHP method (more reliable for remote databases)
        $backupContent = createBackupPHP($conn, $database);
        if ($backupContent === false) {
            throw new Exception('Failed to create backup: ' . ($conn->error ?? 'Unknown error'));
        }
        file_put_contents($filepath, $backupContent);
    } else {
        // Try mysqldump first (only for local databases)
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers --max_allowed_packet=1G %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            // Fallback to PHP method
            $backupContent = createBackupPHP($conn, $database);
            if ($backupContent === false) {
                throw new Exception('Failed to create backup: ' . ($conn->error ?? 'Unknown error'));
            }
            file_put_contents($filepath, $backupContent);
        }
    }
    
    // Update last backup time
    $stmt = $conn->prepare("INSERT INTO backup_settings (setting_key, setting_value) 
                           VALUES ('last_backup', ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    $nowStr = date('Y-m-d H:i:s');
    $stmt->bind_param("ss", $nowStr, $nowStr);
    $stmt->execute();
    $stmt->close();
    
    // Clean old backups
    cleanOldBackups($conn, $backupDir);
    
    // Log success (works for both localhost and production)
    $logMessage = "Automatic backup created: $filename (Size: " . formatBytes(filesize($filepath)) . ")";
    error_log($logMessage);
    
    // If running from CLI, also output to console
    if (php_sapi_name() === 'cli') {
        echo date('Y-m-d H:i:s') . " - " . $logMessage . "\n";
    }
    
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = "Automatic backup failed: " . $e->getMessage();
    error_log($errorMessage);
    
    // If running from CLI, also output to console
    if (php_sapi_name() === 'cli') {
        echo date('Y-m-d H:i:s') . " - ERROR: " . $errorMessage . "\n";
    }
    
    exit(1);
}

// Helper function to create backup using PHP
function createBackupPHP($conn, $database) {
    try {
        // Check connection before starting
        if (!$conn || $conn->connect_error) {
            throw new Exception('Database connection lost: ' . ($conn->connect_error ?? 'Unknown error'));
        }
        
        // Set timeout settings
        $conn->query("SET SESSION wait_timeout = 600");
        $conn->query("SET SESSION interactive_timeout = 600");
        
        $output = "-- Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: $database\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        if (!$result) {
            throw new Exception('Failed to get table list: ' . $conn->error);
        }
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        $result->free();
        
        foreach ($tables as $table) {
            // Check connection before each table
            if (!$conn->ping()) {
                throw new Exception("Connection lost while backing up table: $table");
            }
            
            // Table structure
            $output .= "-- Table structure for `$table`\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $createTable = $conn->query("SHOW CREATE TABLE `$table`");
            if (!$createTable) {
                error_log("Warning: Failed to get structure for table $table: " . $conn->error);
                continue;
            }
            $row = $createTable->fetch_array();
            $output .= $row[1] . ";\n\n";
            $createTable->free();
            
            // Table data - process in chunks to avoid memory issues
            $output .= "-- Data for table `$table`\n";
            $countResult = $conn->query("SELECT COUNT(*) as total FROM `$table`");
            $totalRows = 0;
            if ($countResult) {
                $countRow = $countResult->fetch_assoc();
                $totalRows = (int)$countRow['total'];
                $countResult->free();
            }
            
            if ($totalRows > 0) {
                // Process in chunks of 1000 rows to avoid memory issues
                $chunkSize = 1000;
                $offset = 0;
                $firstChunk = true;
                
                while ($offset < $totalRows) {
                    $data = $conn->query("SELECT * FROM `$table` LIMIT $chunkSize OFFSET $offset");
                    if (!$data) {
                        error_log("Warning: Failed to get data for table $table: " . $conn->error);
                        break;
                    }
                    
                    if ($data->num_rows > 0) {
                        if ($firstChunk) {
                            $output .= "INSERT INTO `$table` VALUES\n";
                            $firstChunk = false;
                        }
                        
                        $rows = [];
                        while ($row = $data->fetch_assoc()) {
                            $values = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $escaped = $conn->real_escape_string($value);
                                    $values[] = "'$escaped'";
                                }
                            }
                            $rows[] = "(" . implode(',', $values) . ")";
                        }
                        
                        $output .= implode(",\n", $rows);
                        if ($offset + $chunkSize < $totalRows) {
                            $output .= ",\n";
                        } else {
                            $output .= ";\n\n";
                        }
                    }
                    
                    $data->free();
                    $offset += $chunkSize;
                    
                    // Small delay to prevent overwhelming the connection
                    usleep(10000); // 10ms
                }
            } else {
                $output .= "-- No data in table `$table`\n\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $output;
        
    } catch (Exception $e) {
        error_log("Backup creation error: " . $e->getMessage());
        return false;
    }
}

// Helper function to clean old backups
function cleanOldBackups($conn, $backupDir) {
    $retentionDays = 30;
    $result = $conn->query("SELECT setting_value FROM backup_settings WHERE setting_key = 'retention_days'");
    if ($row = $result->fetch_assoc()) {
        $retentionDays = (int)$row['setting_value'];
    }
    
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filepath = $backupDir . $file;
                if (filemtime($filepath) < $cutoffTime) {
                    unlink($filepath);
                    error_log("Deleted old backup: $file");
                }
            }
        }
    }
}
?>

