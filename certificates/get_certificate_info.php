<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid certificate ID']);
    exit;
}

try {
    // Get certificate info and determine type based on couple's completion
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.access_id,
            c.certificate_number,
            c.certificate_number as couple_number,
            (SELECT COUNT(DISTINCT al.partner_type)
               FROM attendance_logs al
               JOIN scheduling s ON al.schedule_id = s.schedule_id
              WHERE s.access_id = c.access_id 
                AND al.segment = 'orientation' 
                AND al.status = 'present') AS orientation_present,
            (SELECT COUNT(DISTINCT al.partner_type)
               FROM attendance_logs al
               JOIN scheduling s ON al.schedule_id = s.schedule_id
              WHERE s.access_id = c.access_id 
                AND al.segment = 'counseling' 
                AND al.status = 'present') AS counseling_present
        FROM certificates c
        -- Removed couple_official join as certificate_number is now the couple number
        WHERE c.certificate_id = ?
    ");
    
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
        exit;
    }
    
    // Determine certificate type based on number pattern (same logic as template files)
    // YYYY-N format (e.g., 2025-1) = counseling
    // YYYY-MM-NNN format (e.g., 2025-10-001) = orientation
    $cert_number = $result['certificate_number'] ?? '';
    $certificate_type = '';
    if (preg_match('/^[0-9]{4}-[0-9]+$/', $cert_number)) {
        $certificate_type = 'counseling';  // YYYY-N format
    } else {
        $certificate_type = 'orientation';  // YYYY-MM-NNN format
    }
    
    echo json_encode([
        'success' => true,
        'certificate_type' => $certificate_type,
        'certificate_id' => $certificate_id,
        'orientation_present' => $result['orientation_present'],
        'counseling_present' => $result['counseling_present']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
