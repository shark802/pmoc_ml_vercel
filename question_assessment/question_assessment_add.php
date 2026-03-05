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
    
    $categoryId = $_POST['category_id'];
    $questionText = trim($_POST['question_text']);
    // Custom formatting with ALL-CAPS preservation
    $formatText = function ($text) {
        $original = $text;
        $text = trim($text);
        // Preserve ALL-CAPS inputs (only trim whitespace)
        $lettersOnly = preg_replace('/[^A-Za-záéíóúñäëïöüàèìòùÁÉÍÓÚÑÄËÏÖÜÀÈÌÒÙ]+/', '', $original);
        if ($lettersOnly !== '' && strtoupper($lettersOnly) === $lettersOnly) {
            return $text;
        }
        // Capture ALL-CAPS tokens (length >= 2) to preserve them after normalization
        $capsTokens = [];
        if (preg_match_all('/\b[A-Z]{2,}\b/u', $original, $m)) {
            $capsTokens = array_unique($m[0]);
        }
        // Apply sentence-case and capitalize standalone 'i'
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
            WHERE category_id = ? AND question_text = ?");
        $checkStmt->bind_param("is", $categoryId, $questionText);
        $checkStmt->execute();
        if($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("Question already exists in this category!");
        }

        // Insert main question
        $insertStmt = $conn->prepare("INSERT INTO question_assessment 
            (category_id, question_text) VALUES (?, ?)");
        $insertStmt->bind_param("is", $categoryId, $questionText);
        $insertStmt->execute();
        $questionId = $conn->insert_id;
        
        // Get category name for logging
        $catStmt = $conn->prepare("SELECT category_name FROM question_category WHERE category_id = ?");
        $catStmt->bind_param("i", $categoryId);
        $catStmt->execute();
        $categoryName = $catStmt->get_result()->fetch_assoc()['category_name'] ?? '';
        $catStmt->close();

        // Insert sub-questions
        if(!empty($_POST['sub_questions'])) {
            $subStmt = $conn->prepare("INSERT INTO sub_question_assessment 
                (question_id, sub_question_text) VALUES (?, ?)");
                
            foreach($_POST['sub_questions'] as $subText) {
                $cleanSub = $formatText($subText);
                if(!empty($cleanSub)) {
                    // Validate sub-question
                    $checkSub = $conn->prepare("SELECT sub_question_id FROM sub_question_assessment 
                        WHERE question_id = ? AND sub_question_text = ?");
                    $checkSub->bind_param("is", $questionId, $cleanSub);
                    $checkSub->execute();
                    if($checkSub->get_result()->num_rows === 0) {
                        $subStmt->bind_param("is", $questionId, $cleanSub);
                        $subStmt->execute();
                    }
                }
            }
        }

        $conn->commit();
        
        // Log question creation
        logAudit($conn, $_SESSION['admin_id'], AUDIT_CREATE, 
            'Question created: ' . substr($questionText, 0, 50) . '... (Category: ' . $categoryName . ')', 
            'question_assessment', 
            ['question_id' => $questionId, 'category_id' => $categoryId, 'category_name' => $categoryName, 'question_text' => $questionText]);
        
        $_SESSION['success_message'] = "Question added successfully!";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header("Location: question_assessment.php");
exit();