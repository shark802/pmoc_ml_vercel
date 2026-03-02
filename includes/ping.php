<?php
// Simple ping script for session keep-alive
session_start();

// Update session activity to prevent timeout
$_SESSION['LAST_ACTIVITY'] = time();

// Just return a simple response to indicate the server is alive
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
?> 