<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

try {
    // Soft delete - just mark as deleted instead of actually deleting
    $stmt = $conn->prepare("UPDATE notifications SET notification_status = 'deleted' WHERE notification_id = ?");
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or already deleted']);
    }

} catch (Exception $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 