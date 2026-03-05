<?php
// Set JSON header first to prevent any HTML output
header('Content-Type: application/json');

// Start output buffering to catch any unwanted output
ob_start();

// Check session before including session.php (which might redirect)
session_start();
if (!isset($_SESSION['admin_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit();
}

// Now include required files
require_once '../includes/conn.php';
require_once '../includes/audit_log.php';
require_once '../includes/csrf_helper.php';

// Verify admin is active (basic check without full session.php)
try {
    $checkStmt = $conn->prepare("SELECT is_active FROM admin WHERE admin_id = ?");
    $checkStmt->bind_param("i", $_SESSION['admin_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 1) {
        $adminCheck = $checkResult->fetch_assoc();
        if ((int)($adminCheck['is_active'] ?? 1) === 0) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Your account is deactivated. Please contact the administrator.']);
            exit();
        }
    }
    $checkStmt->close();
} catch (Exception $e) {
    // Continue if check fails
}

// Clear any output that might have been generated
ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit();
    }
    $response = ['status' => 'error'];
    $admin_id = (int)$_POST['admin_id'];
    $admin_name = trim($_POST['admin_name']);
    $username = trim($_POST['username']);
    $email = filter_var(trim($_POST['email_address']), FILTER_SANITIZE_EMAIL);
    $position = trim($_POST['position']);
    
    if (!preg_match('/^[a-zA-ZáéíóúñäëïöüàèìòùÁÉÍÓÚÑÄËÏÖÜÀÈÌÒÙ\s\-\']+$/', $admin_name)) {
        $response['message'] = 'Full name should only contain letters, spaces, hyphens, or apostrophes';
        echo json_encode($response);
        exit();
    }

    if (strlen($admin_name) < 2) {
        $response['message'] = 'Full name must be at least 2 characters';
        echo json_encode($response);
        exit();
    }
    
    if (strlen($username) < 8) {
        $response['message'] = 'Username must be at least 8 characters';
        echo json_encode($response);
        exit();
    }
    
    if (!empty($_POST['password']) && strlen($_POST['password']) < 8) {
        $response['message'] = 'Password must be at least 8 characters if provided';
        echo json_encode($response);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE admin_name = ? AND admin_id != ?");
    $stmt->bind_param("si", $admin_name, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['message'] = 'Full name already exists';
        echo json_encode($response);
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE (username = ? OR email_address = ?) AND admin_id != ?");
    $stmt->bind_param("ssi", $username, $email, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['message'] = 'Username or email already exists';
        echo json_encode($response);
        exit();
    }
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT image, IFNULL(is_active, 1) AS is_active FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_data = $result->fetch_assoc();
    $stmt->close();
    
    $image = $current_data['image'] ?? '../images/profiles/default.jpg';
    
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../images/profiles/";
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            if ($image !== '../images/profiles/default.jpg' && file_exists($image)) {
                unlink($image);
            }
            $image = $target_file;
        }
    }
    
    // Check if user is updating their own profile
    $isUpdatingSelf = (int)$_SESSION['admin_id'] === (int)$admin_id;
    
    $sql = "UPDATE admin SET admin_name = ?, username = ?, email_address = ?, image = ?";
    $params = [$admin_name, $username, $email, $image];
    $types = "ssss";

    // Only superadmin can change positions of OTHER admins (not their own)
    // When updating own profile, position is read-only and should not be updated
    if (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin' && !$isUpdatingSelf) {
        // Validate position when editing other admins
        $allowed_positions = ['admin', 'counselor'];
        if (!in_array($position, $allowed_positions)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid position selected']);
            exit();
        }
        
        // If trying to set to superadmin, ensure only one exists (self)
        if (strtolower($position) === 'superadmin') {
            // Check if any other superadmin exists
            $check = $conn->prepare("SELECT COUNT(*) as cnt FROM admin WHERE LOWER(position) = 'superadmin' AND admin_id != ?");
            $check->bind_param("i", $admin_id);
            $check->execute();
            $cnt = $check->get_result()->fetch_assoc()['cnt'] ?? 0;
            $check->close();
            if ((int)$cnt > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Only one Super Admin is allowed.']);
                exit();
            }
        }
        // Include position in update when editing others
        $sql = "UPDATE admin SET admin_name = ?, username = ?, email_address = ?, position = ?, image = ?";
        $params = [$admin_name, $username, $email, $position, $image];
        $types = "sssss";
    }
    // When updating own profile, position is NOT included in the UPDATE query (preserved as-is)
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password = ?";
        $params[] = $password;
        $types .= "s";
    }
    
    $sql .= " WHERE admin_id = ?";
    $params[] = $admin_id;
    $types .= "i";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Refresh session values so navbar and other includes reflect latest data immediately
            if ($isUpdatingSelf) {
                $_SESSION['admin_name'] = $admin_name;
                $_SESSION['image'] = $image;
                // Position is preserved (not updated when editing own profile)
            }

            // Log admin update
            $actionType = !empty($_POST['password']) ? 'Admin updated (including password change)' : 'Admin updated';
            $details = [
                'target_admin_id' => $admin_id,
                'target_admin_name' => $admin_name,
                'target_username' => $username,
                'position_changed' => isset($position) && isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin' && !$isUpdatingSelf,
                'password_changed' => !empty($_POST['password'])
            ];
            logAudit($conn, $_SESSION['admin_id'], AUDIT_UPDATE, $actionType, 'admin', $details);

            $response = [
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'new_image' => $image
            ];
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Admin edit error: " . $e->getMessage());
        $response = [
            'status' => 'error',
            'message' => 'Failed to update profile: ' . $e->getMessage()
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit();
?>