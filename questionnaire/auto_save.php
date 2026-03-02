<?php
session_start();
require_once '../includes/conn.php';
require_once '../includes/session_resume.php';

header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['access_id'], $_SESSION['respondent'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit();
}

$access_id = $_SESSION['access_id'];
$respondent = $_SESSION['respondent'];

try {
    $conn->begin_transaction();
    
    // Update session activity
    updateSessionActivity($access_id, $respondent);
    
    // Get form data
    $formData = $_POST;
    
    // Save progress to temporary table
    $stmt = $conn->prepare("
        INSERT INTO questionnaire_progress 
        (access_id, respondent, category_id, question_id, response, reason, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        response = VALUES(response),
        reason = VALUES(reason),
        updated_at = NOW()
    ");
    
    $saved_count = 0;
    
    if (isset($formData['responses']) && is_array($formData['responses'])) {
        foreach ($formData['responses'] as $question_id => $response_data) {
            $category_id = $response_data['category_id'] ?? 0;
            $response = $response_data['response'] ?? '';
            $reason = $response_data['reason'] ?? '';
            
            $stmt->bind_param("iiiss", $access_id, $respondent, $category_id, $question_id, $response, $reason);
            if ($stmt->execute()) {
                $saved_count++;
            }
        }
    }
    
    // Calculate progress
    $progressStmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT q.question_id) as total_questions,
            COUNT(DISTINCT qp.question_id) as answered_questions
        FROM questions q
        LEFT JOIN questionnaire_progress qp ON q.question_id = qp.question_id 
            AND qp.access_id = ? AND qp.respondent = ?
        WHERE q.is_active = 1
    ");
    $progressStmt->bind_param("is", $access_id, $respondent);
    $progressStmt->execute();
    $progress = $progressStmt->get_result()->fetch_assoc();
    
    $percentage = $progress['total_questions'] > 0 
        ? round(($progress['answered_questions'] / $progress['total_questions']) * 100, 1)
        : 0;
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'saved_count' => $saved_count,
        'progress' => [
            'answered' => $progress['answered_questions'],
            'total' => $progress['total_questions'],
            'percentage' => $percentage
        ],
        'message' => 'Progress saved successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Auto-save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save progress'
    ]);
}
?>