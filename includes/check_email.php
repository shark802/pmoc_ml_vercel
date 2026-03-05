<?php
require '../includes/conn.php';

header('Content-Type: application/json');

if (isset($_GET['email_address'])) {
    $email = trim($_GET['email_address']);
    $excludeId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email format']);
        exit();
    }

    if ($excludeId > 0) {
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE email_address = ? AND admin_id != ?");
        $stmt->bind_param("si", $email, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE email_address = ?");
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    echo json_encode([
        'exists' => $result->num_rows > 0
    ]);

    $stmt->close();
} else {
    echo json_encode([
        'exists' => false
    ]);
}
?>