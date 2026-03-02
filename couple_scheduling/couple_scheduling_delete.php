<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors instead
require '../includes/conn.php';
require '../includes/session.php';

// Only allow POST requests for security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_once '../includes/csrf_helper.php';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please refresh the page and try again.';
        header("Location: couple_scheduling.php");
        exit();
    }
    
    $schedule_id = $_POST['schedule_id'] ?? null;
    
    if (!$schedule_id) {
        $_SESSION['error_message'] = "No schedule ID provided.";
        header("Location: couple_scheduling.php");
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First get couple info for logging/reporting
        $stmt = $conn->prepare("
            SELECT s.session_date, s.session_type, 
                   GROUP_CONCAT(
                       CONCAT(cp.first_name, ' ', cp.last_name, ' (', TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE()), ')')
                       ORDER BY cp.sex DESC 
                       SEPARATOR ' & '
                   ) as couple_names
            FROM scheduling s
            JOIN couple_access ca ON s.access_id = ca.access_id
            JOIN couple_profile cp ON ca.access_id = cp.access_id
            WHERE s.schedule_id = ?
            GROUP BY s.schedule_id
        ");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $schedule = $stmt->get_result()->fetch_assoc();

        if (!$schedule) {
            throw new Exception("Schedule not found.");
        }

        // Delete the schedule
        $stmt = $conn->prepare("DELETE FROM scheduling WHERE schedule_id = ?");
        $stmt->bind_param("i", $schedule_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error deleting schedule: " . $conn->error);
        }

        $conn->commit();
        
        $_SESSION['success_message'] = sprintf(
            "Deleted schedule for %s on %s (%s)",
            htmlspecialchars($schedule['couple_names']),
            date('M d, Y', strtotime($schedule['session_date'])),
            $schedule['session_type']
        );
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: couple_scheduling.php");
    exit();
}

// If not POST request, show confirmation dialog
$schedule_id = $_GET['id'] ?? null;
if (!$schedule_id) {
    $_SESSION['error_message'] = "No schedule ID provided.";
    header("Location: couple_scheduling.php");
    exit();
}

// Get schedule details for confirmation
$stmt = $conn->prepare("
    SELECT s.session_date, s.session_type, 
           GROUP_CONCAT(
               CONCAT(cp.first_name, ' ', cp.last_name)
               ORDER BY cp.sex DESC 
               SEPARATOR ' & '
           ) as couple_names
    FROM scheduling s
    JOIN couple_access ca ON s.access_id = ca.access_id
    JOIN couple_profile cp ON ca.access_id = cp.access_id
    WHERE s.schedule_id = ?
    GROUP BY s.schedule_id
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

if (!$schedule) {
    $_SESSION['error_message'] = "Schedule not found.";
    header("Location: couple_scheduling.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Confirm Deletion</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Confirm Deletion</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Warning!</h5>
                            Are you sure you want to delete this schedule?
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Couple</th>
                                    <td><?= htmlspecialchars($schedule['couple_names']) ?></td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td><?= date('M d, Y', strtotime($schedule['session_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Session Type</th>
                                    <td><?= htmlspecialchars($schedule['session_type']) ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <form method="POST">
                            <?php require_once '../includes/csrf_helper.php'; ?>
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <input type="hidden" name="schedule_id" value="<?= $schedule_id ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Confirm Delete
                            </button>
                            <a href="couple_scheduling.php" class="btn btn-default">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>
<?php include '../includes/scripts.php'; ?>
</body>
</html>