<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$schedule_id = intval($_POST['schedule_id'] ?? 0);
$access_id   = intval($_POST['access_id'] ?? 0);
$status      = $_POST['status'] ?? '';

if (!$schedule_id || !$access_id || !in_array($status, ['present','absent'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }

    // Determine segment by session_type
    $s = $conn->prepare("SELECT session_type, session_date FROM scheduling WHERE schedule_id = ? AND access_id = ?");
    $s->bind_param('ii', $schedule_id, $access_id);
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    if (!$session) { throw new Exception('Session not found'); }

    $segments = [];
    if ($session['session_type'] === 'Orientation + Counseling') {
        $segments = ['orientation','counseling'];
    } elseif (strpos($session['session_type'], 'Counseling') !== false && strpos($session['session_type'], 'Orientation') === false) {
        $segments = ['counseling'];
    } else {
        $segments = ['orientation'];
    }

    // Insert attendance for both partners for each required segment
    foreach ($segments as $segment) {
        foreach (['male','female'] as $partner) {
            $log_stmt = $conn->prepare("INSERT INTO attendance_logs (schedule_id, access_id, partner_type, segment, status, recorded_by, recorded_at) VALUES (?,?,?,?,?,?,NOW())");
            $log_stmt->bind_param('iisssi', $schedule_id, $access_id, $partner, $segment, $status, $_SESSION['admin_id']);
            $log_stmt->execute();
        }
    }

    // Note: We only use attendance_logs for tracking - scheduling.attendance column is not needed

    if (method_exists($conn, 'commit')) { $conn->commit(); }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (method_exists($conn, 'rollback')) { $conn->rollback(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>


