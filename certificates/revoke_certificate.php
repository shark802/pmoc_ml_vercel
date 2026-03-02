<?php
session_start();
require_once '../includes/conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

if (!isset($_POST['certificate_id']) || !isset($_POST['action']) || $_POST['action'] !== 'revoke') {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$certificate_id = (int)$_POST['certificate_id'];
$admin_id = $_SESSION['admin_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Get certificate information
    $certStmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.access_id,
            c.status,
            ca.access_code,
            CONCAT(mp.first_name, ' ', mp.last_name) as male_name,
            CONCAT(fp.first_name, ' ', fp.last_name) as female_name
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        WHERE c.certificate_id = ?
    ");
    $certStmt->bind_param("i", $certificate_id);
    $certStmt->execute();
    $certificate = $certStmt->get_result()->fetch_assoc();

    if (!$certificate) {
        throw new Exception('Certificate not found');
    }

    if ($certificate['status'] === 'revoked') {
        throw new Exception('Certificate is already revoked');
    }

    // Update certificate status to revoked
    $updateStmt = $conn->prepare("
        UPDATE certificates 
        SET status = 'revoked' 
        WHERE certificate_id = ?
    ");
    $updateStmt->bind_param("i", $certificate_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to revoke certificate');
    }

    // Create notification about revocation
    $notificationContent = "Certificate revoked for couple: " . $certificate['male_name'] . " & " . $certificate['female_name'];
    $notificationStmt = $conn->prepare("
        INSERT INTO notifications (recipients, content, access_id, notification_status, created_at) 
        VALUES (?, ?, ?, 'created', NOW())
    ");
    $notificationStmt->bind_param("ssi", $certificate['access_code'], $notificationContent, $certificate['access_id']);
    $notificationStmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Certificate revoked successfully for ' . $certificate['male_name'] . ' & ' . $certificate['female_name'],
        'certificate_id' => $certificate_id
    ]);

} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }
    
    error_log("Certificate Revocation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to revoke certificate: ' . $e->getMessage()
    ]);
}