<?php
/**
 * Forgot Password Handler
 * Handles password reset requests and sends reset emails
 */

header('Content-Type: application/json');

require_once 'includes/conn.php';
require_once 'includes/email_helper.php';
require_once 'includes/email_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address'
    ]);
    exit();
}

try {
    // Check if email exists in admin table
    $stmt = $conn->prepare("SELECT admin_id, admin_name, username, email_address FROM admin WHERE email_address = ? AND is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Always return success message (security: don't reveal if email exists)
    $response = [
        'success' => true,
        'message' => 'If an account exists with this email, a password reset link has been sent.'
    ];
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // Create password reset token table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                token_id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_admin_id (admin_id),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (admin_id) REFERENCES admin(admin_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
        
        // Invalidate any existing unused tokens for this user
        $conn->query("UPDATE password_reset_tokens SET used = 1 WHERE admin_id = {$admin['admin_id']} AND used = 0");
        
        // Insert new token
        $tokenStmt = $conn->prepare("INSERT INTO password_reset_tokens (admin_id, token, expires_at) VALUES (?, ?, ?)");
        $tokenStmt->bind_param("iss", $admin['admin_id'], $token, $expiresAt);
        $tokenStmt->execute();
        $tokenStmt->close();
        
        // Send reset email
        $emailHelper = new EmailHelper($conn);
        $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
        
        $subject = "BCPDO: Password Reset Request";
        $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; padding: 12px 30px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .button:hover { background: #3f37c9; }
                    .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px; }
                    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello {$admin['admin_name']},</p>
                        <p>You have requested to reset your password for your BCPDO account.</p>
                        <p>Click the button below to reset your password:</p>
                        <div style='text-align: center;'>
                            <a href='{$resetLink}' class='button'>Reset Password</a>
                        </div>
                        <p>Or copy and paste this link into your browser:</p>
                        <p style='word-break: break-all; color: #4361ee;'>{$resetLink}</p>
                        <div class='warning'>
                            <strong>⚠️ Important:</strong> This link will expire in 1 hour. If you didn't request this, please ignore this email.
                        </div>
                        <p>If you didn't request a password reset, you can safely ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from BCPDO System. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $emailHelper->mailer->clearAddresses();
        $emailHelper->mailer->addAddress($email, $admin['admin_name']);
        $emailHelper->mailer->Subject = $subject;
        $emailHelper->mailer->Body = $htmlBody;
        $emailHelper->mailer->isHTML(true);
        
        $sent = $emailHelper->mailer->send();
        
        if (!$sent) {
            error_log("Failed to send password reset email to: {$email}. Error: " . $emailHelper->mailer->ErrorInfo);
        }
    }
    
    $stmt->close();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode([
        'success' => true, // Still return success for security
        'message' => 'If an account exists with this email, a password reset link has been sent.'
    ]);
}
?>

