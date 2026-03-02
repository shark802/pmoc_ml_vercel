<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

if(isset($_GET['id'])) {
    $questionId = $_GET['id'];
    
    try {
        // Get main question
        $mainStmt = $conn->prepare("
            SELECT q.*, c.category_name 
            FROM question_assessment q
            LEFT JOIN question_category c ON q.category_id = c.category_id
            WHERE q.question_id = ?
        ");
        $mainStmt->bind_param("i", $questionId);
        $mainStmt->execute();
        $question = $mainStmt->get_result()->fetch_assoc();

        // Get sub-questions
        $subStmt = $conn->prepare("SELECT * FROM sub_question_assessment WHERE question_id = ?");
        $subStmt->bind_param("i", $questionId);
        $subStmt->execute();
        $subQuestions = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'question' => $question,
            'sub_questions' => $subQuestions
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}