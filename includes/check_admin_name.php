<?php
require '../includes/conn.php';

header('Content-Type: application/json');

if (isset($_GET['admin_name'])) {
    $admin_name = trim($_GET['admin_name']);
    $admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
    
    $query = "SELECT admin_id FROM admin WHERE admin_name = ?";
    $params = [$admin_name];
    $types = "s";
    
    if ($admin_id > 0) {
        $query .= " AND admin_id != ?";
        $params[] = $admin_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
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