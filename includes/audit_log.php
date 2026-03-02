<?php
/**
 * Audit Log Helper Functions
 * 
 * This file provides functions to log user actions for audit purposes.
 */

/**
 * Ensure audit_logs table exists
 * 
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function ensureAuditLogsTable($conn) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100),
            user_name VARCHAR(255),
            action VARCHAR(100) NOT NULL,
            description TEXT,
            module VARCHAR(100),
            details JSON,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_module (module),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        return true;
    } catch (Exception $e) {
        error_log("Audit log table creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an action to the audit log
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID (admin_id)
 * @param string $action Action performed (e.g., 'login', 'logout', 'create_access_code')
 * @param string $description Detailed description of the action
 * @param string $module Module/page where action occurred (e.g., 'admin', 'access_codes')
 * @param array $details Additional details (JSON encoded)
 * @param string $ip_address IP address of the user
 * @return bool Success status
 */
function logAudit($conn, $user_id, $action, $description, $module = 'system', $details = null, $ip_address = null) {
    try {
        // Ensure table exists
        ensureAuditLogsTable($conn);
        
        // Get user info if available
        $username = null;
        $user_name = null;
        if (isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }
        if (isset($_SESSION['admin_name'])) {
            $user_name = $_SESSION['admin_name'];
        }
        
        // Get IP address if not provided
        if ($ip_address === null) {
            $ip_address = getClientIP();
        }
        
        // Encode details as JSON if provided
        $detailsJson = null;
        if ($details !== null) {
            $detailsJson = json_encode($details);
        }
        
        // Insert audit log
        $stmt = $conn->prepare("INSERT INTO audit_logs 
            (user_id, username, user_name, action, description, module, details, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", 
            $user_id, 
            $username, 
            $user_name, 
            $action, 
            $description, 
            $module, 
            $detailsJson, 
            $ip_address
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Common action types
 */
define('AUDIT_LOGIN', 'login');
define('AUDIT_LOGOUT', 'logout');
define('AUDIT_CREATE', 'create');
define('AUDIT_UPDATE', 'update');
define('AUDIT_DELETE', 'delete');
define('AUDIT_VIEW', 'view');
define('AUDIT_EXPORT', 'export');
define('AUDIT_BACKUP', 'backup');
define('AUDIT_RESTORE', 'restore');
define('AUDIT_ACCESS_DENIED', 'access_denied');
define('AUDIT_PASSWORD_CHANGE', 'password_change');
define('AUDIT_SETTINGS_CHANGE', 'settings_change');
define('AUDIT_EMAIL_SENT', 'email_sent');
define('AUDIT_SMS_SENT', 'sms_sent');
?>

