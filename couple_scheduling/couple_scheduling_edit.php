<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors instead
require '../includes/conn.php';
require '../includes/session.php';
require_once '../vendor/autoload.php';
// Load scheduling capacity configuration
$schedConfig = require_once '../includes/scheduling_config.php';

// Define valid session types
$valid_types = ['Orientation', 'Orientation + Counseling'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once '../includes/csrf_helper.php';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit();
    }
    
    $schedule_id = $_POST['schedule_id'];
    $session_month = $_POST['session_month'];
    $session_day = $_POST['session_day'];
    $session_year = $_POST['session_year'];
    $session_type = $_POST['session_type'];
    $status = $_POST['status'];

    // Load current schedule to detect transitions
    $currentStmt = $conn->prepare("SELECT schedule_id, access_id, session_date, session_type, status FROM scheduling WHERE schedule_id = ?");
    $currentStmt->bind_param('i', $schedule_id);
    $currentStmt->execute();
    $current = $currentStmt->get_result()->fetch_assoc();
    $currentStmt->close();
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Schedule not found.']);
        exit();
    }

    // Create date string in YYYY-MM-DD format
    $session_date = sprintf('%04d-%02d-%02d', $session_year, $session_month, $session_day);

    // Validate inputs
    $errors = [];

    // Validate session type
    if (!in_array($session_type, $valid_types)) {
        $errors[] = "Invalid session type selected.";
    }

    // Validate date components
    if (empty($session_month) || empty($session_day) || empty($session_year)) {
        $errors[] = "Please select a complete date.";
    }

    // Validate date is valid
    if (!checkdate($session_month, $session_day, $session_year)) {
        $errors[] = "Invalid date selected.";
    }

    // Validate date is not in the past
    if (strtotime($session_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Cannot schedule sessions in the past.";
    }

    // Validate day is Tuesday or Friday
    $dayOfWeek = date('N', strtotime($session_date));
    if (!in_array($dayOfWeek, [2, 5])) {
        $errors[] = "Sessions can only be scheduled on Tuesdays or Fridays.";
    }

    // Validate date is within 2 months
    $twoMonthsLater = date('Y-m-d', strtotime('+2 months'));
    if ($session_date > $twoMonthsLater) {
        $errors[] = "Scheduling is only allowed within 2 months from today.";
    }

    // Capacity/conflict enforcement (exclude this schedule_id when counting)
    $capOrientation = (int)($schedConfig['capacity']['Orientation'] ?? 0);
    $capCounseling  = (int)($schedConfig['capacity']['Counseling'] ?? 0);
    $countStatuses  = $schedConfig['count_statuses'] ?? ['pending','confirmed'];

    $inPlaceholders = implode(',', array_fill(0, count($countStatuses), '?'));
    $sqlCount = "
        SELECT 
            SUM(CASE WHEN session_type = 'Orientation' THEN 1 ELSE 0 END) AS cnt_orientation,
            SUM(CASE WHEN session_type = 'Counseling' THEN 1 ELSE 0 END) AS cnt_counseling,
            SUM(CASE WHEN session_type = 'Orientation + Counseling' THEN 1 ELSE 0 END) AS cnt_both
        FROM scheduling
        WHERE session_date = ?
          AND status IN ($inPlaceholders)
          AND schedule_id <> ?
    ";
    $stmtCount = $conn->prepare($sqlCount);
    if ($stmtCount) {
        $types = 's' . str_repeat('s', count($countStatuses)) . 'i';
        $params = array_merge([$session_date], $countStatuses, [(int)$schedule_id]);
        $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $counts = $stmtCount->get_result()->fetch_assoc() ?: ['cnt_orientation'=>0,'cnt_counseling'=>0,'cnt_both'=>0];
        $stmtCount->close();

        $usedOrientation = (int)$counts['cnt_orientation'] + (int)$counts['cnt_both'];
        $usedCounseling  = (int)$counts['cnt_counseling'] + (int)$counts['cnt_both'];

        $remOrientation = max(0, $capOrientation - $usedOrientation);
        $remCounseling  = max(0, $capCounseling  - $usedCounseling);

        if ($session_type === 'Orientation') {
            if ($remOrientation <= 0) {
                $errors[] = "No remaining capacity for Orientation on this date.";
            }
        } elseif ($session_type === 'Counseling') {
            if ($remCounseling <= 0) {
                $errors[] = "No remaining capacity for Counseling on this date.";
            }
        } elseif ($session_type === 'Orientation + Counseling') {
            if ($remOrientation <= 0 || $remCounseling <= 0) {
                $errors[] = "No remaining capacity for Orientation + Counseling on this date.";
            }
        }
    }

    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }
        
        $_SESSION['error_message'] = $error_message;
        header("Location: couple_scheduling.php");
        exit();
    }

    // Get couple info for age validation
    $stmt = $conn->prepare("
        SELECT IFNULL(MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())), 100) as min_age
        FROM scheduling s
        JOIN couple_access ca ON s.access_id = ca.access_id
        JOIN couple_profile cp ON ca.access_id = cp.access_id
        WHERE s.schedule_id = ?
    ");
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $min_age = $row['min_age'];

    // Validate age requirements
    if ($min_age <= 25 && $session_type !== 'Orientation + Counseling') {
        $error_message = "Orientation + Counseling is mandatory for couples with one or both partners age 25 or below";
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }
        
        $_SESSION['error_message'] = $error_message;
        header("Location: couple_scheduling.php");
        exit();
    }

    // Update the schedule (date and type only)
    $stmt = $conn->prepare("
        UPDATE scheduling 
        SET session_date = ?, session_type = ? 
        WHERE schedule_id = ?
    ");
    
    $stmt->bind_param("ssi", $session_date, $session_type, $schedule_id);
    
    $updated = $stmt->execute();

    // If it was a reschedule request or explicitly rescheduling, flip to pending and send a fresh confirmation email
    if ($updated && ($current['status'] === 'reschedule_requested' || isset($_POST['reschedule']) )) {
        // 1) set status back to pending for admin confirmation
        $upStatus = $conn->prepare("UPDATE scheduling SET status = 'pending' WHERE schedule_id = ?");
        $upStatus->bind_param('i', $schedule_id);
        $upStatus->execute();
        $upStatus->close();

        // 1b) clear any previous attendance records so Attendance column shows Pending (not Absent)
        try {
            $clear = $conn->prepare("DELETE FROM attendance_logs WHERE schedule_id = ?");
            $clear->bind_param('i', $schedule_id);
            $clear->execute();
            $clear->close();
        } catch (Throwable $e) { /* ignore */ }

        // 2) send confirmation email (reuse simple PHPMailer flow)
        // Prepare links
        require_once '../includes/email_config.php';
        
        // Fetch couple emails
        $emailsStmt = $conn->prepare("SELECT 
              MAX(CASE WHEN sex='Male' THEN email_address END) AS male_email,
              MAX(CASE WHEN sex='Female' THEN email_address END) AS female_email
            FROM couple_profile WHERE access_id = ?");
        $emailsStmt->bind_param('i', $current['access_id']);
        $emailsStmt->execute();
        $emails = $emailsStmt->get_result()->fetch_assoc();
        $emailsStmt->close();

        $baseUrl = rtrim(SITE_URL, '/') . '/couple_scheduling';
        $approveLink = $baseUrl . '/couple_scheduling_confirm.php?action=accept&access_id=' . $current['access_id'] . '&date=' . urlencode($session_date);
        $reschedLink = $baseUrl . '/couple_scheduling_confirm.php?action=reschedule&access_id=' . $current['access_id'] . '&date=' . urlencode($session_date);

        $subject = 'CITY POPULATION AND DEVELOPMENT OFFICE – PRE-MARRIAGE ORIENTATION AND COUNSELING';
        $timeLabel = ($session_type === 'Orientation') ? '8AM–12PM' : (($session_type === 'Counseling') ? '1PM–4PM' : '8AM–12PM and 1PM–4PM');
        $body =
                '<div style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.5;">' .
                '  <h2 style="margin:0 0 6px 0; font-size:20px;">CITY POPULATION AND DEVELOPMENT OFFICE</h2>' .
                '  <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:600;">PRE-MARRIAGE ORIENTATION AND COUNSELING</h3>' .
                '  <p>Dear Couple,</p>' .
                '  <p>Your BCPDO session has been rescheduled:</p>' .
                '  <p><strong>Date:</strong> ' . date('M d, Y', strtotime($session_date)) . '</p>' .
                '  <p><strong>Time:</strong> ' . $timeLabel . '</p>' .
                '  <p><strong>Type:</strong> ' . htmlspecialchars($session_type) . '</p>' .
                '  <h4 style="margin:16px 0 8px 0; font-size:16px;">IMPORTANT REMINDERS:</h4>' .
                '  <ul style="margin:0 0 12px 18px; padding:0;">' .
                '    <li>Go to the POPCOM Office before 8:00 in the morning</li>' .
                '    <li>Do not wear: sleeveless shirts, shorts, and slippers</li>' .
                '    <li>Eat breakfast before going to the seminar</li>' .
                '    <li>Do not be late</li>' .
                '    <li>Bring &#8369;150.00 for the Marriage License to be paid at the Treasurer\'s Office</li>' .
                '    <li>Please bring an ID with picture</li>' .
                '  </ul>' .
                '  <p>If you have any questions, please don\'t hesitate to contact us.</p>' .
                '  <p>Best regards,<br>BCPDO Team</p>' .
                '</div>';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            if (!empty($emails['male_email'])) $mail->addAddress($emails['male_email']);
            if (!empty($emails['female_email'])) $mail->addAddress($emails['female_email']);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $emailSent = $mail->send();
            
            // Log to notifications table for email logs
            $recipients = [];
            if (!empty($emails['male_email'])) $recipients[] = $emails['male_email'];
            if (!empty($emails['female_email'])) $recipients[] = $emails['female_email'];
            $recipientsStr = implode(', ', $recipients);
            
            if (!empty($recipientsStr) && !empty($current['access_id'])) {
                try {
                    $notifStmt = $conn->prepare("INSERT INTO notifications (access_id, recipients, content, notification_status) VALUES (?, ?, ?, ?)");
                    if ($notifStmt) {
                        $notifStmt->bind_param('isss', $current['access_id'], $recipientsStr, $subject, $emailSent ? 'sent' : 'failed');
                        if ($notifStmt->execute()) {
                            error_log("Email logged successfully to notifications table - ID: " . $conn->insert_id);
                        } else {
                            error_log("Failed to execute notification insert: " . $notifStmt->error);
                        }
                        $notifStmt->close();
                    } else {
                        error_log("Failed to prepare notification insert statement: " . $conn->error);
                    }
                } catch (Exception $e) {
                    error_log("Failed to log email to notifications table: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            } else {
                error_log("Cannot log email - missing recipients or access_id. Recipients: '$recipientsStr', Access ID: " . ($current['access_id'] ?? 'NULL'));
            }
        } catch (Exception $e) {
            // Log only; do not fail the request
            error_log('Edit reschedule email failed: ' . $e->getMessage());
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($updated) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating schedule: ' . $conn->error]);
        }
        exit();
    }
    
    // Regular form submission
    if ($updated) {
        $_SESSION['success_message'] = "Schedule updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating schedule: " . $conn->error;
    }
    
    header("Location: couple_scheduling.php");
    exit();
}

$schedule_id = $_GET['id'] ?? null;
if (!$schedule_id) {
    $_SESSION['error_message'] = "No schedule ID provided.";
    header("Location: couple_scheduling.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT s.*, ca.access_code, 
           GROUP_CONCAT(
               CONCAT(cp.first_name, ' ', cp.last_name, ' (', TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE()), ')')
               ORDER BY cp.sex DESC 
               SEPARATOR ' & '
           ) as couple_names,
           MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) as min_age
    FROM scheduling s
    JOIN couple_access ca ON s.access_id = ca.access_id
    JOIN couple_profile cp ON ca.access_id = cp.access_id
    WHERE s.schedule_id = ?
    GROUP BY s.schedule_id
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

if (!$schedule) {
    $_SESSION['error_message'] = "Schedule not found.";
    header("Location: couple_scheduling.php");
    exit();
}

// Extract date components
$session_date = strtotime($schedule['session_date']);
$current_month = date('n', $session_date);
$current_day = date('j', $session_date);
$current_year = date('Y'); // Use current year as default

// Month names for dropdown
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Schedule</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .age-warning { color: #dc3545; font-weight: bold; }
        .is-invalid { border-color: #dc3545 !important; }
        .date-component { margin-bottom: 10px; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Edit Schedule</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="editForm">
                            <input type="hidden" name="schedule_id" value="<?= $schedule['schedule_id'] ?>">
                            
                            <div class="form-group">
                                <label>Couple</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($schedule['couple_names']) ?>" readonly>
                                <?php if ($schedule['min_age'] <= 25): ?>
                                    <small class="form-text age-warning">Includes partner(s) age 25 or below - Counseling required</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Session Date</label>
                                <div class="row date-component">
                                    <div class="col-md-4">
                                        <select class="form-control" id="session_month" name="session_month" required>
                                            <option value="">Month</option>
                                            <?php foreach ($months as $num => $name): ?>
                                                <option value="<?= $num ?>" <?= $num == $current_month ? 'selected' : '' ?>>
                                                    <?= $name ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" class="form-control" id="session_day" name="session_day" 
                                               min="1" max="31" placeholder="Day" 
                                               value="<?= $current_day ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" class="form-control" id="session_year" name="session_year" 
                                               min="<?= date('Y') ?>" max="<?= date('Y') + 5 ?>" placeholder="Year" 
                                               value="<?= $current_year ?>" required>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Only Tuesdays and Fridays available</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Session Type</label>
                                <select name="session_type" class="form-control" required>
                                    <option value="Orientation" 
                                        <?= $schedule['session_type'] == 'Orientation' ? 'selected' : '' ?>
                                        <?= $schedule['min_age'] <= 25 ? 'disabled' : '' ?>>
                                        Orientation (8AM-12PM)
                                    </option>
                                    <option value="Orientation + Counseling" 
                                        <?= $schedule['session_type'] == 'Orientation + Counseling' ? 'selected' : '' ?>>
                                        Orientation + Counseling (Full Day)
                                    </option>
                                </select>
                                <small id="sessionTypeHelp" class="form-text text-muted">
                                    <?php if ($schedule['min_age'] <= 25): ?>
                                        <span class="text-danger">Orientation + Counseling is required</span>
                                    <?php else: ?>
                                        <span class="text-success">Orientation available</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <?php
                                    $status = $schedule['status'];
                                    $badgeClass = 'badge-secondary';
                                    if ($status === 'pending') $badgeClass = 'badge-warning';
                                    if ($status === 'reschedule_requested') $badgeClass = 'badge-info';
                                ?>
                                <div>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst(str_replace('_',' ', $status)) ?></span>
                                </div>
                                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                <small class="form-text text-muted">Status is set by couple confirmations or admin actions elsewhere.</small>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update
                                </button>
                                <a href="couple_scheduling.php" class="btn btn-default">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>
<?php include '../includes/scripts.php'; ?>
<script>
$(document).ready(function() {
    // Date validation functions
    function validateDayInput() {
        const month = $('#session_month').val();
        const year = $('#session_year').val();

        if (!month || !year) return;

        // Set max days based on month
        let maxDays = 31;
        if ([4, 6, 9, 11].includes(parseInt(month))) {
            maxDays = 30;
        } else if (month == 2) {
            maxDays = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
        }
        $('#session_day').attr('max', maxDays);
        
        // Adjust day if current value exceeds max days
        const currentDay = parseInt($('#session_day').val());
        if (currentDay > maxDays) {
            $('#session_day').val(maxDays);
        }
    }

    // Validate day when month changes
    $('#session_month').change(function() {
        validateDayInput();
        validateSelectedDate();
    });

    // Validate day input
    $('#session_day').on('change input', function() {
        validateSelectedDate();
    });

    // Validate the complete date
    function validateSelectedDate() {
        const month = $('#session_month').val();
        const day = $('#session_day').val();
        const year = $('#session_year').val();

        if (!month || !day || !year) return true;

        const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const date = new Date(dateStr);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Clear previous errors
        $('#session_month, #session_day').removeClass('is-invalid');

        // Check if date is valid
        if (isNaN(date.getTime())) {
            $('#session_month, #session_day').addClass('is-invalid');
            return false;
        }

        // Validate day of week (Tuesday=2, Friday=5)
        if (date.getDay() !== 2 && date.getDay() !== 5) {
            $('#session_month, #session_day').addClass('is-invalid');
            Swal.fire({
                icon: 'error',
                title: 'Invalid Day',
                text: 'Sessions can only be scheduled on Tuesdays or Fridays'
            });
            return false;
        }

        // Validate not in past
        if (date < today) {
            $('#session_month, #session_day').addClass('is-invalid');
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date',
                text: 'Cannot schedule sessions in the past'
            });
            return false;
        }

        // Validate within 2 months
        const twoMonthsLater = new Date();
        twoMonthsLater.setMonth(twoMonthsLater.getMonth() + 2);
        
        if (date > twoMonthsLater) {
            $('#session_month, #session_day').addClass('is-invalid');
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date',
                text: 'Scheduling is only allowed within 2 months from today'
            });
            return false;
        }

        return true;
    }

    // Initialize date validation
    validateDayInput();

    // Form submission validation
    $('#editForm').submit(function(e) {
        let isValid = true;
        
        // Validate session type for age requirements
        const minAge = <?= $schedule['min_age'] ?? 0 ?>;
        const sessionType = $('select[name="session_type"]').val();
        
        if (minAge <= 25 && sessionType !== 'Orientation + Counseling') {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Session Type',
                text: 'Orientation + Counseling is required for couples with partners age 25 or below'
            });
            isValid = false;
        }
        
        // Validate date
        if (!validateSelectedDate()) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>