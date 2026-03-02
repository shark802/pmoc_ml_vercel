<?php
// Email helper using PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    public $mailer;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }
    
    private function configureMailer() {
        try {
            // Check if email notifications are enabled
            if (!ENABLE_EMAIL_NOTIFICATIONS) {
                throw new Exception("Email notifications are disabled in configuration");
            }
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = SMTP_AUTH;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->AuthType = 'LOGIN';
            // Use domain of SMTP username for EHLO/HELO if available
            $smtpDomain = 'localhost';
            if (strpos(SMTP_USERNAME, '@') !== false) {
                $parts = explode('@', SMTP_USERNAME);
                if (!empty($parts[1])) {
                    $smtpDomain = $parts[1];
                }
            }
            $this->mailer->Hostname = $smtpDomain;
            
            // Set security type
            if (SMTP_SECURE === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $this->mailer->Port = SMTP_PORT;
            
            // Connection tuning
            $this->mailer->Timeout = 60; // Increased timeout
            $this->mailer->SMTPAutoTLS = (SMTP_SECURE === 'tls');
            
            // Additional connection settings for better compatibility
            $this->mailer->SMTPKeepAlive = true;
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            // Debug settings
            if (EMAIL_DEBUG) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mailer->Debugoutput = 'error_log';
                // Help local dev on Windows/XAMPP with SSL verification
                $this->mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            
            // Default settings
            $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
        }
    }
    
    public function sendScheduleConfirmation($accessId, $scheduleData) {
        if (!ENABLE_SCHEDULE_CONFIRMATIONS) {
            error_log("Schedule confirmations disabled in config");
            return false;
        }
        
        try {
            error_log("Starting email confirmation for access_id: $accessId, schedule_id: " . $scheduleData['schedule_id']);
            
            // Get both partners' information
            $stmt = $this->conn->prepare("
                SELECT 
                    cp.first_name, cp.last_name, cp.email_address, cp.sex
                FROM couple_profile cp
                WHERE cp.access_id = ?
                ORDER BY cp.sex DESC
            ");
            $stmt->bind_param('i', $accessId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            error_log("Query executed, found " . $result->num_rows . " partner records");
            
            if ($result->num_rows === 0) {
                error_log("No partner records found for access_id: $accessId, schedule_id: " . $scheduleData['schedule_id']);
                return false;
            }
            
            // Collect all partners' data
            $partners = [];
            $emails = [];
            while ($row = $result->fetch_assoc()) {
                $partners[] = $row;
                if (!empty($row['email_address'])) {
                    $emails[] = $row['email_address'];
                }
                error_log("Partner found: " . $row['first_name'] . " " . $row['last_name'] . " (" . $row['email_address'] . ")");
            }
            
            if (empty($emails)) {
                error_log("No valid email addresses found for couple");
                $this->insertNotification($accessId, 'N/A', 'No valid email addresses found for couple', 'failed');
                return false;
            }
            
            error_log("Sending email to: " . implode(', ', $emails));
            
            // Use first partner's data for template and schedule data from parameters
            $couple = $partners[0];
            $couple['session_date'] = $scheduleData['session_date'];
            $couple['session_type'] = $scheduleData['session_type'];
            $couple['status'] = $scheduleData['status'];
            
            // Email content
            $subject = "BCPDO: Schedule Confirmation - " . date('M d, Y', strtotime($scheduleData['session_date']));
            $htmlBody = $this->getScheduleConfirmationTemplate($couple, $scheduleData);
            
            // Send email to all partners
            $this->mailer->clearAddresses();
            foreach ($partners as $partner) {
                if (!empty($partner['email_address'])) {
                    $this->mailer->addAddress($partner['email_address'], $partner['first_name'] . ' ' . $partner['last_name']);
                }
            }
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            error_log("Attempting to send email with subject: $subject");
            $sent = $this->mailer->send();
            error_log("Email send result: " . ($sent ? 'SUCCESS' : 'FAILED'));
            
            // Log notification - store email addresses in recipients field
            $this->insertNotification($accessId, implode(', ', $emails), $subject, $sent ? 'sent' : 'failed');
            
            // Log to audit trail
            if (function_exists('logAudit') && isset($_SESSION['admin_id'])) {
                require_once __DIR__ . '/audit_log.php';
                logAudit($this->conn, $_SESSION['admin_id'], 'email_sent', 
                    'Schedule confirmation email sent to: ' . implode(', ', $emails), 
                    'email', 
                    ['access_id' => $accessId, 'schedule_id' => $scheduleData['schedule_id'] ?? null, 'recipients' => $emails, 'subject' => $subject, 'status' => $sent ? 'sent' : 'failed']);
            }
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            $this->insertNotification($accessId, 'N/A', 'Failed to send email: ' . $e->getMessage(), 'failed');
            return false;
        }
    }
    
    public function sendScheduleReminder($accessId, $scheduleData) {
        if (!ENABLE_SCHEDULE_REMINDERS) {
            return false;
        }
        
        try {
            // Get both partners' information
            $stmt = $this->conn->prepare("
                SELECT 
                    cp.first_name, cp.last_name, cp.email_address, cp.sex,
                    s.session_date, s.session_type
                FROM couple_profile cp
                JOIN scheduling s ON cp.access_id = s.access_id
                WHERE cp.access_id = ? AND s.schedule_id = ?
                ORDER BY cp.sex DESC
            ");
            $stmt->bind_param('ii', $accessId, $scheduleData['schedule_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            // Collect all partners' data
            $partners = [];
            $emails = [];
            while ($row = $result->fetch_assoc()) {
                $partners[] = $row;
                if (!empty($row['email_address'])) {
                    $emails[] = $row['email_address'];
                }
            }
            
            if (empty($emails)) {
                $this->insertNotification($accessId, 'N/A', 'No valid email addresses found for couple', 'failed');
                return false;
            }
            
            // Use first partner's data for template (they all have same session info)
            $couple = $partners[0];
            
            // Email content
            $subject = "BCPDO: Schedule Reminder - Tomorrow " . date('M d, Y', strtotime($couple['session_date']));
            $htmlBody = $this->getScheduleReminderTemplate($couple, $scheduleData);
            
            // Send email to all partners
            $this->mailer->clearAddresses();
            foreach ($partners as $partner) {
                if (!empty($partner['email_address'])) {
                    $this->mailer->addAddress($partner['email_address'], $partner['first_name'] . ' ' . $partner['last_name']);
                }
            }
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            $sent = $this->mailer->send();
            
            // Log notification - store email addresses in recipients field
            $this->insertNotification($accessId, implode(', ', $emails), $subject, $sent ? 'sent' : 'failed');
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            $this->insertNotification($accessId, 'N/A', 'Failed to send reminder: ' . $e->getMessage(), 'failed');
            return false;
        }
    }
    
    public function sendScheduleCancellation($accessId, $scheduleData) {
        if (!ENABLE_SCHEDULE_CANCELLATIONS) {
            return false;
        }
        
        try {
            // Get both partners' information
            $stmt = $this->conn->prepare("
                SELECT 
                    cp.first_name, cp.last_name, cp.email_address, cp.sex
                FROM couple_profile cp
                WHERE cp.access_id = ?
                ORDER BY cp.sex DESC
            ");
            $stmt->bind_param('i', $accessId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            // Collect all partners' data
            $partners = [];
            $emails = [];
            while ($row = $result->fetch_assoc()) {
                $partners[] = $row;
                if (!empty($row['email_address'])) {
                    $emails[] = $row['email_address'];
                }
            }
            
            if (empty($emails)) {
                $this->insertNotification($accessId, 'N/A', 'No valid email addresses found for couple', 'failed');
                return false;
            }
            
            // Use first partner's data for template and schedule data from parameters
            $couple = $partners[0];
            $couple['session_date'] = $scheduleData['session_date'];
            $couple['session_type'] = $scheduleData['session_type'];
            
            // Email content
            $subject = "BCPDO: Schedule Cancellation - " . date('M d, Y', strtotime($scheduleData['session_date']));
            $htmlBody = $this->getScheduleCancellationTemplate($couple, $scheduleData);
            
            // Send email to all partners
            $this->mailer->clearAddresses();
            foreach ($partners as $partner) {
                if (!empty($partner['email_address'])) {
                    $this->mailer->addAddress($partner['email_address'], $partner['first_name'] . ' ' . $partner['last_name']);
                }
            }
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            $sent = $this->mailer->send();
            
            // Log notification - store email addresses in recipients field
            $this->insertNotification($accessId, implode(', ', $emails), $subject, $sent ? 'sent' : 'failed');
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            $this->insertNotification($accessId, 'N/A', 'Failed to send cancellation: ' . $e->getMessage(), 'failed');
            return false;
        }
    }
    
    private function getScheduleConfirmationTemplate($couple, $scheduleData) {
        $sessionDate = date('l, F d, Y', strtotime($couple['session_date']));
        $sessionTime = $this->getSessionTime($couple['session_type']);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>BCPDO Schedule Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .highlight { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>BCPDO Schedule Confirmation</h1>
                </div>
                <div class='content'>
                    <p>Dear {$couple['first_name']} {$couple['last_name']},</p>
                    
                    <p>Your BCPDO session has been successfully scheduled!</p>
                    
                    <div class='highlight'>
                        <h3>Session Details:</h3>
                        <p><strong>Date:</strong> {$sessionDate}</p>
                        <p><strong>Time:</strong> {$sessionTime}</p>
                        <p><strong>Type:</strong> {$couple['session_type']}</p>
                    </div>
                    
                    <p><strong>Important Reminders:</strong></p>
                    <ul>
                        <li>Please arrive 15 minutes before your scheduled time</li>
                        <li>Bring valid government-issued IDs</li>
                        <li>Dress appropriately for the session</li>
                        <li>If you need to reschedule, please contact us at least 24 hours in advance</li>
                    </ul>
                    
                    <p>If you have any questions, please don't hesitate to contact us.</p>
                    
                    <p>Best regards,<br>
                    BCPDO Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getScheduleReminderTemplate($couple, $scheduleData) {
        $sessionDate = date('l, F d, Y', strtotime($couple['session_date']));
        $sessionTime = $this->getSessionTime($couple['session_type']);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>BCPDO Schedule Reminder</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>BCPDO Schedule Reminder</h1>
                </div>
                <div class='content'>
                    <p>Dear {$couple['first_name']} {$couple['last_name']},</p>
                    
                    <p>This is a friendly reminder about your BCPDO session tomorrow.</p>
                    
                    <div class='highlight'>
                        <h3>Session Details:</h3>
                        <p><strong>Date:</strong> {$sessionDate}</p>
                        <p><strong>Time:</strong> {$sessionTime}</p>
                        <p><strong>Type:</strong> {$couple['session_type']}</p>
                    </div>
                    
                    <p><strong>Please remember to:</strong></p>
                    <ul>
                        <li>Arrive 15 minutes before your scheduled time</li>
                        <li>Bring valid government-issued IDs</li>
                        <li>Dress appropriately for the session</li>
                    </ul>
                    
                    <p>We look forward to seeing you!</p>
                    
                    <p>Best regards,<br>
                    BCPDO Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated reminder. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getScheduleCancellationTemplate($couple, $scheduleData) {
        $sessionDate = date('l, F d, Y', strtotime($couple['session_date']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>BCPDO Schedule Cancellation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
                .highlight { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>BCPDO Schedule Cancellation</h1>
                </div>
                <div class='content'>
                    <p>Dear {$couple['first_name']} {$couple['last_name']},</p>
                    
                    <p>Your BCPDO session has been cancelled.</p>
                    
                    <div class='highlight'>
                        <h3>Cancelled Session:</h3>
                        <p><strong>Date:</strong> {$sessionDate}</p>
                        <p><strong>Type:</strong> {$couple['session_type']}</p>
                    </div>
                    
                    <p>An administrator will contact you soon to reschedule your session.</p>
                    
                    <p>If you have any questions, please don't hesitate to contact us.</p>
                    
                    <p>Best regards,<br>
                    BCPDO Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getSessionTime($sessionType) {
        if (strpos($sessionType, 'Orientation') !== false && strpos($sessionType, 'Counseling') !== false) {
            return "8:00 AM - 4:00 PM (Full Day)";
        } elseif (strpos($sessionType, 'Orientation') !== false) {
            return "8:00 AM - 12:00 PM";
        } elseif (strpos($sessionType, 'Counseling') !== false) {
            return "1:00 PM - 4:00 PM";
        }
        return "To be determined";
    }
    
    private function insertNotification($accessId, $recipients, $content, $status) {
        try {
            if (empty($accessId)) {
                error_log("Cannot insert notification - access_id is empty. Recipients: '$recipients', Content: '$content'");
                return;
            }
            
            $stmt = $this->conn->prepare("INSERT INTO notifications (access_id, recipients, content, notification_status) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Failed to prepare notification insert: " . $this->conn->error);
                return;
            }
            
            $stmt->bind_param('isss', $accessId, $recipients, $content, $status);
            if ($stmt->execute()) {
                error_log("Email notification logged successfully - ID: " . $this->conn->insert_id . ", Access ID: $accessId, Status: $status");
            } else {
                error_log("Failed to execute notification insert: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Notification insert failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    public function testConnection() {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress('test@example.com', 'Test User');
            $this->mailer->Subject = 'Test Email';
            $this->mailer->Body = 'This is a test email from BCPDO system.';
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Test email failed: " . $e->getMessage());
            return false;
        }
    }
}
?> 