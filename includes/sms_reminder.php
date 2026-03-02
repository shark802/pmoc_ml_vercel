<?php
// Ensure local timezone
@date_default_timezone_set('Asia/Manila');

// Run non-interactively (CLI or web). Requires DB connection.
require_once __DIR__ . '/conn.php';
$config = require __DIR__ . '/sms_config.php';


// Ensure column exists for deployments where table was created earlier
@$conn->query("ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS session_type VARCHAR(255) NULL AFTER access_id");

function sendSms(string $mobileNumber, string $message, array $config): array {
    $parameters = [
        'message'       => $message,
        'mobile_number' => $mobileNumber,
        'device'        => $config['device'],
        'device_sim'    => $config['device_sim']
    ];

    $headers = [
        'apikey: ' . $config['api_key']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['api_url']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    $error  = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    return [ 'result' => $result, 'error' => $error ];
}

// days_ahead parameter (default 1 = tomorrow). Use 0 to send for today.
$daysAhead = isset($_REQUEST['days_ahead']) ? intval($_REQUEST['days_ahead']) : 1;
if ($daysAhead < 0) { $daysAhead = 0; }

try {
    $timeLabelForType = function (string $sessionType): string {
        if ($sessionType === 'Orientation + Counseling') return '8:00 AM - 4:00 PM';
        if (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') === false) return '1:00 PM - 4:00 PM';
        return '8:00 AM - 12:00 PM';
    };

    // Fetch sessions for the target day (confirmed only)
    $stmt = $conn->prepare("\n        SELECT s.schedule_id, s.session_type, s.session_date, s.status,\n               ca.access_id, ca.access_code,\n               GROUP_CONCAT(DISTINCT TRIM(cp.contact_number) SEPARATOR ',') AS contact_numbers,\n               GROUP_CONCAT(CONCAT(cp.first_name, ' ', cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name\n        FROM scheduling s\n        INNER JOIN couple_access ca ON s.access_id = ca.access_id\n        INNER JOIN couple_profile cp ON ca.access_id = cp.access_id\n        WHERE DATE(s.session_date) = DATE(DATE_ADD(CURDATE(), INTERVAL ? DAY))\n          AND s.status = 'confirmed'\n        GROUP BY s.schedule_id\n    ");
    $stmt->bind_param('i', $daysAhead);
    $stmt->execute();
    $result = $stmt->get_result();

    $nowHour = (int)date('G');
    $runLabel = ($nowHour < 12) ? 'Morning Reminder' : 'Afternoon Reminder';

    // Prepare insert for logs
    $logStmt = $conn->prepare("INSERT INTO sms_logs (schedule_id, access_id, session_type, mobile_number, message, api_response, success, run_label, days_ahead) VALUES (?,?,?,?,?,?,?,?,?)");

    $sent = 0; $failed = 0; $logs = [];
    while ($row = $result->fetch_assoc()) {
        $numbers = array_filter(array_map('trim', explode(',', (string)$row['contact_numbers'])));
        if (empty($numbers)) continue;

        $dateText = date('M d, Y', strtotime($row['session_date'])) ;
        $timeText = $timeLabelForType((string)$row['session_type']);

        // Day-before reminder wording (default daysAhead=1)
        $whenText = ($daysAhead === 1) ? 'tomorrow' : $dateText;
        $message = sprintf(
            '(%s) Reminder: %s %s (%s), %s. Please arrive on time. Bring valid ID. - BCPDO',
            $runLabel,
            $row['session_type'],
            $whenText,
            $dateText,
            $timeText
        );

        foreach ($numbers as $mobile) {
            $resp = sendSms($mobile, $message, $config);
            $ok = $resp['error'] ? 0 : 1;
            if ($resp['error']) { $failed++; $logs[] = "{$mobile}: ERROR {$resp['error']}"; }
            else { $sent++; $logs[] = "{$mobile}: SENT {$resp['result']}"; }

            // Write log row
            $apiResp = $resp['error'] ? $resp['error'] : (string)$resp['result'];
            $logStmt->bind_param(
                'iissssisi',
                $row['schedule_id'],
                $row['access_id'],
                $row['session_type'],
                $mobile,
                $message,
                $apiResp,
                $ok,
                $runLabel,
                $daysAhead
            );
            $logStmt->execute();
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'sent' => $sent,
        'failed' => $failed,
        'run' => $runLabel,
        'days_ahead' => $daysAhead,
        'logs' => $logs
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
