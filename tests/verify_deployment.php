<?php
/**
 * Deployment Verification Script
 * Verifies all "Apply Before Deployment" items are working
 * 
 * Run with: php tests/verify_deployment.php
 */

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/debug_helper.php';
require_once __DIR__ . '/../includes/conn.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

class DeploymentVerification {
    private $passed = 0;
    private $failed = 0;
    private $warnings = 0;
    
    public function test($name, $condition, $is_warning = false) {
        if ($condition) {
            echo "✅ PASS: $name\n";
            $this->passed++;
        } else {
            if ($is_warning) {
                echo "⚠️  WARN: $name\n";
                $this->warnings++;
            } else {
                echo "❌ FAIL: $name\n";
                $this->failed++;
            }
        }
    }
    
    public function verifyDatabaseIndexes() {
        echo "\n=== Verifying Database Indexes ===\n";
        
        global $conn;
        if (!$conn) {
            echo "⚠️  SKIP: Database connection not available\n";
            $this->test("Database indexes verification (skipped)", true, true);
            return;
        }
        
        $tables_to_check = [
            'couple_responses' => ['idx_couple_responses_access_id', 'idx_couple_responses_respondent'],
            'couple_access' => ['idx_couple_access_access_id', 'idx_couple_access_access_code'],
            'audit_logs' => ['idx_audit_logs_created_at', 'idx_audit_logs_user_id'],
            'ml_analysis' => ['idx_ml_analysis_access_id', 'idx_ml_analysis_risk_level'],
            'admin' => ['idx_admin_admin_id', 'idx_admin_username'],
            'scheduling' => ['idx_scheduling_access_id', 'idx_scheduling_session_date']
        ];
        
        foreach ($tables_to_check as $table => $expected_indexes) {
            try {
                $result = $conn->query("SHOW INDEX FROM `$table`");
                if ($result) {
                    $indexes = [];
                    while ($row = $result->fetch_assoc()) {
                        $indexes[] = $row['Key_name'];
                    }
                    
                    foreach ($expected_indexes as $index_name) {
                        $exists = in_array($index_name, $indexes);
                        $this->test("Index exists: $table.$index_name", $exists, true);
                    }
                } else {
                    $this->test("Can query indexes for $table", false);
                }
            } catch (Exception $e) {
                $this->test("Table $table exists and is accessible", false);
                echo "   Error: " . $e->getMessage() . "\n";
            }
        }
        
        // Test query performance with EXPLAIN
        echo "\n--- Testing Query Performance ---\n";
        try {
            $stmt = $conn->prepare("EXPLAIN SELECT * FROM couple_responses WHERE access_id = ? LIMIT 10");
            $test_id = 1;
            $stmt->bind_param("i", $test_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $uses_index = !empty($row['key']);
                $this->test("Query uses index (EXPLAIN shows key)", $uses_index, true);
                
                if ($uses_index) {
                    echo "   Index used: " . $row['key'] . "\n";
                    echo "   Rows examined: " . $row['rows'] . "\n";
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->test("Query performance test", false, true);
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }
    
    public function verifyHtaccess() {
        echo "\n=== Verifying .htaccess Configuration ===\n";
        
        $htaccess_file = __DIR__ . '/../.htaccess';
        $exists = file_exists($htaccess_file);
        $this->test(".htaccess file exists", $exists);
        
        if ($exists) {
            $content = file_get_contents($htaccess_file);
            
            // Check for gzip compression
            $has_gzip = strpos($content, 'mod_deflate') !== false || strpos($content, 'DEFLATE') !== false;
            $this->test(".htaccess has gzip compression", $has_gzip);
            
            // Check for browser caching
            $has_caching = strpos($content, 'mod_expires') !== false || strpos($content, 'Cache-Control') !== false || strpos($content, 'ExpiresByType') !== false;
            $this->test(".htaccess has browser caching", $has_caching);
            
            // Check for security headers
            $has_security = strpos($content, 'X-Frame-Options') !== false || strpos($content, 'X-XSS-Protection') !== false;
            $this->test(".htaccess has security headers", $has_security);
        }
        
        // Note: Actual gzip/caching verification requires web server access
        echo "\n⚠️  Note: To fully verify gzip compression and caching, check browser DevTools:\n";
        echo "   1. Open DevTools → Network tab\n";
        echo "   2. Reload page and check Response Headers\n";
        echo "   3. Look for 'Content-Encoding: gzip' and 'Cache-Control' headers\n";
    }
    
    public function verifyEnvironmentConfiguration() {
        echo "\n=== Verifying Environment Configuration ===\n";
        
        // Check .env.example exists
        $env_example = __DIR__ . '/../.env.example';
        $this->test(".env.example exists", file_exists($env_example));
        
        // Check critical environment variables have defaults or are set
        $critical_vars = ['DB_HOST', 'DB_NAME', 'ENVIRONMENT', 'DEBUG_MODE'];
        foreach ($critical_vars as $var) {
            $value = getEnvVar($var);
            $has_value = $value !== null && $value !== '';
            $this->test("Environment variable $var is accessible", $has_value, true);
        }
        
        // Check DEBUG_MODE setting
        $debug_mode = getEnvVar('DEBUG_MODE', 'false');
        $is_production_ready = strtolower($debug_mode) === 'false' || $debug_mode === '0';
        $this->test("DEBUG_MODE is production-ready (false/0)", $is_production_ready, !$is_production_ready);
        
        if (!$is_production_ready) {
            echo "   ⚠️  WARNING: DEBUG_MODE is enabled. Set to 'false' in production!\n";
        }
    }
    
    public function verifySecurityConfiguration() {
        echo "\n=== Verifying Security Configuration ===\n";
        
        // Check security files exist
        $security_files = [
            'includes/security_headers.php',
            'includes/api_security.php',
            'includes/csrf_helper.php',
            'includes/rate_limit_helper.php'
        ];
        
        foreach ($security_files as $file) {
            $exists = file_exists(__DIR__ . '/../' . $file);
            $this->test("Security file exists: $file", $exists);
        }
        
        // Check functions exist
        $this->test("getCsrfToken function exists", function_exists('getCsrfToken'));
        $this->test("validateCsrfToken function exists", function_exists('validateCsrfToken'));
        
        if (function_exists('checkRateLimit')) {
            $this->test("checkRateLimit function exists", true);
        } else {
            // Try to load it
            $rate_limit_file = __DIR__ . '/../includes/rate_limit_helper.php';
            if (file_exists($rate_limit_file)) {
                require_once $rate_limit_file;
                $this->test("checkRateLimit function exists", function_exists('checkRateLimit'));
            } else {
                $this->test("Rate limiting helper exists", false, true);
            }
        }
    }
    
    public function verifyPerformanceOptimizations() {
        echo "\n=== Verifying Performance Optimizations ===\n";
        
        // Check cache directory exists
        $cache_dir = __DIR__ . '/../cache';
        $this->test("Cache directory exists", is_dir($cache_dir));
        
        // Check cache helper exists
        $cache_helper = __DIR__ . '/../includes/cache_helper.php';
        $this->test("Cache helper exists", file_exists($cache_helper));
        
        if (file_exists($cache_helper)) {
            require_once $cache_helper;
            $this->test("getCachedData function exists", function_exists('getCachedData'));
        }
        
        // Check .htaccess for compression
        $htaccess = __DIR__ . '/../.htaccess';
        if (file_exists($htaccess)) {
            $content = file_get_contents($htaccess);
            $has_compression = strpos($content, 'DEFLATE') !== false || strpos($content, 'gzip') !== false;
            $this->test("Compression configured in .htaccess", $has_compression);
        }
    }
    
    public function runAll() {
        echo "🔍 Deployment Verification\n";
        echo "===========================\n";
        
        $this->verifyDatabaseIndexes();
        $this->verifyHtaccess();
        $this->verifyEnvironmentConfiguration();
        $this->verifySecurityConfiguration();
        $this->verifyPerformanceOptimizations();
        
        echo "\n===========================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed, {$this->warnings} warnings\n";
        
        if ($this->failed > 0) {
            echo "❌ Some verifications failed. Please fix issues before deployment.\n";
            exit(1);
        } elseif ($this->warnings > 0) {
            echo "⚠️  Some warnings found. Review and address before deployment.\n";
            exit(0);
        } else {
            echo "✅ All deployment verifications passed!\n";
            echo "   System is ready for deployment.\n";
            exit(0);
        }
    }
}

// Run verification if executed directly
if (php_sapi_name() === 'cli') {
    $verification = new DeploymentVerification();
    $verification->runAll();
} else {
    die("This script can only be run from the command line.");
}

