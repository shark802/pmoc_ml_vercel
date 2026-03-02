<?php
session_start();
require_once 'includes/conn.php';
require_once 'includes/audit_log.php';

// Log admin logout before destroying session
if (isset($_SESSION['admin_id'])) {
    try {
        logAudit($conn, $_SESSION['admin_id'], AUDIT_LOGOUT, 
            'User logged out', 'authentication');
    } catch (Exception $e) {
        error_log("Audit log error on logout: " . $e->getMessage());
    }
}

try {
    if (isset($_SESSION['access_id'], $_SESSION['respondent'])) {
        $access_id = (int)$_SESSION['access_id'];
        $respondent = $_SESSION['respondent'];
        
        // Clear the selected flag for this user if they haven't submitted their profile
        $clearStmt = $conn->prepare("
            UPDATE couple_access 
            SET {$respondent}_selected = 0 
            WHERE access_id = ? AND {$respondent}_profile_submitted = FALSE
        ");
        $clearStmt->bind_param("i", $access_id);
        $clearStmt->execute();
    }
} catch (Exception $e) {
    // Log error but don't show to user
    error_log("Logout Error: " . $e->getMessage());
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to home page with timeout message if applicable
$redirect = "index.php";
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $redirect .= "?timeout=1";
}
header("Location: " . $redirect);
exit();
?> 