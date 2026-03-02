<?php
require_once '../includes/conn.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['exists' => false];
    
    try {
        // Check main question
        if ($_POST['action'] === 'check_question') {
            $stmt = $conn->prepare("SELECT question_id FROM question_assessment 
                WHERE category_id = ? AND question_text = ? AND question_id != ?");
            $stmt->bind_param("isi", 
                $_POST['category_id'],
                $_POST['question_text'],
                $_POST['question_id'] ?? 0
            );
            $stmt->execute();
            $response['exists'] = $stmt->get_result()->num_rows > 0;
        }

        // Check sub-question
        if ($_POST['action'] === 'check_subquestion') {
            $stmt = $conn->prepare("SELECT sub_question_id FROM sub_question_assessment 
                WHERE question_id = ? AND sub_question_text = ?");
            $stmt->bind_param("is", 
                $_POST['question_id'],
                $_POST['sub_question_text']
            );
            $stmt->execute();
            $response['exists'] = $stmt->get_result()->num_rows > 0;
        }

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    echo json_encode($response);
}