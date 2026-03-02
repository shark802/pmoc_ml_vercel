<?php
/**
 * Debug Helper Functions
 * Provides conditional debug logging based on DEBUG_MODE
 */

if (!function_exists('debug_log')) {
    /**
     * Log debug message only if DEBUG_MODE is enabled
     * 
     * @param string $message Debug message to log
     * @return void
     */
    function debug_log($message) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("DEBUG - " . $message);
        }
    }
}

if (!function_exists('warning_log')) {
    /**
     * Log warning message (always logged)
     * 
     * @param string $message Warning message to log
     * @return void
     */
    function warning_log($message) {
        error_log("WARNING - " . $message);
    }
}

if (!function_exists('error_log_safe')) {
    /**
     * Log error message (always logged)
     * 
     * @param string $message Error message to log
     * @return void
     */
    function error_log_safe($message) {
        error_log("ERROR - " . $message);
    }
}

