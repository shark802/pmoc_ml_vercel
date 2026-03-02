<?php
/**
 * Environment Variable Loader
 * Loads environment variables from .env file if it exists
 * Falls back to $_ENV or $_SERVER if .env is not available
 */

if (!function_exists('loadEnvFile')) {
    function loadEnvFile($filePath = null) {
        if ($filePath === null) {
            // Try to find .env file in project root
            $filePath = __DIR__ . '/../.env';
        }
        
        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE format
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    // Set environment variable if not already set
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
    
    // Auto-load .env file when this file is included
    loadEnvFile();
}

/**
 * Get environment variable with fallback
 * 
 * @param string $key Environment variable key
 * @param mixed $default Default value if not found
 * @return mixed Environment variable value or default
 */
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        // Check $_ENV first (from .env file or system)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Check $_SERVER as last resort
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }
}

/**
 * Check if running in debug mode
 * 
 * @return bool True if debug mode is enabled
 */
if (!function_exists('isDebugMode')) {
    function isDebugMode() {
        $debug = getEnvVar('DEBUG_MODE', 'false');
        return in_array(strtolower($debug), ['true', '1', 'yes', 'on']);
    }
}

// Define DEBUG_MODE constant for use throughout application
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', isDebugMode());
}

