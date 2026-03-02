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

    try {
        // Get category name before deletion for logging
        $nameStmt = $conn->prepare("SELECT category_name FROM question_category WHERE category_id = ?");
        $nameStmt->bind_param("i", $categoryId);
        $nameStmt->execute();
        $categoryName = $nameStmt->get_result()->fetch_assoc()['category_name'] ?? '';
        $nameStmt->close();

        $stmt = $conn->prepare("DELETE FROM question_category WHERE category_id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();

        // Log category deletion
        logAudit($conn, $_SESSION['admin_id'], AUDIT_DELETE, 
            'Question category deleted: ' . $categoryName, 
            'question_category', 
            ['category_id' => $categoryId, 'category_name' => $categoryName]);

        // Clear cache for question categories
        require_once __DIR__ . '/../includes/cache_helper.php';
        clearCache('question_categories');

        $_SESSION['success_message'] = "Category deleted successfully!";
    } catch (Exception $e) {
        error_log("Error deleting category in " . __FILE__ . ": " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to delete category. Please try again.";
    }
}

header("Location: question_category.php");
exit();