<?php
// Admin Dashboard - Main system overview
require_once '../includes/session.php';
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



        // Get dashboard counts and statistics
        $statistics = [];
try {
    // Count registered couples (both partners registered)
    // This should match the ML dashboard logic - count access_ids with BOTH male AND female profiles
    $coupleQuery = "SELECT COUNT(DISTINCT ca.access_id) as total
                    FROM couple_access ca
                    INNER JOIN couple_profile cp1 ON ca.access_id = cp1.access_id AND UPPER(cp1.sex) = 'MALE'
                    INNER JOIN couple_profile cp2 ON ca.access_id = cp2.access_id AND UPPER(cp2.sex) = 'FEMALE'
                    WHERE cp1.first_name IS NOT NULL AND cp2.first_name IS NOT NULL";
    $stmt = $conn->prepare($coupleQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $statistics['registered_couples'] = (int)$row['total'];


    // Count completed orientations (confirmed) based on attendance_logs for orientation segment
    $orientationsQuery = "SELECT COUNT(DISTINCT s.schedule_id) AS total
                          FROM scheduling s
                          WHERE s.session_type IN ('Orientation', 'Orientation + Counseling')
                            AND s.status = 'confirmed'
                            AND EXISTS (
                                SELECT 1 FROM attendance_logs al
                                WHERE al.schedule_id = s.schedule_id AND al.segment = 'orientation' AND al.status = 'present'
                            )";
    $stmt = $conn->prepare($orientationsQuery);
    $stmt->execute();
            $statistics['total_orientations'] = $stmt->get_result()->fetch_assoc()['total'];

    // Count completed counselings (confirmed) based on attendance_logs for counseling segment
    $counselingsQuery = "SELECT COUNT(DISTINCT s.schedule_id) AS total
                         FROM scheduling s
                         WHERE s.session_type = 'Orientation + Counseling'
                           AND s.status = 'confirmed'
                           AND EXISTS (
                               SELECT 1 FROM attendance_logs al
                               WHERE al.schedule_id = s.schedule_id AND al.segment = 'counseling' AND al.status = 'present'
                           )";
    $stmt = $conn->prepare($counselingsQuery);
    $stmt->execute();
            $statistics['total_counselings'] = $stmt->get_result()->fetch_assoc()['total'];

    // Get age distribution
    $ageQuery = "SELECT 
                    FLOOR(DATEDIFF(CURRENT_DATE, date_of_birth)/365) as age,
                    sex,
                    COUNT(*) as count
                 FROM couple_profile
                 WHERE date_of_birth IS NOT NULL
                 GROUP BY age, sex
                 ORDER BY age";
    $stmt = $conn->prepare($ageQuery);
    $stmt->execute();
            $statistics['age_distribution'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get civil status distribution
    $civilQuery = "SELECT 
                    civil_status,
                    COUNT(*) as count
                 FROM couple_profile
                 WHERE civil_status IS NOT NULL
                 GROUP BY civil_status";
    $stmt = $conn->prepare($civilQuery);
    $stmt->execute();
            $statistics['civil_status'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get religion distribution
    $religionQuery = "SELECT 
                    religion,
                    COUNT(*) as count
                 FROM couple_profile
                 WHERE religion IS NOT NULL
                 GROUP BY religion";
    $stmt = $conn->prepare($religionQuery);
    $stmt->execute();
            $statistics['religion'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get education distribution
    $educationQuery = "SELECT 
                    education,
                    COUNT(*) as count
                 FROM couple_profile
                 WHERE education IS NOT NULL
                 GROUP BY education";
    $stmt = $conn->prepare($educationQuery);
    $stmt->execute();
            $statistics['education'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
            error_log("Dashboard statistics error: " . $e->getMessage());
        $statistics = [
        'registered_couples' => 0,
        'total_orientations' => 0,
        'total_counselings' => 0,
        'age_distribution' => [],
        'civil_status' => [],
        'religion' => [],
        'education' => []
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BCPDO | <?= htmlspecialchars($_SESSION['position'] ?? 'Admin') ?> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Dashboard') ?> Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body { background: #f4f6f9; }
        body.dark-mode { background: var(--dark, #343a40); }

        /* AdminLTE3 Original Button Styling */
        .btn {
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Dark mode button styling */
        body.dark-mode .btn {
            transition: all 0.2s ease;
        }

        body.dark-mode .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .content-wrapper {
            padding-top: 20px;
            padding-bottom: 60px;
        }

        .welcome-header {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 24px 32px;
            margin: 20px 0 24px 0;
            transition: all 0.3s ease;
        }
        body.dark-mode .welcome-header { background: #2a2e33; color: #f8f9fa; }

        .welcome-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .welcome-header .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 24px;
            border: 3px solid #007bff;
            transition: transform 0.3s ease;
        }

        .welcome-header:hover .avatar {
            transform: scale(1.05);
        }

        .welcome-header .welcome-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .welcome-header .welcome-sub {
            font-size: 1rem;
            color: #888;
        }

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
            padding: 18px 20px; /* match predictive */
            height: 120px;       /* fixed height for uniformity */
            position: relative;  /* allow absolute footer */
            transition: all 0.3s ease;
            cursor: pointer;
        }
        body.dark-mode .kpi-card { background: #2a2e33; }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.25);
        }

        .kpi-icon {
            width: 48px;        /* match predictive */
            height: 48px;       /* match predictive */
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.8rem;  /* match predictive */
            margin-right: 18px; /* match predictive */
            color: #fff;
            transition: all 0.3s ease;
        }

        .kpi-card:hover .kpi-icon {
            transform: scale(1.1);
        }

        /* Align KPI icon colors to AdminLTE3 small-box palette (solid colors) */
        .kpi-admin {
            background-color: #007bff; /* bg-primary */
        }

        .kpi-couples {
            background-color: #007bff; /* bg-primary */
        }

        .kpi-orientations {
            background-color: #dc3545; /* bg-danger */
        }

        .kpi-counselings {
            background-color: #ffc107; /* bg-warning */
        }

        .kpi-info {
            flex: 1;
            position: relative; /* anchor footer */
            padding-right: 0;
        }

        .kpi-title {
            font-size: 1rem; /* match predictive */
            color: #888;
            margin-bottom: 2px;
        }

        .kpi-value {
            font-size: 1.5rem; /* match predictive */
            font-weight: 700;
            color: #222;
            margin-bottom: 8px; /* space above footer */
        }

        /* Pin footer link inside KPI card for consistent height */
        .kpi-info .small-box-footer {
            position: absolute;
            right: 0;
            bottom: 0;
            margin: 0;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 30px;
            /* Increased margin */
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            background-color: #fff;
        }
        body.dark-mode .card-header { background-color: #2a2e33; border-color: rgba(255,255,255,.1); color: #fff; }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-header .card-tools {
            position: absolute;
            right: 20px;
            top: 15px;
        }

        .bg-primary {
            background-color: #007bff !important;
        }

        .bg-info {
            background-color: #17a2b8 !important;
        }

        .bg-purple {
            background-color: #6f42c1 !important;
        }

        .bg-indigo {
            background-color: #6610f2 !important;
        }

        .bg-teal {
            background-color: #20c997 !important;
        }

        .card-title {
            font-weight: 600;
            color: #333;
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .alert-info {
            border-radius: 8px;
        }



        .btn-action {
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }

        .statistics-chart {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 30px;
            /* Increased margin */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        body.dark-mode .statistics-chart { background: #2a2e33; color: #f8f9fa; }

        .statistics-chart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .chart-container {
            position: relative;
            width: 100%;
        }
        
        .chart-loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 8px;
        }
        body.dark-mode .chart-loading {
            background: rgba(52, 58, 64, 0.8);
        }
        
        .chart-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .time-range-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 15px;
        }

        .time-range-btn {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        body.dark-mode .time-range-btn { background: #2a2e33; color: #f8f9fa; border-color: rgba(255,255,255,.12); }

        .time-range-btn:hover {
            background: #f8f9fa;
        }

        .time-range-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        /* Ensure active state remains visible in dark mode */
        body.dark-mode .time-range-btn.active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        /* Unified legend styling for all cards */
        .chart-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px 16px;
            margin-top: 12px;
            padding: 8px 6px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            padding: 6px 10px;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        body.dark-mode .legend-item { background-color: #2a2e33; color: #f8f9fa; }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            margin-right: 8px;
        }

        .custom-date-range {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .custom-date-range input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
            background-color: #fff;
            color: #333;
        }
        
        /* Dark mode date inputs */
        body.dark-mode .custom-date-range input {
            background-color: #2a2e33;
            color: #f8f9fa;
            border-color: rgba(255,255,255,0.12);
        }
        
        body.dark-mode .custom-date-range input:focus {
            background-color: #2a2e33;
            color: #f8f9fa;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        body.dark-mode .custom-date-range input::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }
        
        body.dark-mode .custom-date-range input::-webkit-datetime-edit-text {
            color: #f8f9fa;
        }
        
        body.dark-mode .custom-date-range input::-webkit-datetime-edit-month-field,
        body.dark-mode .custom-date-range input::-webkit-datetime-edit-day-field,
        body.dark-mode .custom-date-range input::-webkit-datetime-edit-year-field {
            color: #f8f9fa;
        }

        .custom-date-range button {
            padding: 8px 15px;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .custom-date-range button:hover {
            background-color: #0069d9;
        }

        .civil-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .civil-legend-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .civil-legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 5px;
        }

        .religion-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .religion-legend-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            padding: 5px 10px;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .religion-legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 8px;
        }

        /* Unified chart legend styles */
        .chart-legend {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }

        .chart-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            padding: 6px 10px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .section-spacer {
            margin-bottom: 40px;
        }

        /* Split chart and legend side-by-side */
        .chart-split {
            display: flex;
            gap: 16px;
            align-items: stretch;
        }

        .chart-split .chart-col {
            flex: 1 1 65%;
            min-width: 0;
        }

        .chart-split .legend-col {
            flex: 0 0 35%;
            max-width: 35%;
            display: flex;
            align-items: center;
        }

        .chart-split .legend-col .chart-legend {
            width: 100%;
            max-height: 320px;
            overflow: auto;
            justify-content: flex-start;
        }

        @media (max-width: 992px) {
            .chart-split {
                flex-direction: column;
            }

            .chart-split .legend-col {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 18px 12px;
            }

            .welcome-header .avatar {
                margin-bottom: 10px;
                margin-right: 0;
            }

            .kpi-row {
                flex-direction: column;
            }

            .kpi-card {
                margin-bottom: 12px;
            }



            .time-range-buttons {
                overflow-x: auto;
                padding-bottom: 10px;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .time-range-btn {
                white-space: nowrap;
            }

            .chart-container {
                height: 250px !important;
            }

            .custom-date-range {
                flex-direction: column;
                align-items: flex-start;
            }

            .custom-date-range input,
            .custom-date-range button {
                width: 100%;
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
                        <i class="fas fa-tachometer-alt text-primary"></i>
                        <h4 class="mb-0"><?= htmlspecialchars($_SESSION['position'] ?? 'Admin') ?> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Dashboard') ?> Dashboard</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">System overview and key performance indicators</p>

                    <!-- Welcome Header (only after login) -->
                    <?php if (!empty($_SESSION['show_welcome'])): ?>
                        <div class="welcome-header mb-4" id="welcomeHeader">
                            <div style="display: flex; align-items: center;">
                                <img src="<?= htmlspecialchars($_SESSION['image']) ?>" class="avatar" alt="Admin Avatar">
                            </div>
                            <div style="margin-left: 24px;">
                                <div class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</div>
                                <div class="welcome-sub">Here's your admin dashboard overview.</div>
                            </div>
                        </div>
                        <?php unset($_SESSION['show_welcome']); ?>
                    <?php endif; ?>

                    <!-- KPI Cards-->
                    <div class="row kpi-row">
                        <!-- Registered Couples Card -->
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_list/couple_list.php" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-couples">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Registered Couples</div>
                                        <div class="kpi-value"><?= $statistics['registered_couples'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#007bff;">
                                            View All <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Completed Orientations Card -->
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_scheduling/couple_scheduling.php?tab=completed" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-orientations">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Completed Orientations</div>
                                        <div class="kpi-value"><?= $statistics['total_orientations'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#dc3545;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Completed Counselings Card -->
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_scheduling/couple_scheduling.php" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-counselings">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Completed Counselings</div>
                                        <div class="kpi-value"><?= $statistics['total_counselings'] ?></div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#ffc107;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>



                    <!-- Statistics Overview -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-2" style="gap:10px;">
                                <i class="fas fa-chart-pie text-primary"></i>
                                <h4 class="mb-0">Couple Registration Statistics Overview</h4>
                            </div>
                            <p class="text-muted" style="margin-top:-6px;">Track registrations. Filters above apply to all charts.</p>
                        </div>
                    </div>

                    <!-- Analytics Row (Dashboard v2 style) -->
                    <div class="row mt-4">
                        <div class="col-lg-8 col-md-12">
                            <div class="card statistics-chart h-100">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h3 class="card-title mb-0"><i class="fas fa-chart-line mr-2"></i> Couple Registration Trend</h3>
                                    
                                    <!-- Export and Print Options in Header -->
                                    <div class="export-print-options" style="margin: 0; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fas fa-download mr-2"></i>Export
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" id="exportPDF">
                                                    <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                                                </a>
                                                <a class="dropdown-item" href="#" id="exportExcel">
                                                    <i class="fas fa-file-excel mr-2"></i>Export as Excel
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="printReport">
                                            <i class="fas fa-print mr-2"></i>Print
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="collapse" data-target="#registrationTableWrap" aria-expanded="false" aria-controls="registrationTableWrap">
                                            <i class="fas fa-table mr-2"></i>Table
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="time-range-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="past_7_days">Past 7 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="past_14_days">Past 14 Days</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm active" data-range="past_30_days">Past 30 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="present_week">Present Week</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="this_month">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="this_year">This Year</button>
                                    </div>

                                    <!-- Inline filters -->
                                    <div class="custom-date-range" style="display: flex; align-items: center; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                        <input type="date" id="startDate" name="startDate" placeholder="Start Date">
                                        <input type="date" id="endDate" name="endDate" placeholder="End Date">

                                        <!-- Barangay Filter moved here -->
                                        <div class="form-group" style="margin: 0;">
                                            <select id="barangayFilter" class="form-control">
                                                <option value="all">All Barangays</option>
                                                <?php
                                                $stmt = $conn->prepare("SELECT DISTINCT barangay FROM address ORDER BY barangay");
                                                $stmt->execute();
                                                $barangays = $stmt->get_result();
                                                while ($barangay = $barangays->fetch_assoc()):
                                                ?>
                                                    <option value="<?= htmlspecialchars($barangay['barangay']) ?>">
                                                        <?= htmlspecialchars($barangay['barangay']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                                <?php if (isset($stmt)) $stmt->close(); ?>
                                            </select>
                                        </div>

                                        <button id="applyCustomRange">Apply</button>
                                    </div>
                                    <div class="mt-1 text-muted" id="rangeLabel" style="font-size: 0.9rem;"></div>



                                    <div class="chart-container" style="height: 300px; margin-top: 15px;">
                                        <div class="chart-loading" id="chartLoading" style="display: none;">
                                            <div class="chart-loading-spinner"></div>
                                        </div>
                                        <canvas id="registrationChart"></canvas>
                                    </div>
                                    <div class="chart-legend" id="registrationLegend"></div>
                                    
                                    <!-- Registration Data Table -->
                                    <div id="registrationTableWrap" class="collapse table-responsive mt-4">
                                        <table class="table table-striped table-bordered" id="registrationTable">
                                            <thead>
                                                <tr>
                                                    <th>Period</th>
                                                    <th>No. of Couples</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data will be populated dynamically -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12">
                            <!-- Quick Actions Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-bolt mr-2"></i>Quick Actions
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <a href="../admin/access_codes.php" class="btn btn-primary btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-key mr-1"></i>Access Codes
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="../couple_list/couple_list.php" class="btn btn-info btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-user-friends mr-1"></i>View Couples
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="../couple_scheduling/couple_scheduling.php" class="btn btn-success btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-calendar-alt mr-1"></i>Schedule Session
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="../certificates/certificates.php" class="btn btn-warning btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-certificate mr-1"></i>Generate Certificate
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="../question_assessment/question_assessment.php" class="btn btn-secondary btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-poll mr-1"></i>Manage Questions
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="../statistics/statistics.php" class="btn btn-dark btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-chart-bar mr-1"></i>View Statistics
                                            </a>
                                        </div>
                                        <?php if (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin'): ?>
                                        <div class="col-6 mb-2">
                                            <a href="../admin/database_backup.php" class="btn btn-danger btn-sm btn-block quick-action-btn">
                                                <i class="fas fa-database mr-1"></i>Database Backup
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php
                                // Compute upcoming session stats
                                $stats7 = ['sessions'=>0,'orientation'=>0,'counseling'=>0,'pending'=>0,'confirmed'=>0];
                                $stats30 = ['sessions'=>0,'orientation'=>0,'counseling'=>0,'pending'=>0,'confirmed'=>0];
                                try {
                                    $stmt = $conn->prepare("SELECT session_type, status, session_date FROM scheduling WHERE session_date >= CURDATE() AND session_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                                    $stmt->execute();
                                    $q = $stmt->get_result();
                                    while ($r = $q->fetch_assoc()) {
                                        $is7 = (strtotime($r['session_date']) <= strtotime('+7 days'));
                                        $addOrientation = ($r['session_type'] === 'Orientation' || $r['session_type'] === 'Orientation + Counseling');
                                        $addCounseling = ($r['session_type'] === 'Orientation + Counseling');
                                        if ($is7) {
                                            $stats7['sessions']++;
                                            if ($addOrientation) { $stats7['orientation']++; }
                                            if ($addCounseling) { $stats7['counseling']++; }
                                            if ($r['status'] === 'pending') { $stats7['pending']++; }
                                            if ($r['status'] === 'confirmed') { $stats7['confirmed']++; }
                                        }
                                        $stats30['sessions']++;
                                        if ($addOrientation) { $stats30['orientation']++; }
                                        if ($addCounseling) { $stats30['counseling']++; }
                                        if ($r['status'] === 'pending') { $stats30['pending']++; }
                                        if ($r['status'] === 'confirmed') { $stats30['confirmed']++; }
                                    }
                                    if (isset($stmt)) $stmt->close();
                                } catch (Throwable $e) {}
                                $total7 = $stats7['sessions'];
                                $total30 = $stats30['sessions'];
                                $pctConf7 = $total7 ? round(($stats7['confirmed']/$total7)*100) : 0;
                                $pctConf30 = $total30 ? round(($stats30['confirmed']/$total30)*100) : 0;
                            ?>

                            <div class="info-box mb-3">
                                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-calendar-week"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Next 7 Days</span>
                                    <span class="info-box-number">Total <?= $total7 ?></span>
                                    <div class="d-flex" style="gap:8px; margin: 4px 0;">
                                        <span class="badge badge-info">Orientation <?= $stats7['orientation'] ?></span>
                                        <span class="badge badge-warning">Counseling <?= $stats7['counseling'] ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: <?= $pctConf7 ?>%"></div>
                                    </div>
                                    <span class="progress-description">Confirmed <?= $stats7['confirmed'] ?>/<?= $total7 ?> (<?= $pctConf7 ?>%)</span>
                                </div>
                            </div>

                            <div class="info-box mb-3">
                                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-calendar-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Next 30 Days</span>
                                    <span class="info-box-number">Total <?= $total30 ?></span>
                                    <div class="d-flex" style="gap:8px; margin: 4px 0;">
                                        <span class="badge badge-info">Orientation <?= $stats30['orientation'] ?></span>
                                        <span class="badge badge-warning">Counseling <?= $stats30['counseling'] ?></span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: <?= $pctConf30 ?>%"></div>
                                    </div>
                                    <span class="progress-description">Confirmed <?= $stats30['confirmed'] ?>/<?= $total30 ?> (<?= $pctConf30 ?>%)</span>
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
        
        <style>
            /* Quick Actions Button Styling - Uniform Size */
            .quick-action-btn {
                height: 40px !important;
                min-height: 40px !important;
                max-height: 40px !important;
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
                font-weight: 500 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                border-radius: 6px !important;
                transition: all 0.2s ease !important;
            }
            
            .quick-action-btn:hover {
                transform: translateY(-1px) !important;
                box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15) !important;
            }
            
            .quick-action-btn i {
                margin-right: 6px !important;
                font-size: 0.9rem !important;
            }
            
            /* Export and Print Options Styling */
            .export-print-options {
                background: transparent;
                padding: 0;
                border: none;
            }
            
            .export-print-options .btn-sm {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
            }
            
            .export-print-options .btn {
                border-radius: 6px;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .export-print-options .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .export-print-options .dropdown-menu {
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border: 1px solid #dee2e6;
            }
            
            .export-print-options .dropdown-item {
                padding: 8px 16px;
                transition: all 0.2s ease;
            }
            
            .export-print-options .dropdown-item:hover {
                background-color: #f8f9fa;
                color: #495057;
            }
            
            .export-print-options .dropdown-item i {
                width: 16px;
                text-align: center;
                margin-right: 8px;
            }
            
            /* Registration Table Styling */
            #registrationTable {
                margin-top: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            #registrationTable thead th {
                background: linear-gradient(135deg, #007bff, #0056b3);
                color: white;
                border: none;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.9em;
                letter-spacing: 0.5px;
            }
            
            #registrationTable tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            #registrationTable tbody tr:hover {
                background-color: #e3f2fd;
                transform: scale(1.01);
                transition: all 0.2s ease;
            }
            
            #registrationTable td {
                vertical-align: middle;
                border: 1px solid #dee2e6;
            }
            
            #registrationTable td:nth-child(2) {
                text-align: center;
                font-weight: 600;
                color: #007bff;
            }
            
            /* Print Styles */
            @media print {
                .export-print-options,
                .time-range-buttons,
                .custom-date-range,
                .btn,
                .no-print {
                    display: none !important;
                }
                
                /* Use AdminLTE print defaults for layout margins to avoid breaking pushmenu */
                
                .card {
                    border: 1px solid #000 !important;
                    box-shadow: none !important;
                }
            }
            

        </style>
        
        <script>
            // Ensure jQuery is loaded before proceeding
            function waitForJQuery(callback) {
                if (typeof window.jQuery !== 'undefined') {
                    // Ensure $ alias is available
                    if (typeof window.$ === 'undefined') {
                        window.$ = window.jQuery;
                    }
                    console.log('jQuery is ready, executing callback');
                    callback();
                } else {
                    // jQuery not loaded yet, wait a bit and try again
                    console.log('jQuery not ready, waiting...');
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }

            // Prevent $ from being used before jQuery loads - define a safe placeholder
            if (typeof window.$ === 'undefined') {
                window.$ = function() {
                    if (typeof window.jQuery !== 'undefined') {
                        // jQuery is now available, use it
                        if (typeof window.$ === 'function' && window.$ !== window.jQuery) {
                            window.$ = window.jQuery;
                        }
                        return window.jQuery.apply(window.jQuery, arguments);
                    } else {
                        console.error('jQuery ($) used before jQuery is loaded. Please wait for jQuery to load.');
                        return null;
                    }
                };
                // Copy jQuery methods to $ if jQuery is already loaded
                if (typeof window.jQuery !== 'undefined') {
                    window.$ = window.jQuery;
                }
            }

            // Start waiting for jQuery immediately
            waitForJQuery(function() {
                // Ensure $ is properly set to jQuery
                if (typeof window.jQuery !== 'undefined') {
                    window.$ = window.jQuery;
                }
                
                $(document).ready(function() {
                let currentRange = 'present_week';
                let currentStartDate = null;
                let currentEndDate = null;
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

                <?php if (!empty($_SESSION['admin_name']) && !isset($_SESSION['welcome_shown'])): ?>
                    Swal.fire({
                        title: 'Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!',
                        text: 'You have successfully logged in to the BCPDO Admin Dashboard.',
                        icon: 'success',
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        toast: true
                    });
                    <?php $_SESSION['welcome_shown'] = true; ?>
                <?php endif; ?>



                // Welcome header auto-hide
                setTimeout(function() {
                    $('#welcomeHeader').animate({
                        opacity: 0,
                        height: 0,
                        padding: 0,
                        margin: 0
                    }, 600, function() {
                        $(this).remove();
                    });
                }, 5000);

                // Helper to update visible range label under filters
                function updateRangeLabel(range, startDate = null, endDate = null) {
                    const el = document.getElementById('rangeLabel');
                    if (!el) return;
                    const today = new Date();
                    const startOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - ((today.getDay() + 6) % 7)); // Monday
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const fmt = d => d.toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });

                    let text = '';
                    switch (range) {
                        case 'present_week':
                            text = `Present Week (${fmt(startOfWeek)} - ${fmt(endOfWeek)})`;
                            break;
                        case 'past_7_days': {
                            const s = new Date(today);
                            s.setDate(today.getDate() - 6);
                            text = `Past 7 Days (${fmt(s)} - ${fmt(today)})`;
                            break;
                        }
                        case 'past_14_days': {
                            const s = new Date(today);
                            s.setDate(today.getDate() - 13);
                            text = `Past 14 Days (${fmt(s)} - ${fmt(today)})`;
                            break;
                        }
                        case 'this_month':
                            text = `This Month (${fmt(startOfMonth)} - ${fmt(today)})`;
                            break;
                        case 'past_30_days': {
                            const s30 = new Date(today);
                            s30.setDate(today.getDate() - 29);
                            text = `Past 30 Days (${fmt(s30)} - ${fmt(today)})`;
                            break;
                        }
                        case 'this_year': {
                            const sY = new Date(today.getFullYear(), 0, 1);
                            text = `This Year (${fmt(sY)} - ${fmt(today)})`;
                            break;
                        }
                        case 'custom':
                            if (startDate && endDate) {
                                const s = new Date(startDate);
                                const e = new Date(endDate);
                                text = `${fmt(s)} - ${fmt(e)}`;
                            }
                            break;
                        default:
                            text = '';
                    }
                    el.textContent = text;
                }

                // Helper to set inputs based on selected range
                function setDateInputsForRange(range) {
                    const today = new Date();
                    const toISO = d => new Date(d.getFullYear(), d.getMonth(), d.getDate()).toISOString().split('T')[0];
                    let start = null, end = null;
                    switch (range) {
                        case 'present_week': {
                            const startOfWeek = new Date(today);
                            startOfWeek.setDate(today.getDate() - ((today.getDay() + 6) % 7)); // Monday
                            const endOfWeek = new Date(startOfWeek);
                            endOfWeek.setDate(startOfWeek.getDate() + 6);
                            start = startOfWeek; end = endOfWeek;
                            break;
                        }
                        case 'past_7_days':
                            start = new Date(today); start.setDate(today.getDate() - 6);
                            end = new Date(today);
                            break;
                        case 'past_14_days':
                            start = new Date(today); start.setDate(today.getDate() - 13);
                            end = new Date(today);
                            break;
                        case 'this_month':
                            start = new Date(today.getFullYear(), today.getMonth(), 1);
                            end = new Date(today);
                            break;
                        case 'past_30_days':
                            start = new Date(today); start.setDate(today.getDate() - 29);
                            end = new Date(today);
                            break;
                        case 'this_year':
                            start = new Date(today.getFullYear(), 0, 1);
                            end = new Date(today);
                            break;
                        default:
                            return; // do not modify for custom
                    }
                    if (start && end) {
                        $('#startDate').val(toISO(start));
                        $('#endDate').val(toISO(end));
                    }
                }

                // Time range button functionality
                $('.time-range-buttons button[data-range]').click(function() {
                    // Update active button - match export button style
                    $('.time-range-buttons button[data-range]').removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
                    $(this).removeClass('btn-outline-secondary').addClass('btn-outline-primary active');
                    
                    const range = $(this).data('range');
                    currentRange = range;
                    currentStartDate = null;
                    currentEndDate = null;
                    
                    // Show loading indicator
                    $('#chartLoading').show();
                    
                    fetchStatisticsData(range);
                    updateRangeLabel(range);
                    setDateInputsForRange(range);
                });

                // Custom date range functionality
                $('#applyCustomRange').click(function() {
                    const startDate = $('#startDate').val();
                    const endDate = $('#endDate').val();

                    if (!startDate || !endDate) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select both start and end dates',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        return;
                    }
                    
                    // Show loading indicator
                    $('#chartLoading').show();

                    if (new Date(startDate) > new Date(endDate)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Start date must be before end date',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        return;
                    }

                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    if (isNaN(start.getTime())) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Please select a valid start date',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        return;
                    }
                    if (isNaN(end.getTime())) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Please select a valid end date',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        return;
                    }

                    const startFormatted = start.toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    const endFormatted = end.toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });

                    Swal.fire({
                        icon: 'info',
                        title: 'Filtering Data',
                        text: `Showing data from ${startFormatted} to ${endFormatted}`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });

                    // Reset time range buttons when custom date is applied
                    $('.time-range-buttons button[data-range]').removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
                    currentRange = 'custom';
                    currentStartDate = startDate;
                    currentEndDate = endDate;
                    fetchStatisticsData('custom', startDate, endDate);
                    updateRangeLabel('custom', startDate, endDate);
                });

                // Initialize charts with empty data
                const registrationCtx = document.getElementById('registrationChart').getContext('2d');
                window.registrationChart = new Chart(registrationCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: []
                    },
                    options: getLineChartOptions('Couples Registered')
                });

                // Only initialize other charts if present on this page
                const pyramidCanvas = document.getElementById('populationPyramidChart');
                const civilCanvas = document.getElementById('civilChart');
                const religionCanvas = document.getElementById('religionChart');
                const weddingCanvas = document.getElementById('weddingChart');

                let pyramidChart = null;
                if (pyramidCanvas) {
                    pyramidChart = new Chart(pyramidCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: getPyramidChartOptions()
                    });
                }

                let civilChart = null;
                if (civilCanvas) {
                    civilChart = new Chart(civilCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: getBarChartOptions('Civil Status', 'Number of Individuals')
                    });
                }

                let religionChart = null;
                if (religionCanvas) {
                    religionChart = new Chart(religionCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: getBarChartOptions('Religion', 'Number of Individuals')
                    });
                }

                let weddingChart = null;
                if (weddingCanvas) {
                    weddingChart = new Chart(weddingCanvas.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: getPieChartOptions()
                    });
                }

                // Chart options functions
                function getLineChartOptions(title) {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'line',
                                    padding: 16
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' couples registered';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Couples'
                                },
                                ticks: {
                                    stepSize: 1,
                                    callback: function(value) {
                                        return Math.round(value);
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Period'
                                }
                            }
                        }
                    };
                }

                function getPyramidChartOptions() {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + Math.abs(context.parsed.x);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                ticks: {
                                    callback: function(value) {
                                        return Math.abs(value);
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Individuals'
                                },
                                suggestedMin: -10,
                                suggestedMax: 10
                            },
                            y: {
                                stacked: true,
                                title: {
                                    display: true,
                                    text: 'Age Groups'
                                }
                            }
                        }
                    };
                }

                function getBarChartOptions(xTitle, yTitle) {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' individuals';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: yTitle
                                },
                                ticks: {
                                    stepSize: 1,
                                    callback: function(value) {
                                        return Math.round(value);
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: xTitle
                                }
                            }
                        }
                    };
                }

                function getPieChartOptions() {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    };
                }

                // Update chart functions
                function updateRegistrationChart(data) {
                    window.registrationChart.data.labels = data.labels;
                    window.registrationChart.data.datasets = [{
                        label: 'Couples Registered',
                        data: data.values,
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }];
                    window.registrationChart.update();
                    
                    // Hide loading indicator after chart update
                    $('#chartLoading').hide();

                    // Update registration data table
                    updateRegistrationTable(data);

                    // Registration legend with total
                    const legend = document.getElementById('registrationLegend');
                    if (legend) {
                        legend.innerHTML = '';
                        const total = data.values.reduce((a, b) => a + b, 0);
                        const item = document.createElement('div');
                        item.className = 'legend-item';
                        const color = document.createElement('div');
                        color.className = 'legend-color';
                        color.style.backgroundColor = 'rgba(0, 123, 255, 0.7)';
                        const text = document.createElement('span');
                        text.textContent = `Couples Registered: ${total}`;
                        item.appendChild(color);
                        item.appendChild(text);
                        legend.appendChild(item);
                    }
                }

                // Update the registration data table
                function updateRegistrationTable(data) {
                    const tableBody = document.querySelector('#registrationTable tbody');
                    if (!tableBody) return;
                    
                    tableBody.innerHTML = '';
                    
                    data.labels.forEach((label, index) => {
                        const row = document.createElement('tr');
                        const periodCell = document.createElement('td');
                        const countCell = document.createElement('td');
                        
                        periodCell.textContent = label;
                        countCell.textContent = data.values[index];
                        countCell.style.textAlign = 'center';
                        
                        row.appendChild(periodCell);
                        row.appendChild(countCell);
                        tableBody.appendChild(row);
                    });
                }

                function updatePopulationPyramid(data) {
                    const reversedLabels = [...data.labels].reverse();
                    const reversedMale = [...data.male].reverse();
                    const reversedFemale = [...data.female].reverse();

                    pyramidChart.data.labels = reversedLabels;
                    pyramidChart.data.datasets = [{
                            label: 'Male',
                            data: reversedMale.map(val => -val),
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Female',
                            data: reversedFemale,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ];
                    pyramidChart.update();

                    // Legend update
                    const legend = document.getElementById('pyramidLegend');
                    legend.innerHTML = '';
                    const items = [{
                            label: 'Male',
                            color: 'rgba(54, 162, 235, 0.7)'
                        },
                        {
                            label: 'Female',
                            color: 'rgba(255, 99, 132, 0.7)'
                        }
                    ];
                    items.forEach(i => {
                        const el = document.createElement('div');
                        el.className = 'legend-item';
                        const c = document.createElement('div');
                        c.className = 'legend-color';
                        c.style.backgroundColor = i.color;
                        const t = document.createElement('span');
                        t.textContent = i.label;
                        el.appendChild(c);
                        el.appendChild(t);
                        legend.appendChild(el);
                    });
                }

                function updateCivilChart(data) {
                    civilChart.data.labels = data.labels;
                    civilChart.data.datasets = [{
                        label: 'Count',
                        data: data.values,
                        backgroundColor: data.labels.map((label, i) => {
                            if (label.toLowerCase().includes('single')) return 'rgba(54, 162, 235, 0.7)';
                            if (label.toLowerCase().includes('married')) return 'rgba(255, 99, 132, 0.7)';
                            if (label.toLowerCase().includes('widowed')) return 'rgba(255, 206, 86, 0.7)';
                            if (label.toLowerCase().includes('divorced')) return 'rgba(75, 192, 192, 0.7)';
                            if (label.toLowerCase().includes('separated')) return 'rgba(153, 102, 255, 0.7)';
                            return 'rgba(201, 203, 207, 0.7)';
                        }),
                        borderColor: data.labels.map((label, i) => {
                            if (label.toLowerCase().includes('single')) return 'rgba(54, 162, 235, 1)';
                            if (label.toLowerCase().includes('married')) return 'rgba(255, 99, 132, 1)';
                            if (label.toLowerCase().includes('widowed')) return 'rgba(255, 206, 86, 1)';
                            if (label.toLowerCase().includes('divorced')) return 'rgba(75, 192, 192, 1)';
                            if (label.toLowerCase().includes('separated')) return 'rgba(153, 102, 255, 1)';
                            return 'rgba(201, 203, 207, 1)';
                        }),
                        borderWidth: 1
                    }];
                    civilChart.update();

                    // Update civil status legend
                    updateLegend('civilLegend', data.labels, data.values);
                }

                function updateReligionChart(data) {
                    religionChart.data.labels = data.labels;
                    religionChart.data.datasets = [{
                        label: 'Count',
                        data: data.values,
                        backgroundColor: data.colors,
                        borderColor: data.colors.map(color => color.replace(/[\d.]+\)$/, '1)')),
                        borderWidth: 1,
                        hoverOffset: 4
                    }];
                    religionChart.update();

                    // Update religion legend
                    const legendContainer = document.getElementById('religionLegend');
                    legendContainer.innerHTML = '';

                    const total = data.values.reduce((sum, val) => sum + val, 0);

                    // Sort legend entries alphabetically (Aglipay first etc.)
                    const entries = data.labels.map((label, index) => ({
                        label,
                        value: data.values[index],
                        color: data.colors[index]
                    }));
                    entries.sort((a, b) => a.label.localeCompare(b.label));

                    entries.forEach((entry) => {
                        const percentage = total > 0 ? Math.round((entry.value / total) * 100) : 0;
                        const legendItem = document.createElement('div');
                        legendItem.className = 'legend-item';

                        const colorBox = document.createElement('div');
                        colorBox.className = 'legend-color';
                        colorBox.style.backgroundColor = entry.color;

                        const labelText = document.createElement('span');
                        labelText.textContent = `${entry.label}: ${entry.value} (${percentage}%)`;

                        legendItem.appendChild(colorBox);
                        legendItem.appendChild(labelText);
                        legendContainer.appendChild(legendItem);
                    });
                }

                function updateWeddingChart(data) {
                    weddingChart.data.labels = data.labels;
                    weddingChart.data.datasets = [{
                        data: data.values,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }];
                    weddingChart.update();

                    // Legend with counts and percentages
                    const legend = document.getElementById('weddingLegend');
                    legend.innerHTML = '';
                    const total = data.values.reduce((a, b) => a + b, 0);
                    const weddingEntries = data.labels.map((label, idx) => ({
                        label,
                        value: data.values[idx],
                        color: idx === 0 ? 'rgba(75, 192, 192, 0.7)' : 'rgba(153, 102, 255, 0.7)'
                    }));
                    weddingEntries.sort((a, b) => a.label.localeCompare(b.label));
                    weddingEntries.forEach(({
                        label,
                        value,
                        color
                    }) => {
                        const el = document.createElement('div');
                        el.className = 'legend-item';
                        const c = document.createElement('div');
                        c.className = 'legend-color';
                        c.style.backgroundColor = color;
                        const count = value || 0;
                        const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                        const t = document.createElement('span');
                        t.textContent = `${label}: ${count} (${pct}%)`;
                        el.appendChild(c);
                        el.appendChild(t);
                        legend.appendChild(el);
                    });
                }

                // Function to update legend with color coding
                function updateLegend(legendId, labels, values) {
                    const legendContainer = document.getElementById(legendId);
                    legendContainer.innerHTML = '';

                    labels.forEach((label, index) => {
                        const color = getColorForLabel(label);

                        const legendItem = document.createElement('div');
                        legendItem.className = 'legend-item';

                        const colorBox = document.createElement('div');
                        colorBox.className = 'legend-color';
                        colorBox.style.backgroundColor = color;

                        const labelText = document.createElement('span');
                        labelText.textContent = `${label}: ${values[index]}`;

                        legendItem.appendChild(colorBox);
                        legendItem.appendChild(labelText);
                        legendContainer.appendChild(legendItem);
                    });
                }

                // Helper function to get color based on label
                function getColorForLabel(label) {
                    if (label.toLowerCase().includes('single')) return 'rgba(54, 162, 235, 0.7)';
                    if (label.toLowerCase().includes('married')) return 'rgba(255, 99, 132, 0.7)';
                    if (label.toLowerCase().includes('widowed')) return 'rgba(255, 206, 86, 0.7)';
                    if (label.toLowerCase().includes('divorced')) return 'rgba(75, 192, 192, 0.7)';
                    if (label.toLowerCase().includes('separated')) return 'rgba(153, 102, 255, 0.7)';
                    if (label.toLowerCase().includes('catholic')) return 'rgba(255, 99, 132, 0.7)';
                    if (label.toLowerCase().includes('protestant')) return 'rgba(54, 162, 235, 0.7)';
                    if (label.toLowerCase().includes('islam')) return 'rgba(255, 206, 86, 0.7)';
                    if (label.toLowerCase().includes('hindu')) return 'rgba(75, 192, 192, 0.7)';
                    if (label.toLowerCase().includes('buddhist')) return 'rgba(153, 102, 255, 0.7)';
                    return 'rgba(201, 203, 207, 0.7)';
                }

                // Function to fetch dynamic data
                function fetchStatisticsData(range = 'past_7_days', startDate = null, endDate = null) {
                    const barangay = $('#barangayFilter').val();
                    const data = {
                        range: range,
                        barangay: barangay
                    };

                    if (range === 'custom' && startDate && endDate) {
                        data.start_date = startDate;
                        data.end_date = endDate;
                    }

                    // Show loading indicator
                    $('#chartLoading').show();

                    $.ajax({
                        url: '../includes/fetch_statistics.php',
                        method: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(data) {
                            console.log('Statistics data received:', data);
                            console.log('Registration data:', data.registration);
                            
                            if (data.registration) {
                                updateRegistrationChart(data.registration);
                            } else {
                                console.error('No registration data received');
                            }
                            
                            if (pyramidChart && data.population) updatePopulationPyramid(data.population);
                            if (civilChart && data.civil) updateCivilChart(data.civil);
                            if (religionChart && data.religion) updateReligionChart(data.religion);
                            if (weddingChart && data.wedding) updateWeddingChart(data.wedding);
                            
                            // Hide loading indicator
                            $('#chartLoading').hide();
                        },
                        error: function(xhr, status, error) {
                            console.error("Error fetching statistics data:", error);
                            
                            // Hide loading indicator on error
                            $('#chartLoading').hide();
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to fetch statistics data',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    });
                }

                // Set default dates for custom range: start of current month -> today (DD-MM-YYYY requirement for label only; inputs need YYYY-MM-DD)
                const today = new Date();
                const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                const toISO = d => d.toISOString().split('T')[0];
                $('#startDate').val(toISO(startOfMonth));
                $('#endDate').val(toISO(today));

                // Initial fetch: Present Week and set date inputs to Monday -> Sunday of present week
                fetchStatisticsData('present_week');
                updateRangeLabel('present_week');
                (function initDates() {
                    const today = new Date();
                    const startOfWeek = new Date(today);
                    // Monday as start of week
                    startOfWeek.setDate(today.getDate() - ((today.getDay() + 6) % 7));
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    const toISO = d => d.toISOString().split('T')[0];
                    $('#startDate').val(toISO(startOfWeek));
                    $('#endDate').val(toISO(endOfWeek));
                })();

                // Apply filter on barangay change
                $('#barangayFilter').on('change', function() {
                    if (currentRange === 'custom') {
                        fetchStatisticsData('custom', currentStartDate, currentEndDate);
                    } else {
                        fetchStatisticsData(currentRange || 'past_7_days');
                    }
                });

                // Export and Print Functionality for Registration Chart Only
                
                // Export as PDF
                $('#exportPDF').on('click', function(e) {
                    e.preventDefault();
                    exportRegistrationToPDF();
                });

                // Export as Excel
                $('#exportExcel').on('click', function(e) {
                    e.preventDefault();
                    exportRegistrationToExcel();
                });

                // Print Report
                $('#printReport').on('click', function(e) {
                    e.preventDefault();
                    printRegistrationReport();
                });

                // Export Functions for Registration Chart Only
                function exportRegistrationToPDF() {
                    if (!window.registrationChart || !window.registrationChart.data) {
                        Swal.fire({ icon:'warning', title:'No Data Available', text:'Please wait for the registration chart to load before exporting.' });
                        return;
                    }

                    Swal.fire({ title:'Generating PDF...', text:'Please wait while we prepare your report', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

                    try {
                        const { jsPDF } = window.jspdf || {};
                        if (!jsPDF) {
                            throw new Error('jsPDF not loaded');
                        }

                        const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
                        const pageWidth = doc.internal.pageSize.getWidth();
                        const margin = 40;
                        let cursorY = margin;

                        const rangeLabel = document.getElementById('rangeLabel').textContent;
                        const title = 'BCPDO Pre Marriage Orientation & Counseling Registration System';
                        const subtitle = 'Report: Couple Registration Trend';

                        // Header
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(16);
                        doc.text(title, pageWidth/2, cursorY, { align: 'center' });
                        cursorY += 20;
                        doc.setFontSize(12);
                        doc.setFont('helvetica', 'normal');
                        doc.text(subtitle, pageWidth/2, cursorY, { align: 'center' });
                        cursorY += 18;
                        doc.text(`Date Range: ${rangeLabel}`, pageWidth/2, cursorY, { align: 'center' });
                        cursorY += 18;
                        doc.text(`Generated: ${new Date().toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })}`, pageWidth/2, cursorY, { align: 'center' });
                        cursorY += 20;

                        // Chart image from canvas
                        const canvas = document.getElementById('registrationChart');
                        window.registrationChart.update('none');
                        requestAnimationFrame(() => {
                            try {
                                const dataUrl = canvas.toDataURL('image/png', 1.0);
                                const imgWidth = pageWidth - margin*2;
                                const aspect = canvas.height / canvas.width;
                                const imgHeight = imgWidth * aspect;
                                doc.addImage(dataUrl, 'PNG', margin, cursorY, imgWidth, imgHeight);
                                cursorY += imgHeight + 20;
                            } catch(e) {
                                // If canvas export fails, skip image section
                            }

                            // Table of data
                            const labels = window.registrationChart.data.labels;
                            const values = window.registrationChart.data.datasets[0]?.data || [];
                            const tableBody = labels.map((label, idx) => [label, String(values[idx] || 0)]);

                            if (doc.autoTable) {
                                doc.autoTable({
                                    head: [['Period', 'No. of Couples']],
                                    body: tableBody,
                                    startY: cursorY,
                                    styles: { fontSize: 10 },
                                    headStyles: { fillColor: [0,123,255] }
                                });
                                cursorY = doc.lastAutoTable.finalY + 16;
                            }

                            // Summary
                            const total = values.reduce((a,b)=>a+(b||0),0);
                            const max = Math.max(...values);
                            const min = Math.min(...values);
                            const maxIdx = values.indexOf(max);
                            const minIdx = values.indexOf(min);
                            doc.setFont('helvetica','bold');
                            doc.text('Summary', margin, cursorY);
                            doc.setFont('helvetica','normal');
                            cursorY += 14;
                            doc.text(`Total Registrations: ${total}`, margin, cursorY); cursorY += 14;
                            if (values.length > 0 && isFinite(max)) { doc.text(`Highest Registration: ${max} (${labels[maxIdx]})`, margin, cursorY); cursorY += 14; }
                            if (values.length > 0 && isFinite(min)) { doc.text(`Lowest Registration: ${min} (${labels[minIdx]})`, margin, cursorY); cursorY += 14; }

                            // Footer
                            const footerY = doc.internal.pageSize.getHeight() - margin;
                            doc.setFontSize(10);
                            doc.text('Prepared by: Admin User', pageWidth/2, footerY, { align: 'center' });

                            // Save
                            const filename = `BCPDO_Registration_${new Date().toISOString().split('T')[0]}.pdf`;
                            doc.save(filename);
                            Swal.close();
                            Swal.fire({ icon:'success', title:'PDF Generated!', timer:1500, showConfirmButton:false });
                        });
                    } catch (error) {
                        console.error('PDF generation error:', error);
                        Swal.fire({ icon:'error', title:'PDF Generation Failed', text:'There was an error generating the PDF. Please try again.' });
                    }
                }

                // Capture the registration chart as a high-quality image
                function captureRegistrationChartImage() {
                    return new Promise((resolve) => {
                        try {
                            // Ensure chart is fully rendered and updated
                            window.registrationChart.resize();
                            window.registrationChart.update('none');
                            
                            // Wait for the next frame to ensure rendering is complete
                            requestAnimationFrame(() => {
                                try {
                                    const canvas = document.getElementById('registrationChart');
                                    if (canvas && canvas.getContext) {
                                        // Try to get data URL from the canvas
                                        const url = canvas.toDataURL('image/png', 1.0);
                                        if (url && url !== 'data:,') {
                                            return resolve(url);
                                        }
                                    }
                                    
                                    // Fallback: use Chart.js built-in method
                                    const chartUrl = window.registrationChart.toBase64Image('image/png', 1.0);
                                    if (chartUrl && chartUrl !== 'data:,') {
                                        return resolve(chartUrl);
                                    }
                                    
                                    // Final fallback: html2canvas
                                    const node = document.getElementById('registrationChart');
                                    if (node) {
                                        html2canvas(node, { 
                                            background: '#ffffff', 
                                            scale: 2, 
                                            useCORS: true, 
                                            allowTaint: true,
                                            logging: false,
                                            width: node.offsetWidth,
                                            height: node.offsetHeight
                                        }).then(canvas => {
                                            const dataUrl = canvas.toDataURL('image/png', 1.0);
                                            resolve(dataUrl);
                                        }).catch(() => resolve(''));
                                    } else {
                                        resolve('');
                                    }
                                } catch (e) {
                                    console.error('Chart capture error:', e);
                                    resolve('');
                                }
                            });
                        } catch (e) {
                            console.error('Chart capture error:', e);
                            resolve('');
                        }
                    });
                }

                function exportRegistrationToExcel() {
                    console.log('Excel Export - registrationChart:', window.registrationChart);
                    console.log('Excel Export - chart data:', window.registrationChart?.data);
                    
                    if (!window.registrationChart || !window.registrationChart.data) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Data Available',
                            text: 'Please wait for the registration chart to load before exporting.',
                        });
                        return;
                    }

                    try {
                        const data = prepareRegistrationExcelData();
                        const ws = XLSX.utils.json_to_sheet(data);
                        const wb = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(wb, ws, 'Registration Data');
                        
                        const fileName = `BCPDO_Registration_${currentRange}_${new Date().toISOString().split('T')[0]}.xlsx`;
                        XLSX.writeFile(wb, fileName);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Excel Generated!',
                            text: 'Your registration report has been downloaded successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } catch (error) {
                        console.error('Excel generation error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Excel Generation Failed',
                            text: 'There was an error generating the Excel file. Please try again.',
                        });
                    }
                }

                function printRegistrationReport() {
                    console.log('Print Report - registrationChart:', window.registrationChart);
                    console.log('Print Report - chart data:', window.registrationChart?.data);
                    
                    if (!window.registrationChart || !window.registrationChart.data) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Data Available',
                            text: 'Please wait for the registration chart to load before printing.',
                        });
                        return;
                    }

                    const chartData = window.registrationChart.data;
                    const rangeLabel = document.getElementById('rangeLabel').textContent;
                    const barangay = $('#barangayFilter').val();
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>BCPDO Pre Marriage Orientation & Counseling Registration System</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                .report-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                                .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                                .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                .data-table th { background-color: #f2f2f2; font-weight: bold; }
                                .data-table td:nth-child(2) { text-align: center; }
                                .summary { margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-radius: 5px; }
                                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
                                @media print {
                                    body { margin: 0; }
                                    .no-print { display: none; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="report-header">
                                <h1>BCPDO Pre Marriage Orientation & Counseling Registration System</h1>
                                <h2>Report: Couple Registration Trend</h2>
                                <p><strong>Date Range:</strong> ${rangeLabel}</p>
                                <p><strong>Generated:</strong> ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                            </div>
                            
                            <div class="summary">
                                <h3>Summary</h3>
                                <p><strong>Total Registrations:</strong> ${chartData.datasets[0].data.reduce((a, b) => a + b, 0)}</p>
                                <p><strong>Highest Registration:</strong> ${Math.max(...chartData.datasets[0].data)} couples (${chartData.labels[chartData.datasets[0].data.indexOf(Math.max(...chartData.datasets[0].data))]})</p>
                                <p><strong>Lowest Registration:</strong> ${Math.min(...chartData.datasets[0].data)} couples (${chartData.labels[chartData.datasets[0].data.indexOf(Math.min(...chartData.datasets[0].data))]})</p>
                                <p><strong>Date Range:</strong> ${chartData.labels[0]} to ${chartData.labels[chartData.labels.length - 1]}</p>
                            </div>
                            
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>No. of Couples</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${chartData.labels.map((label, index) => `
                                        <tr>
                                            <td>${label}</td>
                                            <td>${chartData.datasets[0].data[index] || 0}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            
                            <div class="footer">
                                <p><strong>Prepared by:</strong> Admin User</p>
                                <p>Page 1 of 1</p>
                            </div>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }

                // New function to create PDF with embedded chart
                function createPDFWithChart(chartData, rangeLabel, barangay) {
                    try {
                        // Create a temporary container for the report
                        const reportContainer = document.createElement('div');
                        reportContainer.style.position = 'absolute';
                        reportContainer.style.left = '-9999px';
                        reportContainer.style.top = '0';
                        reportContainer.style.width = '800px';
                        reportContainer.style.backgroundColor = 'white';
                        reportContainer.style.padding = '20px';
                        reportContainer.style.fontFamily = 'Arial, sans-serif';
                        reportContainer.style.fontSize = '12px';
                        
                        // Add header
                        reportContainer.innerHTML = `
                            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                                <h1 style="margin: 0; color: #333; font-size: 24px;">BCPDO Pre Marriage Orientation & Counseling Registration System</h1>
                                <h2 style="margin: 10px 0; color: #555; font-size: 18px;">Report: Couple Registration Trend</h2>
                                <p style="margin: 10px 0; font-size: 14px;"><strong>Date Range:</strong> ${rangeLabel}</p>
                                <p style="margin: 10px 0; font-size: 14px;"><strong>Generated:</strong> ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                            </div>
                        `;
                        
                        // Add chart container
                        const chartContainer = document.createElement('div');
                        chartContainer.id = 'tempChartContainer';
                        chartContainer.style.textAlign = 'center';
                        chartContainer.style.margin = '20px 0';
                        chartContainer.style.border = '1px solid #ddd';
                        chartContainer.style.padding = '10px';
                        chartContainer.style.backgroundColor = '#fff';
                        
                        // Create chart image using html2canvas for reliable capture
                        try {
                            // Force chart update
                            window.registrationChart.update('none');
                            
                            // Wait a bit for the chart to fully render
                            setTimeout(() => {
                                const chartElement = document.getElementById('registrationChart');
                                if (chartElement) {
                                    html2canvas(chartElement, {
                                        backgroundColor: '#ffffff',
                                        scale: 2,
                                        useCORS: true,
                                        allowTaint: true,
                                        logging: false,
                                        width: chartElement.offsetWidth,
                                        height: chartElement.offsetHeight
                                    }).then(canvas => {
                                        const chartImageUrl = canvas.toDataURL('image/png', 1.0);
                                        
                                        if (chartImageUrl && chartImageUrl !== 'data:,') {
                                            const img = document.createElement('img');
                                            img.src = chartImageUrl;
                                            img.style.maxWidth = '100%';
                                            img.style.height = 'auto';
                                            img.style.display = 'block';
                                            img.style.margin = '0 auto';
                                            img.alt = 'Couple Registration Trend Chart';
                                            img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                                            
                                            // Add chart title
                                            const chartTitle = document.createElement('h3');
                                            chartTitle.textContent = 'Couple Registration Trend';
                                            chartTitle.style.textAlign = 'center';
                                            chartTitle.style.margin = '0 0 15px 0';
                                            chartTitle.style.color = '#333';
                                            
                                            chartContainer.appendChild(chartTitle);
                                            chartContainer.appendChild(img);
                                            
                                            // Ensure image is decoded before generating PDF
                                            if (img.decode) {
                                                img.decode().then(() => generatePDFFromContainer(reportContainer))
                                                    .catch(() => generatePDFFromContainer(reportContainer));
                                            } else {
                                                img.onload = () => generatePDFFromContainer(reportContainer);
                                                img.onerror = () => generatePDFFromContainer(reportContainer);
                                            }
                                        } else {
                                            // Fallback: create a placeholder
                                            chartContainer.innerHTML = `
                                                <h3 style="margin: 0 0 15px 0; color: #666;">Chart Data</h3>
                                                <p style="color: #888; margin: 0;">Chart image could not be generated. Please refer to the data table below.</p>
                                            `;
                                            generatePDFFromContainer(reportContainer);
                                        }
                                    }).catch(error => {
                                        console.error('html2canvas error:', error);
                                        // Fallback: create a placeholder
                                        chartContainer.innerHTML = `
                                            <h3 style="margin: 0 0 15px 0; color: #666;">Chart Data</h3>
                                            <p style="color: #888; margin: 0;">Chart image could not be generated. Please refer to the data table below.</p>
                                        `;
                                        generatePDFFromContainer(reportContainer);
                                    });
                                } else {
                                    // Fallback: create a placeholder
                                    chartContainer.innerHTML = `
                                        <h3 style="margin: 0 0 15px 0; color: #666;">Chart Data</h3>
                                        <p style="color: #888; margin: 0;">Chart element not found. Please refer to the data table below.</p>
                                    `;
                                    generatePDFFromContainer(reportContainer);
                                }
                            }, 1000); // Increased delay to 1 second
                        } catch (error) {
                            console.error('Chart image generation error:', error);
                            // Fallback: create a placeholder
                            chartContainer.innerHTML = `
                                <h3 style="margin: 0 0 15px 0; color: #666;">Chart Data</h3>
                                <p style="color: #888; margin: 0;">Chart image could not be generated. Please refer to the data table below.</p>
                            `;
                            generatePDFFromContainer(reportContainer);
                        }
                        
                        reportContainer.appendChild(chartContainer);
                        
                        // Add data table
                        const table = document.createElement('table');
                        table.style.width = '100%';
                        table.style.borderCollapse = 'collapse';
                        table.style.margin = '20px 0';
                        table.style.border = '1px solid #ddd';
                        
                        table.innerHTML = `
                            <thead>
                                <tr>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f2f2f2; font-weight: bold;">Period</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center; background-color: #f2f2f2; font-weight: bold;">No. of Couples</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${chartData.labels.map((label, index) => `
                                    <tr>
                                        <td style="border: 1px solid #ddd; padding: 8px;">${label}</td>
                                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${chartData.datasets[0].data[index] || 0}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        `;
                        
                        reportContainer.appendChild(table);
                        
                        // Add summary
                        const totalRegistrations = chartData.datasets[0].data.reduce((a, b) => a + b, 0);
                        const maxValue = Math.max(...chartData.datasets[0].data);
                        const minValue = Math.min(...chartData.datasets[0].data);
                        const maxIndex = chartData.datasets[0].data.indexOf(maxValue);
                        const minIndex = chartData.datasets[0].data.indexOf(minValue);
                        
                        const summary = document.createElement('div');
                        summary.style.margin = '20px 0';
                        summary.style.padding = '15px';
                        summary.style.backgroundColor = '#f9f9f9';
                        summary.style.borderRadius = '5px';
                        summary.innerHTML = `
                            <h3 style="margin: 0 0 15px 0;">Summary</h3>
                            <p style="margin: 5px 0;"><strong>Total Registrations:</strong> ${totalRegistrations}</p>
                            <p style="margin: 5px 0;"><strong>Highest Registration:</strong> ${maxValue} couples (${chartData.labels[maxIndex]})</p>
                            <p style="margin: 5px 0;"><strong>Lowest Registration:</strong> ${minValue} couples (${chartData.labels[minIndex]})</p>
                            <p style="margin: 5px 0;"><strong>Date Range:</strong> ${chartData.labels[0]} to ${chartData.labels[chartData.labels.length - 1]}</p>
                        `;
                        
                        reportContainer.appendChild(summary);
                        
                        // Add footer
                        const footer = document.createElement('div');
                        footer.style.marginTop = '30px';
                        footer.style.paddingTop = '20px';
                        footer.style.borderTop = '1px solid #ddd';
                        footer.style.textAlign = 'center';
                        footer.style.fontSize = '12px';
                        footer.style.color = '#666';
                        footer.innerHTML = `
                            <p style="margin: 5px 0;"><strong>Prepared by:</strong> Admin User</p>
                            <p style="margin: 5px 0;">Page 1 of 1</p>
                        `;
                        
                        reportContainer.appendChild(footer);
                        
                        // Append to body temporarily
                        document.body.appendChild(reportContainer);
                        
                        // PDF generation will be handled by generatePDFFromContainer function
                        // after the chart image is captured
                    } catch (error) {
                        console.error('PDF creation error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'PDF Generation Failed',
                            text: 'There was an error creating the PDF content.',
                        });
                    }
                }
                
                // Separate function to generate PDF from the container
                function generatePDFFromContainer(reportContainer) {
                    const opt = {
                        margin: 0.5,
                        filename: `BCPDO_Registration_${currentRange}_${new Date().toISOString().split('T')[0]}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { 
                            scale: 2, 
                            useCORS: true, 
                            allowTaint: true, 
                            background: '#ffffff',
                            logging: false,
                            width: 800,
                            height: reportContainer.scrollHeight,
                            scrollX: 0,
                            scrollY: 0
                        },
                        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                    };
                    
                    // Wait a bit more for any images to load
                    setTimeout(() => {
                        html2pdf().set(opt).from(reportContainer).save()
                            .then(() => {
                                // Clean up
                                if (reportContainer && reportContainer.parentNode) {
                                    reportContainer.parentNode.removeChild(reportContainer);
                                }
                                Swal.fire({ 
                                    icon: 'success', 
                                    title: 'PDF Generated!', 
                                    text: 'Your registration report has been downloaded successfully', 
                                    timer: 2000, 
                                    showConfirmButton: false 
                                });
                            })
                            .catch(err => {
                                // Clean up
                                if (reportContainer && reportContainer.parentNode) {
                                    reportContainer.parentNode.removeChild(reportContainer);
                                }
                                console.error('PDF generation error:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'PDF Generation Failed',
                                    text: 'There was an error generating the PDF. Please try again.',
                                });
                            });
                    }, 1000);
                }

                // Helper Functions for Registration Chart Only
                function createRegistrationPDFContent(chartData, rangeLabel, barangay, chartImageUrl = '') {
                    const container = document.createElement('div');
                    container.style.padding = '20px';
                    container.style.fontFamily = 'Arial, sans-serif';
                    
                    // Add report header
                    const header = document.createElement('div');
                    header.style.textAlign = 'center';
                    header.style.marginBottom = '30px';
                    header.style.borderBottom = '2px solid #333';
                    header.style.paddingBottom = '20px';
                    header.innerHTML = `
                        <h1 style="margin: 0; color: #333;">BCPDO Pre Marriage Orientation & Counseling Registration System</h1>
                        <h2 style="margin: 10px 0; color: #555;">Report: Couple Registration Trend</h2>
                        <p style="margin: 10px 0;"><strong>Date Range:</strong> ${rangeLabel}</p>
                        <p style="margin: 10px 0;"><strong>Generated:</strong> ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                    `;
                    container.appendChild(header);

                    // Embed chart image with proper sizing and fallback
                    if (chartImageUrl && chartImageUrl !== 'data:,') {
                        const imgWrap = document.createElement('div');
                        imgWrap.style.textAlign = 'center';
                        imgWrap.style.margin = '20px 0';
                        imgWrap.style.border = '1px solid #ddd';
                        imgWrap.style.padding = '10px';
                        imgWrap.style.backgroundColor = '#fff';
                        
                        const img = document.createElement('img');
                        img.src = chartImageUrl;
                        img.style.maxWidth = '100%';
                        img.style.height = 'auto';
                        img.style.display = 'block';
                        img.style.margin = '0 auto';
                        img.alt = 'Couple Registration Trend Chart';
                        img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                        
                        // Add chart title
                        const chartTitle = document.createElement('h3');
                        chartTitle.textContent = 'Couple Registration Trend';
                        chartTitle.style.textAlign = 'center';
                        chartTitle.style.margin = '0 0 15px 0';
                        chartTitle.style.color = '#333';
                        
                        imgWrap.appendChild(chartTitle);
                        imgWrap.appendChild(img);
                        container.appendChild(imgWrap);
                    } else {
                        // Fallback: create a placeholder with chart data
                        const fallbackDiv = document.createElement('div');
                        fallbackDiv.style.textAlign = 'center';
                        fallbackDiv.style.margin = '20px 0';
                        fallbackDiv.style.padding = '20px';
                        fallbackDiv.style.border = '2px dashed #ccc';
                        fallbackDiv.style.backgroundColor = '#f9f9f9';
                        fallbackDiv.innerHTML = `
                            <h3 style="margin: 0 0 15px 0; color: #666;">Chart Data (Graph not available)</h3>
                            <p style="color: #888; margin: 0;">The chart image could not be captured. Please refer to the data table below.</p>
                        `;
                        container.appendChild(fallbackDiv);
                    }

                    // Add summary
                    const summary = document.createElement('div');
                    summary.style.margin = '20px 0';
                    summary.style.padding = '15px';
                    summary.style.backgroundColor = '#f9f9f9';
                    summary.style.borderRadius = '5px';
                    summary.innerHTML = `
                        <h3 style="margin: 0 0 15px 0;">Summary</h3>
                        <p style="margin: 5px 0;"><strong>Total Registrations:</strong> ${chartData.datasets[0].data.reduce((a, b) => a + b, 0)}</p>
                        <p style="margin: 5px 0;"><strong>Date Range:</strong> ${chartData.labels[0]} to ${chartData.labels[chartData.labels.length - 1]}</p>
                    `;
                    container.appendChild(summary);

                    // Add data table
                    const table = document.createElement('table');
                    table.style.width = '100%';
                    table.style.borderCollapse = 'collapse';
                    table.style.margin = '20px 0';
                    table.style.border = '1px solid #ddd';
                    
                    // Table header
                    const thead = document.createElement('thead');
                    const headerRow = document.createElement('tr');
                    headerRow.innerHTML = `
                        <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f2f2f2; font-weight: bold;">Period</th>
                        <th style="border: 1px solid #ddd; padding: 12px; text-align: center; background-color: #f2f2f2; font-weight: bold;">No. of Couples</th>
                    `;
                    thead.appendChild(headerRow);
                    table.appendChild(thead);

                    // Table body
                    const tbody = document.createElement('tbody');
                    chartData.labels.forEach((label, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="border: 1px solid #ddd; padding: 8px;">${label}</td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${chartData.datasets[0].data[index] || 0}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    table.appendChild(tbody);
                    container.appendChild(table);

                    // Add enhanced summary
                    const enhancedSummary = document.createElement('div');
                    enhancedSummary.style.margin = '20px 0';
                    enhancedSummary.style.padding = '15px';
                    enhancedSummary.style.backgroundColor = '#f9f9f9';
                    enhancedSummary.style.borderRadius = '5px';
                    
                    const totalRegistrations = chartData.datasets[0].data.reduce((a, b) => a + b, 0);
                    const maxValue = Math.max(...chartData.datasets[0].data);
                    const minValue = Math.min(...chartData.datasets[0].data);
                    const maxIndex = chartData.datasets[0].data.indexOf(maxValue);
                    const minIndex = chartData.datasets[0].data.indexOf(minValue);
                    
                    enhancedSummary.innerHTML = `
                        <h3 style="margin: 0 0 15px 0;">Summary</h3>
                        <p style="margin: 5px 0;"><strong>Total Registrations:</strong> ${totalRegistrations}</p>
                        <p style="margin: 5px 0;"><strong>Highest Registration:</strong> ${maxValue} couples (${chartData.labels[maxIndex]})</p>
                        <p style="margin: 5px 0;"><strong>Lowest Registration:</strong> ${minValue} couples (${chartData.labels[minIndex]})</p>
                        <p style="margin: 5px 0;"><strong>Date Range:</strong> ${chartData.labels[0]} to ${chartData.labels[chartData.labels.length - 1]}</p>
                    `;
                    container.appendChild(enhancedSummary);

                    // Add footer
                    const footer = document.createElement('div');
                    footer.style.marginTop = '30px';
                    footer.style.paddingTop = '20px';
                    footer.style.borderTop = '1px solid #ddd';
                    footer.style.textAlign = 'center';
                    footer.style.fontSize = '12px';
                    footer.style.color = '#666';
                    footer.innerHTML = `
                        <p style="margin: 5px 0;"><strong>Prepared by:</strong> Admin User</p>
                        <p style="margin: 5px 0;">Page 1 of 1</p>
                    `;
                    container.appendChild(footer);

                    return container;
                }

                function prepareRegistrationExcelData() {
                    const data = [];
                    const rangeLabel = document.getElementById('rangeLabel').textContent;
                    const chartData = window.registrationChart.data;
                    
                    // Add summary data
                    data.push({
                        'Report Type': 'BCPDO Pre Marriage Orientation & Counseling Registration System',
                        'Report': 'Couple Registration Trend',
                        'Date Range': rangeLabel,
                        'Generated': new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                    });
                    
                    // Add empty row for separation
                    data.push({});
                    
                    // Add chart data
                    chartData.labels.forEach((label, index) => {
                        data.push({
                            'Period': label,
                            'No. of Couples': chartData.datasets[0].data[index] || 0
                        });
                    });
                    
                    // Add empty row for separation
                    data.push({});
                    
                    // Add summary statistics
                    const totalRegistrations = chartData.datasets[0].data.reduce((a, b) => a + b, 0);
                    const maxValue = Math.max(...chartData.datasets[0].data);
                    const minValue = Math.min(...chartData.datasets[0].data);
                    const maxIndex = chartData.datasets[0].data.indexOf(maxValue);
                    const minIndex = chartData.datasets[0].data.indexOf(minValue);
                    
                    data.push({
                        'Summary': '',
                        '': ''
                    });
                    data.push({
                        'Total Registrations': totalRegistrations,
                        '': ''
                    });
                    data.push({
                        'Highest Registration': `${maxValue} couples (${chartData.labels[maxIndex]})`,
                        '': ''
                    });
                    data.push({
                        'Lowest Registration': `${minValue} couples (${chartData.labels[minIndex]})`,
                        '': ''
                    });
                    
                    return data;
                }
            });
                }); // End of waitForJQuery function
        </script>
        <style>
            /* Compact scheduling card tweaks */
            .scheduling-compact .badge { font-size: .75rem; padding: .25rem .5rem; }
            .scheduling-compact .font-weight-600 { font-weight: 600; }
            .scheduling-compact .xsmall { font-size: .75rem; }
        </style>
    </div>
</body>

</html>