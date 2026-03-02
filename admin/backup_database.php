<?php
// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

// Suppress any output that might interfere with JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead
ini_set('log_errors', 1);

// Start output buffering early to catch any unexpected output
if (ob_get_level() == 0) {
    ob_start();
}

require_once '../includes/session.php';
require_once '../includes/conn.php';
require_once '../includes/audit_log.php';

// Clear any output that might have been generated during includes
if (ob_get_level() > 0) {
    ob_clean();
}

header('Content-Type: application/json');

// Only allow superadmin
if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_POST['action'] ?? '';

// Backup directory
$backupDir = '../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Ensure backups directory is not accessible via web
$htaccessFile = $backupDir . '.htaccess';
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, "deny from all\n");
}

try {
    switch ($action) {
        case 'create_backup':
            // Get database credentials from conn.php (already loaded)
            // Use global variables from conn.php
            global $host, $username, $password, $database_name;
            $database = $database_name ?? 'u520834156_DBpmoc25';
            
            // Set longer timeout for remote database connections
            ini_set('max_execution_time', 600); // 10 minutes
            ini_set('memory_limit', '512M');
            
            // Increase MySQL connection timeout settings
            // Note: max_allowed_packet is read-only on shared hosting, so we skip it
            if (isset($conn)) {
                $conn->query("SET SESSION wait_timeout = 600");
                $conn->query("SET SESSION interactive_timeout = 600");
                // max_allowed_packet cannot be set at session level on shared hosting
            }
            
            // Generate backup filename
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$database}_{$timestamp}.sql";
            $filepath = $backupDir . $filename;
            
            // For remote databases, use PHP method directly (more reliable)
            // mysqldump may not work well with remote connections from localhost
            $usePHPBackup = true; // Always use PHP method for remote databases
            
            if (!$usePHPBackup) {
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
                    $usePHPBackup = true;
                }
            }
            
            if ($usePHPBackup) {
                // Use PHP method (more reliable for remote databases)
                $backupContent = createBackupPHP($conn, $database);
                if ($backupContent === false) {
                    throw new Exception('Failed to create backup: ' . ($conn->error ?? 'Unknown error'));
                }
                file_put_contents($filepath, $backupContent);
            }
            
            // Update last backup time
            updateBackupSettings('last_backup', date('Y-m-d H:i:s'));
            
            // Clean old backups based on retention
            cleanOldBackups();
            
            // Log backup creation
            logAudit($conn, $_SESSION['admin_id'], AUDIT_BACKUP, 
                'Database backup created: ' . $filename, 'backup', 
                ['filename' => $filename, 'size' => filesize($filepath)]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully: ' . $filename,
                'filename' => $filename
            ]);
            break;
            
        case 'save_settings':
            $autoBackupEnabled = isset($_POST['auto_backup_enabled']) ? (int)$_POST['auto_backup_enabled'] : 0;
            $backupFrequency = $_POST['backup_frequency'] ?? 'daily';
            $retentionDays = isset($_POST['retention_days']) ? (int)$_POST['retention_days'] : 30;
            
            updateBackupSettings('auto_backup_enabled', $autoBackupEnabled);
            updateBackupSettings('backup_frequency', $backupFrequency);
            updateBackupSettings('retention_days', $retentionDays);
            
            // Log settings change
            logAudit($conn, $_SESSION['admin_id'], AUDIT_SETTINGS_CHANGE, 
                'Backup settings updated', 'backup', 
                [
                    'auto_backup_enabled' => $autoBackupEnabled,
                    'backup_frequency' => $backupFrequency,
                    'retention_days' => $retentionDays
                ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Settings saved successfully'
            ]);
            break;
            
        case 'restore_backup':
            // Clear any existing output
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            // Set longer timeout for restore operation (can take time for large databases)
            ini_set('max_execution_time', 1800); // 30 minutes
            ini_set('memory_limit', '512M');
            
            // Prevent script timeout
            set_time_limit(1800); // 30 minutes
            
            // Disable compression and keep-alive to prevent connection issues
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ini_set('zlib.output_compression', 0);
            
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception('Filename is required');
            }
            
            // Security: only allow .sql files
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
                throw new Exception('Invalid file type');
            }
            
            $filepath = $backupDir . basename($filename);
            if (!file_exists($filepath)) {
                throw new Exception('Backup file not found');
            }
            
            // Don't use output buffering for flushing during restore
            // Instead, we'll just execute and return clean JSON at the end
            
            // Step 1: Create a backup of current database before restoring (safety measure)
            $safetyBackupFilename = 'pre_restore_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $safetyBackupPath = $backupDir . $safetyBackupFilename;
            
            try {
                // Create safety backup using PHP method
                $safetyBackupContent = createBackupPHP($conn, $database);
                if ($safetyBackupContent === false) {
                    throw new Exception('Failed to create safety backup before restore');
                }
                file_put_contents($safetyBackupPath, $safetyBackupContent);
                
                // Log safety backup creation
                logAudit($conn, $_SESSION['admin_id'], AUDIT_BACKUP, 
                    'Safety backup created before restore: ' . $safetyBackupFilename, 'backup', 
                    ['filename' => $safetyBackupFilename, 'restore_from' => $filename]);
            } catch (Exception $e) {
                throw new Exception('Failed to create safety backup: ' . $e->getMessage());
            }
            
            // Step 2: Read the backup file
            $backupContent = file_get_contents($filepath);
            if ($backupContent === false) {
                throw new Exception('Failed to read backup file');
            }
            
            // Step 3: Restore database using PHP
            $conn->query('SET FOREIGN_KEY_CHECKS=0');
            $conn->query('SET AUTOCOMMIT=0');
            $conn->begin_transaction();
            
            try {
                // Remove comments
                $backupContent = preg_replace('/^--.*$/m', '', $backupContent);
                $backupContent = preg_replace('/\/\*.*?\*\//s', '', $backupContent);
                
                // Use mysqli_multi_query for better handling of multiple statements
                // First, split by semicolon but be careful with strings
                $statements = [];
                $delimiter = ';';
                $current = '';
                $len = strlen($backupContent);
                $inString = false;
                $stringChar = '';
                
                for ($i = 0; $i < $len; $i++) {
                    $char = $backupContent[$i];
                    $nextChar = ($i < $len - 1) ? $backupContent[$i + 1] : '';
                    
                    // Handle string literals
                    if (($char === '"' || $char === "'") && ($i === 0 || $backupContent[$i - 1] !== '\\')) {
                        if (!$inString) {
                            $inString = true;
                            $stringChar = $char;
                        } elseif ($char === $stringChar) {
                            $inString = false;
                            $stringChar = '';
                        }
                    }
                    
                    $current .= $char;
                    
                    // If we hit a semicolon and we're not in a string, it's end of statement
                    if ($char === $delimiter && !$inString) {
                        $stmt = trim($current);
                        if (!empty($stmt) && 
                            !preg_match('/^SET\s+(FOREIGN_KEY_CHECKS|AUTOCOMMIT)/i', $stmt) &&
                            strlen($stmt) > 10) {
                            $statements[] = $stmt;
                        }
                        $current = '';
                    }
                }
                
                // Add any remaining statement
                if (!empty(trim($current))) {
                    $stmt = trim($current);
                    if (!preg_match('/^SET\s+(FOREIGN_KEY_CHECKS|AUTOCOMMIT)/i', $stmt) && strlen($stmt) > 10) {
                        $statements[] = $stmt;
                    }
                }
                
                // Execute all statements
                $executed = 0;
                $totalStatements = count($statements);
                foreach ($statements as $index => $statement) {
                    if (!empty($statement)) {
                        if (!$conn->query($statement)) {
                            throw new Exception('SQL Error: ' . $conn->error . ' in statement: ' . substr($statement, 0, 150));
                        }
                        $executed++;
                    }
                }
                
                $conn->commit();
                $conn->query('SET FOREIGN_KEY_CHECKS=1');
                $conn->query('SET AUTOCOMMIT=1');
                
            } catch (Exception $e) {
                $conn->rollback();
                $conn->query('SET FOREIGN_KEY_CHECKS=1');
                $conn->query('SET AUTOCOMMIT=1');
                throw new Exception('Restore failed: ' . $e->getMessage());
            }
            
            // Clear any output buffers before sending JSON response
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Log restore action
            logAudit($conn, $_SESSION['admin_id'], AUDIT_RESTORE, 
                'Database restored from: ' . $filename . ' (Safety backup: ' . $safetyBackupFilename . ')', 'backup', 
                [
                    'restored_from' => $filename,
                    'safety_backup' => $safetyBackupFilename,
                    'statements_executed' => $executed
                ]);
            
            // Send clean JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Database restored successfully. Safety backup created: ' . $safetyBackupFilename,
                'safety_backup' => $safetyBackupFilename
            ]);
            exit(); // Important: exit after sending JSON
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Clear any output buffers before sending JSON response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Send clean JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit(); // Important: exit after sending JSON
}

// Helper function to create backup using PHP (fallback method)
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

// Helper function to update backup settings
function updateBackupSettings($key, $value) {
    global $conn;
    
    // Create table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    
    $stmt = $conn->prepare("INSERT INTO backup_settings (setting_key, setting_value) 
                           VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    $stmt->execute();
    $stmt->close();
}

// Helper function to clean old backups
function cleanOldBackups() {
    global $backupDir, $conn;
    
    // Get retention days
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
                }
            }
        }
    }
}
?>

