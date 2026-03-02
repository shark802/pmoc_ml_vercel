<?php
require_once '../includes/session.php';
require_once '../includes/image_helper.php';
require_once '../includes/audit_log.php';
date_default_timezone_set('Asia/Manila');

// Get admin info with position
try {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $_SESSION['admin_name'] = $admin['admin_name'];
    $_SESSION['image'] = $admin['image'] ?? '../images/profiles/default.jpg';
    $_SESSION['position'] = $admin['position'];
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit();
}

// Function to convert alphanumeric sequence to numeric for check digit calculation
function sequenceToNumeric($sequence) {
    // If sequence is numeric (001-999), return as-is
    if (preg_match('/^\d{3}$/', $sequence)) {
        return $sequence;
    }
    // If sequence is alphanumeric (A00-Z99), convert to numeric
    // Format: Letter (A-Z = 10-35) + 2 digits (00-99)
    if (preg_match('/^([A-Z])(\d{2})$/', $sequence, $matches)) {
        $letter = $matches[1];
        $digits = $matches[2];
        $letterValue = ord($letter) - 55; // A=10, B=11, ..., Z=35
        return str_pad($letterValue, 2, '0', STR_PAD_LEFT) . $digits;
    }
    return $sequence; // Fallback
}

// Function to generate check digit for access codes
function generateCheckDigit($date, $sequence) {
    $sum = 0;
    $weights = [3, 1, 3, 1, 3, 1, 3, 1, 3, 1, 3]; // Weights for 11 digits
    
    // Convert sequence to numeric format for calculation
    $numericSequence = sequenceToNumeric($sequence);
    
    // Combine date (8 digits) + sequence (3 digits) = 11 digits
    $input = $date . $numericSequence;
    
    // Convert input to array of digits
    $digits = str_split($input);
    
    // Apply weights and sum
    for ($i = 0; $i < count($digits) && $i < count($weights); $i++) {
        $sum += intval($digits[$i]) * $weights[$i];
    }
    
    // Generate check digit (A-Z, 0-9)
    $checkDigit = $sum % 36;
    if ($checkDigit < 10) {
        return strval($checkDigit);
    } else {
        return chr(65 + ($checkDigit - 10)); // A-Z
    }
}

// Function to generate sequence number (supports 001-999 numeric, then A00-Z99 alphanumeric)
function generateSequence($count) {
    $sequenceNum = $count + 1;
    
    // 001-999: numeric sequences
    if ($sequenceNum <= 999) {
        return str_pad($sequenceNum, 3, '0', STR_PAD_LEFT);
    }
    
    // 1000+: alphanumeric sequences (A00-Z99)
    // Calculate: (sequenceNum - 1000) gives us the offset
    // Each letter has 100 combinations (00-99)
    $offset = $sequenceNum - 1000;
    $letterIndex = intval($offset / 100); // 0-25 for A-Z
    $digitPart = $offset % 100; // 0-99
    
    // Convert to letter (A-Z)
    $letter = chr(65 + $letterIndex); // A=65, B=66, ..., Z=90
    
    // Format as Letter + 2 digits
    return $letter . str_pad($digitPart, 2, '0', STR_PAD_LEFT);
}

