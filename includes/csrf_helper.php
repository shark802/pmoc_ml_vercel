<?php
/**
 * CSRF Protection Helper
 * 
 * Usage in forms:
 *   <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
 * 
 * Usage in POST handlers:
 *   if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
 *       die('Invalid CSRF token');
 *   }
 */

/**
 * Generate or retrieve CSRF token from session
 * 
 * @return string CSRF token
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start(); // Suppress warnings in CLI mode
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start(); // Suppress warnings in CLI mode
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token (use after successful form submission)
 * 
 * @return string New CSRF token
 */
function regenerateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token for AJAX requests (returns token as JSON)
 * 
 * @return string JSON encoded token
 */
function getCsrfTokenJson() {
    return json_encode(['csrf_token' => getCsrfToken()]);
}


