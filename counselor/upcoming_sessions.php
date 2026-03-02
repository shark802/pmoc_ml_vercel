<?php
require_once '../includes/session.php';
require_once '../includes/conn.php';

// Get filter parameter
$filter = $_GET['filter'] ?? 'week'; // Default to 'week'

// Automatically mark sessions as absent if they passed without attendance being marked
try {
    // Find sessions that have passed (date is before today) and still have pending attendance
    $autoAbsentStmt = $conn->prepare("
        SELECT s.schedule_id, s.access_id, s.session_type, s.session_date
        FROM scheduling s
        WHERE s.status = 'confirmed'
        AND DATE(s.session_date) < CURDATE()
        AND NOT EXISTS(
            SELECT 1 FROM attendance_logs al 
            WHERE al.schedule_id = s.schedule_id 
            AND (al.status = 'present' OR al.status = 'absent')
        )
    ");
    $autoAbsentStmt->execute();
    $autoAbsentResult = $autoAbsentStmt->get_result();
    
    // Mark each expired session as absent
    while ($expiredSession = $autoAbsentResult->fetch_assoc()) {
        $schedule_id = $expiredSession['schedule_id'];
        $access_id = $expiredSession['access_id'];
        $session_type = $expiredSession['session_type'];
        
        // Determine segments based on session type
        $segments = [];
        if ($session_type === 'Orientation + Counseling') {
            $segments = ['orientation', 'counseling'];
        } elseif (strpos($session_type, 'Counseling') !== false && strpos($session_type, 'Orientation') === false) {
            $segments = ['counseling'];
        } else {
            $segments = ['orientation'];
        }
        
        // Insert absent records for both partners for each required segment
        foreach ($segments as $segment) {
            foreach (['male', 'female'] as $partner) {
                $log_stmt = $conn->prepare("
                    INSERT INTO attendance_logs (schedule_id, access_id, partner_type, segment, status, recorded_by, recorded_at) 
                    VALUES (?, ?, ?, ?, 'absent', ?, NOW())
                    ON DUPLICATE KEY UPDATE status = 'absent'
                ");
                // Use system/admin_id = 0 or current admin_id for auto-marking
                $recorded_by = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;
                $log_stmt->bind_param('iissi', $schedule_id, $access_id, $partner, $segment, $recorded_by);
                $log_stmt->execute();
            }
        }
    }
} catch (Exception $e) {
    // Log error but don't stop the process
    error_log("Auto-absent marking error: " . $e->getMessage());
}

try {
    // Build date filter and limit based on selected filter
    // Start from today (not yesterday) to include today's sessions
    $startDate = date('Y-m-d 00:00:00'); // Start of today
    $endDate = null;
    $limit = null;
    
    switch ($filter) {
        case 'week':
            // This week: from today to 7 days from today
            $endDate = date('Y-m-d 23:59:59', strtotime('+7 days'));
            $limit = 50;
            break;
        case 'month':
            // This month: from today to end of current month
            $endDate = date('Y-m-t 23:59:59'); // Last day of current month
            $limit = 200;
            break;
        case 'all':
            // All upcoming: from today onwards, no limit
            $endDate = null;
            $limit = null;
            break;
        default:
            // Default to week
            $endDate = date('Y-m-d 23:59:59', strtotime('+7 days'));
            $limit = 50;
    }
    
    // Build the query
    $sql = "
        SELECT
            s.schedule_id AS session_id,
            s.access_id,
            COALESCE(
                GROUP_CONCAT(CONCAT(cp.first_name, ' ', cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & '),
                'Unknown Couple'
            ) AS couple_name,
            s.session_type,
            s.session_date,
            s.status,
            -- Get attendance status from attendance_logs
            CASE 
                WHEN EXISTS(SELECT 1 FROM attendance_logs al WHERE al.schedule_id = s.schedule_id AND al.status = 'present') THEN 'present'
                WHEN EXISTS(SELECT 1 FROM attendance_logs al WHERE al.schedule_id = s.schedule_id AND al.status = 'absent') THEN 'absent'
                ELSE 'pending'
            END as attendance_status
        FROM scheduling s
        LEFT JOIN couple_profile cp ON s.access_id = cp.access_id
        WHERE DATE(s.session_date) >= CURDATE()
        AND s.status = 'confirmed'
        -- Exclude sessions marked as present (they should appear in completed sessions)
        AND NOT EXISTS (
            SELECT 1 FROM attendance_logs al
            WHERE al.schedule_id = s.schedule_id 
            AND al.status = 'present'
        )
    ";
    
    if ($endDate !== null) {
        $sql .= " AND s.session_date <= ?";
    }
    
    $sql .= "
        GROUP BY s.schedule_id, s.access_id, s.session_type, s.session_date, s.status
        ORDER BY s.session_date ASC
    ";
    
    if ($limit !== null) {
        $sql .= " LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    // Bind parameters
    if ($endDate !== null && $limit !== null) {
        $stmt->bind_param('si', $endDate, $limit);
    } elseif ($endDate !== null) {
        $stmt->bind_param('s', $endDate);
    } elseif ($limit !== null) {
        $stmt->bind_param('i', $limit);
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

        $sessions[] = [
            'session_id' => $row['session_id'],
            'access_id' => $row['access_id'],
            'couple_name' => $row['couple_name'],
            'session_type' => $row['session_type'],
            'session_date' => $dateLabel,
            'session_date_raw' => $row['session_date'], // Raw date for comparison
            'status' => $row['status'],
            'attendance_status' => $row['attendance_status'],
            'date_only' => $dateOnly
        ];
    }

    // Debug information (remove in production if needed)
    $debug_info = [
        'filter' => $filter,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'session_count' => count($sessions)
    ];
    
    echo json_encode([
        'status' => 'success',
        'data' => $sessions,
        'debug' => $debug_info // Remove this line in production
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
