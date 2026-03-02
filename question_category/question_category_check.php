<?php
require_once '../includes/conn.php';
header('Content-Type: application/json');

if (isset($_GET['category_name'])) {
    $categoryName = trim($_GET['category_name']);
    
    try {
        $stmt = $conn->prepare("SELECT category_name FROM question_category WHERE category_name = ?");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        
        echo json_encode(['exists' => $exists]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}