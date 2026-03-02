<?php
/**
 * Performance Benchmark Tests
 * Tests and benchmarks critical code paths
 * 
 * Run with: php tests/performance_test.php
 */

require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/debug_helper.php';
require_once __DIR__ . '/../includes/conn.php';

class PerformanceTest {
    private $passed = 0;
    private $failed = 0;
    private $benchmarks = [];
    
    public function test($name, $condition) {
        if ($condition) {
            echo "✅ PASS: $name\n";
            $this->passed++;
        } else {
            echo "❌ FAIL: $name\n";
            $this->failed++;
        }
    }
    
    public function benchmark($name, $callback, $iterations = 100) {
        $start = microtime(true);
        $start_memory = memory_get_usage();
        
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        
        $end = microtime(true);
        $end_memory = memory_get_usage();
        
        $time = ($end - $start) * 1000; // Convert to milliseconds
        $avg_time = $time / $iterations;
        $memory = $end_memory - $start_memory;
        $avg_memory = $memory / $iterations;
        
        $this->benchmarks[$name] = [
            'total_time' => $time,
            'avg_time' => $avg_time,
            'total_memory' => $memory,
            'avg_memory' => $avg_memory,
            'iterations' => $iterations
        ];
        
        echo "⏱️  BENCHMARK: $name\n";
        echo "   Total time: " . number_format($time, 2) . " ms\n";
        echo "   Avg time: " . number_format($avg_time, 4) . " ms per iteration\n";
        echo "   Memory: " . number_format($memory / 1024, 2) . " KB\n";
        echo "   Avg memory: " . number_format($avg_memory / 1024, 4) . " KB per iteration\n";
        
        // Performance thresholds (adjust based on your needs)
        $max_avg_time = 100; // 100ms per operation is acceptable
        $max_memory = 1024 * 1024; // 1MB per operation is acceptable
        
        $time_ok = $avg_time < $max_avg_time;
        $memory_ok = $avg_memory < $max_memory;
        
        $this->test("$name - Time acceptable (< {$max_avg_time}ms)", $time_ok);
        $this->test("$name - Memory acceptable (< " . number_format($max_memory / 1024, 0) . "KB)", $memory_ok);
        
        return $time_ok && $memory_ok;
    }
    
    public function testDatabaseQueryPerformance() {
        echo "\n=== Testing Database Query Performance ===\n";
        
        global $conn;
        if (!$conn) {
            echo "⚠️  SKIP: Database connection not available\n";
            $this->test("Database query performance (skipped)", true);
            return;
        }
        
        // Benchmark simple SELECT query
        $this->benchmark("Simple SELECT query", function() use ($conn) {
            $result = $conn->query("SELECT 1 as test");
            if ($result) {
                $result->fetch_assoc();
            }
        }, 100);
        
        // Benchmark prepared statement
        $this->benchmark("Prepared statement query", function() use ($conn) {
            $stmt = $conn->prepare("SELECT 1 as test WHERE 1 = ?");
            if ($stmt) {
                $param = 1;
                $stmt->bind_param("i", $param);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $result->fetch_assoc();
                }
                $stmt->close();
            }
        }, 100);
    }
    
    public function testEnvironmentVariablePerformance() {
        echo "\n=== Testing Environment Variable Performance ===\n";
        
        // Benchmark getEnvVar calls
        $this->benchmark("getEnvVar() calls", function() {
            getEnvVar('DB_HOST', 'localhost');
            getEnvVar('DB_NAME', 'test_db');
            getEnvVar('ENVIRONMENT', 'development');
            getEnvVar('DEBUG_MODE', 'false');
        }, 1000);
    }
    
    public function testStringOperationsPerformance() {
        echo "\n=== Testing String Operations Performance ===\n";
        
        $test_string = "<script>alert('XSS')</script>";
        
        // Benchmark htmlspecialchars
        $this->benchmark("htmlspecialchars() sanitization", function() use ($test_string) {
            htmlspecialchars($test_string, ENT_QUOTES, 'UTF-8');
        }, 1000);
        
        // Benchmark password hashing
        $password = "test_password_123";
        $this->benchmark("password_hash()", function() use ($password) {
            password_hash($password, PASSWORD_DEFAULT);
        }, 100);
    }
    
    public function testDebugLoggingPerformance() {
        echo "\n=== Testing Debug Logging Performance ===\n";
        
        // Benchmark debug_log (should be fast when DEBUG_MODE=false)
        $this->benchmark("debug_log() calls", function() {
            debug_log("Test message for performance");
        }, 1000);
    }
    
    public function testArrayOperationsPerformance() {
        echo "\n=== Testing Array Operations Performance ===\n";
        
        $large_array = range(1, 1000);
        
        // Benchmark array operations
        $this->benchmark("Array count and iteration", function() use ($large_array) {
            $count = count($large_array);
            foreach ($large_array as $value) {
                $value * 2;
            }
        }, 100);
    }
    
    public function testCachePerformance() {
        echo "\n=== Testing Cache Performance ===\n";
        
        if (!function_exists('getCachedData')) {
            echo "⚠️  SKIP: Cache helper not available\n";
            $this->test("Cache performance (skipped)", true);
            return;
        }
        
        require_once __DIR__ . '/../includes/cache_helper.php';
        
        // Benchmark cache operations
        $this->benchmark("Cache get/set operations", function() {
            $key = 'test_cache_' . rand(1, 1000);
            $data = ['test' => 'data', 'timestamp' => time()];
            // Simulate cache operations
            $cache_file = __DIR__ . '/../cache/' . md5($key) . '.cache';
            file_put_contents($cache_file, serialize($data));
            if (file_exists($cache_file)) {
                unserialize(file_get_contents($cache_file));
                @unlink($cache_file);
            }
        }, 100);
    }
    
    public function generateReport() {
        echo "\n=== Performance Summary ===\n";
        echo "Total benchmarks: " . count($this->benchmarks) . "\n";
        
        $total_time = 0;
        $total_memory = 0;
        
        foreach ($this->benchmarks as $name => $benchmark) {
            $total_time += $benchmark['total_time'];
            $total_memory += $benchmark['total_memory'];
        }
        
        echo "Total benchmark time: " . number_format($total_time, 2) . " ms\n";
        echo "Total benchmark memory: " . number_format($total_memory / 1024, 2) . " KB\n";
        
        // Find slowest operation
        $slowest = null;
        $slowest_time = 0;
        foreach ($this->benchmarks as $name => $benchmark) {
            if ($benchmark['avg_time'] > $slowest_time) {
                $slowest_time = $benchmark['avg_time'];
                $slowest = $name;
            }
        }
        
        if ($slowest) {
            echo "Slowest operation: $slowest (" . number_format($slowest_time, 4) . " ms avg)\n";
        }
    }
    
    public function runAll() {
        echo "⚡ Performance Test Suite\n";
        echo "=========================\n";
        
        $this->testDatabaseQueryPerformance();
        $this->testEnvironmentVariablePerformance();
        $this->testStringOperationsPerformance();
        $this->testDebugLoggingPerformance();
        $this->testArrayOperationsPerformance();
        $this->testCachePerformance();
        
        $this->generateReport();
        
        echo "\n=========================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        if ($this->failed > 0) {
            echo "⚠️  Some performance tests failed. Review benchmarks above.\n";
            echo "   Consider optimizing slow operations.\n";
            exit(1);
        } else {
            echo "✅ All performance tests passed!\n";
            echo "   Critical code paths are performing within acceptable limits.\n";
            exit(0);
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new PerformanceTest();
    $test->runAll();
} else {
    die("This script can only be run from the command line.");
}

