<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';
require_once '../includes/email_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

try {
    // Get notification details
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE notification_id = ? AND notification_status = 'failed'");
    $stmt->bind_param('i', $notification_id);
    $stmt->execute();
    $notification = $stmt->get_result()->fetch_assoc();

    if (!$notification) {
        echo json_encode(['success' => false, 'message' => 'Notification not found or not failed']);
        exit;
    }

    // Extract email addresses from content (format: "subject -> email1, email2")
    $content = $notification['content'];
    $emails = [];
    
    if (strpos($content, ' -> ') !== false) {
        $parts = explode(' -> ', $content, 2);
        if (isset($parts[1])) {
            $emails = array_map('trim', explode(',', $parts[1]));
        }
    }
    
    // If no emails found in content, try to extract from recipients field
    if (empty($emails)) {
        $recipients = $notification['recipients'];
        if (filter_var($recipients, FILTER_VALIDATE_EMAIL)) {
            $emails = [$recipients];
        } else {
            $emails = array_map('trim', explode(',', $recipients));
        }
    }
    
    // Filter out empty emails and validate
    $emails = array_filter($emails, function($email) {
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    if (empty($emails)) {
        echo json_encode(['success' => false, 'message' => 'No valid email addresses found in notification']);
        exit;
    }

    // Resend the email
    $emailHelper = new EmailHelper($conn);
    $emailHelper->mailer->clearAddresses();
    
    foreach ($emails as $email) {
        $emailHelper->mailer->addAddress($email);
    }
    
    // Extract subject from content if available
    $subject = 'BCPDO: Schedule Confirmation - ' . date('M d, Y');
    if (strpos($content, ' -> ') !== false) {
        $parts = explode(' -> ', $content, 2);
        if (isset($parts[0])) {
            $subject = trim($parts[0]);
        }
    }
    
    $emailHelper->mailer->Subject = $subject;
    $emailHelper->mailer->Body = $notification['content'];
    
    $sent = $emailHelper->mailer->send();
    
    if ($sent) {
        // Update notification status to sent
        $updateStmt = $conn->prepare("UPDATE notifications SET notification_status = 'sent' WHERE notification_id = ?");
        $updateStmt->bind_param('i', $notification_id);
        $updateStmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Email resent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to resend email: ' . $emailHelper->mailer->ErrorInfo]);
    }

} catch (Exception $e) {
    error_log("Error resending email: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error occurred while resending email']);
}
?> 