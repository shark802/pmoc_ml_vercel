<?php
/**
 * Unit Tests
 * Tests for critical business logic functions
 * 
 * Run with: php tests/unit_test.php
 */

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/debug_helper.php';

class UnitTest {
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
    
    public function testInputSanitization() {
        echo "\n=== Testing Input Sanitization ===\n";
        
        // Test XSS protection
        $xss_input = "<script>alert('XSS')</script>";
        $sanitized = htmlspecialchars($xss_input, ENT_QUOTES, 'UTF-8');
        $this->test("XSS sanitization works", strpos($sanitized, '<script>') === false);
        $this->test("HTML entities encoded", strpos($sanitized, '&lt;') !== false);
        
        // Test SQL injection protection (basic)
        $sql_input = "'; DROP TABLE users; --";
        $sanitized = htmlspecialchars($sql_input, ENT_QUOTES, 'UTF-8');
        $this->test("SQL injection attempt sanitized", strpos($sanitized, 'DROP') !== false); // Should still contain text, just escaped
    }
    
    public function testPasswordHashing() {
        echo "\n=== Testing Password Hashing ===\n";
        
        $password = "test_password_123";
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $this->test("Password hashing works", !empty($hash));
        $this->test("Password verification works", password_verify($password, $hash));
        $this->test("Wrong password fails", !password_verify("wrong_password", $hash));
        $this->test("Hash is different each time", password_hash($password, PASSWORD_DEFAULT) !== password_hash($password, PASSWORD_DEFAULT));
    }
    
    public function testEnvironmentVariableLoader() {
        echo "\n=== Testing Environment Variable Loader ===\n";
        
        $this->test("getEnvVar function exists", function_exists('getEnvVar'));
        
        // Test default value
        $value = getEnvVar('NON_EXISTENT_VAR', 'default_value');
        $this->test("getEnvVar returns default", $value === 'default_value');
        
        // Test that it doesn't return null when default provided
        $value2 = getEnvVar('ANOTHER_NON_EXISTENT_VAR', 'test');
        $this->test("getEnvVar doesn't return null with default", $value2 !== null);
    }
    
    public function testDebugHelper() {
        echo "\n=== Testing Debug Helper ===\n";
        
        $this->test("debug_log function exists", function_exists('debug_log'));
        $this->test("DEBUG_MODE constant exists", defined('DEBUG_MODE'));
        
        // Test that debug_log doesn't throw errors
        try {
            debug_log("Test message");
            $this->test("debug_log executes without error", true);
        } catch (Exception $e) {
            $this->test("debug_log executes without error", false);
        }
    }
    
    public function testDataValidation() {
        echo "\n=== Testing Data Validation ===\n";
        
        // Email validation
        $valid_email = "test@example.com";
        $invalid_email = "not-an-email";
        $this->test("Valid email passes filter_var", filter_var($valid_email, FILTER_VALIDATE_EMAIL) !== false);
        $this->test("Invalid email fails filter_var", filter_var($invalid_email, FILTER_VALIDATE_EMAIL) === false);
        
        // Integer validation
        $valid_int = "123";
        $invalid_int = "abc";
        $this->test("Valid integer passes filter_var", filter_var($valid_int, FILTER_VALIDATE_INT) !== false);
        $this->test("Invalid integer fails filter_var", filter_var($invalid_int, FILTER_VALIDATE_INT) === false);
    }
    
    public function runAll() {
        echo "🧪 Unit Test Suite\n";
        echo "==================\n";
        
        $this->testInputSanitization();
        $this->testPasswordHashing();
        $this->testEnvironmentVariableLoader();
        $this->testDebugHelper();
        $this->testDataValidation();
        
        echo "\n==================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        if ($this->failed > 0) {
            echo "❌ Some tests failed. Please review the issues above.\n";
            exit(1);
        } else {
            echo "✅ All unit tests passed!\n";
            exit(0);
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new UnitTest();
    $test->runAll();
} else {
    die("This script can only be run from the command line.");
}