// Generate access code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_access_code'])) {
    // Get current date in YYYYMMDD format
    $currentDate = date('Ymd');
    
    // Get the next sequence number for today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM couple_access 
        WHERE access_code LIKE ? 
        AND date_created >= ?
    ");
    $todayPattern = 'BCPDO-' . $currentDate . '-%';
    $todayStart = date('Y-m-d 00:00:00');
    $stmt->bind_param("ss", $todayPattern, $todayStart);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    // Generate sequence number (supports 001-999 numeric, then A00-Z99 alphanumeric)
    $sequence = generateSequence($count);
    
    // Generate check digit (simple validation character)
    $checkDigit = generateCheckDigit($currentDate, $sequence);
    
    // Format: BCPDO + Date (YYYYMMDD) + Sequence (001-999 numeric, then A00-Z99 alphanumeric) + Check Digit
    $couple_access_code = 'BCPDO-' . $currentDate . '-' . $sequence . '-' . $checkDigit;
    $created_at = date('Y-m-d H:i:s');

    try {
        $stmt = $conn->prepare("INSERT INTO couple_access (access_code, code_status, date_created) VALUES (?, 'active', ?)");
        $stmt->bind_param("ss", $couple_access_code, $created_at);
        $stmt->execute();
        
        // Get access_id for logging
        $access_id = $conn->insert_id;

        // Log access code generation
        logAudit($conn, $_SESSION['admin_id'], AUDIT_CREATE, 
            'Access code generated: ' . $couple_access_code, 
            'access_codes', 
            ['access_code' => $couple_access_code, 'access_id' => $access_id]);

        $_SESSION['success_message'] = "New Couple Code generated successfully: " . $couple_access_code;
        $_SESSION['new_access_code'] = $couple_access_code;

        if (!isset($_SESSION['notifications'])) {
            $_SESSION['notifications'] = [];
        }
        array_unshift($_SESSION['notifications'], [
            'type' => 'new_code',
            'code' => $couple_access_code,
            'time' => $created_at
        ]);
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error generating code: " . $e->getMessage();
    }

    header("Location: access_codes.php");
    exit();
}

// Get access code statistics
$codeStats = [
    'total' => 0,
    'available' => 0,
    'used' => 0,
    'expired' => 0
];

