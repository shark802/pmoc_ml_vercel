<?php
require_once '../includes/session.php';
require_once '../includes/conn.php';

// Only allow admin or superadmin
if (!in_array($_SESSION['position'] ?? '', ['admin','superadmin'])) {
    http_response_code(403);
    exit('Access denied');
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

// Get email notification details
$stmt = $conn->prepare("
    SELECT 
        n.notification_id as id,
        n.created_at,
        n.recipients,
        n.content as subject,
        n.notification_status as status,
        n.access_id,
        GROUP_CONCAT(DISTINCT CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name
    FROM notifications n
    LEFT JOIN couple_profile cp ON cp.access_id = n.access_id
    WHERE n.notification_id = ?
    GROUP BY n.notification_id
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$email = $result->fetch_assoc();

if (!$email) {
    http_response_code(404);
    exit('Email not found');
}

// Format status badge
$statusBadge = '';
switch($email['status']) {
    case 'sent':
        $statusBadge = '<span class="badge badge-success">Sent</span>';
        break;
    case 'failed':
        $statusBadge = '<span class="badge badge-danger">Failed</span>';
        break;
    case 'confirmed':
        $statusBadge = '<span class="badge badge-info">Confirmed</span>';
        break;
    default:
        $statusBadge = '<span class="badge badge-secondary">' . ucfirst($email['status']) . '</span>';
}
?>

<div class="modal fade" id="emailLogModal" tabindex="-1" role="dialog" aria-labelledby="emailLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope"></i> Email Log Details
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><strong>Email Information</strong></h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Sent Time:</strong></td>
                                <td><?= htmlspecialchars($email['created_at']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><?= $statusBadge ?></td>
                            </tr>
                            <tr>
                                <td><strong>Subject:</strong></td>
                                <td><?= htmlspecialchars($email['subject']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><strong>Recipient Information</strong></h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Couple:</strong></td>
                                <td><?= htmlspecialchars($email['couple_name'] ?: 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Recipients:</strong></td>
                                <td><?= htmlspecialchars($email['recipients'] ?: 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Access ID:</strong></td>
                                <td><?= htmlspecialchars($email['access_id'] ?: 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($email['status'] === 'sent'): ?>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Email sent successfully</strong> to the recipients listed above.
                </div>
                <?php elseif ($email['status'] === 'failed'): ?>
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Email sending failed.</strong> Please check the email configuration and recipient addresses.
                </div>
                <?php elseif ($email['status'] === 'confirmed'): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Email confirmed.</strong> The recipients have confirmed receipt of this email.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>
