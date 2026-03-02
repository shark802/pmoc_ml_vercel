<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1); // Log errors instead
require '../includes/session.php';
require '../includes/conn.php';
// Load scheduling capacity configuration
$schedConfig = require_once '../includes/scheduling_config.php';
include '../includes/header.php';

function fetchSchedules($conn)
{
    $schedules = [];
    $stmt = $conn->prepare("
        SELECT 
            s.schedule_id, 
            s.session_date,
            s.session_type, 
            s.status,
            s.access_id,
            ca.access_code,
            GROUP_CONCAT(
                CONCAT(cp.first_name, ' ', cp.last_name, ' (', TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE()), ')')
                ORDER BY cp.sex DESC SEPARATOR ' & '
            ) as couple_names,
            MIN(TIMESTAMPDIFF(YEAR, cp.date_of_birth, CURDATE())) as min_age,
            -- Get attendance status from attendance_logs
            CASE 
                WHEN EXISTS(SELECT 1 FROM attendance_logs al WHERE al.schedule_id = s.schedule_id AND al.status = 'present') THEN 'present'
                WHEN EXISTS(SELECT 1 FROM attendance_logs al WHERE al.schedule_id = s.schedule_id AND al.status = 'absent') THEN 'absent'
                ELSE 'pending'
            END as attendance_status
        FROM scheduling s
        INNER JOIN couple_access ca ON s.access_id = ca.access_id
        INNER JOIN couple_profile cp ON ca.access_id = cp.access_id
        GROUP BY s.schedule_id
        ORDER BY FIELD(s.status,'pending','reschedule_requested','confirmed') ASC, s.created_at DESC, s.schedule_id DESC
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    return $schedules;
}

