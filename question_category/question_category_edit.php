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
    
    $categoryId = $_POST['category_id'];
    $categoryName = trim($_POST['category_name']);
    // Convert to all caps to match add flow
    $categoryName = strtoupper($categoryName);

    try {
        // Check if category exists (excluding current)
        $stmt = $conn->prepare("SELECT * FROM question_category 
            WHERE category_name = ? AND category_id != ?");
        $stmt->bind_param("si", $categoryName, $categoryId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error_message'] = "Category already exists!";
            header("Location: question_category.php");
            exit();
        }

        // Get old category name for logging
        $oldStmt = $conn->prepare("SELECT category_name FROM question_category WHERE category_id = ?");
        $oldStmt->bind_param("i", $categoryId);
        $oldStmt->execute();
        $oldCategory = $oldStmt->get_result()->fetch_assoc()['category_name'] ?? '';
        $oldStmt->close();

        // Update category
        $stmt = $conn->prepare("UPDATE question_category SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $categoryName, $categoryId);
        $stmt->execute();

        // Log category update
        logAudit($conn, $_SESSION['admin_id'], AUDIT_UPDATE, 
            'Question category updated: ' . $oldCategory . ' → ' . $categoryName, 
            'question_category', 
            ['category_id' => $categoryId, 'old_name' => $oldCategory, 'new_name' => $categoryName]);

        // Clear cache for question categories
        require_once __DIR__ . '/../includes/cache_helper.php';
        clearCache('question_categories');

        $_SESSION['success_message'] = "Category updated successfully!";
    } catch (Exception $e) {
        error_log("Error updating category in " . __FILE__ . ": " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update category. Please try again.";
    }
}

header("Location: question_category.php");
exit();