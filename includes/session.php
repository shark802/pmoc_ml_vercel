<?php
// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Configure session settings for longer sessions (only if session not already started)
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
    // This prevents SameSite cookie issues with mixed content
    ini_set('session.cookie_samesite', ($is_localhost || !$is_https) ? 'Lax' : 'Strict'); // CSRF protection
    
    session_start();
}
include 'conn.php';

// Redirect to login if not logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get fresh admin data from database
$admin_id = $_SESSION['admin_id'];
// Ensure is_active column exists
try {
    $checkCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'is_active'");
    $checkCol->execute();
    $res = $checkCol->get_result()->fetch_assoc();
    if ((int)$res['cnt'] === 0) {
        $conn->query("ALTER TABLE admin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
    $checkCol->close();
} catch (Exception $e) {
    // ignore
}
try {
    $stmt = $conn->prepare("SELECT *, IFNULL(is_active, 1) AS is_active FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If admin doesn't exist in DB, destroy session
    if ($result->num_rows != 1) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?error=invalid_session');
        exit();
    }

    // Store admin data in variable for use in pages
    $admin = $result->fetch_assoc();

    // Update session with fresh data
    $_SESSION['username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['admin_name'];
    $_SESSION['position'] = $admin['position'] ?? ($_SESSION['position'] ?? 'admin');
    $_SESSION['image'] = $admin['image'] ?? ($_SESSION['image'] ?? '../images/profiles/default.jpg');

    // If deactivated, destroy session
    if ((int)$admin['is_active'] === 0) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?error=deactivated');
        exit();
    }

} catch (Exception $e) {
    // If DB error occurs, destroy session
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=db_error');
    exit();
}

// Session timeout (2 hours of inactivity)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 7200)) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?error=timeout');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time

// Counselor permission restrictions - Access control
if (isset($_SESSION['position']) && $_SESSION['position'] === 'counselor') {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    
    // Restricted pages (admin-only)
    $restricted_pages = [
        'admin.php', 'admin_add.php', 'admin_edit.php', 'admin_delete.php', 
        'admin_row.php', 'admin_toggle_active.php', 'access_codes.php',
        'admin_dashboard.php', 'statistics.php', 'predictive_dashboard.php',
        'question_assessment.php', 'question_category.php', 'notifications.php'
    ];
    
    // Restricted directories (admin-only)
    $restricted_directories = [
        'admin', 'statistics', 'predictive_analytics', 'question_assessment', 
        'question_category', 'notifications', 'couple_scheduling'
    ];
    
    // Check if current page is restricted
    if (in_array($current_page, $restricted_pages)) {
        header('Location: ../counselor/counselor_dashboard.php');
        exit();
    }
    
    // Check if current directory is restricted
    if (in_array($current_dir, $restricted_directories)) {
        header('Location: ../counselor/counselor_dashboard.php');
        exit();
    }
}

// Admin (non-superadmin) restrictions - prevent access to superadmin-only pages
if (isset($_SESSION['position']) && $_SESSION['position'] === 'admin') {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));

    // Superadmin-only admin management pages
    $superadmin_pages = [
        'admin.php', 'admin_add.php', 'admin_edit.php', 'admin_delete.php',
        'admin_row.php', 'admin_toggle_active.php'
    ];

    if (in_array($current_page, $superadmin_pages)) {
        header('Location: ../admin/admin_dashboard.php');
        exit();
    }
}

?>