try {
    // Get all codes with their registration counts - matching the exact logic from the table
    $stmt = $conn->prepare("
        SELECT 
            ca.access_id,
            ca.code_status,
            ca.date_created,
            COUNT(DISTINCT cp.couple_profile_id) AS reg_count
        FROM couple_access ca
        LEFT JOIN couple_profile cp ON ca.access_id = cp.access_id
        GROUP BY ca.access_id, ca.code_status, ca.date_created
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $codeStats['total'] = $result->num_rows;
    
    // Count by status - matching the exact logic from the table display
    while ($row = $result->fetch_assoc()) {
        $reg_count = (int)$row['reg_count'];
        $createdTs = strtotime($row['date_created']);
        $expired = (($createdTs + 12 * 3600) < time()) && $reg_count < 2;
        
        // Update expired status in database if needed
        if ($expired && $row['code_status'] === 'active') {
            $upd = $conn->prepare("UPDATE couple_access SET code_status = 'expired' WHERE access_id = ?");
            $upd->bind_param("i", $row['access_id']);
            $upd->execute();
            $row['code_status'] = 'expired';
        }
        
        // Count based on status - matching table logic exactly
        if ($reg_count === 2) {
            $codeStats['used']++;
        } elseif ($expired || $row['code_status'] === 'expired') {
            $codeStats['expired']++;
        } else {
            // Available: not expired and not fully used (includes pending with 1/2 registered)
            $codeStats['available']++;
        }
    }
} catch (Exception $e) {
    error_log("Access code statistics error: " . $e->getMessage());
}

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_code'])) {
        $code_id = $_POST['code_id'];
        try {
            $conn->begin_transaction();
            
            // Delete related records first to handle foreign key constraints
            // couple_sessions removed from system; no cleanup needed here
            
            // Delete couple responses
            $stmt = $conn->prepare("DELETE FROM couple_responses WHERE access_id = ?");
            $stmt->bind_param("i", $code_id);
            $stmt->execute();
            
            // Delete scheduling records
            $stmt = $conn->prepare("DELETE FROM scheduling WHERE access_id = ?");
            $stmt->bind_param("i", $code_id);
            $stmt->execute();
            
            // Delete couple profiles (this will also handle address cleanup if needed)
            $stmt = $conn->prepare("DELETE FROM couple_profile WHERE access_id = ?");
            $stmt->bind_param("i", $code_id);
            $stmt->execute();
            
            // Finally delete the access code
            $stmt = $conn->prepare("DELETE FROM couple_access WHERE access_id = ?");
            $stmt->bind_param("i", $code_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success_message'] = "Code and all related data deleted successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error deleting code: " . $e->getMessage();
        }
        header("Location: access_codes.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BCPDO Admin | Access Codes</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* KPI Cards Styles - Matching Admin Dashboard */
        .kpi-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -7.5px;
            margin-left: -7.5px;
            margin-bottom: 20px;
        }

        .kpi-row>[class*="col-"] {
            padding-right: 7.5px;
            padding-left: 7.5px;
            margin-bottom: 15px;
        }

        .kpi-card {
            flex: 1 1 0;
            min-width: 220px;
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            padding: 18px 20px;
            height: 120px;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        body.dark-mode .kpi-card { background: #343a40; }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.25);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.8rem;
            margin-right: 18px;
            color: #fff;
            transition: all 0.3s ease;
        }

        .kpi-card:hover .kpi-icon {
            transform: scale(1.1);
        }

        .kpi-total {
            background-color: #007bff;
        }

        .kpi-available {
            background-color: #28a745;
        }

        .kpi-used {
            background-color: #17a2b8;
        }

        .kpi-expired {
            background-color: #dc3545;
        }

        .kpi-info {
            flex: 1;
            position: relative;
            padding-right: 0;
        }

        .kpi-title {
            font-size: 1rem;
            color: #888;
            margin-bottom: 2px;
        }

        .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 8px;
        }
        body.dark-mode .kpi-value { color: #fff; }

        .kpi-info .small-box-footer {
            position: absolute;
            right: 0;
            bottom: 0;
            margin: 0;
        }

        @media (max-width: 768px) {
            .kpi-row {
                flex-direction: column;
            }

            .kpi-card {
                margin-bottom: 12px;
            }
        }









        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
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
                        <i class="fas fa-key text-primary"></i>
                        <h4 class="mb-0">Access Code Management</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Generate and manage couple access codes for registration</p>
                    
                    <!-- Access Code Statistics Cards -->
                    <div class="row kpi-row">
                        <!-- Total Codes Card -->
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <a href="#recent-codes" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-total">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Total Codes</div>
                                        <div class="kpi-value"><?= $codeStats['total'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#007bff;">
                                            View All <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Available Codes Card -->
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <a href="#recent-codes" onclick="document.getElementById('filter-available').click(); return false;" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-available">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Available</div>
                                        <div class="kpi-value"><?= $codeStats['available'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#28a745;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Used Codes Card -->
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <a href="#recent-codes" onclick="document.getElementById('filter-used').click(); return false;" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-used">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Used</div>
                                        <div class="kpi-value"><?= $codeStats['used'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#17a2b8;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Expired Codes Card -->
                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <a href="#recent-codes" onclick="document.getElementById('filter-expired').click(); return false;" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-expired">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Expired</div>
                                        <div class="kpi-value"><?= $codeStats['expired'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#dc3545;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['new_access_code'])): ?>
                        <div class="row justify-content-center mb-3">
                            <div class="col-lg-6 col-md-8 col-12">
                                <div class="card border-success text-center" style="border-width:2px; padding: 15px 0; animation: pulse 2s infinite;">
                                    <div class="card-body p-3">
                                        <span class="badge badge-success mb-2" style="font-size:1rem;"><i class="fas fa-check-circle mr-1"></i> Success</span>
                                        <div class="font-weight-bold text-success" style="font-size:1.5rem; letter-spacing:1px; margin: 10px 0;">
                                            <?= htmlspecialchars($_SESSION['new_access_code']) ?>
                                        </div>
                                        <button class="btn btn-outline-success btn-lg print-btn" data-id="<?= htmlspecialchars($_SESSION['new_access_code']) ?>">
                                            <i class="fas fa-print mr-1"></i> Print
                                        </button>
                                        <div class="mt-2 text-muted" style="font-size:1rem;">Share this code with both partners</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php unset($_SESSION['new_access_code']); ?>
                    <?php endif; ?>

                    <!-- Expiry Information -->
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Access Code Expiry:</strong> Codes expire 12 hours after generation if not fully used (2/2 registered).
                        Expired codes cannot be used for registration.
                    </div>

                    <!-- Access Codes Table (standardized DataTable) -->
                    <div class="row" id="recent-codes">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Access Codes</h3>
                                    <div class="card-tools" style="display: flex; gap: 10px; align-items: center;">
                                        <form method="POST" action="" class="d-inline-block" style="margin: 0;">
                                            <button type="submit" name="generate_access_code" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Generate New Code
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-primary active" data-filter="all" id="filter-all">
                                            <i class="fas fa-list"></i> All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="used" id="filter-used">
                                            <i class="fas fa-check-circle"></i> Used
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="available" id="filter-available">
                                            <i class="fas fa-circle"></i> Available
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="expired" id="filter-expired">
                                            <i class="fas fa-times-circle"></i> Expired
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="accessCodesTable" class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Access Code</th>
                                                <th>Status</th>
                                                <th>Date Created</th>
                                                <th>Registered</th>
                                                <th>Couple Number</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $conn->prepare("
                                                SELECT 
                                                    ca.access_id,
                                                    ca.access_code,
                                                    ca.code_status,
                                                    ca.date_created,
                                                    COUNT(DISTINCT cp.couple_profile_id) AS reg_count,
                                                    NULL as couple_number
                                                FROM couple_access ca
                                                LEFT JOIN couple_profile cp ON ca.access_id = cp.access_id
                                                -- Removed couple_official join as certificate_number is now the couple number
                                                GROUP BY ca.access_id
                                                ORDER BY ca.date_created DESC
                                            ");
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            while ($row = $result->fetch_assoc()):
                                                $reg_count = (int)$row['reg_count'];
                                                $createdTs = strtotime($row['date_created']);
                                                $expired = (($createdTs + 12 * 3600) < time()) && $reg_count < 2;
                                                if ($expired && $row['code_status'] === 'active') {
                                                    $upd = $conn->prepare("UPDATE couple_access SET code_status = 'expired' WHERE access_id = ?");
                                                    $upd->bind_param("i", $row['access_id']);
                                                    $upd->execute();
                                                    $row['code_status'] = 'expired';
                                                }
                                                $status = 'Available';
                                                $status_class = 'badge badge-secondary';
                                                $status_filter = 'available';
                                                if ($expired) { $status = 'Expired'; $status_class = 'badge badge-danger'; $status_filter = 'expired'; }
                                                if ($reg_count === 2) { $status = 'Used'; $status_class = 'badge badge-success'; $status_filter = 'used'; }
                                                if ($reg_count === 1 && !$expired) { $status = 'Pending'; $status_class = 'badge badge-warning'; $status_filter = 'available'; }
                                                $couple_number_display = !empty($row['couple_number']) ? '<span class="badge badge-success">' . $row['couple_number'] . '</span>' : '<span class="badge badge-secondary">Not Assigned</span>';
                                            ?>
                                                <tr data-status="<?= $status_filter ?>">
                                                    <td><?= htmlspecialchars($row['access_code']) ?></td>
                                                    <td><span class="<?= $status_class ?>"><?= $status ?></span></td>
                                                    <td>
                                                        <span style="display:none;"><?= htmlspecialchars($row['date_created']) ?></span>
                                                        <?= date('M d, Y h:i A', strtotime($row['date_created'])) ?>
                                                    </td>
                                                    <td><?= $reg_count ?> / 2</td>
                                                    <td><?= $couple_number_display ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btn-action print-btn" data-id="<?= htmlspecialchars($row['access_code']) ?>" title="Print Code">
                                                            <i class="fas fa-print"></i> Print
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </section>
        </div>
        <?php include '../modals/admin_modal.php'; ?>
        <?php include '../includes/footer.php'; ?>
        <?php include '../includes/scripts.php'; ?>
        
        <script>
            // Ensure jQuery is loaded before proceeding
            function waitForJQuery(callback) {
                if (typeof window.jQuery !== 'undefined') {
                    // Ensure $ alias is available
                    if (typeof window.$ === 'undefined') {
                        window.$ = window.jQuery;
                    }
                    callback();
                } else {
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }

            // Prevent $ from being used before jQuery loads
            if (typeof window.$ === 'undefined') {
                window.$ = function() {
                    if (typeof window.jQuery !== 'undefined') {
                        window.$ = window.jQuery;
                        return window.jQuery.apply(window.jQuery, arguments);
                    }
                    console.error('jQuery ($) used before jQuery is loaded');
                    return null;
                };
            }

            // Start waiting for jQuery
            waitForJQuery(function() {
                // Ensure $ is properly set
                if (typeof window.jQuery !== 'undefined') {
                    window.$ = window.jQuery;
                }
                
                $(document).ready(function() {
                <?php // success_message toast now emitted globally in includes/scripts.php ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: '<?= addslashes($_SESSION['error_message']) ?>',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                // Initialize DataTable
                var table = $('#accessCodesTable').DataTable({
                    "responsive": true,
                    "autoWidth": false,
                    "order": [[2, "desc"]],
                    "columnDefs": [
                        { "width": "90px", "targets": 5 } // Actions column
                    ]
                });

                // Tab filtering functionality
                var currentFilter = 'all';
                
                // Custom filter function
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {
                        if (settings.nTable.id !== 'accessCodesTable') {
                            return true;
                        }
                        if (currentFilter === 'all') {
                            return true;
                        }
                        var row = table.row(dataIndex).node();
                        var rowStatus = $(row).attr('data-status');
                        return rowStatus === currentFilter;
                    }
                );

                $('button[data-filter]').on('click', function(e) {
                    e.preventDefault();
                    
                    // Update active button
                    $('button[data-filter]').removeClass('btn-primary active').addClass('btn-outline-secondary');
                    $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
                    
                    // Update current filter
                    currentFilter = $(this).data('filter');
                    
                    // Redraw table with new filter
                    table.draw();
                });

                // Print functionality
                $('.print-btn').click(function() {
                    const code = $(this).data('id');
                    const button = $(this);

                    button.html('<i class="fas fa-spinner fa-spin mr-1"></i> Preparing...');
                    button.prop('disabled', true);

                    setTimeout(() => {
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`
                            <html>
                                <head>
                                    <title>Couple Access Code</title>
                                    <style>
                                        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                                        .code { font-size: 2.5rem; font-weight: bold; margin: 20px 0; letter-spacing: 2px; }
                                        .title { font-size: 1.8rem; margin-bottom: 15px; color: #007bff; }
                                        .instructions { font-size: 1rem; color: #666; max-width: 400px; margin: 0 auto; }
                                        .logo { margin-bottom: 20px; }
                                        .print-date { font-size: 0.9rem; color: #999; margin-top: 20px; }
                                    </style>
                                </head>
                                <body>
                                    <div class="logo">
                                        <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO Logo" style="height: 60px;">
                                    </div>
                                    <div class="title">Couple Access Code</div>
                                    <div class="code">${code}</div>
                                    <div class="instructions">
                                        Share this enhanced access code with both partners to register for the questionnaire.<br>
                                        Code will expire 12 hours after generation if not fully used.<br>
                                        <strong>Format:</strong> BCPDO-Date-Sequence-CheckDigit
                                    </div>
                                    <div class="print-date">
                                        Printed on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                                    </div>
                                </body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.print();

                        button.html('<i class="fas fa-print mr-1"></i> Print');
                        button.prop('disabled', false);

                        Swal.fire({
                            icon: 'success',
                            title: 'Print Dialog Opened',
                            text: 'Print dialog opened for code: ' + code,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }, 1000);
                });
            });
            }); // End of waitForJQuery
        </script>
    </div>
</body>

</html>
