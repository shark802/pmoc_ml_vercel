<?php
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_admin'])) {
    $admin_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT *, IFNULL(is_active, 1) AS is_active FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'admin' => $admin
        ]);
    } else {
        echo json_encode(['status' => 'error']);
    }

    $stmt->close();
}
?>
