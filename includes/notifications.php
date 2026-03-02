<?php
// Enhanced notification helpers with PHPMailer integration
require_once __DIR__ . '/email_helper.php';

function insert_notification(mysqli $conn, int $accessId, string $recipients, string $content, string $status = 'unread'): void
{
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (access_id, recipients, content, notification_status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $accessId, $recipients, $content, $status);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('Notification insert failed: ' . $e->getMessage());
    }
}

function send_email_notification(mysqli $conn, int $accessId, string $toEmail, string $subject, string $htmlBody): bool
{
    if (empty($toEmail)) {
        error_log("send_email_notification: Empty email address for access_id $accessId");
        return false;
    }

    if (empty($subject)) {
        error_log("send_email_notification: Empty subject for access_id $accessId, email $toEmail");
        return false;
    }

    try {
        $emailHelper = new EmailHelper($conn);
        
        // Create a simple email using PHPMailer
        $emailHelper->mailer->clearAddresses();
        $emailHelper->mailer->addAddress($toEmail);
        $emailHelper->mailer->Subject = $subject;
        $emailHelper->mailer->Body = $htmlBody;
        
        $sent = $emailHelper->mailer->send();
        insert_notification($conn, $accessId, $toEmail, $subject, $sent ? 'sent' : 'failed');
        
        error_log("send_email_notification result for access_id $accessId, email $toEmail: " . ($sent ? 'SUCCESS' : 'FAILED'));
        return $sent;
        
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        insert_notification($conn, $accessId, 'N/A', 'Failed to send email: ' . $e->getMessage(), 'failed');
        return false;
    }
}

function send_schedule_confirmation_email(mysqli $conn, int $accessId, array $scheduleData): bool
{
    try {
        $emailHelper = new EmailHelper($conn);
        return $emailHelper->sendScheduleConfirmation($accessId, $scheduleData);
    } catch (Exception $e) {
        error_log('Schedule confirmation email failed: ' . $e->getMessage());
        return false;
    }
}

function send_schedule_reminder_email(mysqli $conn, int $accessId, array $scheduleData): bool
{
    try {
        $emailHelper = new EmailHelper($conn);
        return $emailHelper->sendScheduleReminder($accessId, $scheduleData);
    } catch (Exception $e) {
        error_log('Schedule reminder email failed: ' . $e->getMessage());
        return false;
    }
}

function send_schedule_cancellation_email(mysqli $conn, int $accessId, array $scheduleData): bool
{
    try {
        $emailHelper = new EmailHelper($conn);
        return $emailHelper->sendScheduleCancellation($accessId, $scheduleData);
    } catch (Exception $e) {
        error_log('Schedule cancellation email failed: ' . $e->getMessage());
        return false;
    }
}

// Configure SMS via environment variables if available (e.g., Twilio)
// TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
function send_sms_notification(mysqli $conn, int $accessId, string $toNumber, string $message): bool
{
    if (empty($toNumber)) {
        return false;
    }

    $sid = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    $from = getenv('TWILIO_FROM');

    if ($sid && $token && $from) {
        // Using simple cURL call to Twilio Messages API
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';
        $data = http_build_query(['To' => $toNumber, 'From' => $from, 'Body' => $message]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $ok = $httpCode >= 200 && $httpCode < 300;
        insert_notification($conn, $accessId, $toNumber, 'SMS to ' . $toNumber . ' status ' . $httpCode . ' ' . ($err ?: ''), $ok ? 'sent' : 'failed');
        return $ok;
    }

    // Fallback: log only
    insert_notification($conn, $accessId, $toNumber, 'SMS (not configured) to ' . $toNumber . ': ' . $message, 'queued');
    return false;
}


