<?php
/**
 * Simple file-based caching helper
 * 
 * Usage:
 *   $categories = getCachedData('question_categories', function() use ($conn) {
 *       // Your database query here
 *       $stmt = $conn->prepare("SELECT * FROM question_category");
 *       $stmt->execute();
 *       return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 *   }, 3600); // Cache for 1 hour
 */

// Ensure cache directory exists
$cacheDir = __DIR__ . '/../cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
// Always ensure .htaccess exists to protect cache directory
if (!file_exists($cacheDir . '/.htaccess')) {
    file_put_contents($cacheDir . '/.htaccess', "Deny from all\n");
}

/**
 * Get cached data or execute callback and cache result
 * 
 * @param string $key Cache key (filename will be based on this)
 * @param callable $callback Function to execute if cache miss
 * @param int $ttl Time to live in seconds (default: 3600 = 1 hour)
 * @return mixed Cached data or result from callback
 */
function getCachedData($key, $callback, $ttl = 3600) {
    global $cacheDir;
    
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    
    // Check if cache exists and is valid
    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        $age = time() - $cacheTime;
        
        if ($age < $ttl) {
            // Cache is valid, return cached data
            $data = unserialize(file_get_contents($cacheFile));
            if ($data !== false) {
                return $data;
            }
        } else {
            // Cache expired, delete it
            @unlink($cacheFile);
        }
    }
    
    // Cache miss or expired, execute callback
    try {
        $data = $callback();
        
        // Save to cache
        file_put_contents($cacheFile, serialize($data), LOCK_EX);
        
        return $data;
    } catch (Exception $e) {
        error_log("Cache callback error for key '$key': " . $e->getMessage());
        throw $e;
    }
}

/**
 * Clear a specific cache entry
 * 
 * @param string $key Cache key to clear
 * @return bool Success status
 */
function clearCache($key) {
    global $cacheDir;
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    
    if (file_exists($cacheFile)) {
        return @unlink($cacheFile);
    }
    return true;
}

/**
 * Clear all cache files
 * 
 * @return int Number of files cleared
 */
function clearAllCache() {
    global $cacheDir;
    $count = 0;
    
    if (file_exists($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
    }
    
    return $count;
}

