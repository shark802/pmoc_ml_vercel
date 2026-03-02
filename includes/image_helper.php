<?php
/**
 * Image Helper Functions
 * Provides secure image paths that work with HTTPS
 */

/**
 * Get secure image path (protocol-relative or absolute HTTPS)
 * 
 * @param string $image_path Relative path to image (e.g., '../images/bcpdo.png')
 * @return string Secure image path
 */
function getSecureImagePath($image_path) {
    // Remove leading slash if present
    $image_path = ltrim($image_path, '/');
    
    // If already absolute URL, return as is
    if (preg_match('/^https?:\/\//', $image_path)) {
        return $image_path;
    }
    
    // Detect if we're on HTTPS
    $is_https = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    
    // Check if we're on localhost
    $is_localhost = (
        isset($_SERVER['HTTP_HOST']) && (
            $_SERVER['HTTP_HOST'] === 'localhost' ||
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
        )
    );
    
    // Get current directory structure to build correct relative path
    $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $base_path = rtrim($script_dir, '/');
    
    // If image path starts with ../, we need to adjust
    if (strpos($image_path, '../') === 0) {
        // Count ../ levels
        $levels = substr_count($image_path, '../');
        $path_parts = explode('/', trim($base_path, '/'));
        $path_parts = array_slice($path_parts, 0, -$levels);
        $base_path = '/' . implode('/', $path_parts);
        $image_path = str_replace('../', '', $image_path);
    } elseif (strpos($image_path, 'images/') === 0) {
        // If path starts with images/, it's relative to root
        $base_path = '';
    }
    
    // Build full path
    $full_path = $base_path . '/' . $image_path;
    $full_path = str_replace('//', '/', $full_path); // Remove double slashes
    
    // In production (non-localhost), prefer relative paths to avoid hardcoded domains
    // Only use absolute URLs when necessary (HTTPS mixed content issues)
    if (!$is_localhost) {
        // In production, use relative paths - browser will resolve correctly
        // Only use absolute URL if we're on HTTPS and need to prevent mixed content
        if ($is_https) {
            // Use absolute HTTPS URL to prevent mixed content warnings
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host && !$is_localhost) {
                return 'https://' . $host . $full_path;
            }
        }
        // For HTTP in production, use relative path
        return $full_path;
    }
    
    // For localhost, use relative paths (browser will use current protocol)
    return $full_path;
}

