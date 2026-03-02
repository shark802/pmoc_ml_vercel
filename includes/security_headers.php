<?php
/**
 * Security Headers Helper
 * Sets security-related HTTP headers
 */

if (!function_exists('setSecurityHeaders')) {
    /**
     * Set security HTTP headers
     * 
     * @return void
     */
    function setSecurityHeaders() {
            // HTTPS enforcement (only for production, skip for localhost)
            $https_enabled = getEnvVar('HTTPS_ENABLED', 'true');
            $is_localhost = (
                (isset($_SERVER['HTTP_HOST']) && (
                    $_SERVER['HTTP_HOST'] === 'localhost' || 
                    $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
                    strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
                )) ||
                php_sapi_name() === 'cli'
            );
            
            // Only enforce HTTPS redirect in production (not localhost)
            if (strtolower($https_enabled) === 'true' && !$is_localhost && !isset($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] != 443) {
                // Redirect to HTTPS if not already on HTTPS
                if ($_SERVER['REQUEST_METHOD'] === 'GET' && !headers_sent() && isset($_SERVER['HTTP_HOST'])) {
                    $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header("Location: $url", true, 301);
                    exit();
                }
            }
        
        // Security headers
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy (allows necessary CDNs while maintaining security)
            // Allow trusted CDNs for JavaScript, CSS, fonts, and images
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' " .
                   "https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com " .
                   "https://code.jquery.com; " .
                   "style-src 'self' 'unsafe-inline' " .
                   "https://cdn.jsdelivr.net https://fonts.googleapis.com " .
                   "https://cdnjs.cloudflare.com https://cdn.datatables.net " .
                   "https://code.jquery.com; " .
                   "font-src 'self' https://fonts.gstatic.com https://fonts.googleapis.com " .
                   "https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data: blob:; " .
                   "img-src 'self' data: https: http: blob:; " .
                   "connect-src 'self' https:; " .
                   "frame-src 'self';";
            header("Content-Security-Policy: $csp");
            
            // HTTPS Strict Transport Security (if HTTPS is enabled)
            if (strtolower($https_enabled) === 'true') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }
    }
    
    // Auto-set security headers when file is included
    setSecurityHeaders();
}