$schedules = fetchSchedules($conn);
$bookedCounts = [];
foreach ($schedules as $schedule) {
    if (empty($schedule['session_date'])) continue;

    $date = $schedule['session_date'];
    $type = $schedule['session_type'];

    // Debug: Log session types
    error_log("Session type: " . $type . " for date: " . $date);

    if (!isset($bookedCounts[$date])) {
        $bookedCounts[$date] = ['Orientation' => 0, 'Counseling' => 0];
    }

    if ($type === 'Orientation + Counseling') {
        $bookedCounts[$date]['Orientation']++;
        $bookedCounts[$date]['Counseling']++;
    } elseif (isset($bookedCounts[$date][$type])) {
        $bookedCounts[$date][$type]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Couple Scheduling</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --orientation-color: #28a745;
            --counseling-color: #ffc107;
            --full-color: #dc3545;
            --pending-color: #ffc107;
            --confirmed-color: #28a745;
        }

        .badge-pending {
            background-color: var(--pending-color);
            color: #212529;
        }

        .badge-confirmed {
            background-color: var(--confirmed-color);
            color: white;
        }

        .slot-info {
            position: absolute;
            top: 22px; /* leave space for day number */
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 2px;
            font-size: 12px;
            line-height: 1.2;
            transition: all 0.2s ease;
            z-index: 5;
            pointer-events: none; /* keep cell clicks working */
        }

        .slot-title { font-size: 15px; line-height: 1.2; font-weight: 700; }
        .slot-count { font-size: 16px; line-height: 1.2; font-weight: 700; opacity: 0.95; }

        .slot-orientation {
            background-color: var(--orientation-color) !important;
            color: white;
            border-radius: 2px;
            text-align: center;
            padding: 4px 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1 1 0;
        }

        .slot-counseling {
            background-color: var(--counseling-color) !important;
            color: #212529;
            border-radius: 2px;
            text-align: center;
            padding: 4px 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1 1 0;
        }

        .slot-full {
            background-color: var(--full-color) !important;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .fc-day.fc-past {
            pointer-events: none;
            cursor: not-allowed;
            background-color: #f5f5f5 !important;
            opacity: 0.6;
        }

        .fc-day.fc-tue,
        .fc-day.fc-fri {
            background-color: #f8f9fa;
            position: relative;
            min-height: 90px;
            transition: all 0.3s ease;
        }

        .fc-day.fc-tue:hover,
        .fc-day.fc-fri:hover {
            background-color: #e9ecef !important;
            transform: scale(1.02);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1;
        }

        .legend {
            display: inline-block;
            margin-right: 20px;
            font-size: 16px;
            font-weight: bold;
        }

        .legend-color {
            display: inline-block;
            width: 30px;
            height: 30px;
            margin-right: 8px;
            vertical-align: middle;
            border-radius: 4px;
        }

        .legend-orientation {
            background-color: var(--orientation-color);
        }

        .legend-counseling {
            background-color: var(--counseling-color);
        }

        /* Sidebar fixes */
        .wrapper {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        .content-wrapper {
            flex: 1;
            overflow: auto;
            margin-left: 250px;
            padding-top: 20px;
        }

        .main-sidebar {
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            width: 250px;
        }

        @media (max-width: 767.98px) {
            .content-wrapper {
                margin-left: 0;
                padding-top: 60px;
            }

            .main-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar-open .main-sidebar {
                transform: translateX(0);
            }

            .navbar-toggler {
                display: block;
            }
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        .couple-age {
            font-weight: bold;
        }

        .couple-age.under-26 {
            color: #dc3545;
        }

        /* Card styles */
        .card {
            margin-bottom: 20px;
            border: 1px solid rgba(0, 0, 0, .125);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, .075);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
            transform: translateY(-2px);
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, .125);
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .card-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #495057;
            flex: 1;
        }

        #calendar {
            width: 100%;
            margin: 0;
            padding: 15px 0;
        }

        .calendar-card .card-body {
            padding: 0;
            overflow-x: auto;
        }

        /* Table improvements - Match admin.php and couple_list.php styling */
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            padding: 12px;
        }

        .table td {
            vertical-align: middle;
            padding: 12px;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, .05);
        }

        /* Actions column styling - prevent button stacking */
        .table td:last-child {
            white-space: nowrap;
            min-width: 200px;
        }

        .table td:last-child .btn {
            display: inline-block;
            margin-right: 4px;
            margin-bottom: 2px;
        }

        .table td:last-child .btn:last-child {
            margin-right: 0;
        }







        /* Table responsive improvements */
        .table-responsive {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }





        /* DataTables spacing (desktop) */
        .dataTables_filter,
        .dataTables_length {
            margin: 10px 0;
        }

        /* Loading spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-spinner.show {
            display: flex !important;
            animation: fadeIn 0.3s ease-in-out;
        }

        .spinner-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.4s ease-out;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e3f2fd;
            border-top: 4px solid #2196f3;
            border-right: 4px solid #1976d2;
            border-bottom: 4px solid #0d47a1;
            border-radius: 50%;
            animation: spin 1.2s linear infinite;
            margin: 0 auto 15px;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .spinner-text {
            color: #333;
            font-size: 16px;
            font-weight: 500;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .spinner-subtext {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
            opacity: 0.8;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes fadeIn {
            0% {
                opacity: 0;
            }
            100% {
                opacity: 1;
            }
        }


        /* Responsive table container - Match standard AdminLTE */
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-pending {
            background-color: var(--pending-color);
        }

        .status-confirmed {
            background-color: var(--confirmed-color);
        }

        /* Animation classes */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Mobile specific styles */
        @media (max-width: 767.98px) {
            /* Spinner mobile adjustments */
            .spinner-container {
                padding: 20px;
                margin: 20px;
                max-width: 300px;
            }

            .spinner {
                width: 50px;
                height: 50px;
                margin-bottom: 12px;
            }

            .spinner-text {
                font-size: 14px;
            }

            .spinner-subtext {
                font-size: 12px;
            }
            /* Legend improvements for mobile */
            .legend {
                display: block;
                margin-bottom: 15px;
                margin-right: 0;
                text-align: center;
            }

            .legend-container {
                flex-direction: column;
                gap: 10px !important;
                padding: 15px !important;
            }

            .legend-item {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            /* Calendar improvements */
            #calendar {
                padding: 10px !important;
                font-size: 12px;
            }

            .fc-header-toolbar {
                flex-direction: column;
                gap: 10px;
            }

            .fc-left, .fc-center, .fc-right {
                text-align: center;
            }

            .fc-day-header {
                font-size: 11px;
                padding: 5px 2px;
            }

            .fc-day {
                min-height: 60px;
            }

            .slot-info {
                font-size: 10px;
                padding: 0 2px;
            }

            .slot-info { top: 20px; left: 0; right: 0; bottom: 0; gap: 2px; padding: 2px; }
            .slot-title { font-size: 13px; }
            .slot-count { font-size: 14px; }

            /* Table improvements */
            .table td,
            .table th {
                padding: 8px 4px;
                font-size: 12px;
                white-space: nowrap;
            }

            .table th {
                font-size: 11px;
                padding: 10px 4px;
            }

            /* Button improvements */
            .table td:last-child .btn {
                padding: 4px 6px;
                font-size: 10px;
                min-width: 45px;
                height: 26px;
                margin-right: 2px;
                margin-bottom: 1px;
            }

            .table td:last-child .btn i {
                font-size: 9px;
            }

            .table td:last-child {
                min-width: 180px;
            }

            .action-placeholder {
                width: 50px;
                height: 28px;
            }

            .placeholder-text {
                font-size: 9px;
            }

            /* Card improvements */
            .card-header {
                padding: 0.75rem;
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .card-title {
                font-size: 1.1rem;
                margin-bottom: 0;
            }

            .card-body {
                padding: 0.75rem;
            }

            /* Badge improvements */
            .badge {
                padding: 3px 6px;
                font-size: 9px;
            }

            /* DataTables improvements */
            .dataTables_wrapper {
                padding: 10px;
            }

            .dataTables_filter {
                margin-bottom: 10px;
                text-align: center;
            }

            .dataTables_filter input {
                width: 100%;
                max-width: 200px;
                margin: 0 auto;
            }

            .dataTables_length {
                margin-bottom: 10px;
                text-align: center;
            }

            .dataTables_length select {
                min-width: 60px;
                padding: 4px 20px 4px 8px;
                background-size: 14px;
            }

            .dataTables_info {
                margin-top: 10px;
                text-align: center;
                font-size: 12px;
            }

            .dataTables_paginate {
                margin-top: 15px;
                justify-content: center;
                flex-wrap: wrap;
                gap: 5px;
            }

            .dataTables_paginate .paginate_button {
                padding: 6px 10px;
                margin: 0 2px;
                font-size: 11px;
                min-width: 40px;
            }

            .dataTables_paginate .paginate_button.previous,
            .dataTables_paginate .paginate_button.next {
                min-width: 60px;
            }

            /* Content wrapper adjustments */
            .content-wrapper {
                margin-left: 0;
                padding-top: 60px;
            }

            /* Container adjustments */
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            /* Row spacing */
            .row {
                margin-left: -5px;
                margin-right: -5px;
            }

            .col-md-12 {
                padding-left: 5px;
                padding-right: 5px;
            }
        }

        /* Extra small devices */
        @media (max-width: 575.98px) {
            /* Spinner extra small adjustments */
            .spinner-container {
                padding: 15px;
                margin: 15px;
                max-width: 250px;
            }

            .spinner {
                width: 40px;
                height: 40px;
                margin-bottom: 10px;
            }

            .spinner-text {
                font-size: 13px;
            }

            .spinner-subtext {
                font-size: 11px;
            }
            .legend-container {
                padding: 10px !important;
            }

            .legend-item {
                font-size: 14px;
            }

            #calendar {
                padding: 5px !important;
            }

            .fc-day-header {
                font-size: 10px;
                padding: 3px 1px;
            }

            .slot-orientation,
            .slot-counseling,
            .slot-full {
                font-size: 8px;
                padding: 1px 3px;
            }

            .table td,
            .table th {
                padding: 6px 3px;
                font-size: 11px;
            }

            .table td:last-child .btn {
                padding: 3px 5px;
                font-size: 9px;
                min-width: 40px;
                height: 24px;
                margin-right: 1px;
                margin-bottom: 1px;
            }

            .table td:last-child .btn i {
                font-size: 8px;
            }

            .table td:last-child {
                min-width: 160px;
            }

            .card-header {
                padding: 0.5rem;
            }

            .card-title {
                font-size: 1rem;
            }

            .dataTables_paginate .paginate_button {
                padding: 5px 8px;
                font-size: 10px;
                min-width: 35px;
            }

            .dataTables_paginate .paginate_button.previous,
            .dataTables_paginate .paginate_button.next {
                min-width: 50px;
            }
        }

        /* Table loading state */
        .table-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Empty state styling */
        .table tbody:empty::after {
            content: "No scheduled sessions found";
            display: block;
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="loading-spinner">
        <div class="spinner-container">
            <div class="spinner"></div>
            <p class="spinner-text">Processing...</p>
            <p class="spinner-subtext">Please wait while we save your data</p>
        </div>
    </div>

    <div class="wrapper">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1>Couple Scheduling</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../admin/admin_dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active">Couple Scheduling</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>
            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>

                    

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Scheduled Sessions</h3>
                                    <div class="card-tools" style="display: flex; gap: 10px; align-items: center;">
                                        <button type="button" class="btn btn-sm btn-primary active" data-tab="pending-tab" id="pendingFilter">
                                            <i class="fas fa-clock"></i> Pending/Upcoming
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="completed-tab" id="completedFilter">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="reschedule-tab" id="rescheduleFilter">
                                            <i class="fas fa-calendar-alt"></i> Reschedule
                                        </button>
                                        <button id="scrollToCalendarBtn" class="btn btn-success btn-sm">
                                            <i class="fas fa-plus"></i> Add New Session
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Tab Content -->
                                    <div class="tab-content">
                                        <!-- Pending/Upcoming Sessions Tab -->
                                        <div class="tab-pane fade show active" id="pending-tab">
                                            <div class="table-responsive">
                                                <table id="pendingSchedulesTable" class="table table-bordered table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Couple</th>
                                                            <th>Date</th>
                                                            <th>Session Type</th>
                                                            <th>Status</th>
                                                            <th>Attendance</th>
                                                            <th class="text-center">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $pendingSchedules = array_filter($schedules, function($schedule) {
                                                            $attendanceStatus = $schedule['attendance_status'] ?? 'pending';
                                                            return $attendanceStatus !== 'present' && $attendanceStatus !== 'absent';
                                                        });
                                                        ?>
                                                        <?php if (empty($pendingSchedules)): ?>
                                                            <tr>
                                                                <td colspan="6" class="text-center text-muted">No pending or upcoming sessions found</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($pendingSchedules as $schedule): ?>
                                                                <?php $rowHighlight = (isset($_GET['access_id']) && intval($_GET['access_id']) === intval($schedule['access_id'] ?? 0)); ?>
                                                                <tr class="<?= $rowHighlight ? 'table-warning' : '' ?>">
                                                                    <td><?= str_replace(
                                                                            ['(25)', '(24)', '(23)', '(22)', '(21)', '(20)'],
                                                                            array_map(fn($age) => '<span class="text-danger">(' . $age . ')</span>', range(20, 25)),
                                                                            htmlspecialchars($schedule['couple_names'])
                                                                        ) ?></td>
                                                                    <td><?= date('M d, Y', strtotime($schedule['session_date'])) ?></td>
                                                                    <td>
                                                                        <?php
                                                                        $sessionType = $schedule['session_type'];
                                                                        $badgeClass = 'badge-info'; // Default: blue for Orientation only
                                                                        if (strpos($sessionType, 'Orientation + Counseling') !== false || 
                                                                            (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') !== false)) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Orientation + Counseling
                                                                        } elseif (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') === false) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Counseling only
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sessionType) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-<?= $schedule['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                                            <?= ucfirst($schedule['status']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php
                                                                        $attendanceStatus = $schedule['attendance_status'] ?? 'pending';
                                                                        $attendanceClass = 'badge-secondary';
                                                                        if ($attendanceStatus === 'present') $attendanceClass = 'badge-success';
                                                                        elseif ($attendanceStatus === 'absent') $attendanceClass = 'badge-danger';
                                                                        elseif ($attendanceStatus === 'pending') $attendanceClass = 'badge-warning';
                                                                        ?>
                                                                        <span class="badge <?= $attendanceClass ?>">
                                                                            <?= ucfirst($attendanceStatus) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php 
                                                                            // Always allow editing regardless of status
                                                                            $allowEdit = true;
                                                                        ?>
                                                                        <button onclick="editSchedule(<?= $schedule['schedule_id'] ?>)" class="btn btn-sm btn-outline-primary mr-2" title="Edit">
                                                                            <i class="fas fa-edit"></i> Edit
                                                                        </button>
                                                                        <?php if ($schedule['status'] === 'pending'): ?>
                                                                        <button onclick="oneClickConfirm(<?= $schedule['schedule_id'] ?>)" class="btn btn-sm btn-outline-success mr-2" title="Confirm (walk-in)">
                                                                            <i class="fas fa-check"></i> Confirm
                                                                        </button>
                                                                        <?php elseif ($schedule['status'] === 'confirmed'): ?>
                                                                        <button class="btn btn-sm btn-success mr-2" disabled title="Confirmed">
                                                                            <i class="fas fa-check-circle"></i> Confirmed
                                                                        </button>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Completed Sessions Tab -->
                                        <div class="tab-pane fade" id="completed-tab">
                                            <div class="table-responsive">
                                                <table id="completedSchedulesTable" class="table table-bordered table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Couple</th>
                                                            <th>Date</th>
                                                            <th>Session Type</th>
                                                            <th>Status</th>
                                                            <th>Attendance</th>
                                                            <th class="text-center">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $completedSchedules = array_filter($schedules, function($schedule) {
                                                            $attendanceStatus = $schedule['attendance_status'] ?? 'pending';
                                                            return $attendanceStatus === 'present';
                                                        });
                                                        ?>
                                                        <?php if (empty($completedSchedules)): ?>
                                                            <tr>
                                                                <td colspan="6" class="text-center text-muted">No completed sessions found</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($completedSchedules as $schedule): ?>
                                                                <?php $rowHighlight = (isset($_GET['access_id']) && intval($_GET['access_id']) === intval($schedule['access_id'] ?? 0)); ?>
                                                                <tr class="<?= $rowHighlight ? 'table-warning' : '' ?>">
                                                                    <td><?= str_replace(
                                                                            ['(25)', '(24)', '(23)', '(22)', '(21)', '(20)'],
                                                                            array_map(fn($age) => '<span class="text-danger">(' . $age . ')</span>', range(20, 25)),
                                                                            htmlspecialchars($schedule['couple_names'])
                                                                        ) ?></td>
                                                                    <td><?= date('M d, Y', strtotime($schedule['session_date'])) ?></td>
                                                                    <td>
                                                                        <?php
                                                                        $sessionType = $schedule['session_type'];
                                                                        $badgeClass = 'badge-info'; // Default: blue for Orientation only
                                                                        if (strpos($sessionType, 'Orientation + Counseling') !== false || 
                                                                            (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') !== false)) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Orientation + Counseling
                                                                        } elseif (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') === false) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Counseling only
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sessionType) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-<?= $schedule['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                                            <?= ucfirst($schedule['status']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-success">
                                                                            Present
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="text-muted">No actions available</span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Reschedule Sessions Tab -->
                                        <div class="tab-pane fade" id="reschedule-tab">
                                            <div class="table-responsive">
                                                <table id="rescheduleSchedulesTable" class="table table-bordered table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Couple</th>
                                                            <th>Date</th>
                                                            <th>Session Type</th>
                                                            <th>Status</th>
                                                            <th>Attendance</th>
                                                            <th class="text-center">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $rescheduleSchedules = array_filter($schedules, function($schedule) {
                                                            $attendanceStatus = $schedule['attendance_status'] ?? 'pending';
                                                            return $attendanceStatus === 'absent';
                                                        });
                                                        ?>
                                                        <?php if (empty($rescheduleSchedules)): ?>
                                                            <tr>
                                                                <td colspan="6" class="text-center text-muted">No sessions requiring rescheduling found</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($rescheduleSchedules as $schedule): ?>
                                                                <?php $rowHighlight = (isset($_GET['access_id']) && intval($_GET['access_id']) === intval($schedule['access_id'] ?? 0)); ?>
                                                                <tr class="<?= $rowHighlight ? 'table-warning' : '' ?>">
                                                                    <td><?= str_replace(
                                                                            ['(25)', '(24)', '(23)', '(22)', '(21)', '(20)'],
                                                                            array_map(fn($age) => '<span class="text-danger">(' . $age . ')</span>', range(20, 25)),
                                                                            htmlspecialchars($schedule['couple_names'])
                                                                        ) ?></td>
                                                                    <td><?= date('M d, Y', strtotime($schedule['session_date'])) ?></td>
                                                                    <td>
                                                                        <?php
                                                                        $sessionType = $schedule['session_type'];
                                                                        $badgeClass = 'badge-info'; // Default: blue for Orientation only
                                                                        if (strpos($sessionType, 'Orientation + Counseling') !== false || 
                                                                            (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') !== false)) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Orientation + Counseling
                                                                        } elseif (strpos($sessionType, 'Counseling') !== false && strpos($sessionType, 'Orientation') === false) {
                                                                            $badgeClass = 'badge-warning'; // Yellow/orange for Counseling only
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sessionType) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-<?= $schedule['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                                            <?= ucfirst($schedule['status']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-danger">
                                                                            Absent
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <button onclick="editSchedule(<?= $schedule['schedule_id'] ?>)" class="btn btn-sm btn-outline-warning" title="Reschedule">
                                                                            <i class="fas fa-calendar-alt"></i> Reschedule
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card calendar-card animate__animated animate__fadeIn shadow-sm" id="calendarCard">
                                <div class="card-header bg-white d-flex align-items-center">
                                    <h3 class="card-title mb-0"><i class="fas fa-calendar-alt mr-2"></i> Scheduling Calendar</h3>
                                    <div class="ml-auto d-flex align-items-center legend-inline">
                                        <span class="legend-color legend-orientation mr-2"></span>
                                        <span class="mr-3">Orientation (8AM–12PM)</span>
                                        <span class="legend-color legend-counseling mr-2"></span>
                                        <span>Counseling (1PM–4PM)</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="calendar"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <?php include '../includes/footer.php'; ?>
        <?php include '../modals/scheduling_modal.php'; ?>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js'></script>
    <script>
        $(document).ready(function() {
            // Initialize tooltips safely
            try {
                $('[data-toggle="tooltip"]').tooltip({
                    trigger: 'hover'
                });
            } catch (e) {
                console.log('Initial tooltip initialization skipped:', e.message);
            }

            // Show loading spinner on AJAX requests (but exclude DataTables and other background requests)
            var ajaxRequestCount = 0;
            $(document).ajaxStart(function(event, xhr, settings) {
                // Exclude DataTables internal requests and other background operations
                if (settings && settings.url && (
                    settings.url.includes('DataTables') || 
                    settings.url.includes('datatables') ||
                    settings.url.includes('fullcalendar') ||
                    settings.url.includes('get_notifications') ||
                    settings.url.includes('ping.php')
                )) {
                    return; // Don't show spinner for these
                }
                ajaxRequestCount++;
                if (ajaxRequestCount === 1) {
                    $('.loading-spinner').addClass('show');
                }
            }).ajaxStop(function() {
                ajaxRequestCount = 0;
                $('.loading-spinner').removeClass('show');
            }).ajaxError(function(event, xhr, settings) {
                ajaxRequestCount = 0;
                $('.loading-spinner').removeClass('show');
            });

            <?php if (!empty($_SESSION['error_message'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= addslashes($_SESSION['error_message']) ?>',
                    showClass: {
                        popup: 'animate__animated animate__shakeX'
                    }
                });
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php // success_message toast now emitted globally in includes/scripts.php ?>

            const bookedCounts = <?= json_encode($bookedCounts) ?>;
            const capOrientation = <?= (int)($schedConfig['capacity']['Orientation'] ?? 0) ?>;
            const capCounseling  = <?= (int)($schedConfig['capacity']['Counseling'] ?? 0) ?>;
            console.log('Booked counts data:', bookedCounts);

            // Initialize calendar with simplified header
            const calendar = $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                // Mobile responsive calendar settings
                height: 'auto',
                aspectRatio: window.innerWidth <= 768 ? 1.0 : 1.3,
                defaultView: 'month',
                editable: false,
                eventLimit: true,
                navLinks: true,
                nowIndicator: true,
                eventRender: function(event, element) {
                    element.remove();
                },
                dayRender: function(date, cell) {
                    const dateStr = date.format('YYYY-MM-DD');
                    const dayOfWeek = date.day();

                    if (dayOfWeek === 2 || dayOfWeek === 5) {
                        const orientationCount = bookedCounts[dateStr]?.Orientation || 0;
                        const counselingCount = bookedCounts[dateStr]?.Counseling || 0;
                        const orientationFull = capOrientation > 0 && orientationCount >= capOrientation;
                        const counselingFull  = capCounseling  > 0 && counselingCount  >= capCounseling;

                        console.log(`Date: ${dateStr}, Orientation: ${orientationCount}, Counseling: ${counselingCount}`);

                        const slotInfo = `
                            <div class="slot-info">
                                <div class="${orientationFull ? 'slot-full' : 'slot-orientation'}" 
                                     data-toggle="tooltip" 
                                     title="${orientationFull ? 'Fully booked' : 'Available slots'}"
                                     style="display: block !important; visibility: visible !important;">
                                    <div class="slot-title">Orientation:</div>
                                    <div class="slot-count">${orientationCount}/${capOrientation} slots</div>
                                </div>
                                <div class="${counselingFull ? 'slot-full' : 'slot-counseling'}"
                                     data-toggle="tooltip" 
                                     title="${counselingFull ? 'Fully booked' : 'Available slots'}"
                                     style="display: block !important; visibility: visible !important;">
                                    <div class="slot-title">Counseling:</div>
                                    <div class="slot-count">${counselingCount}/${capCounseling} slots</div>
                                </div>
                            </div>
                        `;
                        $(cell).append(slotInfo);
                    }

                    if (date.isBefore(moment().startOf('day'))) {
                        $(cell).addClass('fc-past');
                    }
                },
                dayClick: function(date, jsEvent, view) {
                    // Validate date first
                    if (date.isBefore(moment().startOf('day'))) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Cannot schedule in the past',
                            showClass: {
                                popup: 'animate__animated animate__headShake'
                            },
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        });
                        return;
                    }

                    if (date.day() !== 2 && date.day() !== 5) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Day',
                            text: 'Scheduling only available on Tuesdays and Fridays',
                            showClass: {
                                popup: 'animate__animated animate__headShake'
                            },
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        });
                        return;
                    }

                    const twoMonthsLater = moment().add(6, 'months');
                    if (date.isAfter(twoMonthsLater)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Date',
                            text: 'Scheduling is only allowed within 6 months from today',
                            showClass: {
                                popup: 'animate__animated animate__headShake'
                            },
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        });
                        return;
                    }

                    // Revert to modal flow: open modal and prefill
                    $('#addScheduleModal').modal('show');
                    setTimeout(() => {
                        $('#session_month').val(date.month() + 1);
                        $('#session_day').val(date.date());
                        $('#session_year').val(date.year());
                        // ensure day max is correct
                        const month = $('#session_month').val();
                        const year = $('#session_year').val();
                        if (month && year) {
                            let maxDays = 31;
                            if ([4,6,9,11].includes(parseInt(month))) maxDays = 30; else if (month == 2) maxDays = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
                            $('#session_day').attr('max', maxDays);
                        }
                    }, 50);
                },
                viewRender: function(view, element) {
                    // Update tooltips when view changes
                    setTimeout(function() {
                        $('[data-toggle="tooltip"]').each(function() {
                            safeTooltipDispose(this);
                        });
                        
                        // Initialize new tooltips
                        $('[data-toggle="tooltip"]').each(function() {
                            safeTooltipInit(this);
                        });
                    }, 100);
                }
            });

            // Refresh calendar when modal is closed
            $('#addScheduleModal').on('hidden.bs.modal', function() {
                $('#calendar').fullCalendar('refetchEvents');
            });

            // Mobile sidebar toggle
            $('.navbar-toggler').on('click', function() {
                $('body').toggleClass('sidebar-open');
            });

            // Scroll to calendar instead of opening modal
            $('#scrollToCalendarBtn').on('click', function(e){
                e.preventDefault();
                const $card = $('#calendarCard');
                if ($card.length){
                    $('html, body').animate({ scrollTop: $card.offset().top - 80 }, 250);
                }
            });
        });

        $(document).ready(function() {
            // Check URL parameter for tab
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            // Initialize DataTables for all three tables - only if table has data rows
            var pendingTable = null;
            var completedTable = null;
            var rescheduleTable = null;
            
            // Helper function to check if table has actual data (not empty message)
            function hasTableData(tableId) {
                const $table = $(tableId);
                if (!$table.length) return false;
                const $rows = $table.find('tbody tr');
                if ($rows.length === 0) return false;
                // Check if first row contains "No ... found" message
                const firstRowText = $rows.first().find('td').first().text().toLowerCase();
                if (firstRowText.includes('no ') && firstRowText.includes('found')) {
                    return false;
                }
                return true;
            }
            
            // Helper function to initialize DataTable safely
            function initDataTableSafely(tableId, options) {
                try {
                    if (!$.fn.DataTable) {
                        console.warn('DataTables not loaded');
                        return null;
                    }
                    
                    // Destroy existing instance if any
                    if ($.fn.dataTable.isDataTable(tableId)) {
                        $(tableId).DataTable().destroy();
                    }
                    
                    // Only initialize if table has data
                    if (hasTableData(tableId)) {
                        return $(tableId).DataTable(options);
                    }
                } catch (e) {
                    console.error('Error initializing DataTable for ' + tableId + ':', e);
                }
                return null;
            }
            
            // Initialize pending table (active tab by default)
            if (hasTableData('#pendingSchedulesTable')) {
                pendingTable = initDataTableSafely('#pendingSchedulesTable', {
                    "responsive": true,
                    "autoWidth": false,
                    "order": [],
                    "columnDefs": [
                        { targets: [2,3,4], className: 'text-center' },
                        { targets: 5, orderable: false }
                    ]
                });
            }
            
            // Handle tab switching with buttons
            $('button[data-tab]').on('click', function(e) {
                e.preventDefault();
                
                // Update active button
                $('button[data-tab]').removeClass('btn-primary active').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
                
                // Hide all tab panes
                $('.tab-pane').removeClass('show active');
                
                // Show selected tab pane
                const targetTab = $(this).data('tab');
                const $targetPane = $('#' + targetTab);
                $targetPane.addClass('show active');
                
                // Initialize DataTable for the newly shown tab if not already initialized
                setTimeout(function() {
                    try {
                        if (targetTab === 'pending-tab') {
                            if (!pendingTable && hasTableData('#pendingSchedulesTable')) {
                                pendingTable = initDataTableSafely('#pendingSchedulesTable', {
                                    "responsive": true,
                                    "autoWidth": false,
                                    "order": [],
                                    "columnDefs": [
                                        { targets: [2,3,4], className: 'text-center' },
                                        { targets: 5, orderable: false }
                                    ]
                                });
                            }
                            if (pendingTable) {
                                pendingTable.columns.adjust();
                            }
                        } else if (targetTab === 'completed-tab') {
                            if (!completedTable && hasTableData('#completedSchedulesTable')) {
                                completedTable = initDataTableSafely('#completedSchedulesTable', {
                                    "responsive": true,
                                    "autoWidth": false,
                                    "order": [[1, 'desc']],
                                    "columnDefs": [
                                        { targets: [2,3,4], className: 'text-center' },
                                        { targets: 5, orderable: false }
                                    ]
                                });
                            }
                            if (completedTable) {
                                completedTable.columns.adjust();
                            }
                        } else if (targetTab === 'reschedule-tab') {
                            if (!rescheduleTable && hasTableData('#rescheduleSchedulesTable')) {
                                rescheduleTable = initDataTableSafely('#rescheduleSchedulesTable', {
                                    "responsive": true,
                                    "autoWidth": false,
                                    "order": [[1, 'desc']],
                                    "columnDefs": [
                                        { targets: [2,3,4], className: 'text-center' },
                                        { targets: 5, orderable: false }
                                    ]
                                });
                            }
                            if (rescheduleTable) {
                                rescheduleTable.columns.adjust();
                            }
                        }
                    } catch (e) {
                        console.error('Error in tab switch handler:', e);
                    }
                }, 100);
            });

            // Handle window resize for mobile responsiveness
            $(window).resize(function() {
                // Resize calendar for mobile
                if (window.innerWidth <= 768) {
                    $('#calendar').fullCalendar('option', 'aspectRatio', 1.2);
                } else {
                    $('#calendar').fullCalendar('option', 'aspectRatio', 1.35);
                }
                $('#calendar').fullCalendar('render');
            });
            
            // Switch to tab if specified in URL parameter (after event handlers are set up)
            if (tabParam === 'completed') {
                setTimeout(function() {
                    $('#completedFilter').trigger('click');
                }, 100);
            } else if (tabParam === 'reschedule') {
                setTimeout(function() {
                    $('#rescheduleFilter').trigger('click');
                }, 100);
            }
        });


        function editSchedule(scheduleId) {
            // Show loading spinner
            $('.spinner-text').text('Loading Schedule...');
            $('.spinner-subtext').text('Please wait while we fetch the schedule details');
            $('.loading-spinner').addClass('show');
            
            // Fetch schedule data via AJAX
            $.ajax({
                url: 'couple_scheduling_get.php',
                type: 'GET',
                data: { id: scheduleId },
                dataType: 'json',
                success: function(data) {
                    // Populate modal fields
                    $('#edit_schedule_id').val(data.schedule_id);
                    $('#edit_couple_names').val(data.couple_names);
                    $('#edit_session_month').val(data.month);
                    $('#edit_session_day').val(data.day);
                    $('#edit_session_year').val(new Date().getFullYear()); // Use current year
                    $('#edit_status').val(data.status);
                    // Update status badge
                    var badge = $('#edit_status_badge');
                    var text = (data.status || '').replace(/_/g,' ');
                    badge.text(text.charAt(0).toUpperCase() + text.slice(1));
                    badge.removeClass('badge-secondary badge-warning badge-info');
                    if (data.status === 'pending') badge.addClass('badge-warning');
                    else if (data.status === 'reschedule_requested') badge.addClass('badge-info');
                    else badge.addClass('badge-secondary');
                    
                    // Handle age warning and auto-select session type
                    if (data.min_age <= 25) {
                        $('#edit_age_warning').show().data('min-age', data.min_age);
                        $('#edit_type_orientation').prop('disabled', true);
                        $('#edit_sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                        // Auto-select Orientation + Counseling for young couples
                        $('input[name="session_type"]').prop('checked', false);
                        $('#edit_type_both').prop('checked', true);
                    } else {
                        $('#edit_age_warning').hide();
                        $('#edit_type_orientation').prop('disabled', false);
                        $('#edit_sessionTypeHelp').html('<span class="text-success">Orientation is sufficient. Counseling optional upon request.</span>');
                        // Auto-select Orientation for older couples
                        $('input[name="session_type"]').prop('checked', false);
                        $('#edit_type_orientation').prop('checked', true);
                    }
                    
                    // Validate day input
                    validateEditDayInput();
                    
                    // If navigated via reschedule deep link, set reschedule flag and adjust title/button
                    try {
                        const params = new URLSearchParams(window.location.search);
                        const isReschedule = params.get('action') === 'reschedule';
                        if (isReschedule) {
                            $('#edit_reschedule_flag').val('1');
                            $('#editScheduleModalLabel').text('Reschedule Session');
                            $('#editScheduleForm .btn-primary').text('Save (Set to Pending)');
                        } else {
                            $('#edit_reschedule_flag').val('0');
                            $('#editScheduleModalLabel').text('Edit Schedule');
                            $('#editScheduleForm .btn-primary').text('Update Schedule');
                        }
                    } catch (e) {}

                    // Show modal
                    $('#editScheduleModal').modal('show');
                    $('.loading-spinner').removeClass('show');
                },
                error: function() {
                    $('.loading-spinner').removeClass('show');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load schedule data',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                }
            });
        }

        function oneClickConfirm(scheduleId) {
            Swal.fire({
                title: 'Confirm Session?',
                text: 'Mark this schedule as confirmed (walk-in).',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, confirm'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('.spinner-text').text('Confirming Schedule...');
                    $('.spinner-subtext').text('Please wait while we update the status');
                    $('.loading-spinner').addClass('show');
                    $.ajax({
                        url: 'couple_scheduling_confirm.php',
                        type: 'POST',
                        data: { action: 'admin_confirm', schedule_id: scheduleId },
                        dataType: 'json',
                        success: function(resp){
                            $('.loading-spinner').removeClass('show');
                            if (resp && resp.success){
                                // Remove deep-link params so row highlight and reschedule state are cleared after confirm
                                try { if (window.location.search) history.replaceState(null, '', window.location.pathname); } catch(e){}
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Confirmed!',
                                    text: resp.message || 'Schedule confirmed successfully',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 1800,
                                    timerProgressBar: true,
                                    showConfirmButton: false
                                });
                                
                                // Update the button to show "Confirmed" instead of reloading
                                var confirmBtn = $('button[onclick="oneClickConfirm(' + scheduleId + ')"]');
                                if (confirmBtn.length) {
                                    // Get the row before replacing the button
                                    var row = confirmBtn.closest('tr');
                                    
                                    // Replace the button
                                    confirmBtn.replaceWith(
                                        '<button class="btn btn-sm btn-success mr-2" disabled title="Confirmed">' +
                                        '<i class="fas fa-check-circle"></i> Confirmed' +
                                        '</button>'
                                    );
                                    
                                    // Update status badge in the same row
                                    var statusBadge = row.find('td').eq(3).find('.badge');
                                    if (statusBadge.length) {
                                        statusBadge.removeClass('badge-warning').addClass('badge-success').text('Confirmed');
                                    }
                                }
                            } else {
                                Swal.fire({ icon:'error', title:'Error', text: (resp && resp.message) ? resp.message : 'Failed to confirm schedule' });
                            }
                        },
                        error: function(xhr, status, error){
                            $('.loading-spinner').removeClass('show');
                            console.error('AJAX Error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                            
                            // Try to parse error response
                            let errorMsg = 'Failed to confirm schedule. Please try again.';
                            try {
                                if (xhr.responseText) {
                                    const errorResponse = JSON.parse(xhr.responseText);
                                    if (errorResponse.message) {
                                        errorMsg = errorResponse.message;
                                    }
                                }
                            } catch (e) {
                                // If response is not JSON, use default message
                                if (xhr.status === 404) {
                                    errorMsg = 'Schedule not found.';
                                } else if (xhr.status === 500) {
                                    errorMsg = 'Server error occurred. Please check the logs.';
                                }
                            }
                            
                            Swal.fire({ 
                                icon:'error', 
                                title:'Error', 
                                text: errorMsg,
                                timer: 5000,
                                showConfirmButton: true
                            });
                        }
                    });
                }
            });
        }

        function confirmSchedule(scheduleId) {
            Swal.fire({
                title: 'Confirm Attendance',
                text: "Mark this session as completed?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, confirm',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading('Confirming Attendance...', 'Please wait while we update the attendance status');
                    window.location.href = 'couple_scheduling_confirm.php?id=' + scheduleId;
                }
            });
        }

        function showLoading(message = 'Processing...', submessage = 'Please wait while we process your request') {
            $('.spinner-text').text(message);
            $('.spinner-subtext').text(submessage);
            $('.loading-spinner').addClass('show');
        }

        function validateEditDayInput() {
            const month = $('#edit_session_month').val();
            const year = $('#edit_session_year').val();

            if (!month || !year) return;

            // Set max days based on month
            let maxDays = 31;
            if ([4, 6, 9, 11].includes(parseInt(month))) {
                maxDays = 30;
            } else if (month == 2) {
                maxDays = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
            }
            $('#edit_session_day').attr('max', maxDays);
        }

        // Safe tooltip management function
        function safeTooltipDispose(element) {
            try {
                if (element && $(element).data('bs.tooltip')) {
                    $(element).tooltip('dispose');
                }
            } catch (e) {
                console.log('Tooltip dispose error:', e.message);
            }
        }

        function safeTooltipInit(element) {
            try {
                if (element && $(element).attr('data-toggle') === 'tooltip') {
                    $(element).tooltip({
                        trigger: 'hover'
                    });
                }
            } catch (e) {
                console.log('Tooltip init error:', e.message);
            }
        }

        // Modal functionality
        $(document).ready(function() {
            // Track how modal was opened
            let modalTrigger = null;

            // Button click handler
            $('[data-target="#addScheduleModal"]').click(function() {
                modalTrigger = 'button';
                // Clear date fields
                $('#session_month').val('');
                $('#session_day').val('');
            });

            // Calendar click handler (from couple_scheduling.php)
            window.calendarDayClick = function(date) {
                modalTrigger = 'calendar';
                window.calendarClickedDate = date.toDate();
            };

            // Modal show handler
            $('#addScheduleModal').on('show.bs.modal', function(e) {
                // Clear all fields
                $('#couple_code').val('');
                $('input[name="session_type"]').prop('checked', false).prop('disabled', false);
                $('#sessionTypeHelp').text('');

                // Pre-fill date if we have a clicked date
                if (window.calendarClickedDate) {
                    $('#session_month').val(window.calendarClickedDate.month);
                    $('#session_day').val(window.calendarClickedDate.day);
                    $('#session_year').val(window.calendarClickedDate.year);
                    validateDayInput();
                }
            });

            // Modal shown handler - trigger couple selection logic if couple is already selected
            $('#addScheduleModal').on('shown.bs.modal', function(e) {
                // If couple is already selected, trigger the change event
                if ($('#couple_code').val() !== '') {
                    $('#couple_code').trigger('change');
                }
            });

            // Reset when modal closes
            $('#addScheduleModal').on('hidden.bs.modal', function() {
                window.calendarClickedDate = null;
            });

            // Date validation functions
            function validateDayInput() {
                const month = $('#session_month').val();
                const year = $('#session_year').val();

                if (!month || !year) return;

                // Set max days based on month
                let maxDays = 31;
                if ([4, 6, 9, 11].includes(parseInt(month))) {
                    maxDays = 30;
                } else if (month == 2) {
                    maxDays = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
                }
                $('#session_day').attr('max', maxDays);
            }

            // Update session type help text when couple is selected
            $('#couple_code').change(function() {
                const selectedOption = $(this).find('option:selected');
                const minAge = selectedOption.data('min-age');

                if (selectedOption.val() === '') {
                    $('#sessionTypeHelp').text('');
                    // Clear radio button selections
                    $('input[name="session_type"]').prop('checked', false);
                    $('#type_orientation').prop('disabled', false);
                    return;
                }

                if (minAge <= 25) {
                    $('#sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                    $('#type_orientation').prop('disabled', true);
                    $('#type_both').prop('disabled', false);
                    // Auto-select Orientation + Counseling
                    $('#type_both').prop('checked', true);
                } else {
                    $('#sessionTypeHelp').html('<span class="text-success">Orientation is sufficient. Counseling optional upon request.</span>');
                    $('#type_orientation').prop('disabled', false);
                    $('#type_both').prop('disabled', false);
                    // Auto-select Orientation
                    $('#type_orientation').prop('checked', true);
                }
            });

            $('#session_month, #session_day').on('change input', validateDayInput);

            $('input[name="session_type"]').change(function() {
                const minAge = $('#couple_code option:selected').data('min-age');
                if (minAge <= 25 && $(this).val() === 'Orientation') {
                    $('#sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                    $('#type_orientation').addClass('is-invalid');
                } else {
                    $('#type_orientation').removeClass('is-invalid');
                    if (minAge <= 25) {
                        $('#sessionTypeHelp').html('<span class="text-success">Valid selection - counseling is required</span>');
                    } else {
                        $('#sessionTypeHelp').html('<span class="text-success">Orientation is sufficient. Counseling optional upon request.</span>');
                    }
                }
            });

            // Inline form validation mirrors modal form
            $('#inlineScheduleForm').submit(function(e) {
                $('.is-invalid').removeClass('is-invalid');
                let isValid = true;
                if ($('#inline_couple_code').val() === '') { $('#inline_couple_code').addClass('is-invalid'); isValid = false; }
                if ($('#inline_session_month').val() === '' || $('#inline_session_day').val() === '') { $('#inline_session_month, #inline_session_day').addClass('is-invalid'); isValid = false; }
                if (!$('input[name="session_type"]:checked').length) { $('#inline_type_orientation').addClass('is-invalid'); isValid = false; }
                const minAge = (typeof data.min_age === 'number' ? data.min_age : 100);
                if (minAge <= 25 && $('input[name="session_type"]:checked').val() === 'Orientation') {
                    $('#inline_type_orientation').addClass('is-invalid');
                    $('#inline_sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                    isValid = false;
                }
                if (!isValid) {
                    e.preventDefault();
                    Swal.fire({ icon:'error', title:'Validation Error', text:'Please fix the errors in the form before submitting.', showClass:{ popup:'animate__animated animate__shakeX' }, timer:4000, timerProgressBar:true, showConfirmButton:false });
                    return false;
                }
                // Do not show page-level loading overlay on full form submit
                return true;
            });

            $('#inline_couple_code').change(function(){
                const selected = $(this).find('option:selected');
                const minAge = selected.data('min-age');
                if (selected.val() === '') { $('#inline_sessionTypeHelp').text(''); $('input[name="session_type"]').prop('checked', false); $('#inline_type_orientation').prop('disabled', false); return; }
                if (minAge <= 25) { $('#inline_sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>'); $('#inline_type_orientation').prop('disabled', true); $('#inline_type_both').prop('checked', true); }
                else { $('#inline_sessionTypeHelp').html('<span class="text-success">Orientation is sufficient. Counseling optional upon request.</span>'); $('#inline_type_orientation').prop('disabled', false); $('#inline_type_orientation').prop('checked', true); }
            });

            function validateInlineDayInput(){
                const month = $('#inline_session_month').val();
                const year = $('#inline_session_year').val();
                if (!month || !year) return;
                let maxDays = 31;
                if ([4,6,9,11].includes(parseInt(month))) maxDays = 30; else if (month == 2) maxDays = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
                $('#inline_session_day').attr('max', maxDays);
            }
            $('#inline_session_month, #inline_session_day').on('change input', validateInlineDayInput);

            $('#scheduleForm').submit(function(e) {
                // Clear previous invalid states
                $('.is-invalid').removeClass('is-invalid');

                // Basic validation
                let isValid = true;
                if ($('#couple_code').val() === '') {
                    $('#couple_code').addClass('is-invalid');
                    isValid = false;
                }
                if ($('#session_month').val() === '' || $('#session_day').val() === '') {
                    $('#session_month, #session_day').addClass('is-invalid');
                    isValid = false;
                }
                if (!$('input[name="session_type"]:checked').length) {
                    $('#type_orientation').addClass('is-invalid');
                    isValid = false;
                }

                // Age validation
                const minAge = $('#couple_code option:selected').data('min-age');
                if (minAge <= 25 && $('input[name="session_type"]:checked').val() === 'Orientation') {
                    $('#type_orientation').addClass('is-invalid');
                    $('#sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    // Show error message but keep modal open
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fix the errors in the form before submitting.',
                        showClass: {
                            popup: 'animate__animated animate__shakeX'
                        },
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    return false;
                }

                // Do not show loading overlay on full page submit to avoid persistent spinner on redirect
                return true;
            });

            $('#edit_session_month, #edit_session_day').on('change input', validateEditDayInput);

            // Edit form validation and submission
                function clearDateInlineError(){ $('#edit_date_error').hide().text(''); $('#edit_session_day').removeClass('is-invalid'); $('#edit_session_month').removeClass('is-invalid'); }

                $('#edit_session_month, #edit_session_day').on('change input', function(){
                    // Clear inline date error when admin changes the date
                    clearDateInlineError();
                });

                $('#editScheduleForm').submit(function(e) {
                e.preventDefault();
                $('#edit_form_error').hide().text('');
                clearDateInlineError();
                
                // Clear previous invalid states
                $('.is-invalid').removeClass('is-invalid');

                // Basic validation
                let isValid = true;
                if ($('#edit_session_month').val() === '' || $('#edit_session_day').val() === '') {
                    $('#edit_session_month, #edit_session_day').addClass('is-invalid');
                    isValid = false;
                }
                if (!$('input[name="session_type"]:checked').length) {
                    $('#edit_type_orientation').addClass('is-invalid');
                    isValid = false;
                }

                // Age validation
                const minAge = parseInt($('#edit_age_warning').data('min-age') || 100, 10);
                if (minAge <= 25 && $('input[name="session_type"]:checked').val() === 'Orientation') {
                    $('#edit_type_orientation').addClass('is-invalid');
                    $('#edit_sessionTypeHelp').html('<span class="text-danger">Orientation + Counseling is MANDATORY for couples with one or both partners age 25 or below</span>');
                    isValid = false;
                }

                if (!isValid) {
                    $('#edit_form_error').text('Please fix the errors in the form before submitting.').show();
                    return false;
                }

                // Show loading spinner
                $('.spinner-text').text('Updating Schedule...');
                $('.spinner-subtext').text('Please wait while we save your changes');
                $('.loading-spinner').addClass('show');
                
                // Submit form via AJAX
                $.ajax({
                    url: 'couple_scheduling_edit.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                    $('.loading-spinner').removeClass('show');
                    if (response.success) {
                        $('#editScheduleModal').modal('hide');
                        location.search = '';
                        location.reload();
                    } else {
                    // If server returns a Tuesday/Friday validation message, show it inline under date
                    var msg = (response && response.message) ? response.message : 'Failed to update schedule';
                    if (/tuesday|friday/i.test(msg) || /past/i.test(msg)) {
                        $('#edit_date_error').html(msg).show();
                        $('#edit_session_day').addClass('is-invalid');
                        $('#edit_session_month').addClass('is-invalid');
                    } else {
                        $('#edit_form_error').html(msg).show();
                    }
                    }
                },
                error: function(xhr, status, error) {
                    $('.loading-spinner').removeClass('show');
                    $('#edit_form_error').text('Failed to update schedule. Please try again.').show();
                }
                });
            });
        });

        function rescheduleSession(scheduleId, accessId, dateStr) {
            Swal.fire({
                title: 'Reschedule Session',
                text: 'This will notify the admin to reschedule this session.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reschedule',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('../includes/get_notifications.php', { 
                        action: 'create_reschedule_notice', 
                        schedule_id: scheduleId, 
                        access_id: accessId, 
                        date: dateStr 
                    }, function(resp) {
                        Swal.fire({
                            title: 'Reschedule Requested',
                            text: 'Admin has been notified to reschedule this session.',
                            icon: 'info',
                            timer: 3000,
                            showConfirmButton: false
                        });
                        // Optionally reload the page to refresh the table
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }).fail(function() {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Error', 
                            text: 'Failed to notify admin. Please try again.' 
                        });
                    });
                }
            });
        }
    </script>
</body>

</html>
