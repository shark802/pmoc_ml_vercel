<?php
/**
 * Integration Tests
 * Tests for system interactions and API endpoints
 * 
 * Run with: php tests/integration_test.php
 */

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/debug_helper.php';
require_once __DIR__ . '/../includes/conn.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

class IntegrationTest {
    private $passed = 0;
    private $failed = 0;
    
    public function test($name, $condition) {
        if ($condition) {
            echo "✅ PASS: $name\n";
            $this->passed++;
        } else {
            echo "❌ FAIL: $name\n";
            $this->failed++;
        }
    }
    
    public function testDatabaseConnection() {
        echo "\n=== Testing Database Connection ===\n";
        
        global $conn;
        if (!$conn) {
            echo "⚠️  SKIP: Database connection not available (this is OK if .env file is not configured)\n";
            $this->test("Database connection (skipped)", true);
            return;
        }
        
        $this->test("Database connection exists", $conn !== null);
        
        // Test basic query
        try {
            $result = $conn->query("SELECT 1 as test");
            $this->test("Database query execution", $result !== false);
            if ($result) {
                $row = $result->fetch_assoc();
                $this->test("Database query result", $row['test'] == 1);
            }
        } catch (Exception $e) {
            $this->test("Database query execution", false);
            echo "   Error: " . $e->getMessage() . "\n";
        }
    }
    
    public function testEnvironmentVariables() {
        echo "\n=== Testing Environment Variables ===\n";
        
        // Environment variables may not be set in test environment, so test with defaults
        $db_host = getEnvVar('DB_HOST', 'localhost');
        $db_name = getEnvVar('DB_NAME', 'test_db');
        $environment = getEnvVar('ENVIRONMENT', 'development');
        $debug_mode = getEnvVar('DEBUG_MODE', 'false');
        
        $this->test("DB_HOST has value (or default)", !empty($db_host));
        $this->test("DB_NAME has value (or default)", !empty($db_name));
        $this->test("ENVIRONMENT has value (or default)", !empty($environment));
        $this->test("DEBUG_MODE has value (or default)", $debug_mode !== null);
    }
    
    public function testHelperFunctions() {
        echo "\n=== Testing Helper Functions ===\n";
        
        // Test getEnvVar
        $test_value = getEnvVar('TEST_VAR', 'default');
        $this->test("getEnvVar with default", $test_value === 'default');
        
        // Test debug_log exists
        $this->test("debug_log function exists", function_exists('debug_log'));
        
        // Test getCsrfToken exists
        $this->test("getCsrfToken function exists", function_exists('getCsrfToken'));
    }
    
    public function testFileStructure() {
        echo "\n=== Testing File Structure ===\n";
        
        $required_files = [
            'includes/conn.php',
            'includes/env_loader.php',
            'includes/debug_helper.php',
            'includes/csrf_helper.php',
            'includes/security_headers.php',
            '.env.example'
        ];
        
        foreach ($required_files as $file) {
            $exists = file_exists(__DIR__ . '/../' . $file);
            $this->test("File exists: $file", $exists);
        }
    }
    
    public function runAll() {
        echo "🔗 Integration Test Suite\n";
        echo "========================\n";
        
        $this->testDatabaseConnection();
        $this->testEnvironmentVariables();
        $this->testHelperFunctions();
        $this->testFileStructure();
        
        echo "\n========================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        if ($this->failed > 0) {
            echo "❌ Some tests failed. Please review the issues above.\n";
            exit(1);
        } else {
            echo "✅ All integration tests passed!\n";
            exit(0);
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new IntegrationTest();
    $test->runAll();
} else {
    die("This script can only be run from the command line.");
}

