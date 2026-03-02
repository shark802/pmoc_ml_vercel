<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

if (isset($_GET['category_id'])) {
    $categoryId = $_GET['category_id'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM question_category WHERE category_id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        
        if ($category) {
            echo json_encode($category);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}