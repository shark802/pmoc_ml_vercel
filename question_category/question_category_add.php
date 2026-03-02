<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';
require_once '../includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once '../includes/csrf_helper.php';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        header("Location: question_category.php");
        exit();
    }
    
    $categoryName = trim($_POST['category_name']);
    // Convert to all caps
    $categoryName = strtoupper($categoryName);

    try {
        // Check if category exists
        $stmt = $conn->prepare("SELECT * FROM question_category WHERE category_name = ?");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error_message'] = "Category already exists!";
            header("Location: question_category.php");
            exit();
        }

        // Insert new category
        $stmt = $conn->prepare("INSERT INTO question_category (category_name) VALUES (?)");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $categoryId = $conn->insert_id;

        // Log category creation
        logAudit($conn, $_SESSION['admin_id'], AUDIT_CREATE, 
            'Question category created: ' . $categoryName, 
            'question_category', 
            ['category_id' => $categoryId, 'category_name' => $categoryName]);

        // Clear cache for question categories
        require_once __DIR__ . '/../includes/cache_helper.php';
        clearCache('question_categories');

        $_SESSION['success_message'] = "Category added successfully!";
    } catch (Exception $e) {
        error_log("Error adding category in " . __FILE__ . ": " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to add category. Please try again.";
    }
}

header("Location: question_category.php");
exit();