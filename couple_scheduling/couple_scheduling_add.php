<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors instead
require '../includes/conn.php';
require '../includes/session.php';
require_once '../includes/audit_log.php';
// Load scheduling capacity configuration
$schedConfig = require_once '../includes/scheduling_config.php';
require_once '../includes/email_helper.php';
require_once '../includes/notifications.php';
require_once '../vendor/autoload.php';

// Add PHPMailer use statements at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once '../includes/csrf_helper.php';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        header("Location: couple_scheduling.php");
        exit();
    }
    
    $access_code = $_POST['couple_code'];
    $session_month = $_POST['session_month'];
    $session_day = $_POST['session_day'];
    $session_year = $_POST['session_year'];
    $session_type = $_POST['session_type'];
    $status = 'pending';

    // Create date string in YYYY-MM-DD format
    $session_date = sprintf('%04d-%02d-%02d', $session_year, $session_month, $session_day);

    // Validate inputs
    $errors = [];

    // Prevent scheduling in the past
    if (strtotime($session_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Cannot schedule sessions in the past.";
    }

    // Validate date is within 6 months
    $sixMonthsLater = date('Y-m-d', strtotime('+6 months'));
    if ($session_date > $sixMonthsLater) {
        $errors[] = "Scheduling is only allowed within 6 months from today.";
    }

    // Validate it's Tuesday or Friday
    $dayOfWeek = date('N', strtotime($session_date));
    if (!in_array($dayOfWeek, [2, 5])) {
        $errors[] = "Scheduling only available on Tuesdays and Fridays";
    }

    // Enforce capacity per date and type
    $capOrientation = (int)($schedConfig['capacity']['Orientation'] ?? 0);
    $capCounseling  = (int)($schedConfig['capacity']['Counseling'] ?? 0);
    $countStatuses  = $schedConfig['count_statuses'] ?? ['pending','confirmed'];

    // Build placeholders for IN clause
    $inPlaceholders = implode(',', array_fill(0, count($countStatuses), '?'));

    // Count existing bookings on that date for each type that consume capacity
    $sqlCount = "
        SELECT 
            SUM(CASE WHEN session_type = 'Orientation' THEN 1 ELSE 0 END) AS cnt_orientation,
            SUM(CASE WHEN session_type = 'Counseling' THEN 1 ELSE 0 END) AS cnt_counseling,
            SUM(CASE WHEN session_type = 'Orientation + Counseling' THEN 1 ELSE 0 END) AS cnt_both
        FROM scheduling
        WHERE session_date = ?
          AND status IN ($inPlaceholders)
    ";
    $stmtCount = $conn->prepare($sqlCount);
    if ($stmtCount) {
        // Bind params: date + statuses
        $types = 's' . str_repeat('s', count($countStatuses));
        $params = array_merge([$session_date], $countStatuses);
        $stmtCount->bind_param($types, ...$params);
        $stmtCount->execute();
        $counts = $stmtCount->get_result()->fetch_assoc() ?: ['cnt_orientation'=>0,'cnt_counseling'=>0,'cnt_both'=>0];
        $stmtCount->close();

        $usedOrientation = (int)$counts['cnt_orientation'] + (int)$counts['cnt_both'];
        $usedCounseling  = (int)$counts['cnt_counseling'] + (int)$counts['cnt_both'];

        // Compute remaining capacity
        $remOrientation = max(0, $capOrientation - $usedOrientation);
        $remCounseling  = max(0, $capCounseling  - $usedCounseling);

        // Check against requested type
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
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: couple_scheduling.php");
        exit();
    }

    // Get couple info and check if profiles are complete
    $stmt = $conn->prepare("
        SELECT 
            ca.access_id,
            MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) as min_age,
            ca.male_profile_submitted,
            ca.female_profile_submitted,
            ca.male_questionnaire_submitted,
            ca.female_questionnaire_submitted
        FROM couple_access ca
        JOIN couple_profile cp ON ca.access_id = cp.access_id
        WHERE ca.access_code = ?
        GROUP BY ca.access_id
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $access_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid couple code.";
        header("Location: couple_scheduling.php");
        exit();
    }

    $row = $result->fetch_assoc();
    $access_id = $row['access_id'];
    $min_age = $row['min_age'];

    // Check if profiles and questionnaires are complete
    if (!$row['male_profile_submitted'] || !$row['female_profile_submitted'] || 
        !$row['male_questionnaire_submitted'] || !$row['female_questionnaire_submitted']) {
        $_SESSION['error_message'] = "Cannot schedule - couple profiles or questionnaires are incomplete.";
        header("Location: couple_scheduling.php");
        exit();
    }

    // Check if couple already has an active schedule (future date or active status)
    $checkStmt = $conn->prepare("
        SELECT 1 FROM scheduling 
        WHERE access_id = ? 
        AND (session_date >= CURDATE() 
             OR status IN ('pending', 'confirmed', 'reschedule_requested'))
        LIMIT 1
    ");
    $checkStmt->bind_param("i", $access_id);
    $checkStmt->execute();

    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['error_message'] = "This couple already has an active scheduled session. Please complete or cancel the existing schedule before creating a new one.";
        header("Location: couple_scheduling.php");
        exit();
    }

    // Validate age requirements
    if ($min_age <= 25 && $session_type !== 'Orientation + Counseling') {
        $_SESSION['error_message'] = "Orientation + Counseling is mandatory for couples with one or both partners age 25 or younger. Please select 'Orientation + Counseling'";
        header("Location: couple_scheduling.php");
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert single session
        $stmt = $conn->prepare("
            INSERT INTO scheduling 
            (access_id, session_date, session_type, status) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $access_id, $session_date, $session_type, $status);
        $stmt->execute();
        $scheduleId = $conn->insert_id;
        
        // Log schedule creation
        logAudit($conn, $_SESSION['admin_id'], AUDIT_CREATE, 
            'Couple session scheduled: ' . $session_type . ' on ' . $session_date, 
            'scheduling', 
            ['schedule_id' => $scheduleId, 'access_id' => $access_id, 'session_date' => $session_date, 'session_type' => $session_type]);

        $emailMaleStmt = $conn->prepare("
        SELECT email_address FROM couple_profile  
        WHERE sex = 'Male'
        AND access_id = ?
        ");
        $emailMaleStmt->bind_param('i', $access_id);
        $emailMaleStmt->execute();
        $infoMale = $emailMaleStmt->get_result()->fetch_assoc();

        $emailFemaleStmt = $conn->prepare("
        SELECT email_address FROM couple_profile  
        WHERE sex = 'Female'
        AND access_id = ?
        ");
        $emailFemaleStmt->bind_param('i', $access_id);
        $emailFemaleStmt->execute();
        $infoFemale = $emailFemaleStmt->get_result()->fetch_assoc();

        // Get both emails for logging purposes
        $maleEmail = $infoMale['email_address'] ?? '';
        $femaleEmail = $infoFemale['email_address'] ?? '';
        
        // Log the emails being used
        error_log("Scheduling emails - Male: $maleEmail, Female: $femaleEmail");

        // Remove the manual test email code - let the EmailHelper handle it properly
        // The proper email will be sent via send_schedule_confirmation_email() below

        // Lookup couple emails and phones
        $infoStmt = $conn->prepare("
            SELECT 
                MAX(CASE WHEN cp.sex='Male' THEN cp.email_address END) AS male_email,
                MAX(CASE WHEN cp.sex='Female' THEN cp.email_address END) AS female_email,
                MAX(CASE WHEN cp.sex='Male' THEN cp.contact_number END) AS male_phone,
                MAX(CASE WHEN cp.sex='Female' THEN cp.contact_number END) AS female_phone
            FROM couple_profile cp
            WHERE cp.access_id = ?
        ");
        $infoStmt->bind_param('i', $access_id);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();


        require_once '../includes/email_config.php';
        $baseUrl = rtrim(SITE_URL, '/') . '/couple_scheduling';
        $approveLink = $baseUrl . '/couple_scheduling_confirm.php?action=accept&access_id=' . $access_id . '&date=' . urlencode($session_date);
        $rescheduleLink = $baseUrl . '/couple_scheduling_confirm.php?action=reschedule&access_id=' . $access_id . '&date=' . urlencode($session_date);

        // Log the URL generation
        error_log("Base URL: " . $baseUrl);
        error_log("Approve link: " . $approveLink);
        error_log("Reschedule link: " . $rescheduleLink);

        $subject = 'CITY POPULATION AND DEVELOPMENT OFFICE – PRE-MARRIAGE ORIENTATION AND COUNSELING';
        $timeLabel = ($session_type === 'Orientation') ? '8AM–12PM' : (($session_type === 'Counseling') ? '1PM–4PM' : '8AM–12PM and 1PM–4PM');
        $body =
                '<div style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.5;">' .
                '  <h2 style="margin:0 0 6px 0; font-size:20px;">CITY POPULATION AND DEVELOPMENT OFFICE</h2>' .
                '  <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:600;">PRE-MARRIAGE ORIENTATION AND COUNSELING</h3>' .
                '  <p>Dear Couple,</p>' .
                '  <p>Your BCPDO session has been scheduled:</p>' .
                '  <p><strong>Date:</strong> ' . date('M d, Y', strtotime($session_date)) . '</p>' .
                '  <p><strong>Time:</strong> ' . $timeLabel . '</p>' .
                '  <p><strong>Type:</strong> ' . htmlspecialchars($session_type) . '</p>' .
                '  <h4 style="margin:16px 0 8px 0; font-size:16px;">IMPORTANT REMINDERS:</h4>' .
                '  <ul style="margin:0 0 12px 18px; padding:0;">' .
                '    <li>Go to the BCPDO before 8:00 in the morning</li>' .
                '    <li>Do not wear: sleeveless shirts, shorts, and slippers</li>' .
                '    <li>Eat breakfast before going to the seminar</li>' .
                '    <li>Do not be late</li>' .
                '    <li>Bring &#8369;150.00 for the Marriage License to be paid at the Treasurer\'s Office</li>' .
                '    <li>Please bring an ID with picture</li>' .
                '  </ul>' .
                '  <p>If you have any questions, please don\'t hesitate to contact us.</p>' .
                '  <p>Best regards,<br>BCPDO Team</p>' .
                '</div>';

        // Log the email content
        error_log("Email subject: " . $subject);
        error_log("Email body: " . $body);

        // Get the schedule ID for email notifications
        $scheduleId = $conn->insert_id;
        $scheduleData = [
            'schedule_id' => $scheduleId,
            'session_date' => $session_date,
            'session_type' => $session_type,
            'status' => $status
        ];

        // Debug logging
        error_log("Schedule created - ID: $scheduleId, Access ID: $access_id, Date: $session_date, Type: $session_type");
        error_log("Schedule data: " . json_encode($scheduleData));

        // Email will be sent only when admin confirms the schedule
        // No email sent during creation - schedule starts as 'pending'
        $emailSent = false;
        error_log("Schedule created with pending status - no email sent yet");
        
        // Create notification for schedule creation (disabled by request)
        // Feature note: We keep the original code wrapped in a guard so it won't
        // execute, preserving the implementation without populating notifications.
        if (false) try {
            error_log("Starting notification creation for access_id: " . $access_id);
            
            // Get couple names for notification
            $namesStmt = $conn->prepare("
                SELECT 
                    CONCAT(m.first_name, ' ', m.last_name) as male_name,
                    CONCAT(f.first_name, ' ', f.last_name) as female_name,
                    m.email_address as male_email,
                    f.email_address as female_email
                FROM couple_profile m
                JOIN couple_profile f ON m.access_id = f.access_id
                WHERE m.access_id = ? AND m.sex = 'Male' AND f.sex = 'Female'
            ");
            $namesStmt->bind_param("i", $access_id);
            $namesStmt->execute();
            $namesResult = $namesStmt->get_result()->fetch_assoc();
            
            error_log("Couple names query result: " . print_r($namesResult, true));
            
            if ($namesResult) {
                $coupleNames = $namesResult['male_name'] . ' & ' . $namesResult['female_name'];
                $maleEmail = $namesResult['male_email'];
                $femaleEmail = $namesResult['female_email'];
                $formattedDate = date('M d, Y', strtotime($session_date));
                
                error_log("Couple names: " . $coupleNames);
                error_log("Formatted date: " . $formattedDate);
                error_log("Session type: " . $session_type);
                error_log("Access ID: " . $access_id);
                
                // Create notification for schedule creation
                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications (recipients, content, access_id, notification_status, created_at) 
                    VALUES (?, ?, ?, 'created', NOW())");
                $notificationContent = "Schedule created - {$session_type} on {$formattedDate}";
                // Truncate content to fit within varchar(255) limit
                if (strlen($notificationContent) > 250) {
                    $notificationContent = substr($notificationContent, 0, 247) . "...";
                }
                error_log("Notification content: " . $notificationContent);
                error_log("Content length: " . strlen($notificationContent));
                error_log("SQL Query: INSERT INTO notifications (recipients, content, access_id, notification_status, created_at) VALUES ('{$coupleNames}', '{$notificationContent}', {$access_id}, 'created', NOW())");
                
                $notificationStmt->bind_param("ssi", $coupleNames, $notificationContent, $access_id);
                $result = $notificationStmt->execute();
                
                if ($result) {
                    error_log("Schedule notification created successfully: " . $notificationContent);
                    error_log("Notification ID: " . $conn->insert_id);
                    
                    // No email notification created during schedule creation
                    // Email will be sent and logged when admin confirms the schedule
                    error_log("Schedule created - email will be sent upon admin confirmation");
                } else {
                    error_log("Failed to create schedule notification. SQL Error: " . $notificationStmt->error);
                }
            } else {
                error_log("No couple names found for access_id: " . $access_id);
            }
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            // Don't fail the entire operation if notification creation fails
        }

        // No email sent during schedule creation - only when admin confirms
        error_log("Schedule created successfully - email will be sent when admin confirms");

        $conn->commit();
        
        $_SESSION['success_message'] = "Session scheduled successfully for " . $session_date . ". Waiting for admin confirmation to send email notification.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error scheduling session: " . $e->getMessage();
    }

    header("Location: couple_scheduling.php");
    exit();
}

$_SESSION['error_message'] = "Invalid request.";
header("Location: couple_scheduling.php");
exit();