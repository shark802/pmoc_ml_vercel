<?php
/**
 * Test Runner - Run All Tests
 * Executes all test suites and provides a summary
 * 
 * Run with: php tests/run_all_tests.php
 */

echo "🧪 Running All Test Suites\n";
echo "==========================\n\n";

$test_files = [
    'Unit Tests' => 'unit_test.php',
    'Integration Tests' => 'integration_test.php',
    'Security Tests' => 'security_test.php',
    'Performance Tests' => 'performance_test.php',
    'End-to-End Tests' => 'e2e_test.php'
];

$results = [];
$total_passed = 0;
$total_failed = 0;

foreach ($test_files as $name => $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (!file_exists($file_path)) {
        echo "⚠️  SKIP: $name ($file not found)\n\n";
        continue;
    }
    
    echo "Running $name...\n";
    echo str_repeat('-', 50) . "\n";
    
    // Capture output
    ob_start();
    $exit_code = 0;
    passthru("php \"$file_path\" 2>&1", $exit_code);
    $output = ob_get_clean();
    
    echo $output . "\n";
    
    // Parse results (look for "passed, X failed" pattern)
    if (preg_match('/(\d+) passed, (\d+) failed/', $output, $matches)) {
        $passed = (int)$matches[1];
        $failed = (int)$matches[2];
        $total_passed += $passed;
        $total_failed += $failed;
        
        $results[$name] = [
            'passed' => $passed,
            'failed' => $failed,
            'status' => $failed === 0 ? 'PASS' : 'FAIL'
        ];
    } else {
        $results[$name] = [
            'passed' => 0,
            'failed' => 0,
            'status' => 'UNKNOWN'
        ];
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "📊 Test Summary\n";
echo str_repeat('=', 50) . "\n\n";

foreach ($results as $name => $result) {
    $status_icon = $result['status'] === 'PASS' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : '⚠️');
    echo "$status_icon $name: {$result['passed']} passed, {$result['failed']} failed\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Total: $total_passed passed, $total_failed failed\n";

if ($total_failed > 0) {
    echo "❌ Some tests failed. Please review the output above.\n";
    exit(1);
} else {
    echo "✅ All tests passed!\n";
    exit(0);
}

