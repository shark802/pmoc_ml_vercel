<?php
require_once '../includes/session.php';
require_once '../includes/csrf_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Security Error',
            'text' => 'Invalid security token. Please refresh the page and try again.'
        ];
        header("Location: admin.php");
        exit();
    }
    if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Unauthorized',
            'text' => 'Only Super Admin can add new admins.'
        ];
        header("Location: admin.php");
        exit();
    }
    $admin_name = trim($_POST['admin_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email_address']);
    $raw_password = $_POST['password'];
    $position = trim($_POST['position']); // Get position from form

    if (!preg_match('/^[a-zA-Z\s]+$/', $admin_name)) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Full name should only contain letters and spaces'
        ];
        header("Location: admin.php");
        exit();
    }

    if (strlen($admin_name) < 3) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Full name must be at least 3 characters'
        ];
        header("Location: admin.php");
        exit();
    }
    
    if (strlen($username) < 8) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Username must be at least 8 characters'
        ];
        header("Location: admin.php");
        exit();
    }
    
    if (strlen($raw_password) < 8) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Password must be at least 8 characters'
        ];
        header("Location: admin.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Invalid email format'
        ];
        header("Location: admin.php");
        exit();
    }
    
    // Validate position
    $allowed_positions = ['admin', 'counselor'];
    if (!in_array($position, $allowed_positions)) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Invalid position selected'
        ];
        header("Location: admin.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE admin_name = ?");
    $stmt->bind_param("s", $admin_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Full name already exists'
        ];
        $stmt->close();
        $conn->close();
        header("Location: admin.php");
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? OR email_address = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Username or email already exists'
        ];
        $stmt->close();
        $conn->close();
        header("Location: admin.php");
        exit();
    }
    $stmt->close();

    $password = password_hash($raw_password, PASSWORD_DEFAULT);

    $image = '../images/profiles/default.jpg';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../images/profiles/";
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false && move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO admin (admin_name, username, email_address, password, position, image, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("ssssss", $admin_name, $username, $email, $password, $position, $image);
    
    if ($stmt->execute()) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Success!',
            'text' => 'Admin added successfully'
        ];
    } else {
        // Log the actual error for debugging
        error_log("Failed to add admin in " . __FILE__ . " line " . __LINE__ . ": " . $conn->error);
        error_log("Admin data: name=" . $admin_name . ", username=" . $username . ", email=" . $email);
        
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Failed to add admin. Please try again or contact support.'
        ];
    }
    
    $stmt->close();
    $conn->close();
    header("Location: admin.php");
    exit();
}
?>