<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';
require_once '../includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once '../includes/csrf_helper.php';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        header("Location: question_assessment.php");
        exit();
    }
    
    $questionId = $_POST['question_id'];
    $categoryId = $_POST['category_id'];
    $questionText = trim($_POST['question_text']);
    // Match Add flow formatting with ALL-CAPS preservation
    $formatText = function ($text) {
        $original = $text;
        $text = trim($text);
        // Preserve ALL-CAPS inputs (only trim whitespace)
        $lettersOnly = preg_replace('/[^A-Za-z]+/', '', $original);
        if ($lettersOnly !== '' && strtoupper($lettersOnly) === $lettersOnly) {
            return $text;
        }
        // Capture ALL-CAPS tokens (length >= 2) to preserve them after normalization
        $capsTokens = [];
        if (preg_match_all('/\b[A-Z]{2,}\b/u', $original, $m)) {
            $capsTokens = array_unique($m[0]);
        }
        $text = strtolower($text);
        $text = ucfirst($text);
        $text = preg_replace('/\bi\b/', 'I', $text);
        // Restore preserved ALL-CAPS tokens
        foreach ($capsTokens as $tok) {
            $lowerTok = strtolower($tok);
            $text = preg_replace('/\b' . preg_quote($lowerTok, '/') . '\b/u', $tok, $text);
        }
        return $text;
    };
    $questionText = $formatText($questionText);

    try {
        $conn->begin_transaction();

        // Validate main question
        $checkStmt = $conn->prepare("SELECT question_id FROM question_assessment 
            WHERE category_id = ? AND question_text = ? AND question_id != ?");
        $checkStmt->bind_param("isi", $categoryId, $questionText, $questionId);
        $checkStmt->execute();
        if($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("Question already exists in this category!");
        }

        // Get old question data for logging
        $oldStmt = $conn->prepare("SELECT question_text, category_id FROM question_assessment WHERE question_id = ?");
        $oldStmt->bind_param("i", $questionId);
        $oldStmt->execute();
        $oldData = $oldStmt->get_result()->fetch_assoc();
        $oldQuestionText = $oldData['question_text'] ?? '';
        $oldCategoryId = $oldData['category_id'] ?? 0;
        $oldStmt->close();
        
        // Get category names for logging
        $catStmt = $conn->prepare("SELECT category_name FROM question_category WHERE category_id = ?");
        $catStmt->bind_param("i", $categoryId);
        $catStmt->execute();
        $categoryName = $catStmt->get_result()->fetch_assoc()['category_name'] ?? '';
        $catStmt->close();

        // Update main question
        $updateStmt = $conn->prepare("UPDATE question_assessment 
            SET category_id = ?, question_text = ? 
            WHERE question_id = ?");
        $updateStmt->bind_param("isi", $categoryId, $questionText, $questionId);
        $updateStmt->execute();

        // Process sub-questions
        $existingSubs = $_POST['existing_sub_ids'] ?? [];
        $subQuestions = $_POST['sub_questions'] ?? [];
        
        // Delete removed sub-questions
        if(!empty($existingSubs)) {
            $placeholders = implode(',', array_fill(0, count($existingSubs), '?'));
            $deleteStmt = $conn->prepare("DELETE FROM sub_question_assessment 
                WHERE question_id = ? AND sub_question_id NOT IN ($placeholders)");
            $params = array_merge([$questionId], $existingSubs);
            $deleteStmt->bind_param(str_repeat('i', count($params)), ...$params);
            $deleteStmt->execute();
        }

        // Insert/Update sub-questions
        $subStmt = $conn->prepare("INSERT INTO sub_question_assessment 
            (sub_question_id, question_id, sub_question_text) 
            VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE sub_question_text = VALUES(sub_question_text)");
            
        foreach($subQuestions as $index => $subText) {
            $subId = $existingSubs[$index] ?? null;
            $cleanSub = $formatText($subText);
            
            if(!empty($cleanSub)) {
                // Validate sub-question
                $checkSub = $conn->prepare("SELECT sub_question_id FROM sub_question_assessment 
                    WHERE question_id = ? AND sub_question_text = ? AND sub_question_id != ?");
                $checkSub->bind_param("isi", $questionId, $cleanSub, $subId);
                $checkSub->execute();
                if($checkSub->get_result()->num_rows === 0) {
                    $subStmt->bind_param("iis", $subId, $questionId, $cleanSub);
                    $subStmt->execute();
                }
            }
        }

        $conn->commit();
        
        // Log question update
        logAudit($conn, $_SESSION['admin_id'], AUDIT_UPDATE, 
            'Question updated: ' . substr($questionText, 0, 50) . '... (Category: ' . $categoryName . ')', 
            'question_assessment', 
            ['question_id' => $questionId, 'category_id' => $categoryId, 'old_text' => $oldQuestionText, 'new_text' => $questionText]);
        
        $_SESSION['success_message'] = "Question updated successfully!";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: question_assessment.php");
exit();