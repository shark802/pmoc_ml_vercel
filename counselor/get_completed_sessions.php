<?php
require_once '../includes/session.php';

// Get filter parameter
$filter = $_GET['filter'] ?? 'week'; // Default to 'week'

try {
    require_once '../includes/conn.php';
    
    // Build date filter and limit based on selected filter
    // For completed sessions, we want to show past sessions, not future ones
    $startDate = null;
    $endDate = date('Y-m-d 23:59:59'); // End of today
    $limit = null;
    
    switch ($filter) {
        case 'week':
            // This week: from 7 days ago to today
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $limit = 50;
            break;
        case 'month':
            // This month: from first day of current month to today
            $startDate = date('Y-m-01 00:00:00');
            $limit = 200;
            break;
        case 'all':
            // All completed: no date limit, just show all past sessions
            $startDate = null;
            $limit = null;
            break;
        default:
            // Default to week
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $limit = 50;
    }
    
    // Build the query - only sessions marked as present
    $sql = "
        SELECT 
            s.schedule_id AS session_id,
            s.access_id,
            COALESCE(
                (SELECT GROUP_CONCAT(DISTINCT CONCAT(cp2.first_name, ' ', cp2.last_name) ORDER BY cp2.sex DESC SEPARATOR ' & ')
                 FROM couple_profile cp2 
                 WHERE cp2.access_id = s.access_id),
                'Unknown Couple'
            ) AS couple_name,
            s.session_type,
            s.session_date,
            s.status,
            (SELECT MIN(al2.recorded_at) 
             FROM attendance_logs al2 
             WHERE al2.schedule_id = s.schedule_id 
             AND al2.status = 'present') as completed_at
        FROM scheduling s
        WHERE s.session_date <= ?
        AND s.status = 'confirmed'
        AND EXISTS (
            SELECT 1 FROM attendance_logs al
            WHERE al.schedule_id = s.schedule_id 
            AND al.status = 'present'
        )
    ";
    
    if ($startDate !== null) {
        $sql .= " AND s.session_date >= ?";
    }
    
    $sql .= "
        GROUP BY s.schedule_id, s.access_id, s.session_type, s.session_date, s.status
        ORDER BY s.session_date DESC, completed_at DESC
    ";
    
    if ($limit !== null) {
        $sql .= " LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    // Bind parameters
    if ($startDate !== null && $limit !== null) {
        $stmt->bind_param('ssi', $endDate, $startDate, $limit);
    } elseif ($startDate !== null) {
        $stmt->bind_param('ss', $endDate, $startDate);
    } elseif ($limit !== null) {
        $stmt->bind_param('si', $endDate, $limit);
    } else {
        $stmt->bind_param('s', $endDate);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        // Map session type to time window label
        $timeLabel = '08:00 AM - 12:00 PM';
        if (strpos($row['session_type'], 'Counseling') !== false && strpos($row['session_type'], 'Orientation') === false) {
            $timeLabel = '01:00 PM - 04:00 PM';
        } elseif ($row['session_type'] === 'Orientation + Counseling') {
            $timeLabel = '08:00 AM - 04:00 PM';
        }

        $dateLabel = date('M d, Y', strtotime($row['session_date'])) . ' ' . $timeLabel;
        $dateOnly  = date('M d, Y', strtotime($row['session_date']));
        $completedAt = $row['completed_at'] ? date('M d, Y h:i A', strtotime($row['completed_at'])) : 'N/A';

        $sessions[] = [
            'session_id' => $row['session_id'],
            'access_id' => $row['access_id'],
            'couple_name' => $row['couple_name'],
            'session_type' => $row['session_type'],
            'session_date' => $dateLabel,
            'session_date_raw' => $row['session_date'],
            'status' => $row['status'],
            'date_only' => $dateOnly,
            'completed_at' => $completedAt
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

