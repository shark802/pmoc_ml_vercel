<?php
/**
 * Simple rate limiting helper using file-based storage
 * 
 * Usage:
 *   if (!checkRateLimit('login', $_SERVER['REMOTE_ADDR'], 5, 300)) {
 *       die('Too many requests. Please try again later.');
 *   }
 */

/**
 * Check rate limit for an action
 * 
 * @param string $action Action identifier (e.g., 'login', 'api_call')
 * @param string $identifier User identifier (IP address, user ID, etc.)
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds (default: 300 = 5 minutes)
 * @return bool True if allowed, false if rate limited
 */
function checkRateLimit($action, $identifier, $maxAttempts = 5, $timeWindow = 300) {
    $rateLimitDir = __DIR__ . '/../cache/rate_limits';
    if (!file_exists($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
        file_put_contents($rateLimitDir . '/.htaccess', "Deny from all\n");
    }
    
    $key = md5($action . '_' . $identifier);
    $file = $rateLimitDir . '/' . $key . '.json';
    
    $now = time();
    $attempts = [];
    
    // Load existing attempts
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['attempts'])) {
            $attempts = $data['attempts'];
        }
    }
    
    // Remove old attempts outside time window
    $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    // Check if limit exceeded
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    // Record this attempt
    $attempts[] = $now;
    
    // Save attempts
    file_put_contents($file, json_encode([
        'attempts' => array_values($attempts),
        'last_attempt' => $now
    ]), LOCK_EX);
    
    return true;
}

/**
 * Get remaining attempts for an action
 * 
 * @param string $action Action identifier
 * @param string $identifier User identifier
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return int Remaining attempts
 */
function getRemainingAttempts($action, $identifier, $maxAttempts = 5, $timeWindow = 300) {
    $rateLimitDir = __DIR__ . '/../cache/rate_limits';
    $key = md5($action . '_' . $identifier);
    $file = $rateLimitDir . '/' . $key . '.json';
    
    if (!file_exists($file)) {
        return $maxAttempts;
    }
    
    $now = time();
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data || !isset($data['attempts'])) {
        return $maxAttempts;
    }
    
    // Remove old attempts
    $attempts = array_filter($data['attempts'], function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    return max(0, $maxAttempts - count($attempts));
}

/**
 * Clear rate limit for an action (useful after successful login)
 * 
 * @param string $action Action identifier
 * @param string $identifier User identifier
 * @return bool Success status
 */
function clearRateLimit($action, $identifier) {
    $rateLimitDir = __DIR__ . '/../cache/rate_limits';
    $key = md5($action . '_' . $identifier);
    $file = $rateLimitDir . '/' . $key . '.json';
    
    if (file_exists($file)) {
        return @unlink($file);
    }
    return true;
}


