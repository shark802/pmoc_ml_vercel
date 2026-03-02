<?php
	/**
	 * Database Connection Singleton
	 * Reuses existing connection to prevent exceeding max_connections_per_hour limit
	 */
	
	// Load environment variables
	require_once __DIR__ . '/env_loader.php';
	
	// Auto-detect localhost vs production environment
	// Handle CLI mode and cases where HTTP_HOST might not be set
	$is_localhost = true; // Default to localhost for safety
	
	if (php_sapi_name() === 'cli') {
		// For CLI, check environment variable or default to localhost
		$env = getEnvVar('ENVIRONMENT', 'development');
		$is_localhost = (strtolower($env) !== 'production');
	} elseif (isset($_SERVER['HTTP_HOST'])) {
		// Web mode - check HTTP_HOST if available
		$http_host = $_SERVER['HTTP_HOST'];
		$is_localhost = (
			$http_host === 'localhost' || 
			$http_host === '127.0.0.1' ||
			strpos($http_host, 'localhost:') === 0 ||
			strpos($http_host, '127.0.0.1:') === 0 ||
			strpos($http_host, '.local') !== false
		);
	} else {
		// HTTP_HOST not set - check environment variable or SERVER_NAME as fallback
		$env = getEnvVar('ENVIRONMENT', 'development');
		$server_name = $_SERVER['SERVER_NAME'] ?? '';
		
		if (strtolower($env) === 'production') {
			$is_localhost = false;
		} elseif (!empty($server_name)) {
			// Check if server name indicates production (has domain, not localhost)
			$is_localhost = (
				$server_name === 'localhost' || 
				$server_name === '127.0.0.1' ||
				strpos($server_name, 'localhost') !== false ||
				strpos($server_name, '.local') !== false
			);
		} else {
			// Default to localhost if we can't determine
			$is_localhost = true;
		}
	}
	
	// Override: If domain contains production domain, force production mode
	$host_check = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
	if (!empty($host_check) && (strpos($host_check, 'pmoc.bccbsis.com') !== false || 
	                             strpos($host_check, 'bccbsis.com') !== false ||
	                             strpos($host_check, '.com') !== false && strpos($host_check, 'localhost') === false)) {
		$is_localhost = false;
	}
	
	// Connection configuration - Use environment variables with fallbacks
	if ($is_localhost) {
		// Local development database - use environment variables or defaults
		$host = getEnvVar('DB_HOST', 'localhost');
		$username = getEnvVar('DB_USER', 'root');
		$password = getEnvVar('DB_PASSWORD', '');
		$database_name = getEnvVar('DB_NAME', 'u520834156_DBpmoc25');
	} else {
		// Remote production database - use environment variables with production fallbacks
		$host = getEnvVar('DB_HOST', 'srv1322.hstgr.io');
		$username = getEnvVar('DB_USER', 'u520834156_userPmoc');
		$password = getEnvVar('DB_PASSWORD', ''); // Will log error if empty but allow connection attempt
		$database_name = getEnvVar('DB_NAME', 'u520834156_DBpmoc25');
		
		// Security check: Log warning if password is not set in production
		if (empty($password) && php_sapi_name() !== 'cli') {
			error_log("CRITICAL: DB_PASSWORD environment variable is not set in production! Connection will fail.");
			error_log("Please create a .env file in the project root with: DB_PASSWORD=your_password");
		} elseif (empty($password) && php_sapi_name() === 'cli') {
			// For CLI mode (tests), just log a warning but don't die
			error_log("WARNING: DB_PASSWORD not set. Database connection may fail. Create .env file with DB_PASSWORD.");
		}
	}
	
	// Singleton pattern: reuse existing connection if available
	if (!isset($GLOBALS['db_connection']) || 
	    !$GLOBALS['db_connection'] || 
	    (is_object($GLOBALS['db_connection']) && method_exists($GLOBALS['db_connection'], 'ping') && !$GLOBALS['db_connection']->ping())) {
	    
	    // Create new connection only if needed
	    $GLOBALS['db_connection'] = @new mysqli($host, $username, $password, $database_name);
	    
	    // Check if connection failed
	    if (!$GLOBALS['db_connection'] || (is_object($GLOBALS['db_connection']) && $GLOBALS['db_connection']->connect_error)) {
	        // Log error instead of dying immediately
	        $error_msg = (is_object($GLOBALS['db_connection']) && $GLOBALS['db_connection']->connect_error) 
	                     ? $GLOBALS['db_connection']->connect_error 
	                     : 'Failed to create connection object';
	        error_log("Database connection failed: " . $error_msg);
	        
	        // Set connection to null so calling code can handle it
	        $GLOBALS['db_connection'] = null;
	        
	        // Don't die() here - let the calling code handle the error
	        // This prevents outputting text that breaks JSON responses
	    } else {
	        // Ensure consistent charset/collation across environments
	        if (is_object($GLOBALS['db_connection'])) {
	            $GLOBALS['db_connection']->set_charset('utf8mb4');
	            $GLOBALS['db_connection']->query("SET collation_connection = 'utf8mb4_general_ci'");
	            
	            // Set connection timeout to prevent hanging connections
	            $GLOBALS['db_connection']->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
	            $GLOBALS['db_connection']->options(MYSQLI_OPT_READ_TIMEOUT, 10);
	        }
	    }
	}
	
	// Use the global connection
	$conn = $GLOBALS['db_connection'];
	
	// Register shutdown function to close connection only at script end
	if (!function_exists('close_db_connection')) {
	    function close_db_connection() {
	        if (isset($GLOBALS['db_connection']) && $GLOBALS['db_connection']) {
	            // Don't close - let PHP handle it at script end
	            // Closing and reopening causes more connections
	            // $GLOBALS['db_connection']->close();
	        }
	    }
	    register_shutdown_function('close_db_connection');
	}
?>