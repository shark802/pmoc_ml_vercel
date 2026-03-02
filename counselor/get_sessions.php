<?php
require_once '../includes/session.php';

$couple_id = $_GET['couple_id'] ?? '';

if (empty($couple_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Couple ID required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT 
            schedule_id as session_id,
            session_type,
            session_date,
            status
        FROM scheduling
        WHERE access_id = ? AND status IN ('confirmed', 'completed')
        ORDER BY session_date DESC
    ");
    $stmt->bind_param("i", $couple_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = [
            'session_id' => $row['session_id'],
            'session_type' => $row['session_type'],
            'session_date' => date('M d, Y H:i', strtotime($row['session_date'])),
            'status' => $row['status']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $sessions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
