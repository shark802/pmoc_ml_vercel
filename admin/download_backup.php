<?php
require_once '../includes/session.php';

// Only allow superadmin
if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
    header('HTTP/1.0 403 Forbidden');
    exit('Unauthorized access');
}

$backupDir = '../backups/';
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    header('HTTP/1.0 400 Bad Request');
    exit('Filename is required');
}

// Security: only allow .sql files
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    header('HTTP/1.0 400 Bad Request');
    exit('Invalid file type');
}

$filepath = $backupDir . basename($filename);

if (!file_exists($filepath)) {
    header('HTTP/1.0 404 Not Found');
    exit('Backup file not found');
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($filepath);
exit;
?>

