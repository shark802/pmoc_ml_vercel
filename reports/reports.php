<?php
require_once '../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports | BCPDO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .report-card { box-shadow: 0 4px 12px rgba(0,0,0,.07); }
        .table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
        .small-note { font-size: .85rem; color: #6c757d; }
        /* Add comfortable spacing between action buttons (Excel / PDF / Print) */
        .report-actions, .report-actions-pmc { display: flex; align-items: center; gap: 10px; }
        .report-actions .btn, .report-actions-pmc .btn { min-width: 92px; }
        
        /* Tab styling - blue highlight for active tab */
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            border-bottom: 2px solid transparent;
            background-color: transparent;
        }
        .nav-tabs .nav-link:hover {
            color: #007bff;
            border-bottom-color: #dee2e6;
            background-color: transparent;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            background-color: #fff;
            border-bottom: 2px solid #007bff;
            font-weight: 600;
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
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
                    <div class="row mb-2">
                        <div class="col-sm-6 d-flex align-items-center">
                            <h1 class="mr-2 mb-0">Reports</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../admin/admin_dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active">Reports</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="card">
                        <div class="card-header p-0">
                            <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="pmo-tab" data-toggle="tab" href="#pmo-report" role="tab" aria-controls="pmo-report" aria-selected="true">
                                        <i class="fas fa-file-alt mr-2"></i>Pre-Marriage Orientation (PMO)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="pmc-tab" data-toggle="tab" href="#pmc-report" role="tab" aria-controls="pmc-report" aria-selected="false">
                                        <i class="fas fa-file-alt mr-2"></i>Pre-Marriage Counseling (PMC)
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="reportTabContent">
                                <!-- PMO Report Tab -->
                                <div class="tab-pane fade show active" id="pmo-report" role="tabpanel" aria-labelledby="pmo-tab">
                    <div class="card report-card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                            <h3 class="card-title mb-0"><i class="fas fa-file-alt mr-2"></i>Couples Attended Pre–Marriage Orientation (PMO)</h3>
                            <div class="report-actions mt-2 mt-sm-0" role="group">
                                <button id="btnExportExcel" class="btn btn-sm btn-outline-success" title="Download Excel">
                                    <i class="fas fa-file-excel mr-1"></i> Excel
                                </button>
                                <button id="btnExportPDF" class="btn btn-sm btn-outline-danger" title="Download PDF">
                                    <i class="fas fa-file-pdf mr-1"></i> PDF
                                </button>
                                <button id="btnPrint" class="btn btn-sm btn-outline-secondary" title="Print">
                                    <i class="fas fa-print mr-1"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <?php 
                                    $currentYear = (int)date('Y');
                                    // Build monthly counts (Couples attended Orientation)
                                    $monthly = array_fill(1, 12, 0);
                                    try {
                                        // Count couples by pair (distinct access_id) who attended orientation with status present per schedule
                                        $sql = "
                                            SELECT MONTH(s.session_date) AS m, COUNT(DISTINCT s.access_id) AS c
                                            FROM scheduling s
                                            JOIN attendance_logs al ON al.schedule_id = s.schedule_id
                                                AND al.segment = 'orientation' AND al.status = 'present'
                                            WHERE YEAR(s.session_date) = ?
                                              AND s.session_type IN ('Orientation','Orientation + Counseling')
                                            GROUP BY MONTH(s.session_date)
                                        ";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->bind_param('i', $currentYear);
                                        $stmt->execute();
                                        $res = $stmt->get_result();
                                        while ($row = $res->fetch_assoc()) {
                                            $m = (int)$row['m'];
                                            $monthly[$m] = (int)$row['c'];
                                        }
                                    } catch (Throwable $e) {
                                        // If query fails, keep zeros; optional log
                                    }
                                    $total = array_sum($monthly);
                                    
                                    // Calculate analysis metrics
                                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                    $monthsWithData = array_filter($monthly, function($val) { return $val > 0; });
                                    $maxMonth = array_search(max($monthly), $monthly);
                                    $filteredMonthly = array_filter($monthly, function($val) { return $val > 0; });
                                    $minMonth = !empty($filteredMonthly) ? array_search(min($filteredMonthly), $monthly) : false;
                                    $average = count($monthsWithData) > 0 ? round($total / count($monthsWithData), 2) : 0;
                                    $overallAverage = round($total / 12, 2);
                                ?>
                                <table id="reportTable" class="table table-bordered table-hover text-center" style="min-width: 520px;">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Month</th>
                                            <th><?php echo $currentYear; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($months as $idx => $label) {
                                            $mIndex = $idx + 1;
                                            echo '<tr>';
                                            echo '<td class="text-left">'.htmlspecialchars($label).'</td>';
                                            echo '<td>'.($monthly[$mIndex] > 0 ? $monthly[$mIndex] : '—').'</td>';
                                            echo '</tr>';
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-left">Total</th>
                                            <th><?php echo $total > 0 ? $total : '—'; ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="mt-3 small-note">Counts reflect couples who attended Orientation (present) this year.</div>
                            
                            <!-- Analysis Section -->
                            <div class="mt-4">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Analysis</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Total Couples</span>
                                                        <span class="info-box-number"><?php echo $total; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-success"><i class="fas fa-calendar-check"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Months with Data</span>
                                                        <span class="info-box-number"><?php echo count($monthsWithData); ?> / 12</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning"><i class="fas fa-chart-bar"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Average per Month (Active)</span>
                                                        <span class="info-box-number"><?php echo $average > 0 ? $average : '—'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-primary"><i class="fas fa-calculator"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Overall Average</span>
                                                        <span class="info-box-number"><?php echo $overallAverage; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="callout callout-success">
                                                    <h5><i class="fas fa-arrow-up mr-2"></i>Highest Month</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo $maxMonth ? $months[$maxMonth - 1] : '—'; ?></strong>
                                                        <?php if ($maxMonth): ?>
                                                            with <strong><?php echo $monthly[$maxMonth]; ?></strong> couples
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="callout callout-warning">
                                                    <h5><i class="fas fa-arrow-down mr-2"></i>Lowest Month (Active)</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo $minMonth ? $months[$minMonth - 1] : '—'; ?></strong>
                                                        <?php if ($minMonth): ?>
                                                            with <strong><?php echo $monthly[$minMonth]; ?></strong> couples
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php
                                        // Identify months with zero data
                                        $zeroMonths = [];
                                        foreach ($monthly as $idx => $val) {
                                            if ($val == 0) {
                                                $zeroMonths[] = $months[$idx - 1];
                                            }
                                        }
                                        if (!empty($zeroMonths)):
                                        ?>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="callout callout-danger">
                                                    <h5><i class="fas fa-exclamation-triangle mr-2"></i>Months with Zero Attendance</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo implode(', ', $zeroMonths); ?></strong>
                                                        <?php if (count($zeroMonths) == 1): ?>
                                                            has no attendance data.
                                                        <?php else: ?>
                                                            have no attendance data.
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Recommended Actions -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="callout callout-info">
                                                    <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
                                                    <ul class="mb-0">
                                                        <?php
                                                        $recommendations = [];
                                                        
                                                        // Check if total is low
                                                        if ($total < 50) {
                                                            $recommendations[] = "Consider increasing outreach and promotional activities to boost attendance. Current total of {$total} couples is below optimal levels.";
                                                        }
                                                        
                                                        // Check months with no data
                                                        $monthsWithoutData = 12 - count($monthsWithData);
                                                        if ($monthsWithoutData > 0) {
                                                            $recommendations[] = "{$monthsWithoutData} month(s) have no attendance data. Schedule orientation sessions for these months to ensure consistent service delivery.";
                                                        }
                                                        
                                                        // Check for significant variance
                                                        if ($maxMonth && $minMonth && $monthly[$maxMonth] > 0 && $monthly[$minMonth] > 0) {
                                                            $variance = $monthly[$maxMonth] - $monthly[$minMonth];
                                                            if ($variance > ($monthly[$maxMonth] * 0.5)) {
                                                                $recommendations[] = "Significant variance detected between highest ({$months[$maxMonth - 1]}: {$monthly[$maxMonth]}) and lowest ({$months[$minMonth - 1]}: {$monthly[$minMonth]}) months. Consider balancing session distribution throughout the year.";
                                                            }
                                                        }
                                                        
                                                        // Check average per month
                                                        if ($average > 0 && $average < 5) {
                                                            $recommendations[] = "Average attendance per active month ({$average}) is relatively low. Review scheduling frequency and consider increasing session availability.";
                                                        }
                                                        
                                                        // Check if most months have data
                                                        if (count($monthsWithData) >= 10 && $total > 0) {
                                                            $recommendations[] = "Good coverage throughout the year with " . count($monthsWithData) . " active months. Maintain this consistency and consider expanding capacity during peak months.";
                                                        }
                                                        
                                                        // If no specific recommendations, provide general guidance
                                                        if (empty($recommendations)) {
                                                            if ($total > 0) {
                                                                $recommendations[] = "Attendance patterns look healthy. Continue monitoring and maintain current scheduling practices.";
                                                            } else {
                                                                $recommendations[] = "No attendance data available for this year. Begin scheduling orientation sessions and promote the program to eligible couples.";
                                                            }
                                                        }
                                                        
                                                        foreach ($recommendations as $rec) {
                                                            echo '<li class="mb-2">' . htmlspecialchars($rec) . '</li>';
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PMC Report Tab -->
                                <div class="tab-pane fade" id="pmc-report" role="tabpanel" aria-labelledby="pmc-tab">
                                    <div class="card report-card">
                                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                                            <h3 class="card-title mb-0"><i class="fas fa-file-alt mr-2"></i>Couples Attended Pre–Marriage Counseling (PMC)</h3>
                                            <div class="report-actions-pmc mt-2 mt-sm-0" role="group">
                                                <button id="btnExportExcelPMC" class="btn btn-sm btn-outline-success" title="Download Excel">
                                                    <i class="fas fa-file-excel mr-1"></i> Excel
                                                </button>
                                                <button id="btnExportPDFPMC" class="btn btn-sm btn-outline-danger" title="Download PDF">
                                                    <i class="fas fa-file-pdf mr-1"></i> PDF
                                                </button>
                                                <button id="btnPrintPMC" class="btn btn-sm btn-outline-secondary" title="Print">
                                                    <i class="fas fa-print mr-1"></i> Print
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                            <div class="table-responsive">
                                <?php 
                                    $currentYear = (int)date('Y');
                                    // Build monthly counts (Couples attended Counseling)
                                    $monthlyPMC = array_fill(1, 12, 0);
                                    try {
                                        // Count couples by pair (distinct access_id) who attended counseling with status present per schedule
                                        $sqlPMC = "
                                            SELECT MONTH(s.session_date) AS m, COUNT(DISTINCT s.access_id) AS c
                                            FROM scheduling s
                                            JOIN attendance_logs al ON al.schedule_id = s.schedule_id
                                                AND al.segment = 'counseling' AND al.status = 'present'
                                            WHERE YEAR(s.session_date) = ?
                                              AND s.session_type IN ('Counseling','Orientation + Counseling')
                                            GROUP BY MONTH(s.session_date)
                                        ";
                                        $stmtPMC = $conn->prepare($sqlPMC);
                                        $stmtPMC->bind_param('i', $currentYear);
                                        $stmtPMC->execute();
                                        $resPMC = $stmtPMC->get_result();
                                        while ($rowPMC = $resPMC->fetch_assoc()) {
                                            $m = (int)$rowPMC['m'];
                                            $monthlyPMC[$m] = (int)$rowPMC['c'];
                                        }
                                    } catch (Throwable $e) {
                                        // If query fails, keep zeros; optional log
                                    }
                                    $totalPMC = array_sum($monthlyPMC);
                                    
                                    // Calculate analysis metrics for PMC
                                    $monthsPMC = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                    $monthsWithDataPMC = array_filter($monthlyPMC, function($val) { return $val > 0; });
                                    $maxMonthPMC = array_search(max($monthlyPMC), $monthlyPMC);
                                    $filteredMonthlyPMC = array_filter($monthlyPMC, function($val) { return $val > 0; });
                                    $minMonthPMC = !empty($filteredMonthlyPMC) ? array_search(min($filteredMonthlyPMC), $monthlyPMC) : false;
                                    $averagePMC = count($monthsWithDataPMC) > 0 ? round($totalPMC / count($monthsWithDataPMC), 2) : 0;
                                    $overallAveragePMC = round($totalPMC / 12, 2);
                                ?>
                                <table id="reportTablePMC" class="table table-bordered table-hover text-center" style="min-width: 520px;">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Month</th>
                                            <th><?php echo $currentYear; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($monthsPMC as $idx => $label) {
                                            $mIndex = $idx + 1;
                                            echo '<tr>';
                                            echo '<td class="text-left">'.htmlspecialchars($label).'</td>';
                                            echo '<td>'.($monthlyPMC[$mIndex] > 0 ? $monthlyPMC[$mIndex] : '—').'</td>';
                                            echo '</tr>';
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-left">Total</th>
                                            <th><?php echo $totalPMC > 0 ? $totalPMC : '—'; ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="mt-3 small-note">Counts reflect couples who attended Counseling (present) this year.</div>
                            
                            <!-- Analysis Section -->
                            <div class="mt-4">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Analysis</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Total Couples</span>
                                                        <span class="info-box-number"><?php echo $totalPMC; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-success"><i class="fas fa-calendar-check"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Months with Data</span>
                                                        <span class="info-box-number"><?php echo count($monthsWithDataPMC); ?> / 12</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning"><i class="fas fa-chart-bar"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Average per Month (Active)</span>
                                                        <span class="info-box-number"><?php echo $averagePMC > 0 ? $averagePMC : '—'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-primary"><i class="fas fa-calculator"></i></span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Overall Average</span>
                                                        <span class="info-box-number"><?php echo $overallAveragePMC; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="callout callout-success">
                                                    <h5><i class="fas fa-arrow-up mr-2"></i>Highest Month</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo $maxMonthPMC ? $monthsPMC[$maxMonthPMC - 1] : '—'; ?></strong>
                                                        <?php if ($maxMonthPMC): ?>
                                                            with <strong><?php echo $monthlyPMC[$maxMonthPMC]; ?></strong> couples
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="callout callout-warning">
                                                    <h5><i class="fas fa-arrow-down mr-2"></i>Lowest Month (Active)</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo $minMonthPMC ? $monthsPMC[$minMonthPMC - 1] : '—'; ?></strong>
                                                        <?php if ($minMonthPMC): ?>
                                                            with <strong><?php echo $monthlyPMC[$minMonthPMC]; ?></strong> couples
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php
                                        // Identify months with zero data for PMC
                                        $zeroMonthsPMC = [];
                                        foreach ($monthlyPMC as $idx => $val) {
                                            if ($val == 0) {
                                                $zeroMonthsPMC[] = $monthsPMC[$idx - 1];
                                            }
                                        }
                                        if (!empty($zeroMonthsPMC)):
                                        ?>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="callout callout-danger">
                                                    <h5><i class="fas fa-exclamation-triangle mr-2"></i>Months with Zero Attendance</h5>
                                                    <p class="mb-0">
                                                        <strong><?php echo implode(', ', $zeroMonthsPMC); ?></strong>
                                                        <?php if (count($zeroMonthsPMC) == 1): ?>
                                                            has no attendance data.
                                                        <?php else: ?>
                                                            have no attendance data.
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Recommended Actions -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="callout callout-info">
                                                    <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
                                                    <ul class="mb-0">
                                                        <?php
                                                        $recommendationsPMC = [];
                                                        
                                                        // Check if total is low
                                                        if ($totalPMC < 50) {
                                                            $recommendationsPMC[] = "Consider increasing outreach and promotional activities to boost counseling attendance. Current total of {$totalPMC} couples is below optimal levels.";
                                                        }
                                                        
                                                        // Check months with no data
                                                        $monthsWithoutDataPMC = 12 - count($monthsWithDataPMC);
                                                        if ($monthsWithoutDataPMC > 0) {
                                                            $recommendationsPMC[] = "{$monthsWithoutDataPMC} month(s) have no counseling attendance data. Schedule counseling sessions for these months to ensure consistent service delivery.";
                                                        }
                                                        
                                                        // Check for significant variance
                                                        if ($maxMonthPMC && $minMonthPMC && $monthlyPMC[$maxMonthPMC] > 0 && $monthlyPMC[$minMonthPMC] > 0) {
                                                            $variancePMC = $monthlyPMC[$maxMonthPMC] - $monthlyPMC[$minMonthPMC];
                                                            if ($variancePMC > ($monthlyPMC[$maxMonthPMC] * 0.5)) {
                                                                $recommendationsPMC[] = "Significant variance detected between highest ({$monthsPMC[$maxMonthPMC - 1]}: {$monthlyPMC[$maxMonthPMC]}) and lowest ({$monthsPMC[$minMonthPMC - 1]}: {$monthlyPMC[$minMonthPMC]}) months. Consider balancing counseling session distribution throughout the year.";
                                                            }
                                                        }
                                                        
                                                        // Check average per month
                                                        if ($averagePMC > 0 && $averagePMC < 5) {
                                                            $recommendationsPMC[] = "Average counseling attendance per active month ({$averagePMC}) is relatively low. Review scheduling frequency and consider increasing session availability.";
                                                        }
                                                        
                                                        // Check if most months have data
                                                        if (count($monthsWithDataPMC) >= 10 && $totalPMC > 0) {
                                                            $recommendationsPMC[] = "Good coverage throughout the year with " . count($monthsWithDataPMC) . " active months. Maintain this consistency and consider expanding capacity during peak months.";
                                                        }
                                                        
                                                        // If no specific recommendations, provide general guidance
                                                        if (empty($recommendationsPMC)) {
                                                            if ($totalPMC > 0) {
                                                                $recommendationsPMC[] = "Counseling attendance patterns look healthy. Continue monitoring and maintain current scheduling practices.";
                                                            } else {
                                                                $recommendationsPMC[] = "No counseling attendance data available for this year. Begin scheduling counseling sessions and promote the program to eligible couples.";
                                                            }
                                                        }
                                                        
                                                        foreach ($recommendationsPMC as $rec) {
                                                            echo '<li class="mb-2">' . htmlspecialchars($rec) . '</li>';
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../includes/footer.php'; ?>
        <?php include '../includes/scripts.php'; ?>
        <script>
            (function(){
                function makeTableJSON(){
                    var data = [];
                    var rows = document.querySelectorAll('#reportTable tbody tr');
                    rows.forEach(function(tr){
                        var month = tr.children[0].innerText.trim();
                        var val = tr.children[1].innerText.trim();
                        data.push({ Month: month, Couples: val === '—' ? 0 : parseInt(val,10) });
                    });
                    return data;
                }

                document.getElementById('btnExportExcel').addEventListener('click', function(){
                    try {
                        var data = makeTableJSON();
                        var ws = XLSX.utils.json_to_sheet(data);
                        var wb = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(wb, ws, 'PMO ' + new Date().getFullYear());
                        XLSX.writeFile(wb, 'BCPDO_PMO_Report_' + new Date().getFullYear() + '.xlsx');
                    } catch(e) {
                        Swal.fire({ icon:'error', title:'Export Failed', text:'Unable to export Excel.' });
                    }
                });

                document.getElementById('btnExportPDF').addEventListener('click', function(){
                    try {
                        const { jsPDF } = window.jspdf || {};
                        if (!jsPDF || !html2pdf) throw new Error('PDF libraries missing');
                        const pmoTab = document.getElementById('pmo-report');
                        const element = pmoTab.querySelector('.report-card');
                        const reportActions = element.querySelector('.report-actions');
                        
                        // Hide export buttons before PDF export
                        if (reportActions) {
                            reportActions.style.display = 'none';
                        }
                        
                        const opt = {
                            margin: 10,
                            filename: 'BCPDO_PMO_Report_' + new Date().getFullYear() + '.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2 },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                        };
                        
                        html2pdf().from(element).set(opt).save().then(function() {
                            // Show export buttons again after PDF export
                            if (reportActions) {
                                reportActions.style.display = 'flex';
                            }
                        });
                    } catch(e) {
                        Swal.fire({ icon:'error', title:'Export Failed', text:'Unable to export PDF.' });
                        // Make sure to show buttons again if export fails
                        const pmoTab = document.getElementById('pmo-report');
                        const element = pmoTab.querySelector('.report-card');
                        const reportActions = element.querySelector('.report-actions');
                        if (reportActions) {
                            reportActions.style.display = 'flex';
                        }
                    }
                });

                document.getElementById('btnPrint').addEventListener('click', function(){
                    var w = window.open('', '_blank');
                    // Get the report card and analysis section
                    var reportCard = document.querySelector('#pmo-report .report-card');
                    var analysisCard = document.querySelector('#pmo-report .card-info');
                    if (!reportCard) return;
                    
                    // Clone the report card
                    var cardClone = reportCard.cloneNode(true);
                    var analysisClone = analysisCard ? analysisCard.cloneNode(true) : null;
                    
                    // Remove export buttons from clone
                    var reportActions = cardClone.querySelector('.report-actions');
                    if (reportActions && reportActions.parentNode) {
                        reportActions.parentNode.removeChild(reportActions);
                    }
                    
                    // Create clean HTML document
                    var printContent = '<!DOCTYPE html><html><head><title>PMO Report</title><style>' +
                        'body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }' +
                        '.card { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }' +
                        '.card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }' +
                        '.card-header h3 { margin: 0; font-size: 18px; }' +
                        '.card-body { padding: 15px; }' +
                        '.table { width: 100%; border-collapse: collapse; }' +
                        '.table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }' +
                        '.table th { background: #f8f9fa; font-weight: bold; }' +
                        '.table tfoot th { background: #f8f9fa; font-weight: bold; }' +
                        '.text-center { text-align: center; }' +
                        '.text-left { text-align: left; }' +
                        '.small-note { font-size: 12px; color: #6c757d; margin-top: 10px; }' +
                        '.info-box { display: flex; margin-bottom: 15px; }' +
                        '.info-box-icon { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white; }' +
                        '.info-box-content { flex: 1; padding: 10px; }' +
                        '.info-box-text { font-size: 12px; text-transform: uppercase; }' +
                        '.info-box-number { font-size: 24px; font-weight: bold; }' +
                        '.callout { padding: 15px; border-left: 4px solid; margin-bottom: 15px; }' +
                        '.callout-success { background: #d4edda; border-color: #28a745; }' +
                        '.callout-warning { background: #fff3cd; border-color: #ffc107; }' +
                        '.callout-danger { background: #f8d7da; border-color: #dc3545; }' +
                        '.callout-info { background: #d1ecf1; border-color: #17a2b8; }' +
                        '.callout h5 { margin: 0 0 10px 0; font-size: 16px; }' +
                        '.callout p { margin: 0; }' +
                        '.callout ul { margin: 10px 0 0 0; padding-left: 20px; }' +
                        '.callout li { margin-bottom: 8px; }' +
                        '.row { display: flex; flex-wrap: wrap; }' +
                        '.col-md-6 { width: 50%; padding: 0 10px; }' +
                        '.col-12 { width: 100%; padding: 0 10px; }' +
                        '.mt-3 { margin-top: 15px; }' +
                        '.mt-4 { margin-top: 20px; }' +
                        '.mb-0 { margin-bottom: 0; }' +
                        '.mb-2 { margin-bottom: 8px; }' +
                        '</style></head><body>' + cardClone.outerHTML + (analysisClone ? analysisClone.outerHTML : '') + '</body></html>';
                    
                    w.document.open();
                    w.document.write(printContent);
                    w.document.close();
                    setTimeout(function(){ w.print(); w.close(); }, 500);
                });

                // PMC Report Export Functions
                function makeTableJSONPMC(){
                    var data = [];
                    var rows = document.querySelectorAll('#reportTablePMC tbody tr');
                    rows.forEach(function(tr){
                        var month = tr.children[0].innerText.trim();
                        var val = tr.children[1].innerText.trim();
                        data.push({ Month: month, Couples: val === '—' ? 0 : parseInt(val,10) });
                    });
                    return data;
                }

                document.getElementById('btnExportExcelPMC').addEventListener('click', function(){
                    try {
                        var data = makeTableJSONPMC();
                        var ws = XLSX.utils.json_to_sheet(data);
                        var wb = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(wb, ws, 'PMC ' + new Date().getFullYear());
                        XLSX.writeFile(wb, 'BCPDO_PMC_Report_' + new Date().getFullYear() + '.xlsx');
                    } catch(e) {
                        Swal.fire({ icon:'error', title:'Export Failed', text:'Unable to export Excel.' });
                    }
                });

                document.getElementById('btnExportPDFPMC').addEventListener('click', function(){
                    try {
                        const { jsPDF } = window.jspdf || {};
                        if (!jsPDF || !html2pdf) throw new Error('PDF libraries missing');
                        const pmcTab = document.getElementById('pmc-report');
                        const element = pmcTab.querySelector('.report-card');
                        const reportActions = element.querySelector('.report-actions-pmc');
                        
                        // Hide export buttons before PDF export
                        if (reportActions) {
                            reportActions.style.display = 'none';
                        }
                        
                        const opt = {
                            margin: 10,
                            filename: 'BCPDO_PMC_Report_' + new Date().getFullYear() + '.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2 },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                        };
                        
                        html2pdf().from(element).set(opt).save().then(function() {
                            // Show export buttons again after PDF export
                            if (reportActions) {
                                reportActions.style.display = 'flex';
                            }
                        });
                    } catch(e) {
                        Swal.fire({ icon:'error', title:'Export Failed', text:'Unable to export PDF.' });
                        // Make sure to show buttons again if export fails
                        const pmcTab = document.getElementById('pmc-report');
                        const element = pmcTab.querySelector('.report-card');
                        const reportActions = element.querySelector('.report-actions-pmc');
                        if (reportActions) {
                            reportActions.style.display = 'flex';
                        }
                    }
                });

                document.getElementById('btnPrintPMC').addEventListener('click', function(){
                    var w = window.open('', '_blank');
                    // Get the report card and analysis section
                    var reportCard = document.querySelector('#pmc-report .report-card');
                    var analysisCard = document.querySelector('#pmc-report .card-info');
                    if (!reportCard) return;
                    
                    // Clone the report card
                    var cardClone = reportCard.cloneNode(true);
                    var analysisClone = analysisCard ? analysisCard.cloneNode(true) : null;
                    
                    // Remove export buttons from clone
                    var reportActions = cardClone.querySelector('.report-actions-pmc');
                    if (reportActions && reportActions.parentNode) {
                        reportActions.parentNode.removeChild(reportActions);
                    }
                    
                    // Create clean HTML document
                    var printContent = '<!DOCTYPE html><html><head><title>PMC Report</title><style>' +
                        'body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }' +
                        '.card { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }' +
                        '.card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }' +
                        '.card-header h3 { margin: 0; font-size: 18px; }' +
                        '.card-body { padding: 15px; }' +
                        '.table { width: 100%; border-collapse: collapse; }' +
                        '.table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }' +
                        '.table th { background: #f8f9fa; font-weight: bold; }' +
                        '.table tfoot th { background: #f8f9fa; font-weight: bold; }' +
                        '.text-center { text-align: center; }' +
                        '.text-left { text-align: left; }' +
                        '.small-note { font-size: 12px; color: #6c757d; margin-top: 10px; }' +
                        '.info-box { display: flex; margin-bottom: 15px; }' +
                        '.info-box-icon { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white; }' +
                        '.info-box-content { flex: 1; padding: 10px; }' +
                        '.info-box-text { font-size: 12px; text-transform: uppercase; }' +
                        '.info-box-number { font-size: 24px; font-weight: bold; }' +
                        '.callout { padding: 15px; border-left: 4px solid; margin-bottom: 15px; }' +
                        '.callout-success { background: #d4edda; border-color: #28a745; }' +
                        '.callout-warning { background: #fff3cd; border-color: #ffc107; }' +
                        '.callout-danger { background: #f8d7da; border-color: #dc3545; }' +
                        '.callout-info { background: #d1ecf1; border-color: #17a2b8; }' +
                        '.callout h5 { margin: 0 0 10px 0; font-size: 16px; }' +
                        '.callout p { margin: 0; }' +
                        '.callout ul { margin: 10px 0 0 0; padding-left: 20px; }' +
                        '.callout li { margin-bottom: 8px; }' +
                        '.row { display: flex; flex-wrap: wrap; }' +
                        '.col-md-6 { width: 50%; padding: 0 10px; }' +
                        '.col-12 { width: 100%; padding: 0 10px; }' +
                        '.mt-3 { margin-top: 15px; }' +
                        '.mt-4 { margin-top: 20px; }' +
                        '.mb-0 { margin-bottom: 0; }' +
                        '.mb-2 { margin-bottom: 8px; }' +
                        '</style></head><body>' + cardClone.outerHTML + (analysisClone ? analysisClone.outerHTML : '') + '</body></html>';
                    
                    w.document.open();
                    w.document.write(printContent);
                    w.document.close();
                    setTimeout(function(){ w.print(); w.close(); }, 500);
                });
            })();
        </script>
    </div>
</body>
</html>


