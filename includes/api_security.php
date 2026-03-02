<?php
/**
 * API Security Helper
 * Provides API key validation and rate limiting for API endpoints
 */

require_once __DIR__ . '/rate_limit_helper.php';

/**
 * Validate API key for external API calls
 * 
 * @param string $api_key API key to validate
 * @return bool True if valid, false otherwise
 */
function validateApiKey($api_key) {
    if (empty($api_key)) {
        return false;
    }
    
    // Get expected API key from environment
    $expected_key = getEnvVar('API_KEY', '');
    
    // If no API key is configured, allow requests (for backward compatibility)
    // In production, this should be set
    if (empty($expected_key)) {
        // Log warning but allow (for development)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("WARNING - API_KEY not configured in environment");
        }
        return true; // Allow for now, but should be false in production
    }
    
    return hash_equals($expected_key, $api_key);
}

/**
 * Check if request is from internal source (same origin)
 * 
 * @return bool True if internal request
 */
function isInternalRequest() {
    // Check if request is from same origin
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    if (empty($referer) || empty($host)) {
        return false;
    }
    
    return strpos($referer, $host) !== false;
}

/**
 * Validate API request (API key or internal request)
 * 
 * @param string $action API action being called
 * @return bool True if valid, false otherwise
 */
function validateApiRequest($action) {
    // Internal-only actions (require session)
    $internal_actions = ['train', 'analyze_batch'];
    
    // Public actions (can be called externally with API key)
    $public_actions = ['status', 'analyze', 'get_analysis', 'training_status'];
    
    // If internal action, must be from same origin (session check happens in main code)
    if (in_array($action, $internal_actions)) {
        return isInternalRequest();
    }
    
    // For public actions, check API key if provided
    $api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (!empty($api_key)) {
        return validateApiKey($api_key);
    }
    
    // If no API key provided, must be internal request
    return isInternalRequest();
}

/**
 * Rate limit API endpoint
 * 
 * @param string $endpoint API endpoint name
 * @param int $max_requests Maximum requests allowed
 * @param int $time_window Time window in seconds
 * @return bool True if within limit, false if rate limited
 */
function rateLimitApi($endpoint, $max_requests = 60, $time_window = 60) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "api_{$endpoint}_{$ip_address}";
    
    return checkRateLimit($key, $ip_address, $max_requests, $time_window);
}

