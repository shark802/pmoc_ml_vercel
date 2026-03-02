<?php
// Start output buffering to prevent any output before JSON response
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead
ini_set('log_errors', 1);

require '../includes/conn.php';
require '../includes/session.php';
require_once '../includes/notifications.php';
require_once '../includes/audit_log.php';
require_once '../vendor/autoload.php';

// Add PHPMailer use statements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$action = $_REQUEST['action'] ?? '';
$schedule_id = isset($_REQUEST['schedule_id']) ? (int)$_REQUEST['schedule_id'] : 0;
$access_id = isset($_REQUEST['access_id']) ? (int)$_REQUEST['access_id'] : 0;
$date = $_REQUEST['date'] ?? '';

// Validate inputs
if (!in_array($action, ['accept', 'reschedule', 'admin_confirm'], true)) {
    // If called via AJAX (admin_confirm), return JSON
    if ($action === 'admin_confirm' || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }
    ob_clean();
    http_response_code(400);
    echo '<h2>Invalid action</h2>';
    echo '<p>The confirmation request is invalid.</p>';
    echo '<p><a href="../couple_scheduling/couple_scheduling.php">← Back to Scheduling</a></p>';
    exit;
}

try {
    // Find the schedule
    if ($schedule_id > 0) {
        $stmt = $conn->prepare("SELECT schedule_id, access_id, session_date, session_type, status FROM scheduling WHERE schedule_id = ? LIMIT 1");
        $stmt->bind_param('i', $schedule_id);
    } else {
        $stmt = $conn->prepare("SELECT schedule_id, access_id, session_date, session_type, status FROM scheduling WHERE access_id = ? AND session_date = ? LIMIT 1");
        $stmt->bind_param('is', $access_id, $date);
    }
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();
    
    if (!$schedule) {
        // If called via AJAX (admin_confirm), return JSON
        if ($action === 'admin_confirm') {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Schedule not found or has already been processed.']);
            exit;
        }
        echo '<h2>Schedule Not Found</h2>';
        echo '<p>The schedule you are trying to confirm could not be found or has already been processed.</p>';
        echo '<p><a href="../couple_scheduling/couple_scheduling.php">← Back to Scheduling</a></p>';
        exit;
    }
    
    // Check if already processed
    if ($schedule['status'] !== 'pending') {
        // If called via AJAX (admin_confirm), return JSON
        if ($action === 'admin_confirm') {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'This schedule has already been ' . $schedule['status'] . '.']);
            exit;
        }
        echo '<h2>Schedule Already Processed</h2>';
        echo '<p>This schedule has already been ' . $schedule['status'] . '.</p>';
        echo '<p><a href="../couple_scheduling/couple_scheduling.php">← Back to Scheduling</a></p>';
        exit;
    }
    
    // Get couple information for notifications
    $coupleStmt = $conn->prepare("
        SELECT 
            CONCAT(m.first_name, ' ', m.last_name) as male_name,
            CONCAT(f.first_name, ' ', f.last_name) as female_name,
            m.email_address as male_email,
            f.email_address as female_email
        FROM couple_profile m
        JOIN couple_profile f ON m.access_id = f.access_id
        WHERE m.access_id = ? AND m.sex = 'Male' AND f.sex = 'Female'
    ");
    $coupleStmt->bind_param("i", $access_id);
    $coupleStmt->execute();
    $coupleInfo = $coupleStmt->get_result()->fetch_assoc();
    
    $coupleNames = $coupleInfo ? ($coupleInfo['male_name'] . ' & ' . $coupleInfo['female_name']) : 'Couple';
    $formattedDate = date('M d, Y', strtotime($date));

    // Time label based on session type
    $type = $schedule['session_type'];
    $timeLabel = ($type === 'Orientation') ? '8AM–12PM' : (($type === 'Counseling') ? '1PM–4PM' : '8AM–12PM and 1PM–4PM');
    
    // Process the action
    switch ($action) {
        case 'accept':
            // Update schedule status to confirmed
            $updateStmt = $conn->prepare('UPDATE scheduling SET status = "confirmed" WHERE schedule_id = ?');
            $updateStmt->bind_param('i', $schedule['schedule_id']);
            $updateStmt->execute();
            
            // Update original schedule notification to accepted
            $updateNotificationStmt = $conn->prepare("
                UPDATE notifications 
                SET notification_status = 'accepted' 
                WHERE access_id = ? AND content LIKE ? AND notification_status = 'created'");
            $schedulePattern = "%Schedule created%{$formattedDate}%";
            $updateNotificationStmt->bind_param("is", $access_id, $schedulePattern);
            $updateNotificationStmt->execute();
            
            // Update the email confirmation notification to show "confirmed" status
            $updateEmailNotificationStmt = $conn->prepare("
                UPDATE notifications 
                SET notification_status = 'confirmed' 
                WHERE access_id = ? AND content LIKE ? AND notification_status = 'sent'");
            $emailPattern = "%BCPDO: Schedule Confirmation%{$formattedDate}%";
            $updateEmailNotificationStmt->bind_param("is", $access_id, $emailPattern);
            $updateEmailNotificationStmt->execute();
            
            $message = '<h2>✅ Schedule Confirmed!</h2>';
            $message .= '<p>Your BCPDO session has been <strong>confirmed</strong>.</p>';
            $message .= '<p><strong>Session Details:</strong></p>';
            $message .= '<ul>';
            $message .= '<li><strong>Date:</strong> ' . date('l, F d, Y', strtotime($date)) . '</li>';
            $message .= '<li><strong>Type:</strong> ' . htmlspecialchars($schedule['session_type']) . '</li>';
            $message .= '<li><strong>Time:</strong> ' . $timeLabel . '</li>';
            $message .= '</ul>';
            $message .= '<p>We look forward to seeing you!</p>';
            break;
        case 'admin_confirm':
            // Admin one-click confirmation - now sends email automatically
            try {
                $updateStmt = $conn->prepare('UPDATE scheduling SET status = "confirmed" WHERE schedule_id = ?');
                if (!$updateStmt) {
                    throw new Exception('Failed to prepare update statement: ' . $conn->error);
                }
                $updateStmt->bind_param('i', $schedule['schedule_id']);
                if (!$updateStmt->execute()) {
                    throw new Exception('Failed to update schedule: ' . $updateStmt->error);
                }
                $updateStmt->close();

                // Get access_id and date from schedule record
                $access_id = $schedule['access_id'];
                $date = $schedule['session_date'];
                error_log("Starting email sending process for access_id: " . $access_id . ", date: " . $date);
                
                // Get couple email addresses
                $emailStmt = $conn->prepare("
                    SELECT cp.first_name, cp.last_name, cp.email_address, cp.sex 
                    FROM couple_profile cp 
                    WHERE cp.access_id = ?
                ");
                $emailStmt->bind_param('i', $access_id);
                $emailStmt->execute();
                $emailResult = $emailStmt->get_result();
                
                $maleEmail = '';
                $femaleEmail = '';
                while ($row = $emailResult->fetch_assoc()) {
                    error_log("Found couple member: " . $row['first_name'] . " " . $row['last_name'] . " (" . $row['sex'] . ") - " . $row['email_address']);
                    if (strtolower($row['sex']) === 'male') {
                        $maleEmail = $row['email_address'];
                    } else {
                        $femaleEmail = $row['email_address'];
                    }
                }

                error_log("Email addresses - Male: '$maleEmail', Female: '$femaleEmail'");

                // Prepare email content and recipients
                $recipients = [];
                if (!empty($maleEmail)) $recipients[] = $maleEmail;
                if (!empty($femaleEmail)) $recipients[] = $femaleEmail;
                $recipientsStr = implode(', ', $recipients);
                
                // Email content
                $subject = 'CITY POPULATION AND DEVELOPMENT OFFICE – PRE-MARRIAGE ORIENTATION AND COUNSELING';
                
                // Time label based on session type
                $timeLabel = ($schedule['session_type'] === 'Orientation') ? '8AM–12PM' : (($schedule['session_type'] === 'Counseling') ? '1PM–4PM' : '8AM–12PM and 1PM–4PM');
                
                $body = '<div style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.5;">' .
                    '  <h2 style="margin:0 0 6px 0; font-size:20px;">CITY POPULATION AND DEVELOPMENT OFFICE</h2>' .
                    '  <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:600;">PRE-MARRIAGE ORIENTATION AND COUNSELING</h3>' .
                    '  <p>Dear Couple,</p>' .
                    '  <p>Your BCPDO session has been <strong>CONFIRMED</strong>!</p>' .
                    '  <p><strong>Date:</strong> ' . date('M d, Y', strtotime($date)) . '</p>' .
                    '  <p><strong>Time:</strong> ' . $timeLabel . '</p>' .
                    '  <p><strong>Type:</strong> ' . htmlspecialchars($schedule['session_type']) . '</p>' .
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
                
                // Send email if we have recipients
                $emailSent = false;
                if (!empty($recipientsStr)) {
                    try {
                        error_log("At least one email found, proceeding with email sending...");
                        require_once '../includes/email_config.php';
                        
                        $mail = new PHPMailer(true);
                        
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = SMTP_AUTH;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;
                        $mail->setFrom(FROM_EMAIL, FROM_NAME);
                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        
                        // Add recipients
                        if (!empty($maleEmail)) {
                            $mail->addAddress($maleEmail);
                        }
                        if (!empty($femaleEmail)) {
                            $mail->addAddress($femaleEmail);
                        }
                        
                        $mail->Subject = $subject;
                        $mail->Body = $body;
                        
                        error_log("Attempting to send email...");
                        try {
                            $emailSent = $mail->send();
                        } catch (Exception $sendEx) {
                            error_log("PHPMailer send exception: " . $sendEx->getMessage());
                            $emailSent = false;
                        }
                        error_log("Confirmation email sent: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
                    } catch (Exception $e) {
                        error_log("Email sending failed during confirmation: " . $e->getMessage());
                        error_log("Exception details: " . $e->getTraceAsString());
                        $emailSent = false;
                    }
                } else {
                    error_log("No email addresses found for access_id: " . $access_id);
                }
                
                // ALWAYS log to notifications table (regardless of email send success/failure)
                // This is critical for email logs - must happen outside email sending try-catch
                error_log("Preparing to log email to notifications table. Recipients: '$recipientsStr', Access ID: $access_id, Subject: '$subject', Email Sent: " . ($emailSent ? 'YES' : 'NO'));
                if (!empty($access_id)) {
                    try {
                        // Use recipients string or 'N/A' if no emails
                        $logRecipients = !empty($recipientsStr) ? $recipientsStr : 'N/A';
                        $logStatus = $emailSent ? 'sent' : (!empty($recipientsStr) ? 'failed' : 'no_email');
                        
                        $notifStmt = $conn->prepare("INSERT INTO notifications (access_id, recipients, content, notification_status) VALUES (?, ?, ?, ?)");
                        if ($notifStmt) {
                            $notifStmt->bind_param('isss', $access_id, $logRecipients, $subject, $logStatus);
                            if ($notifStmt->execute()) {
                                $insertedId = $conn->insert_id;
                                error_log("✓ Email logged successfully to notifications table - ID: $insertedId, Access ID: $access_id, Recipients: $logRecipients, Status: $logStatus");
                                
                                // Verify the insert by querying it back
                                $verifyStmt = $conn->prepare("SELECT notification_id, recipients, content, notification_status FROM notifications WHERE notification_id = ?");
                                $verifyStmt->bind_param('i', $insertedId);
                                $verifyStmt->execute();
                                $verifyResult = $verifyStmt->get_result()->fetch_assoc();
                                if ($verifyResult) {
                                    error_log("✓ Verified notification exists: " . json_encode($verifyResult));
                                } else {
                                    error_log("✗ WARNING: Notification insert reported success but record not found!");
                                }
                                $verifyStmt->close();
                            } else {
                                error_log("✗ Failed to execute notification insert: " . $notifStmt->error);
                            }
                            $notifStmt->close();
                        } else {
                            error_log("✗ Failed to prepare notification insert statement: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        error_log("✗ Exception logging email to notifications table: " . $e->getMessage());
                        error_log("Stack trace: " . $e->getTraceAsString());
                    }
                } else {
                    error_log("✗ Cannot log email - access_id is empty. Recipients: '$recipientsStr'");
                }
                
                // Log to audit trail
                if (isset($_SESSION['admin_id']) && function_exists('logAudit') && !empty($recipientsStr)) {
                    try {
                        logAudit($conn, $_SESSION['admin_id'], 'email_sent', 
                            'Schedule confirmation email sent to: ' . $recipientsStr, 
                            'email', 
                            ['access_id' => $access_id, 'schedule_id' => $schedule['schedule_id'], 'recipients' => $recipients, 'subject' => $subject, 'status' => $emailSent ? 'sent' : 'failed']);
                    } catch (Exception $auditEx) {
                        error_log("Audit log failed: " . $auditEx->getMessage());
                    }
                }

                // Clear any output that might have been generated
                ob_clean();
                
                // Return JSON for AJAX caller
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Schedule confirmed and email sent successfully.']);
                exit;
                
            } catch (Exception $e) {
                // Clear any output that might have been generated
                ob_clean();
                
                // Return JSON error for AJAX caller
                error_log("Schedule confirmation error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to confirm schedule: ' . htmlspecialchars($e->getMessage())]);
                exit;
            }
        
        case 'reschedule':
            // Update schedule status to reschedule_requested
            $updateStmt = $conn->prepare('UPDATE scheduling SET status = "reschedule_requested" WHERE schedule_id = ?');
            $updateStmt->bind_param('i', $schedule['schedule_id']);
            $updateStmt->execute();
            
            // Update original schedule notification to reschedule_requested
            $updateNotificationStmt = $conn->prepare("
                UPDATE notifications 
                SET notification_status = 'reschedule_requested' 
                WHERE access_id = ? AND content LIKE ? AND notification_status = 'created'");
            $schedulePattern = "%Schedule created%{$formattedDate}%";
            $updateNotificationStmt->bind_param("is", $access_id, $schedulePattern);
            $updateNotificationStmt->execute();
            
            // Update the email confirmation notification to show "reschedule_requested" status
            $updateEmailNotificationStmt = $conn->prepare("
                UPDATE notifications 
                SET notification_status = 'reschedule_requested' 
                WHERE access_id = ? AND content LIKE ? AND notification_status = 'sent'");
            $emailPattern = "%BCPDO: Schedule Confirmation%{$formattedDate}%";
            $updateEmailNotificationStmt->bind_param("is", $access_id, $emailPattern);
            $updateEmailNotificationStmt->execute();
            
            // Optional: create an admin-visible notification about the reschedule request
            if (function_exists('insert_notification')) {
                insert_notification($conn, (int)$access_id, 'system', 'Couple requested reschedule for ' . date('M d, Y', strtotime($date)), 'created');
            }
            
            $message = '<h2>🔄 Reschedule Requested</h2>';
            $message .= '<p>You have requested to <strong>reschedule</strong> the BCPDO session.</p>';
            $message .= '<p><strong>Current Session Details:</strong></p>';
            $message .= '<ul>';
            $message .= '<li><strong>Date:</strong> ' . date('l, F d, Y', strtotime($date)) . '</li>';
            $message .= '<li><strong>Type:</strong> ' . htmlspecialchars($schedule['session_type']) . '</li>';
            $message .= '<li><strong>Time:</strong> ' . $timeLabel . '</li>';
            $message .= '</ul>';
            $message .= '<p>Thank you. Our staff will review your request and contact you with available dates.</p>';
            break;
    }
    
} catch (Exception $e) {
    error_log("Schedule confirmation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // If called via AJAX (admin_confirm), return JSON
    if ($action === 'admin_confirm' || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . htmlspecialchars($e->getMessage())]);
        exit;
    }
    
    ob_clean();
    $message = '<h2>❌ Error</h2>';
    $message .= '<p>An error occurred while processing your request. Please try again or contact support.</p>';
    $message .= '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// For non-AJAX requests, end output buffering and display HTML
// (AJAX requests already exit before reaching here)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    ob_end_clean(); // Clean buffer before outputting HTML
}
?>
<!DOCTYPE html>
<html lang="en">
                <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>BCPDO Schedule Confirmation</title>
                    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
        ul {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
            <h1>BCPDO Schedule Confirmation</h1>
                        </div>
                        <div class="content">
            <?php echo $message; ?>
                        </div>
                        <div class="footer">
            <a href="../couple_scheduling/couple_scheduling.php" class="btn">Exit</a>
                        </div>
                    </div>
                </body>
</html> 