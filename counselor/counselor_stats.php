<?php
require_once '../includes/session.php';

$counselor_id = $_SESSION['admin_id'];

try {
    // Get total couples (count by pair: both sexes present per access_id)
    $stmt = $conn->prepare("SELECT COUNT(*) AS total_pairs FROM (
        SELECT cp.access_id
        FROM couple_profile cp
        JOIN couple_profile cp2 ON cp.access_id = cp2.access_id AND cp.sex <> cp2.sex
        GROUP BY cp.access_id
        HAVING COUNT(DISTINCT cp.sex) = 2
    ) AS pairs");
    $stmt->execute();
    $totalCouples = $stmt->get_result()->fetch_assoc()['total_pairs'];

    // Get sessions this week (next 7 days) - confirmed sessions that are pending (not yet marked as present or absent)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.schedule_id) as total
        FROM scheduling s
        WHERE s.session_date >= CURDATE()
          AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND s.status = 'confirmed'
          AND NOT EXISTS (
              SELECT 1 FROM attendance_logs al
              WHERE al.schedule_id = s.schedule_id 
              AND (al.status = 'present' OR al.status = 'absent')
          )
    ");
    $stmt->execute();
    $upcomingSessions = $stmt->get_result()->fetch_assoc()['total'];

    // Get completed sessions - sessions that have been marked as present
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.schedule_id) as total 
        FROM scheduling s
        WHERE s.status = 'confirmed'
          AND EXISTS (
              SELECT 1 FROM attendance_logs al
              WHERE al.schedule_id = s.schedule_id 
              AND al.status = 'present'
          )
    ");
    $stmt->execute();
    $completedSessions = $stmt->get_result()->fetch_assoc()['total'];

    // Total certificates issued
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM certificates");
    $stmt->execute();
    $totalCertificates = $stmt->get_result()->fetch_assoc()['total'];

    echo json_encode([
        'status' => 'success',
        'data' => [
            'totalCouples' => $totalCouples,
            'upcomingSessions' => $upcomingSessions,
            'completedSessions' => $completedSessions,
            'totalCertificates' => $totalCertificates
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
