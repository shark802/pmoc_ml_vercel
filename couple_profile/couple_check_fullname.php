<?php
declare(strict_types=1);
require_once '../includes/conn.php';

header('Content-Type: application/json');

$response = ['exists' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    // Required fields
    $requiredFields = ['first_name', 'last_name', 'date_of_birth'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("$field parameter missing", 400);
        }
    }

    // Sanitize inputs
    $firstName = trim($_POST['first_name']);
    $middleName = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
    $lastName = trim($_POST['last_name']);
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : null;
    $birthDate = $_POST['date_of_birth'];

    // Validate birth date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD', 400);
    }

    // Base SQL query (checks first + last + birthdate)
    $sql = "SELECT COUNT(*) as count FROM couple_profile 
            WHERE first_name = ? 
            AND last_name = ? 
            AND date_of_birth = ?";

    $params = [$firstName, $lastName, $birthDate];
    $paramTypes = "sss";

    // Add middle name if provided (flexible matching)
    if ($middleName) {
        $sql .= " AND (middle_name = ? OR middle_name IS NULL OR middle_name = '')";
        $params[] = $middleName;
        $paramTypes .= "s";
    }

    // Add suffix if provided (strict matching)
    if ($suffix) {
        $sql .= " AND (suffix = ? OR suffix IS NULL OR suffix = '')";
        $params[] = $suffix;
        $paramTypes .= "s";
    }

    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $response['exists'] = ($result['count'] > 0);
    $response['message'] = $response['exists'] 
        ? 'A profile with this name and birthdate already exists' 
        : 'No duplicate found';

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>