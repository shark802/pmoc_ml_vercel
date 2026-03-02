<?php
/**
 * Resource Loader Helper
 * Provides functions to load CSS/JS resources with smart CDN/local fallback
 * 
 * Best Practice:
 * - Development (localhost): Use local files (no network dependency, no 502 errors)
 * - Production: Use CDN with local fallback (faster, cached, but reliable)
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

/**
 * Check if running on localhost/development
 * 
 * @return bool True if localhost, false if production
 */
if (!function_exists('isLocalhost')) {
    function isLocalhost() {
        if (php_sapi_name() === 'cli') {
            // CLI mode - check environment variable
            $env = getEnvVar('ENVIRONMENT', 'development');
            return (strtolower($env) !== 'production');
        }
        
        if (!isset($_SERVER['HTTP_HOST'])) {
            return true; // Default to localhost if HTTP_HOST not set
        }
        
        $host = $_SERVER['HTTP_HOST'];
        return (
            $host === 'localhost' || 
            $host === '127.0.0.1' ||
            strpos($host, 'localhost:') === 0 ||
            strpos($host, '127.0.0.1:') === 0
        );
    }
}

/**
 * Get resource URL (local or CDN based on environment)
 * 
 * @param string $localPath Local file path (e.g., '../plugins/jquery/jquery.min.js')
 * @param string $cdnUrl CDN URL (e.g., 'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js')
 * @return string Resource URL to use
 */
if (!function_exists('getResourceUrl')) {
    function getResourceUrl($localPath, $cdnUrl) {
        return isLocalhost() ? $localPath : $cdnUrl;
    }
}

/**
 * Generate script tag with smart CDN/local loading
 * 
 * @param string $localPath Local file path
 * @param string $cdnUrl CDN URL
 * @param array $options Additional options (async, defer, onerror, etc.)
 * @return string HTML script tag
 */
if (!function_exists('scriptTag')) {
    function scriptTag($localPath, $cdnUrl, $options = []) {
        $useLocal = isLocalhost();
        $src = $useLocal ? $localPath : $cdnUrl;
        
        $attrs = ['src' => htmlspecialchars($src)];
        
        // Add fallback handler if using CDN
        if (!$useLocal) {
            $onerror = "this.onerror=null; this.src='" . htmlspecialchars($localPath) . "';";
            if (isset($options['onerror'])) {
                $onerror = $options['onerror'] . ' ' . $onerror;
            }
            $attrs['onerror'] = $onerror;
        }
        
        // Add other attributes
        if (isset($options['async'])) $attrs['async'] = $options['async'] ? '' : null;
        if (isset($options['defer'])) $attrs['defer'] = $options['defer'] ? '' : null;
        if (isset($options['id'])) $attrs['id'] = htmlspecialchars($options['id']);
        
        $attrString = '';
        foreach ($attrs as $key => $value) {
            if ($value === null) continue;
            if ($value === '') {
                $attrString .= " $key";
            } else {
                $attrString .= " $key=\"" . htmlspecialchars($value) . "\"";
            }
        }
        
        return "<script$attrString></script>";
    }
}

/**
 * Generate link tag for CSS with smart CDN/local loading
 * 
 * @param string $localPath Local file path
 * @param string $cdnUrl CDN URL
 * @param array $options Additional options (media, onerror, etc.)
 * @return string HTML link tag
 */
if (!function_exists('cssLink')) {
    function cssLink($localPath, $cdnUrl, $options = []) {
        $useLocal = isLocalhost();
        $href = $useLocal ? $localPath : $cdnUrl;
        
        $attrs = [
            'rel' => 'stylesheet',
            'href' => htmlspecialchars($href)
        ];
        
        // Add fallback handler if using CDN
        if (!$useLocal) {
            $onerror = "this.onerror=null; this.href='" . htmlspecialchars($localPath) . "';";
            if (isset($options['onerror'])) {
                $onerror = $options['onerror'] . ' ' . $onerror;
            }
            $attrs['onerror'] = $onerror;
        }
        
        // Add other attributes
        if (isset($options['media'])) $attrs['media'] = htmlspecialchars($options['media']);
        if (isset($options['id'])) $attrs['id'] = htmlspecialchars($options['id']);
        
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= " $key=\"" . htmlspecialchars($value) . "\"";
        }
        
        return "<link$attrString>";
    }
}

