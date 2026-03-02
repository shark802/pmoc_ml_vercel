<?php
/**
 * Database Connection Test
 * Use this to diagnose connection issues
 * Access: https://your-domain.com/test_connection.php
 */

header('Content-Type: application/json');

$results = [];

// Test 1: Check if env_loader exists
$results['env_loader'] = file_exists('includes/env_loader.php') ? 'exists' : 'missing';

// Test 2: Load env_loader
if (file_exists('includes/env_loader.php')) {
    require_once 'includes/env_loader.php';
    $results['env_loader_loaded'] = function_exists('getEnvVar') ? 'loaded' : 'failed';
    
    // Test 3: Check if .env file exists
    $env_file = __DIR__ . '/.env';
    $results['env_file_exists'] = file_exists($env_file) ? 'exists' : 'missing';
    $results['env_file_path'] = $env_file;
    
    // Test 4: Check environment variables
    $results['env_vars'] = [
        'DB_HOST' => getEnvVar('DB_HOST', 'not_set'),
        'DB_USER' => getEnvVar('DB_USER', 'not_set'),
        'DB_NAME' => getEnvVar('DB_NAME', 'not_set'),
        'DB_PASSWORD' => getEnvVar('DB_PASSWORD', 'not_set') ? 'set' : 'not_set',
        'ENVIRONMENT' => getEnvVar('ENVIRONMENT', 'not_set')
    ];
    
    // Test 5: Check server info
    $results['server_info'] = [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not_set',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'not_set',
        'PHP_SAPI' => php_sapi_name()
    ];
}

// Test 4: Check if conn.php exists
$results['conn_file'] = file_exists('includes/conn.php') ? 'exists' : 'missing';

// Test 5: Try to load conn.php
if (file_exists('includes/conn.php')) {
    try {
        require_once 'includes/conn.php';
        $results['conn_loaded'] = 'loaded';
        
        // Test 6: Check if $conn is set
        $results['conn_set'] = isset($conn) ? 'set' : 'not_set';
        
        // Test 7: Check connection object
        if (isset($conn)) {
            if ($conn === null) {
                $results['conn_value'] = 'null';
            } elseif (is_object($conn)) {
                $results['conn_value'] = 'object';
                if (property_exists($conn, 'connect_error')) {
                    $results['conn_error'] = $conn->connect_error ?: 'none';
                }
                if (method_exists($conn, 'ping')) {
                    $results['conn_ping'] = $conn->ping() ? 'success' : 'failed';
                }
            } else {
                $results['conn_value'] = gettype($conn);
            }
        }
    } catch (Exception $e) {
        $results['conn_error'] = $e->getMessage();
    } catch (Error $e) {
        $results['conn_fatal_error'] = $e->getMessage();
    }
}

// Test 8: Check other required files
$results['required_files'] = [
    'audit_log.php' => file_exists('includes/audit_log.php') ? 'exists' : 'missing',
    'rate_limit_helper.php' => file_exists('includes/rate_limit_helper.php') ? 'exists' : 'missing'
];

echo json_encode($results, JSON_PRETTY_PRINT);

