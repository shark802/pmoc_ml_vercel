<?php
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $admin_id = $_POST['admin_id'];
    $response = ['status' => 'error'];
    
    // Only superadmin can delete (though we will no longer call this endpoint from UI)
    if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }

    try {
        // Get admin info first
        $stmt = $conn->prepare("SELECT admin_name, image FROM admin WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'Admin not found';
            echo json_encode($response);
            exit();
        }
        
        $admin = $result->fetch_assoc();
        $stmt->close();
        
        // Delete the admin
        $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        
        if ($stmt->execute()) {
            // Delete the image if it's not the default
            if ($admin['image'] != '../images/profiles/default.jpg' && file_exists($admin['image'])) {
                unlink($admin['image']);
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Admin "' . htmlspecialchars($admin['admin_name']) . '" deleted successfully'
            ];
        } else {
            $response['message'] = 'Database error: ' . $conn->error;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    $conn->close();
    echo json_encode($response);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>