<?php
require_once '../includes/session.php';

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            s.access_id as couple_id,
            GROUP_CONCAT(CONCAT(cp.first_name, ' ', cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') as couple_name
        FROM scheduling s
        INNER JOIN couple_profile cp ON s.access_id = cp.access_id
        WHERE s.status IN ('confirmed', 'completed')
        GROUP BY s.access_id
        ORDER BY couple_name ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $couples = [];
    while ($row = $result->fetch_assoc()) {
        $couples[] = [
            'couple_id' => $row['couple_id'],
            'couple_name' => $row['couple_name']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $couples
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
