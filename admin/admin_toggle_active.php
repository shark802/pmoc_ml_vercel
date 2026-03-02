<?php
// Admin toggle active status - Superadmin only
require_once '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$targetAdminId = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
if ($targetAdminId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing admin id']);
    exit();
}

// Prevent toggling own account
if ((int)$_SESSION['admin_id'] === $targetAdminId) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot change your own activation status.']);
    exit();
}

try {
    // Ensure is_active column exists
    $checkCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'is_active'");
    $checkCol->execute();
    $res = $checkCol->get_result()->fetch_assoc();
    if ((int)$res['cnt'] === 0) {
        $conn->query("ALTER TABLE admin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
    $checkCol->close();

    // Fetch target admin
    $stmt = $conn->prepare("SELECT admin_name, position, IFNULL(is_active,1) AS is_active FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $targetAdminId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
        exit();
    }
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (strtolower($admin['position']) === 'superadmin') {
        echo json_encode(['status' => 'error', 'message' => 'Cannot change status of Super Admin.']);
        exit();
    }

    $newActive = ((int)$admin['is_active'] === 1) ? 0 : 1;
    $update = $conn->prepare("UPDATE admin SET is_active = ? WHERE admin_id = ?");
    $update->bind_param("ii", $newActive, $targetAdminId);

    if ($update->execute()) {
        $verb = $newActive === 1 ? 'activated' : 'deactivated';
        echo json_encode(['status' => 'success', 'message' => 'Admin "' . htmlspecialchars($admin['admin_name']) . '" ' . $verb . ' successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
    $update->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}

exit();
?>


