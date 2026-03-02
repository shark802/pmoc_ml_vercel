<?php
declare(strict_types=1);
require_once '../includes/conn.php';

header('Content-Type: application/json');

$response = ['exists' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    if (!isset($_POST['email'])) {
        throw new Exception('Email parameter missing', 400);
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format', 400);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM couple_profile WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $response['exists'] = ($result['count'] > 0);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>