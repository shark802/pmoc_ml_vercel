<?php
// Attendance Check-in Handler - Strict Late = Absent Logic
require_once '../includes/conn.php';
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = intval($_POST['schedule_id']);
    $access_id = intval($_POST['access_id']);
    $partner_type = $_POST['partner_type']; // 'male' or 'female'
    $segment = $_POST['segment']; // 'orientation' or 'counseling'
    $check_in_time = $_POST['check_in_time']; // Format: Y-m-d H:i:s
    
    try {
        if (method_exists($conn, 'begin_transaction')) {
            $conn->begin_transaction();
        }
        
        // Get session details (derive times and requirements)
        $session_stmt = $conn->prepare("
            SELECT session_date, session_type
            FROM scheduling 
            WHERE schedule_id = ? AND access_id = ?
        ");
        $session_stmt->bind_param("ii", $schedule_id, $access_id);
        $session_stmt->execute();
        $session_result = $session_stmt->get_result();
        
        if ($session_result->num_rows === 0) {
            throw new Exception("Session not found");
        }
        
        $session = $session_result->fetch_assoc();
        $session_date = $session['session_date'];
        $session_type = (string)$session['session_type'];
        
        // Calculate expected start time (fixed windows)
        $expected_start = null;
        if ($segment === 'orientation') {
            $expected_start = $session_date . ' 08:00:00';
        } elseif ($segment === 'counseling') {
            $expected_start = $session_date . ' 13:00:00';
        }
        
        // Determine status based on check-in time (simplified logic)
        $check_in_timestamp = strtotime($check_in_time);
        $expected_timestamp = strtotime($expected_start);
        $is_late = ($check_in_timestamp > $expected_timestamp);
        
        $status = $is_late ? 'absent' : 'present';
        
        // Insert attendance record
        $attendance_stmt = $conn->prepare("
            INSERT INTO attendance_logs 
            (schedule_id, access_id, partner_type, segment, status, recorded_by, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $attendance_stmt->bind_param("iisssi", 
            $schedule_id, $access_id, $partner_type, $segment, $status, $_SESSION['admin_id']
        );
        $attendance_stmt->execute();
        
        // Check if session should be marked for reschedule
        if ($status === 'absent') {
            // Check if this is orientation (mandatory for all)
            if ($segment === 'orientation') {
                // Mark session for reschedule - orientation is mandatory for all
                $reschedule_stmt = $conn->prepare("
                    UPDATE scheduling 
                    SET status = 'reschedule'
                    WHERE schedule_id = ?
                ");
                $reschedule_stmt->bind_param("i", $schedule_id);
                $reschedule_stmt->execute();
                
                // Create notification
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications (admin_id, title, message, type, created_at)
                    VALUES (1, 'Session Reschedule Required', 
                            CONCAT('Couple with access code ', (SELECT access_code FROM couple_access WHERE access_id = ?), 
                                   ' was absent for orientation. Reschedule required.'), 
                            'reschedule_required', NOW())
                ");
                $notif_stmt->bind_param("i", $access_id);
                $notif_stmt->execute();
                
            } elseif ($segment === 'counseling') {
                // Determine if counseling is required by age (any partner <= 25)
                $age_stmt = $conn->prepare("SELECT MIN(TIMESTAMPDIFF(YEAR, DATE(date_of_birth), CURDATE())) AS min_age FROM couple_profile WHERE access_id = ?");
                $age_stmt->bind_param("i", $access_id);
                $age_stmt->execute();
                $min_age_row = $age_stmt->get_result()->fetch_assoc();
                $requires_counseling = intval($min_age_row['min_age'] ?? 0) <= 25 ? 1 : 0;
                
                if ($requires_counseling === 1) {
                    // Mark session for reschedule - counseling is mandatory for age <= 25
                    $reschedule_stmt = $conn->prepare("
                        UPDATE scheduling 
                        SET status = 'reschedule'
                        WHERE schedule_id = ?
                    ");
                    $reschedule_stmt->bind_param("i", $schedule_id);
                    $reschedule_stmt->execute();
                    
                    // Create notification
                    $notif_stmt = $conn->prepare("
                        INSERT INTO notifications (admin_id, title, message, type, created_at)
                        VALUES (1, 'Session Reschedule Required', 
                                CONCAT('Couple with access code ', (SELECT access_code FROM couple_access WHERE access_id = ?), 
                                       ' was absent for mandatory counseling. Reschedule required.'), 
                                'reschedule_required', NOW())
                    ");
                    $notif_stmt->bind_param("i", $access_id);
                    $notif_stmt->execute();
                }
            }
        }
        
        // Check if session is complete (both present for orientation; counseling if required)
        $complete_check_stmt = $conn->prepare("
            SELECT 
                COUNT(CASE WHEN segment = 'orientation' AND status = 'present' THEN 1 END) as orientation_present,
                COUNT(CASE WHEN segment = 'counseling' AND status = 'present' THEN 1 END) as counseling_present,
                COUNT(DISTINCT partner_type) as total_partners
            FROM attendance_logs 
            WHERE schedule_id = ?
        ");
        $complete_check_stmt->bind_param("i", $schedule_id);
        $complete_check_stmt->execute();
        $complete_result = $complete_check_stmt->get_result();
        $complete_data = $complete_result->fetch_assoc();
        
        // Determine if counseling required for completion
        $age_stmt2 = $conn->prepare("SELECT MIN(TIMESTAMPDIFF(YEAR, DATE(date_of_birth), CURDATE())) AS min_age FROM couple_profile WHERE access_id = ?");
        $age_stmt2->bind_param("i", $access_id);
        $age_stmt2->execute();
        $min_age_row2 = $age_stmt2->get_result()->fetch_assoc();
        $requires_counseling2 = intval($min_age_row2['min_age'] ?? 0) <= 25 ? 1 : 0;
        
        // Mark session as completed if all requirements met
        if ($complete_data['orientation_present'] == 2 && 
            ($complete_data['counseling_present'] == 2 || $requires_counseling2 == 0)) {
            
            $complete_stmt = $conn->prepare("
                UPDATE scheduling 
                SET status = 'completed'
                WHERE schedule_id = ?
            ");
            $complete_stmt->bind_param("i", $schedule_id);
            $complete_stmt->execute();
        }
        
        if (method_exists($conn, 'commit')) {
            $conn->commit();
        }
        
        echo json_encode([
            'success' => true, 
            'status' => $status,
            'message' => $status === 'present' ? 'Check-in successful' : 'Late arrival - marked as absent'
        ]);
        
    } catch (Exception $e) {
        if (method_exists($conn, 'rollback')) {
            $conn->rollback();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
