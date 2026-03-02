<?php
// Use consistent session management (without redirects for AJAX)
// Load environment variables for session configuration
require_once __DIR__ . '/env_loader.php';

// Configure session settings (only if session not already started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    ini_set('session.cookie_lifetime', 7200); // 2 hours
    
    // Security: Set secure session cookie flags
    $https_enabled = getEnvVar('HTTPS_ENABLED', 'true');
    
    // Detect if running on localhost (for development)
    $is_localhost = (
        (isset($_SERVER['HTTP_HOST']) && (
            $_SERVER['HTTP_HOST'] === 'localhost' || 
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
        )) ||
        php_sapi_name() === 'cli'
    );
    
    // Detect if we're actually on HTTPS (even on localhost)
    $is_https = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    
    // Set Secure flag if HTTPS is enabled and we're actually on HTTPS
    if (strtolower($https_enabled) === 'true' && $is_https) {
        ini_set('session.cookie_secure', 1); // Only send over HTTPS
    }
    ini_set('session.cookie_httponly', 1); // Prevent JavaScript access
    // Use Lax for localhost, Strict for production (when on HTTPS)
    ini_set('session.cookie_samesite', ($is_localhost || !$is_https) ? 'Lax' : 'Strict'); // CSRF protection
    
    session_start();
}

require_once __DIR__ . '/conn.php';

// Check authentication for AJAX requests (return JSON error instead of redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized', 'success' => false, 'message' => 'Please log in']);
    exit;
}

// Function to get unread notification count
function getUnreadNotificationCount($conn) {
    try {
        // Check if table exists first
        $checkTable = "SHOW TABLES LIKE 'notifications'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            error_log("Notifications table does not exist for count query");
            return 0;
        }
        
        // Count only unread notifications (including 'queued' status and NULL)
        // Only count notifications that are explicitly unread, queued, or NULL (not sent, failed, created, etc.)
        $query = "SELECT COUNT(*) as count FROM notifications WHERE (notification_status = 'unread' OR notification_status = 'queued' OR notification_status IS NULL) AND notification_status != 'deleted'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = $row['count'];
            error_log("Unread notification count: $count");
            $stmt->close();
            return $count;
        }
        error_log("No result from count query");
        if (isset($stmt)) $stmt->close();
        return 0;
    } catch (Exception $e) {
        error_log("Error getting notification count: " . $e->getMessage());
        return 0;
    }
}

// Function to get recent notifications with filtering
function getRecentNotifications($conn, $limit = 5, $filter = 'all') {
    try {
        // Check if table exists first
        $checkTable = "SHOW TABLES LIKE 'notifications'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            return [];
        }
        
        // Build query based on filter
        $whereClause = "WHERE notification_status != 'deleted'";
        if ($filter === 'unread') {
            $whereClause .= " AND (notification_status = 'unread' OR notification_status = 'queued' OR notification_status IS NULL)";
        }
        
        // Use only the fields that exist in the table
        $query = "SELECT 
                    notification_id,
                    access_id,
                    recipients,
                    content,
                    notification_status,
                    created_at,
                    CASE 
                        WHEN notification_status = 'read' THEN '1'
                        ELSE '0'
                    END as is_read
                  FROM notifications 
                  {$whereClause}
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Error getting recent notifications: " . $e->getMessage());
        return [];
    }
}

// Function to mark notification as read
function markNotificationAsRead($conn, $notification_id) {
    try {
        // Check if table exists first
        $checkTable = "SHOW TABLES LIKE 'notifications'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            error_log("Notifications table does not exist");
            return false;
        }
        
        // Check if notification_status column exists
        if ($conn->query("SHOW COLUMNS FROM notifications LIKE 'notification_status'" )->num_rows > 0) {
            // First, check current status
            $checkQuery = "SELECT notification_status FROM notifications WHERE notification_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("i", $notification_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentStatus = $row['notification_status'];
                error_log("Notification ID $notification_id current status: " . ($currentStatus ?: 'NULL'));
                
                // Update to 'read' if it's not already 'read' or 'deleted'
                // This handles all statuses: 'unread', 'queued', 'created', 'sent', 'failed', NULL, etc.
                if ($currentStatus !== 'read' && $currentStatus !== 'deleted') {
                    $query = "UPDATE notifications SET notification_status = 'read' WHERE notification_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $notification_id);
                    $success = $stmt->execute();
                    $affectedRows = $stmt->affected_rows;
                    error_log("Update query executed. Success: " . ($success ? 'true' : 'false') . ", Affected rows: $affectedRows");
                    return $success && $affectedRows > 0;
                } else {
                    error_log("Notification ID $notification_id already marked as read or deleted (status: " . ($currentStatus ?: 'NULL') . ")");
                    return true; // Already read or deleted
                }
            } else {
                error_log("Notification ID $notification_id not found");
                return false;
            }
        } else {
            error_log("notification_status column does not exist");
            return true; // No status column, assume success
        }
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Function to mark all notifications as read
function markAllNotificationsAsRead($conn) {
    try {
        // Check if table exists first
        $checkTable = "SHOW TABLES LIKE 'notifications'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            return false;
        }
        
        if ($conn->query("SHOW COLUMNS FROM notifications LIKE 'notification_status'" )->num_rows > 0) {
            $query = "UPDATE notifications SET notification_status = 'read' WHERE (notification_status = 'unread' OR notification_status = 'queued' OR notification_status IS NULL)";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            $stmt->close();
            return $result;
        }
        return true;
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        // Add debugging for connection issues
        if ($conn->connect_error) {
            echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error, 'success' => false]);
            exit;
        }
        
        switch ($action) {
            case 'get_count':
                $count = getUnreadNotificationCount($conn);
                echo json_encode(['count' => $count, 'success' => true]);
                break;
                
            case 'get_recent':
                $limit = intval($_POST['limit'] ?? 5);
                $filter = $_POST['filter'] ?? 'all';
                $notifications = getRecentNotifications($conn, $limit, $filter);
                echo json_encode(['notifications' => $notifications, 'success' => true]);
                break;
            case 'create_reschedule_notice':
                // Create a reschedule notification for admins
                try {
                    $schedule_id = intval($_POST['schedule_id'] ?? 0);
                    $access_id = intval($_POST['access_id'] ?? 0);
                    $date = $_POST['date'] ?? '';
                    if ($access_id <= 0) throw new Exception('Invalid access id');
                    $content = 'Reschedule request for ' . ($date ? date('M d, Y', strtotime($date)) : 'a scheduled session');
                    $stmt = $conn->prepare("INSERT INTO notifications (access_id, recipients, content, notification_status, created_at) VALUES (?, 'system', ?, 'unread', NOW())");
                    $stmt->bind_param('is', $access_id, $content);
                    $ok = $stmt->execute();
                    echo json_encode(['success' => (bool)$ok]);
                } catch (Exception $e) {
                    error_log('create_reschedule_notice error: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
                }
                break;
                
            case 'mark_read':
                $notification_id = intval($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    $success = markNotificationAsRead($conn, $notification_id);
                    // Add debugging
                    error_log("Mark read request for ID: $notification_id, Success: " . ($success ? 'true' : 'false'));
                    echo json_encode(['success' => $success, 'notification_id' => $notification_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
                }
                break;
                
            case 'mark_all_read':
                $success = markAllNotificationsAsRead($conn);
                echo json_encode(['success' => $success]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action', 'success' => false]);
                break;
        }
    } catch (Exception $e) {
        error_log("Notification system error: " . $e->getMessage());
        echo json_encode(['error' => 'System error occurred', 'success' => false]);
    }
    exit;
}
?> 