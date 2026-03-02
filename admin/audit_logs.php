<?php
require_once '../includes/session.php';
require_once '../includes/audit_log.php';

// Only allow superadmin to view audit logs
if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
    header('Location: admin_dashboard.php?error=unauthorized');
    exit();
}

// Ensure audit_logs table exists before querying
ensureAuditLogsTable($conn);

// Get filter parameters
$filterAction = $_GET['action'] ?? 'all';
$filterModule = $_GET['module'] ?? 'all';
$filterUser = $_GET['user'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

// Action filter
if ($filterAction !== 'all') {
    $whereConditions[] = "al.action = ?";
    $params[] = $filterAction;
    $paramTypes .= 's';
}

// Module filter
if ($filterModule !== 'all') {
    $whereConditions[] = "al.module = ?";
    $params[] = $filterModule;
    $paramTypes .= 's';
}

// User filter
if ($filterUser !== 'all') {
    $whereConditions[] = "al.user_id = ?";
    $params[] = (int)$filterUser;
    $paramTypes .= 'i';
}

// Date filters
if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
    $paramTypes .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $dateTo;
    $paramTypes .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM audit_logs al $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Get audit logs with pagination limit
// Note: DataTables handles client-side pagination, but we limit records to prevent memory issues
// For better performance with large datasets, consider implementing server-side DataTables processing
$maxRecords = 10000; // Maximum records to load (prevents memory exhaustion)

// Build filter params separately
$filterParams = [];
$filterParamTypes = '';
if ($filterAction !== 'all') {
    $filterParams[] = $filterAction;
    $filterParamTypes .= 's';
}
if ($filterModule !== 'all') {
    $filterParams[] = $filterModule;
    $filterParamTypes .= 's';
}
if ($filterUser !== 'all') {
    $filterParams[] = (int)$filterUser;
    $filterParamTypes .= 'i';
}
if (!empty($dateFrom)) {
    $filterParams[] = $dateFrom;
    $filterParamTypes .= 's';
}
if (!empty($dateTo)) {
    $filterParams[] = $dateTo;
    $filterParamTypes .= 's';
}

$sql = "SELECT al.*, a.position 
        FROM audit_logs al 
        LEFT JOIN admin a ON al.user_id = a.admin_id 
        $whereClause 
        ORDER BY al.created_at DESC
        LIMIT ?";
$stmt = $conn->prepare($sql);
$filterParams[] = $maxRecords;
$filterParamTypes .= 'i';
if (!empty($filterParamTypes)) {
    $stmt->bind_param($filterParamTypes, ...$filterParams);
}
$stmt->execute();
$result = $stmt->get_result();
$auditLogs = [];
while ($row = $result->fetch_assoc()) {
    $auditLogs[] = $row;
}
$stmt->close();

// Get unique actions and modules for filters
$stmt = $conn->prepare("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$stmt->execute();
$actionsResult = $stmt->get_result();
$actions = [];
while ($row = $actionsResult->fetch_assoc()) {
    $actions[] = $row['action'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT DISTINCT module FROM audit_logs ORDER BY module");
$stmt->execute();
$modulesResult = $stmt->get_result();
$modules = [];
while ($row = $modulesResult->fetch_assoc()) {
    $modules[] = $row['module'];
}
$stmt->close();

// Get users for filter
$stmt = $conn->prepare("SELECT DISTINCT al.user_id, al.user_name, al.username 
                            FROM audit_logs al 
                            WHERE al.user_id IS NOT NULL 
                            ORDER BY al.user_name");
$stmt->execute();
$usersResult = $stmt->get_result();
$users = [];
while ($row = $usersResult->fetch_assoc()) {
    $users[$row['user_id']] = $row['user_name'] . ' (' . $row['username'] . ')';
}
$stmt->close();

// Action badge colors
function getActionBadgeColor($action) {
    $colors = [
        'login' => 'success',
        'logout' => 'secondary',
        'create' => 'primary',
        'update' => 'info',
        'delete' => 'danger',
        'view' => 'secondary',
        'export' => 'warning',
        'backup' => 'primary',
        'restore' => 'warning',
        'access_denied' => 'danger',
        'password_change' => 'info',
        'settings_change' => 'info'
    ];
    return $colors[$action] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs | Admin Panel</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .action-badge {
            text-transform: capitalize;
        }
        .details-content {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
        }
        .details-content .detail-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .details-content .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .details-content .detail-label {
            font-weight: 600;
            color: #495057;
            display: inline-block;
            min-width: 120px;
        }
        .details-content .detail-value {
            color: #212529;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-flex align-items-center mb-4" style="gap:10px;">
                    <i class="fas fa-clipboard-list text-primary"></i>
                    <h4 class="mb-0">Audit Logs</h4>
                </div>
                <p class="text-muted" style="margin-top:-6px;">Track all system activities and user actions</p>

                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-filter mr-2"></i>Filters
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" id="filterForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Action</label>
                                        <select class="form-control" name="action" id="filterAction">
                                            <option value="all" <?php echo $filterAction === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                            <?php foreach ($actions as $action): ?>
                                            <option value="<?php echo htmlspecialchars($action); ?>" 
                                                    <?php echo $filterAction === $action ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Module</label>
                                        <select class="form-control" name="module" id="filterModule">
                                            <option value="all" <?php echo $filterModule === 'all' ? 'selected' : ''; ?>>All Modules</option>
                                            <?php foreach ($modules as $module): ?>
                                            <option value="<?php echo htmlspecialchars($module); ?>" 
                                                    <?php echo $filterModule === $module ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($module); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>User</label>
                                        <select class="form-control" name="user" id="filterUser">
                                            <option value="all" <?php echo $filterUser === 'all' ? 'selected' : ''; ?>>All Users</option>
                                            <?php foreach ($users as $userId => $userName): ?>
                                            <option value="<?php echo $userId; ?>" 
                                                    <?php echo $filterUser == $userId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($userName); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date From</label>
                                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date To</label>
                                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search mr-1"></i>Filter
                                            </button>
                                            <a href="audit_logs.php" class="btn btn-secondary">
                                                <i class="fas fa-redo mr-1"></i>Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Audit Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-list mr-2"></i>Audit Logs
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($auditLogs)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>No audit logs found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="auditLogsTable" class="table table-bordered table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Module</th>
                                            <th>Description</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                    <?php if ($log['username']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($log['position']): ?>
                                                        <br><span class="badge badge-secondary"><?php echo htmlspecialchars($log['position']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo getActionBadgeColor($log['action']); ?> action-badge">
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['module'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($log['details']): ?>
                                                    <?php
                                                    $details = json_decode($log['details'], true);
                                                    if ($details && is_array($details) && !empty($details)):
                                                    ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-toggle="modal" 
                                                            data-target="#detailsModal"
                                                            data-details='<?php echo htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)); ?>'
                                                            data-action="<?php echo htmlspecialchars($log['action']); ?>"
                                                            data-module="<?php echo htmlspecialchars($log['module'] ?? 'N/A'); ?>">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="detailsModalLabel">
                    <i class="fas fa-info-circle mr-2"></i>Action Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Action:</strong> <span id="modalAction" class="badge badge-primary"></span>
                </div>
                <div class="mb-3">
                    <strong>Module:</strong> <span id="modalModule" class="badge badge-secondary"></span>
                </div>
                <hr>
                <h6 class="mb-3"><strong>Additional Information:</strong></h6>
                <div id="detailsContent" class="details-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
<script>
$(document).ready(function() {
    // Initialize DataTable
    <?php if (!empty($auditLogs)): ?>
    $('#auditLogsTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[0, 'desc']], // Sort by date (newest first)
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        searching: true,
        columnDefs: [
            { "width": "150px", "targets": 0 }, // Date column
            { "width": "150px", "targets": 1 }, // User column
            { "width": "100px", "targets": 2 }, // Action column
            { "width": "120px", "targets": 3 }, // Module column
            { "width": "80px", "targets": 4 }  // Details column
        ]
    });
    <?php endif; ?>

    // Details modal - user-friendly display
    $('#detailsModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const details = button.data('details');
        const action = button.data('action');
        const module = button.data('module');
        const modal = $(this);
        
        // Set action and module
        modal.find('#modalAction').text(action ? action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A');
        modal.find('#modalModule').text(module || 'N/A');
        
        // Format details in a user-friendly way
        let detailsHtml = '';
        if (details && typeof details === 'object') {
            const formatValue = (value) => {
                if (value === null || value === undefined) return '<span class="text-muted">N/A</span>';
                if (typeof value === 'boolean') return value ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>';
                if (typeof value === 'object') return '<code>' + JSON.stringify(value, null, 2) + '</code>';
                return String(value);
            };
            
            for (const [key, value] of Object.entries(details)) {
                const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                detailsHtml += `
                    <div class="detail-item">
                        <span class="detail-label">${label}:</span>
                        <span class="detail-value">${formatValue(value)}</span>
                    </div>
                `;
            }
        } else {
            detailsHtml = '<p class="text-muted mb-0">No additional details available.</p>';
        }
        
        modal.find('#detailsContent').html(detailsHtml || '<p class="text-muted mb-0">No additional details available.</p>');
    });
});
</script>
</body>
</html>

