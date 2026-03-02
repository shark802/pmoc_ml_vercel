<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';
require_once '../includes/audit_log.php';
require_once '../includes/csrf_helper.php';

// Handle both GET (from direct link) and POST (from form)
$questionId = null;
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost) {
    // Validate CSRF token for POST requests
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        header("Location: question_assessment.php");
        exit();
    }
    $questionId = $_POST['question_id'] ?? null;
} else {
    $questionId = $_GET['id'] ?? null;
}

if ($questionId) {

    try {
        // Get question data before deletion for logging
        $infoStmt = $conn->prepare("SELECT qa.question_text, qc.category_name 
                                   FROM question_assessment qa 
                                   LEFT JOIN question_category qc ON qa.category_id = qc.category_id 
                                   WHERE qa.question_id = ?");
        $infoStmt->bind_param("i", $questionId);
        $infoStmt->execute();
        $questionInfo = $infoStmt->get_result()->fetch_assoc();
        $questionText = $questionInfo['question_text'] ?? '';
        $categoryName = $questionInfo['category_name'] ?? '';
        $infoStmt->close();
        
        $conn->begin_transaction();

        // Delete sub-questions
        $deleteSubs = $conn->prepare("DELETE FROM sub_question_assessment WHERE question_id = ?");
        $deleteSubs->bind_param("i", $questionId);
        $deleteSubs->execute();

        // Delete main question
        $deleteMain = $conn->prepare("DELETE FROM question_assessment WHERE question_id = ?");
        $deleteMain->bind_param("i", $questionId);
        $deleteMain->execute();

        $conn->commit();
        
        // Log question deletion
        logAudit($conn, $_SESSION['admin_id'], AUDIT_DELETE, 
            'Question deleted: ' . substr($questionText, 0, 50) . '... (Category: ' . $categoryName . ')', 
            'question_assessment', 
            ['question_id' => $questionId, 'question_text' => $questionText, 'category_name' => $categoryName]);
        
        $_SESSION['success_message'] = "Question and sub-questions deleted!";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Deletion failed: " . $e->getMessage();
    }
}

header("Location: question_assessment.php");
exit();