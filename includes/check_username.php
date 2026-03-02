<?php
require '../includes/conn.php';

header('Content-Type: application/json');

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);
    $excludeId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;

    if ($excludeId > 0) {
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? AND admin_id != ?");
        $stmt->bind_param("si", $username, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    echo json_encode([
        'exists' => $result->num_rows > 0
    ]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        'exists' => false
    ]);
}
?>