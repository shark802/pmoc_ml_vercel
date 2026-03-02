<?php
// Suppress any output and errors before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

// Set JSON header FIRST before any includes
header('Content-Type: application/json');

// Suppress warnings/notices that might be output by includes
$old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log errors but don't output them
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Suppress error output
});

// Use consistent session management configuration
// Load environment variables for session configuration
require_once __DIR__ . '/includes/env_loader.php';

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

// Try to load required files and catch any errors
$init_error = null;
$init_file = null;

// Load conn.php first
if (!file_exists('includes/conn.php')) {
    $init_error = 'conn.php not found';
    $init_file = 'includes/conn.php';
} else {
    try {
        require_once 'includes/conn.php';
    } catch (Exception $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/conn.php';
        error_log("Login: Error loading conn.php - " . $init_error);
    } catch (Error $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/conn.php';
        error_log("Login: Fatal error loading conn.php - " . $init_error);
    }
}

// Load audit_log.php
if (!$init_error && !file_exists('includes/audit_log.php')) {
    $init_error = 'audit_log.php not found';
    $init_file = 'includes/audit_log.php';
} elseif (!$init_error) {
    try {
        require_once 'includes/audit_log.php';
    } catch (Exception $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/audit_log.php';
        error_log("Login: Error loading audit_log.php - " . $init_error);
    } catch (Error $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/audit_log.php';
        error_log("Login: Fatal error loading audit_log.php - " . $init_error);
    }
}

// Load rate_limit_helper.php
if (!$init_error && !file_exists('includes/rate_limit_helper.php')) {
    $init_error = 'rate_limit_helper.php not found';
    $init_file = 'includes/rate_limit_helper.php';
} elseif (!$init_error) {
    try {
        require_once 'includes/rate_limit_helper.php';
    } catch (Exception $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/rate_limit_helper.php';
        error_log("Login: Error loading rate_limit_helper.php - " . $init_error);
    } catch (Error $e) {
        $init_error = $e->getMessage();
        $init_file = 'includes/rate_limit_helper.php';
        error_log("Login: Fatal error loading rate_limit_helper.php - " . $init_error);
    }
}

if ($init_error) {
    ob_clean();
    error_log("Login initialization failed: File=$init_file, Error=$init_error");
    echo json_encode([
        'success' => false,
        'message' => 'System initialization error. Please contact administrator.'
    ]);
    exit();
}

// Clear any output that might have been generated (warnings, notices, etc.)
ob_clean();

// Restore error handler
restore_error_handler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if database connection is available
    if (!isset($conn) || !$conn) {
        ob_clean();
        error_log("Login: Database connection not available");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error. Please contact administrator.'
        ]);
        exit();
    }
    
    // Check for connection errors
    if (is_object($conn) && property_exists($conn, 'connect_error') && $conn->connect_error) {
        ob_clean();
        error_log("Login: Database connection error: " . $conn->connect_error);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error. Please contact administrator.'
        ]);
        exit();
    }
    
    // Rate limiting: 5 attempts per 15 minutes per IP
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('login', $ipAddress, 5, 900)) {
        $remaining = getRemainingAttempts('login', $ipAddress, 5, 900);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Too many login attempts. Please try again in 15 minutes.'
        ]);
        exit();
    }
    // Ensure is_active column exists
    try {
        $checkCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'is_active'");
        if ($checkCol) {
            $checkCol->execute();
            $res = $checkCol->get_result()->fetch_assoc();
            if ((int)$res['cnt'] === 0) {
                $conn->query("ALTER TABLE admin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
            }
            $checkCol->close();
        }
    } catch (Exception $e) {
        // If this fails, continue; default to allowing login
        error_log("Column check error: " . $e->getMessage());
    }
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all fields'
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT admin_id, username, password, admin_name, position, image, IFNULL(is_active, 1) AS is_active FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            if (password_verify($password, $admin['password'])) {
                if ((int)$admin['is_active'] !== 1) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Your account is deactivated. Please contact the super admin.'
                    ]);
                    exit();
                }
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['admin_name'];
                $_SESSION['position'] = $admin['position'] ?? 'admin';
                $_SESSION['image'] = $admin['image'] ?? '../images/profiles/default.jpg';
                
                // Log successful login
                logAudit($conn, $admin['admin_id'], AUDIT_LOGIN, 
                    'User logged in successfully', 'authentication');
                
                // Redirect based on position
                $redirect = 'admin/admin_dashboard.php';
                if ($admin['position'] === 'counselor') {
                    $redirect = 'counselor/counselor_dashboard.php';
                }
                
                // Clear rate limit on successful login
                clearRateLimit('login', $ipAddress);
                
                echo json_encode([
                    'success' => true,
                    'redirect' => $redirect
                ]);
                exit();
            }
        }
        
        // Log failed login attempt
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $userId = null;
        if ($userResult->num_rows === 1) {
            $userData = $userResult->fetch_assoc();
            $userId = $userData['admin_id'];
        }
        $stmt->close();
        
        logAudit($conn, $userId ?? 0, AUDIT_ACCESS_DENIED, 
            'Failed login attempt for username: ' . $username, 'authentication');
        
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'System error occurred. Please try again.'
        ]);
        exit();
    } catch (Error $e) {
        error_log("Login fatal error: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'System error occurred. Please try again.'
        ]);
        exit();
    }
}

ob_clean();
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
exit();
?>