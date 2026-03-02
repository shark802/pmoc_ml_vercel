<?php
/**
 * .htaccess Verification Script
 * Tests if .htaccess compression and caching are working
 * 
 * Run with: php tests/verify_htaccess.php
 * Or access via browser: https://localhost/caps2/tests/verify_htaccess.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>.htaccess Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .test-item {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .pass {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .fail {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .warn {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .status {
            font-weight: bold;
            margin-right: 10px;
        }
        .instructions {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 .htaccess Verification</h1>
        
        <?php
        $tests = [];
        
        // Test 1: Check if .htaccess exists
        $htaccess_path = __DIR__ . '/../.htaccess';
        $htaccess_exists = file_exists($htaccess_path);
        $tests[] = [
            'name' => '.htaccess file exists',
            'status' => $htaccess_exists ? 'pass' : 'fail',
            'message' => $htaccess_exists ? 'File found at: ' . $htaccess_path : 'File not found!'
        ];
        
        if ($htaccess_exists) {
            $htaccess_content = file_get_contents($htaccess_path);
            
            // Test 2: Check for gzip compression
            $has_gzip = strpos($htaccess_content, 'mod_deflate') !== false || 
                       strpos($htaccess_content, 'DEFLATE') !== false ||
                       strpos($htaccess_content, 'AddOutputFilterByType DEFLATE') !== false;
            $tests[] = [
                'name' => 'Gzip compression configured',
                'status' => $has_gzip ? 'pass' : 'fail',
                'message' => $has_gzip ? 'Gzip compression is configured' : 'Gzip compression not found in .htaccess'
            ];
            
            // Test 3: Check for browser caching
            $has_caching = strpos($htaccess_content, 'mod_expires') !== false || 
                          strpos($htaccess_content, 'Cache-Control') !== false ||
                          strpos($htaccess_content, 'ExpiresByType') !== false;
            $tests[] = [
                'name' => 'Browser caching configured',
                'status' => $has_caching ? 'pass' : 'fail',
                'message' => $has_caching ? 'Browser caching is configured' : 'Browser caching not found in .htaccess'
            ];
            
            // Test 4: Check for security headers
            $has_security = strpos($htaccess_content, 'X-Frame-Options') !== false ||
                          strpos($htaccess_content, 'X-XSS-Protection') !== false;
            $tests[] = [
                'name' => 'Security headers configured',
                'status' => $has_security ? 'pass' : 'warn',
                'message' => $has_security ? 'Security headers are configured' : 'Security headers may be set in PHP (this is OK)'
            ];
        }
        
        // Test 5: Check Apache modules (if we can detect)
        $is_apache = strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false;
        $tests[] = [
            'name' => 'Apache server detected',
            'status' => $is_apache ? 'pass' : 'warn',
            'message' => $is_apache ? 'Running on Apache - .htaccess will work' : 'Not Apache - .htaccess may not work (check server type)'
        ];
        
        // Display results
        foreach ($tests as $test) {
            $status_class = $test['status'];
            $status_icon = $test['status'] === 'pass' ? '✅' : ($test['status'] === 'fail' ? '❌' : '⚠️');
            echo "<div class='test-item $status_class'>";
            echo "<span class='status'>$status_icon</span>";
            echo "<strong>{$test['name']}</strong><br>";
            echo "<small>{$test['message']}</small>";
            echo "</div>";
        }
        ?>
        
        <div class="instructions">
            <h3>📋 Manual Verification Steps</h3>
            <p>To fully verify compression and caching are working:</p>
            <ol>
                <li>Open browser DevTools (F12)</li>
                <li>Go to <strong>Network</strong> tab</li>
                <li>Reload the page (Ctrl+R or F5)</li>
                <li>Check <strong>static assets</strong> (CSS, JS, images):
                    <ul>
                        <li>Look for <code>Content-Encoding: gzip</code> in Response Headers</li>
                        <li>Look for <code>Cache-Control</code> headers</li>
                        <li>On second reload, assets should show <code>(from cache)</code></li>
                    </ul>
                </li>
                <li>Check <strong>PHP files</strong> (like this one):
                    <ul>
                        <li>Should have <code>Cache-Control: no-cache</code> (correct!)</li>
                        <li>Should NOT be cached</li>
                    </ul>
                </li>
            </ol>
            
            <h3>🔧 If Compression Not Working</h3>
            <p>If you don't see <code>Content-Encoding: gzip</code>:</p>
            <ol>
                <li>Check if <code>mod_deflate</code> is enabled in Apache:
                    <code>apache2ctl -M | grep deflate</code> (Linux) or check httpd.conf</li>
                <li>Check if <code>mod_rewrite</code> is enabled:
                    <code>apache2ctl -M | grep rewrite</code></li>
                <li>Verify .htaccess is being read (check Apache config for <code>AllowOverride All</code>)</li>
            </ol>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 4px;">
            <strong>ℹ️ Note:</strong> This page checks .htaccess configuration. 
            To verify actual compression, check browser DevTools Network tab for static assets (CSS, JS, images).
        </div>
    </div>
</body>
</html>

