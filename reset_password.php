<?php
/**
 * Password Reset Page
 * Allows users to reset their password using a token from email
 */

session_start();
require_once 'includes/conn.php';
require_once 'includes/image_helper.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$validToken = false;
$adminId = null;

// Validate token
if (!empty($token)) {
    try {
        // Check if token exists and is valid
        $stmt = $conn->prepare("
            SELECT prt.admin_id, prt.token, prt.expires_at, prt.used, a.admin_name, a.email_address
            FROM password_reset_tokens prt
            INNER JOIN admin a ON prt.admin_id = a.admin_id
            WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $tokenData = $result->fetch_assoc();
            $validToken = true;
            $adminId = $tokenData['admin_id'];
        } else {
            $error = 'Invalid or expired reset token. Please request a new password reset.';
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = 'Error validating token. Please try again.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updateStmt = $conn->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $adminId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Mark token as used
            $tokenStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $tokenStmt->bind_param("s", $token);
            $tokenStmt->execute();
            $tokenStmt->close();
            
            $success = 'Password reset successfully! You can now login with your new password.';
            $validToken = false; // Hide form after success
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Error resetting password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password | BCPDO</title>
    <link href="<?= getSecureImagePath('images/bcpdo.png') ?>" rel="icon" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 2.5rem;
            position: relative;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo-container img {
            width: 80px;
            height: auto;
        }
        
        h2 {
            text-align: center;
            color: #212529;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #3f37c9, #7209b7);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-to-login a {
            color: #4361ee;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .success-icon {
            text-align: center;
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo-container">
            <img src="<?= getSecureImagePath('images/bcpdo.png') ?>" alt="BCPDO Logo">
        </div>
        
        <?php if ($success): ?>
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Password Reset Successful!</h2>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
            <div class="back-to-login">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php elseif ($error && !$validToken): ?>
            <h2>Reset Password</h2>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <div class="back-to-login">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php elseif ($validToken): ?>
            <h2>Reset Your Password</h2>
            <p class="subtitle">Enter your new password below</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <input type="password" name="password" class="form-control" 
                           placeholder="New Password" required id="password" minlength="8">
                    <span class="password-toggle" onclick="togglePassword('password', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div class="form-group">
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Confirm New Password" required id="confirmPassword" minlength="8">
                    <span class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    Reset Password
                </button>
            </form>
            
            <div class="back-to-login">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php else: ?>
            <h2>Reset Password</h2>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Invalid or missing reset token.
            </div>
            <div class="back-to-login">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function togglePassword(inputId, toggle) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggle.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // Form validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Resetting...';
        });
    </script>
</body>
</html>

