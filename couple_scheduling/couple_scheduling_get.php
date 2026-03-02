<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors instead
require '../includes/conn.php';
require '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$schedule_id = $_GET['id'] ?? null;
if (!$schedule_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No schedule ID provided']);
    exit();
}

$stmt = $conn->prepare("
    SELECT s.*, ca.access_code, 
           GROUP_CONCAT(
               CONCAT(cp.first_name, ' ', cp.last_name, ' (', TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE()), ')')
               ORDER BY cp.sex DESC 
               SEPARATOR ' & '
           ) as couple_names,
           IFNULL(MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())), 100) as min_age
    FROM scheduling s
    JOIN couple_access ca ON s.access_id = ca.access_id
    JOIN couple_profile cp ON ca.access_id = cp.access_id
    WHERE s.schedule_id = ?
    GROUP BY s.schedule_id
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();

if (!$schedule) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule not found']);
    exit();
}

// Extract date components
$session_date = strtotime($schedule['session_date']);
$month = date('n', $session_date);
$day = date('j', $session_date);
$year = date('Y', $session_date);

$response = [
    'schedule_id' => $schedule['schedule_id'],
    'couple_names' => $schedule['couple_names'],
    'session_type' => $schedule['session_type'],
    'status' => $schedule['status'],
    'month' => $month,
    'day' => $day,
    'year' => $year,
    'min_age' => $schedule['min_age']
];

echo json_encode($response);
?> 