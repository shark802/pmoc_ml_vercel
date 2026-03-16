<?php
/**
 * ML Analysis Configuration
 * Control ML behavior and settings
 */

// 🤖 Auto-Analysis Settings
define('ML_AUTO_ANALYSIS_ENABLED', true);  // Set to false to disable automatic analysis

// ML Service Settings - Auto-detect localhost vs production
// Heroku Flask Service Endpoint
$heroku_endpoint = 'https://endpoint-pmoc-a0a6708d039f.herokuapp.com';

// Check if running on localhost (development) or production
$is_production = (
    strpos($_SERVER['HTTP_HOST'], 'pmoc.bccbsis.com') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'bccbsis.com') !== false
);

if ($is_production) {
    // Production - use Heroku service
    define('ML_SERVICE_URL', $heroku_endpoint);
} else {
    // Local development - use local Flask service
    define('ML_SERVICE_URL', 'http://127.0.0.1:5000');
}

define('ML_SERVICE_TIMEOUT', 30); // seconds

// ML Analysis Settings
define('ML_SAVE_TO_DATABASE', true);  // Save results to ml_analysis table
define('ML_LOG_ANALYSIS', true);      // Log analysis in PHP error log

/**
 * Check if ML auto-analysis is enabled
 */
function is_ml_auto_analysis_enabled() {
    return ML_AUTO_ANALYSIS_ENABLED;
}

/**
 * Get ML service URL
 */
function get_ml_service_url($endpoint = '') {
    return ML_SERVICE_URL . ($endpoint ? '/' . ltrim($endpoint, '/') : '');
}
?>

