<?php
require_once '../includes/session.php';
require_once '../includes/conn.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get certificate statistics
$certificateStats = [
    'pending' => 0,
    'orientation' => 0,
    'counseling' => 0,
    'total' => 0
];

try {
    // Get pending certificates count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM certificates WHERE status = 'pending'");
    $stmt->execute();
    $certificateStats['pending'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get orientation certificates count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM certificates 
        WHERE certificate_number COLLATE utf8mb4_general_ci NOT REGEXP '^[0-9]{4}-[0-9]+$'
    ");
    $stmt->execute();
    $certificateStats['orientation'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get counseling certificates count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM certificates 
        WHERE certificate_number COLLATE utf8mb4_general_ci REGEXP '^[0-9]{4}-[0-9]+$'
    ");
    $stmt->execute();
    $certificateStats['counseling'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get total certificates count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM certificates");
    $stmt->execute();
    $certificateStats['total'] = $stmt->get_result()->fetch_assoc()['total'];
} catch (Exception $e) {
    error_log("Error fetching certificate statistics: " . $e->getMessage());
}

// Get certificates with couple information
$certificates = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.certificate_number,
            c.access_id,
            -- Removed couple_id as it's no longer needed
            c.issue_date,
            c.status,
            c.admin_id,
            c.certificate_number as couple_number,
            -- Determine certificate type based on number pattern
            CASE 
                WHEN c.certificate_number COLLATE utf8mb4_general_ci REGEXP '^[0-9]{4}-[0-9]+$' THEN 'counseling'  -- YYYY-N format (e.g., 2025-1)
                ELSE 'orientation'  -- YYYY-MM-NNN format (e.g., 2025-10-001)
            END AS certificate_type,
            CONCAT(mp.first_name, ' ', mp.last_name) as male_name,
            CONCAT(fp.first_name, ' ', fp.last_name) as female_name,
            a.admin_name as issued_by
        FROM certificates c
        LEFT JOIN couple_access ca ON c.access_id = ca.access_id
        -- Removed couple_official join as certificate_number is now the couple number
        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'
        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'
        LEFT JOIN admin a ON c.admin_id = a.admin_id
        ORDER BY c.issue_date DESC
    ");
    $stmt->execute();
    $certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching certificates: " . $e->getMessage();
}

// Get couples eligible for certificates
$eligibleCouples = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ca.access_id,
            ca.access_code,
            CONCAT(mp.first_name, ' ', mp.last_name) AS male_name,
            CONCAT(fp.first_name, ' ', fp.last_name) AS female_name,
            MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) AS min_age,
            ca.male_profile_submitted, ca.female_profile_submitted,
            ca.male_questionnaire_submitted, ca.female_questionnaire_submitted,
            -- Get the actual session type from scheduling table
            (SELECT s.session_type FROM scheduling s WHERE s.access_id = ca.access_id ORDER BY s.session_date DESC LIMIT 1) AS session_type,
            (SELECT COUNT(DISTINCT al.partner_type)
               FROM attendance_logs al
               JOIN scheduling s ON al.schedule_id = s.schedule_id
              WHERE s.access_id = ca.access_id 
                AND al.segment = 'orientation' 
                AND al.status = 'present') AS orientation_present,
            (SELECT COUNT(DISTINCT al.partner_type)
               FROM attendance_logs al
               JOIN scheduling s ON al.schedule_id = s.schedule_id
              WHERE s.access_id = ca.access_id 
                AND al.segment = 'counseling' 
                AND al.status = 'present') AS counseling_present,
            CASE 
              WHEN ca.male_profile_submitted = 1 AND ca.female_profile_submitted = 1
                   AND ca.male_questionnaire_submitted = 1 AND ca.female_questionnaire_submitted = 1
                   AND (\n                        (MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) <= 25 \n                         AND (SELECT COUNT(DISTINCT al1.partner_type) FROM attendance_logs al1 JOIN scheduling s1 ON al1.schedule_id = s1.schedule_id WHERE s1.access_id = ca.access_id AND al1.segment='orientation' AND al1.status='present') = 2\n                         AND (SELECT COUNT(DISTINCT al2.partner_type) FROM attendance_logs al2 JOIN scheduling s2 ON al2.schedule_id = s2.schedule_id WHERE s2.access_id = ca.access_id AND al2.segment='counseling' AND al2.status='present') = 2)\n                        OR\n                        (MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) > 25 \n                         AND (SELECT COUNT(DISTINCT al3.partner_type) FROM attendance_logs al3 JOIN scheduling s3 ON al3.schedule_id = s3.schedule_id WHERE s3.access_id = ca.access_id AND al3.segment='orientation' AND al3.status='present') = 2)\n                   )\n              THEN 'Eligible'\n              ELSE 'Incomplete'\n            END AS eligibility\n        FROM couple_access ca\n        JOIN couple_profile cp ON ca.access_id = cp.access_id\n        LEFT JOIN couple_profile mp ON ca.access_id = mp.access_id AND mp.sex = 'Male'\n        LEFT JOIN couple_profile fp ON ca.access_id = fp.access_id AND fp.sex = 'Female'\n        WHERE ca.code_status = 'used'\n          AND EXISTS (SELECT 1 FROM scheduling sch WHERE sch.access_id = ca.access_id)\n          AND NOT EXISTS (SELECT 1 FROM certificates WHERE access_id = ca.access_id)\n        GROUP BY ca.access_id, ca.access_code, male_name, female_name, ca.male_profile_submitted, ca.female_profile_submitted, ca.male_questionnaire_submitted, ca.female_questionnaire_submitted\n        ORDER BY ca.date_created DESC\n    ");
    $stmt->execute();
    $eligibleCouples = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching eligible couples: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Management | BCPDO System</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* Certificate-specific styles only */
        .certificate-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-left: 4px solid #28a745;
        }
        
        .certificate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Quick Action Button Styles */
        .quick-action-btn {
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        
        .quick-action-btn i {
            margin-right: 6px;
        }
        
        .status-active { 
            border-left-color: #28a745; 
        }
        
        .status-revoked { 
            border-left-color: #dc3545; 
        }
        
        .status-pending { 
            border-left-color: #ffc107; 
        }
        
        .qr-code {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        
        /* Certificate Stats Cards */
        .cert-stats-row { 
            display: flex; 
            flex-wrap: wrap; 
            margin-right: -7.5px; 
            margin-left: -7.5px; 
            margin-bottom: 20px; 
        }
        .cert-stats-row > [class*="col-"] { 
            padding-right: 7.5px; 
            padding-left: 7.5px; 
            margin-bottom: 15px; 
        }
        .cert-stat-card { 
            flex: 1 1 0; 
            min-width: 220px; 
            display: flex; 
            align-items: center; 
            background: #fff; 
            border-radius: 10px; 
            box-shadow: 0 6px 20px rgba(0,0,0,.15); 
            padding: 18px 20px; 
            height: 120px; 
            position: relative; 
            transition: all .3s ease; 
            cursor: pointer; 
        }
        body.dark-mode .cert-stat-card { 
            background: #343a40; 
        }
        .cert-stat-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(0,123,255,.25); 
        }
        .cert-stat-icon { 
            width: 48px; 
            height: 48px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
            font-size: 1.8rem; 
            margin-right: 18px; 
            color: #fff; 
            transition: all .3s ease; 
        }
        .cert-stat-card:hover .cert-stat-icon { 
            transform: scale(1.1); 
        }
        .cert-stat-pending { 
            background-color: #ffc107; 
        }
        .cert-stat-orientation { 
            background-color: #17a2b8; 
        }
        .cert-stat-counseling { 
            background-color: #ff9800; 
        }
        .cert-stat-info { 
            flex: 1; 
            position: relative; 
            padding-right: 0; 
        }
        .cert-stat-title { 
            font-size: 1rem; 
            color: #888; 
            margin-bottom: 2px; 
        }
        .cert-stat-value { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: #222; 
            margin-bottom: 8px; 
        }
        body.dark-mode .cert-stat-value { 
            color: #fff; 
        }
        @media (max-width: 768px) { 
            .cert-stats-row { 
                flex-direction: column; 
            } 
            .cert-stat-card { 
                margin-bottom: 12px; 
            } 
        }
        /* Standardize actions column like other tables */
        #orientationTable th:last-child,
        #orientationTable td:last-child,
        #counselingTable th:last-child,
        #counselingTable td:last-child,
        #allCertificatesTable th:last-child,
        #allCertificatesTable td:last-child { 
            text-align: center; 
            white-space: nowrap; 
        }
        #orientationTable .btn-action,
        #counselingTable .btn-action,
        #allCertificatesTable .btn-action { 
            margin-right: 2px; 
        }
        #orientationTable .btn-action:last-child,
        #counselingTable .btn-action:last-child,
        #allCertificatesTable .btn-action:last-child { 
            margin-right: 0; 
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .certificate-card {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-flex align-items-center mb-4" style="gap:10px;">
                    <i class="fas fa-certificate text-primary"></i>
                    <h4 class="mb-0">Certificate Management</h4>
                </div>
                <p class="text-muted" style="margin-top:-6px;">Generate, manage, and track marriage certificates</p>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <?php include '../includes/messages.php'; ?>

                <!-- Certificate Statistics Cards -->
                <div class="row cert-stats-row">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="cert-stat-card">
                            <div class="cert-stat-icon cert-stat-pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="cert-stat-info">
                                <div class="cert-stat-title">Pending Certificates</div>
                                <div class="cert-stat-value" id="pendingCertificates"><?= $certificateStats['pending'] ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="cert-stat-card">
                            <div class="cert-stat-icon cert-stat-orientation">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="cert-stat-info">
                                <div class="cert-stat-title">Orientation Certificates</div>
                                <div class="cert-stat-value" id="orientationCertificates"><?= $certificateStats['orientation'] ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="cert-stat-card">
                            <div class="cert-stat-icon cert-stat-counseling">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="cert-stat-info">
                                <div class="cert-stat-title">Counseling Certificates</div>
                                <div class="cert-stat-value" id="counselingCertificates"><?= $certificateStats['counseling'] ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="cert-stat-card">
                            <div class="cert-stat-icon" style="background-color: #28a745;">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <div class="cert-stat-info">
                                <div class="cert-stat-title">Total Certificates</div>
                                <div class="cert-stat-value" id="totalCertificates"><?= $certificateStats['total'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-3">
                    <div class="col-md-12 text-right" style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-primary quick-action-btn" id="viewComplianceTemplateBtn" title="View Certificate of Compliance">
                            <i class="fas fa-file-alt"></i> View Certificate of Compliance
                        </button>
                        <button type="button" class="btn btn-info quick-action-btn" id="viewCounselingTemplateBtn" title="View Certificate of Marriage Counseling">
                            <i class="fas fa-heart"></i> View Certificate of Marriage Counseling
                        </button>
                    </div>
                </div>

                <!-- Eligible Couples Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-users mr-2"></i>Couples Eligible for Certificates
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($eligibleCouples)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No eligible couples found</h5>
                                        <p class="text-muted">All couples who have completed their requirements already have certificates.</p>
                                    </div>
                                <?php else: ?>
                                        <table id="eligibleTable" class="table table-bordered table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Couple Names</th>
                                                    <th>Session Type</th>
                                                    <th>Eligibility</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($eligibleCouples as $couple): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($couple['male_name']) ?></strong> & 
                                                            <strong><?= htmlspecialchars($couple['female_name']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $sessionType = $couple['session_type'] ?? 'Unknown';
                                                                $badgeClass = 'badge-info';
                                                                if (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') !== false) {
                                                                    $badgeClass = 'badge-warning';
                                                                } elseif (strpos($sessionType, 'Counseling') !== false) {
                                                                    $badgeClass = 'badge-warning';
                                                                } else {
                                                                    $badgeClass = 'badge-info';
                                                                }
                                                            ?>
                                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sessionType) ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($couple['eligibility'] === 'Eligible'): ?>
                                                                <span class="badge badge-success">Ready for Certificate</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-warning">Requirements Incomplete</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($couple['eligibility'] === 'Eligible'): ?>
                                                                <button class="btn btn-success btn-sm generate-certificate" 
                                                                        data-access-id="<?= $couple['access_id'] ?>"
                                                                        data-access-code="<?= htmlspecialchars($couple['access_code']) ?>"
                                                                        data-couple-names="<?= htmlspecialchars($couple['male_name'] . ' & ' . $couple['female_name']) ?>">
                                                                    <i class="fas fa-certificate mr-1"></i>Generate Certificate
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-secondary btn-sm" disabled>
                                                                    <i class="fas fa-clock mr-1"></i>Waiting for Completion
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issued Certificates Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-certificate mr-2"></i>Issued Certificates
                                </h3>
                                
                                <!-- Certificate Type Filter Buttons -->
                                <div class="card-tools" style="display: flex; gap: 10px; align-items: center;">
                                    <button type="button" class="btn btn-sm btn-primary active" data-tab="orientation-tab" id="orientationFilter">
                                        <i class="fas fa-graduation-cap"></i> Orientation Type
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="counseling-tab" id="counselingFilter">
                                        <i class="fas fa-heart"></i> Counseling Type
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="all-certificates-tab" id="allFilter">
                                        <i class="fas fa-list"></i> All
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($certificates)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No certificates issued yet</h5>
                                        <p class="text-muted">Certificates will appear here once generated for eligible couples.</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Tab Content -->
                                    <div class="tab-content">
                                        <!-- Orientation Type Tab -->
                                        <div class="tab-pane fade show active" id="orientation-tab">
                                            <div class="table-responsive">
                                            <table id="orientationTable" class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Couple Names</th>
                                                    <th>Certificate Number</th>
                                                    <th>Certificate Type</th>
                                                    <th>Issue Date</th>
                                                    <th>Issued By</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($certificates as $cert): ?>
                                                    <?php if ($cert['certificate_type'] === 'orientation'): ?>
                                                    <tr class="certificate-card status-<?= strtolower($cert['status']) ?>">
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['male_name']) ?></strong> & 
                                                            <strong><?= htmlspecialchars($cert['female_name']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['certificate_number']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $certTypeClass = 'badge-secondary';
                                                            if ($cert['certificate_type'] === 'counseling') {
                                                                $certTypeClass = 'badge-warning'; // Yellow for counseling
                                                            } elseif ($cert['certificate_type'] === 'orientation') {
                                                                $certTypeClass = 'badge-info'; // Blue for orientation
                                                            }
                                                            ?>
                                                            <span class="badge <?= $certTypeClass ?> text-capitalize"><?= htmlspecialchars($cert['certificate_type']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?= date('M d, Y', strtotime($cert['issue_date'])) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($cert['issued_by'] ?? 'System') ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'badge-secondary';
                                                            if ($cert['status'] === 'active' || $cert['status'] === 'issued') $statusClass = 'badge-success';
                                                            elseif ($cert['status'] === 'revoked') $statusClass = 'badge-danger';
                                                            elseif ($cert['status'] === 'pending') $statusClass = 'badge-warning';
                                                            ?>
                                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($cert['status']) ?></span>
                                                        </td>
                                                        <td style="white-space: nowrap;">
                                                            <button class="btn btn-sm btn-outline-primary btn-action view-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success btn-action print-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-print mr-1"></i>Print
                                                            </button>
                                                            <?php if ($cert['status'] === 'active' || $cert['status'] === 'issued'): ?>
                                                                <button class="btn btn-sm btn-outline-danger btn-action revoke-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-ban mr-1"></i>Revoke
                                                                </button>
                                                            <?php elseif ($cert['status'] === 'revoked'): ?>
                                                                <button class="btn btn-sm btn-outline-success btn-action activate-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-check-circle mr-1"></i>Activate
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                        </div>

                                        <!-- Counseling Type Tab -->
                                        <div class="tab-pane fade" id="counseling-tab">
                                            <div class="table-responsive">
                                            <table id="counselingTable" class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Couple Names</th>
                                                    <th>Certificate Number</th>
                                                    <th>Certificate Type</th>
                                                    <th>Issue Date</th>
                                                    <th>Issued By</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($certificates as $cert): ?>
                                                    <?php if ($cert['certificate_type'] === 'counseling'): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['male_name']) ?></strong> & 
                                                            <strong><?= htmlspecialchars($cert['female_name']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['certificate_number']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $certTypeClass = 'badge-secondary';
                                                            if ($cert['certificate_type'] === 'counseling') {
                                                                $certTypeClass = 'badge-warning'; // Yellow for counseling
                                                            } elseif ($cert['certificate_type'] === 'orientation') {
                                                                $certTypeClass = 'badge-info'; // Blue for orientation
                                                            }
                                                            ?>
                                                            <span class="badge <?= $certTypeClass ?> text-capitalize"><?= htmlspecialchars($cert['certificate_type']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?= date('M d, Y', strtotime($cert['issue_date'])) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($cert['issued_by'] ?? 'System') ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'badge-secondary';
                                                            if ($cert['status'] === 'active' || $cert['status'] === 'issued') $statusClass = 'badge-success';
                                                            elseif ($cert['status'] === 'revoked') $statusClass = 'badge-danger';
                                                            elseif ($cert['status'] === 'pending') $statusClass = 'badge-warning';
                                                            ?>
                                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($cert['status']) ?></span>
                                                        </td>
                                                        <td style="white-space: nowrap;">
                                                            <button class="btn btn-sm btn-outline-primary btn-action view-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success btn-action print-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-print mr-1"></i>Print
                                                            </button>
                                                            <?php if ($cert['status'] === 'active' || $cert['status'] === 'issued'): ?>
                                                                <button class="btn btn-sm btn-outline-danger btn-action revoke-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-ban mr-1"></i>Revoke
                                                                </button>
                                                            <?php elseif ($cert['status'] === 'revoked'): ?>
                                                                <button class="btn btn-sm btn-outline-success btn-action activate-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-check-circle mr-1"></i>Activate
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                        </div>

                                        <!-- All Certificates Tab -->
                                        <div class="tab-pane fade" id="all-certificates-tab">
                                            <div class="table-responsive">
                                            <table id="allCertificatesTable" class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Couple Names</th>
                                                    <th>Certificate Number</th>
                                                    <th>Certificate Type</th>
                                                    <th>Issue Date</th>
                                                    <th>Issued By</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($certificates as $cert): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['male_name']) ?></strong> & 
                                                            <strong><?= htmlspecialchars($cert['female_name']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($cert['certificate_number']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $certTypeClass = 'badge-secondary';
                                                            if ($cert['certificate_type'] === 'counseling') {
                                                                $certTypeClass = 'badge-warning'; // Yellow for counseling
                                                            } elseif ($cert['certificate_type'] === 'orientation') {
                                                                $certTypeClass = 'badge-info'; // Blue for orientation
                                                            }
                                                            ?>
                                                            <span class="badge <?= $certTypeClass ?> text-capitalize"><?= htmlspecialchars($cert['certificate_type']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?= date('M d, Y', strtotime($cert['issue_date'])) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($cert['issued_by'] ?? 'System') ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = 'badge-secondary';
                                                            if ($cert['status'] === 'active' || $cert['status'] === 'issued') $statusClass = 'badge-success';
                                                            elseif ($cert['status'] === 'revoked') $statusClass = 'badge-danger';
                                                            elseif ($cert['status'] === 'pending') $statusClass = 'badge-warning';
                                                            ?>
                                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($cert['status']) ?></span>
                                                        </td>
                                                        <td style="white-space: nowrap;">
                                                            <button class="btn btn-sm btn-outline-primary btn-action view-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success btn-action print-certificate" 
                                                                    data-id="<?= $cert['certificate_id'] ?>" style="margin-right: 2px;">
                                                                <i class="fas fa-print mr-1"></i>Print
                                                            </button>
                                                            <?php if ($cert['status'] === 'active' || $cert['status'] === 'issued'): ?>
                                                                <button class="btn btn-sm btn-outline-danger btn-action revoke-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-ban mr-1"></i>Revoke
                                                                </button>
                                                            <?php elseif ($cert['status'] === 'revoked'): ?>
                                                                <button class="btn btn-sm btn-outline-success btn-action activate-certificate" 
                                                                        data-id="<?= $cert['certificate_id'] ?>">
                                                                    <i class="fas fa-check-circle mr-1"></i>Activate
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php include '../includes/scripts.php'; ?>

<script>
// Ensure jQuery is loaded before proceeding
(function() {
    function waitForJQuery(callback) {
        if (typeof window.jQuery !== 'undefined') {
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

    waitForJQuery(function() {
        if (typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
        
        $(document).ready(function() {
    // Initialize DataTables to match Admin List table behavior
    $('#eligibleTable').DataTable({
        responsive: true,
        autoWidth: false
    });
    // Initialize DataTables for certificate type filters
    $('#orientationTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [2,4,5], className: 'text-center' },
            { targets: 6, orderable: false }
        ]
    });
    
    $('#counselingTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [2,4,5], className: 'text-center' },
            { targets: 6, orderable: false }
        ]
    });
    
    $('#allCertificatesTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[3, 'desc']],
        columnDefs: [
            { targets: [2,4,5], className: 'text-center' },
            { targets: 6, orderable: false }
        ]
    });
    
    // Handle tab switching with buttons - reinitialize DataTables when tabs are shown
    $('button[data-tab]').on('click', function(e) {
        e.preventDefault();
        
        // Update active button
        $('button[data-tab]').removeClass('btn-primary active').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
        
        // Hide all tab panes
        $('.tab-pane').removeClass('show active');
        
        // Show selected tab pane
        const targetTab = $(this).data('tab');
        $('#' + targetTab).addClass('show active');
        
        // Recalculate column widths when tab is shown
        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 100);
    });

    // Generate Certificate
    $('.generate-certificate').on('click', function() {
        const accessId = $(this).data('access-id');
        const coupleNames = $(this).data('couple-names');
        
        Swal.fire({
            title: 'Generate Certificate?',
            html: `Generate certificate for <strong>${coupleNames}</strong>?<br><br>
                   This will create an official certificate with QR code verification.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate Certificate',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('generate_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `access_id=${accessId}&action=generate`
                })
                .then(response => response.json())
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`)
                })
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (result.value.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Certificate Generated!',
                        html: `${result.value.message}<br><br>
                               <strong>Certificate Number:</strong> ${result.value.certificate_number}<br>
                               <strong>Type:</strong> ${result.value.certificate_name}`,
                        showCancelButton: false,
                        confirmButtonText: 'Close'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Generation Failed',
                        text: result.value.message
                    });
                }
            }
        });
    });

    // Revoke Certificate
    $('.revoke-certificate').on('click', function() {
        const certificateId = $(this).data('id');
        
        Swal.fire({
            title: 'Revoke Certificate?',
            text: 'This action cannot be undone. The certificate will be marked as revoked.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Revoke Certificate',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('revoke_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `certificate_id=${certificateId}&action=revoke`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Certificate Revoked',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Revocation Failed',
                            text: data.message || 'An error occurred'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to revoke certificate. Please try again or refresh the page.'
                    });
                });
            }
        });
    });

    // Activate Certificate
    $('.activate-certificate').on('click', function() {
        const certificateId = $(this).data('id');
        
        Swal.fire({
            title: 'Activate Certificate?',
            text: 'This will reactivate the revoked certificate and make it valid again.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Activate Certificate',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#28a745'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('activate_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `certificate_id=${certificateId}&action=activate`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Certificate Activated',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Activation Failed',
                            text: data.message || 'An error occurred'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to activate certificate. Please try again or refresh the page.'
                    });
                });
            }
        });
    });

    // View Certificate - Determine which template to use and open directly
    $('.view-certificate').on('click', function() {
        const certificateId = $(this).data('id');
        console.log('View certificate clicked, ID:', certificateId);
        
        // Fetch certificate info to determine type, then open template directly
        fetch(`get_certificate_info.php?id=${certificateId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Error', 
                        text: data.message || 'Unable to determine certificate type' 
                    });
                    return;
                }
                // Open the appropriate template file directly (same as template buttons)
                const templateUrl = (data.certificate_type === 'counseling')
                    ? `certificate_counseling_template.php?id=${certificateId}`
                    : `certificate_compliance_template.php?id=${certificateId}`;
                window.open(templateUrl, '_blank');
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Error', 
                    text: 'Failed to load certificate. Please try again.' 
                });
            });
    });

    // Print Certificate -> open correct template and trigger browser print
    $('.print-certificate').on('click', function() {
        const certificateId = $(this).data('id');
        fetch(`get_certificate_info.php?id=${certificateId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Unable to determine certificate type');
                const templateUrl = (data.certificate_type === 'counseling')
                    ? `certificate_counseling_template.php?id=${certificateId}`
                    : `certificate_compliance_template.php?id=${certificateId}`;
                const w = window.open(templateUrl, '_blank');
                if (!w) {
                    Swal.fire({ icon: 'info', title: 'Popup Blocked', text: 'Allow popups to print the certificate.' });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Print Failed', text: err.message || 'Unexpected error' });
            });
    });

    // View Certificate of Compliance Template Button Click Event
    $('#viewComplianceTemplateBtn').on('click', function() {
        window.open('certificate_compliance_template.php', '_blank');
    });

      // View Certificate of Marriage Counseling Template Button Click Event
      $('#viewCounselingTemplateBtn').on('click', function() {
          window.open('certificate_counseling_template.php', '_blank');
      });
  });
        }); // End of waitForJQuery
    })();
</script>
</body>
</html> 