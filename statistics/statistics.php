<?php
require_once '../includes/session.php';
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Statistics</title>
  <?php include '../includes/header.php'; ?>
  <style>
    /* Early critical styles to prevent flash/morph on first paint */
    .individual-chart-controls { margin-bottom: 20px; }
    .chart-toggle-row { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 10px; margin-bottom: 15px; }
    /* Chart toggle buttons now use Bootstrap classes - removed custom styling */
    .chart-toggle-btn i { font-size: 14px; width: 1.25rem; text-align: center; line-height: 1; }

    /* Time range buttons now use Bootstrap classes - removed custom styling */
    .time-range-buttons { display:flex; flex-wrap:wrap; gap:10px; }

    /* Dark mode: individual chart control buttons - prevent flicker */
    html.dark-boot .chart-toggle-btn,
    body.dark-mode .chart-toggle-btn {
      background: #2f3640 !important; 
      color: #e2e8f0 !important; 
      border-color: rgba(255,255,255,0.12) !important;
    }
    html.dark-boot .chart-toggle-btn:hover,
    body.dark-mode .chart-toggle-btn:hover { 
      background:#3b4147 !important; 
      color:#ffffff !important; 
      border-color: rgba(255,255,255,0.18) !important;
    }
    html.dark-boot .chart-toggle-btn.active,
    body.dark-mode .chart-toggle-btn.active { 
      background:#6c757d !important; 
      border-color:#6c757d !important; 
      color:#fff !important; 
    }
    
    /* Dark mode: make Show/Hide uniform with other controls - prevent flicker */
    html.dark-boot .show-all-charts-btn,
    html.dark-boot .hide-all-charts-btn,
    body.dark-mode .show-all-charts-btn,
    body.dark-mode .hide-all-charts-btn { 
      background:#2f3640 !important; 
      color:#e2e8f0 !important; 
      border-color: rgba(255,255,255,0.12) !important; 
    }
    html.dark-boot .show-all-charts-btn:hover,
    html.dark-boot .hide-all-charts-btn:hover,
    body.dark-mode .show-all-charts-btn:hover,
    body.dark-mode .hide-all-charts-btn:hover { 
      background:#3b4147 !important; 
      color:#ffffff !important; 
      border-color: rgba(255,255,255,0.18) !important; 
    }

    /* Dark mode: time range buttons - prevent flicker */
    html.dark-boot .time-range-btn,
    body.dark-mode .time-range-btn {
      background: #2f3640 !important; 
      color: #e2e8f0 !important; 
      border-color: rgba(255,255,255,0.12) !important;
    }
    html.dark-boot .time-range-btn:hover,
    body.dark-mode .time-range-btn:hover { 
      background:#3b4147 !important; 
      color:#ffffff !important; 
      border-color: rgba(255,255,255,0.18) !important; 
    }
    html.dark-boot .time-range-btn.active,
    body.dark-mode .time-range-btn.active { 
      background:#6c757d !important; 
      border-color:#6c757d !important; 
      color:#fff !important; 
    }

    /* Dark mode: form controls - prevent flicker */
    html.dark-boot .form-control,
    body.dark-mode .form-control {
      background-color: #2f3640 !important; 
      color: #e2e8f0 !important; 
      border-color: rgba(255,255,255,0.12) !important;
    }
    html.dark-boot .form-control:focus,
    body.dark-mode .form-control:focus {
      background-color: #3b4147 !important; 
      color: #ffffff !important; 
      border-color: #6c757d !important; 
      box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25) !important;
    }
    html.dark-boot .form-control:hover,
    body.dark-mode .form-control:hover {
      background-color: #3b4147 !important;
      border-color: rgba(255,255,255,0.18) !important;
    }

    /* Dark mode: export/print buttons - prevent flicker */
    html.dark-boot .export-print-options .btn,
    body.dark-mode .export-print-options .btn {
      background-color: #2f3640 !important; 
      color: #e2e8f0 !important; 
      border-color: rgba(255,255,255,0.12) !important;
      transition: all 0.2s ease !important;
    }
    html.dark-boot .export-print-options .btn:hover,
    body.dark-mode .export-print-options .btn:hover {
      background-color: #3b4147 !important; 
      color: #ffffff !important; 
      border-color: rgba(255,255,255,0.18) !important;
    }
    html.dark-boot .export-print-options .dropdown-menu,
    body.dark-mode .export-print-options .dropdown-menu {
      background-color: #343a40 !important; 
      border-color: rgba(255,255,255,0.12) !important;
    }
    html.dark-boot .export-print-options .dropdown-item,
    body.dark-mode .export-print-options .dropdown-item {
      color: #f8f9fa !important;
    }
    html.dark-boot .export-print-options .dropdown-item:hover,
    body.dark-mode .export-print-options .dropdown-item:hover {
      background-color: #495057 !important; 
      color: #ffffff !important;
    }

    /* Prevent flicker for all buttons with !important rules */
    html.dark-boot .chart-toggle-btn,
    html.dark-boot .show-all-charts-btn,
    html.dark-boot .hide-all-charts-btn,
    html.dark-boot .time-range-btn,
    html.dark-boot .form-control,
    body.dark-mode .chart-toggle-btn,
    body.dark-mode .show-all-charts-btn,
    body.dark-mode .hide-all-charts-btn,
    body.dark-mode .time-range-btn,
    body.dark-mode .form-control {
      transition: all 0.2s ease !important;
    }

    /* Override any conflicting transitions */
    html.dark-boot *,
    body.dark-mode * {
      transition: none !important;
    }

    /* Re-enable smooth transitions only for specific button elements */
    html.dark-boot .chart-toggle-btn,
    html.dark-boot .show-all-charts-btn,
    html.dark-boot .hide-all-charts-btn,
    html.dark-boot .time-range-btn,
    html.dark-boot .form-control,
    html.dark-boot .export-print-options .btn,
    body.dark-mode .chart-toggle-btn,
    body.dark-mode .show-all-charts-btn,
    body.dark-mode .hide-all-charts-btn,
    body.dark-mode .time-range-btn,
    body.dark-mode .form-control,
    body.dark-mode .export-print-options .btn {
      transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease !important;
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
            <i class="fas fa-chart-bar text-primary"></i>
            <h4 class="mb-0">Statistics Charts Dashboard</h4>
          </div>
          <p class="text-muted" style="margin-top:-6px;">Statistical analysis and reporting for couple registrations</p>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">

        <!-- Section Navigation (sticky) -->
        <!-- Removed per request: Demographics/section navigation buttons -->
        
        <!-- Chart Visibility Controls -->
         <div class="row mb-3">
           <div class="col-12">
             <div class="card">
               <div class="card-body">
                 <h5 class="text-muted mb-3"><i class="fas fa-toggle-on mr-2"></i>Individual Chart Controls</h5>
                 
                                  <!-- Individual Chart Toggle Buttons -->
                   <div class="individual-chart-controls">
                     <div class="chart-toggle-row">
                      <!-- Show All Charts Button -->
                      <button type="button" class="btn btn-outline-primary btn-sm show-all-charts-btn" id="showAllChartsBtn">
                        <i class="fas fa-eye mr-1"></i>Show All
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm hide-all-charts-btn" id="hideAllChartsBtn" style="display: none;">
                        <i class="fas fa-eye-slash mr-1"></i>Hide All
                      </button>
                      
                      <!-- Row 1 (4) -->
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="populationPyramidChart">
                        <i class="fas fa-chart-pie mr-1"></i>Age Population
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="civilChart">
                        <i class="fas fa-heart mr-1"></i>Civil Status
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="religionChart">
                        <i class="fas fa-pray mr-1"></i>Religion
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="weddingChart">
                        <i class="fas fa-ring mr-1"></i>Wedding Type
                      </button>
                      
                      <!-- Row 2 (4) -->
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="pregnancyStatusChart">
                        <i class="fas fa-baby mr-1"></i>Pregnancy
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="philhealthChart">
                        <i class="fas fa-heartbeat mr-1"></i>PhilHealth
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="educationChart">
                        <i class="fas fa-graduation-cap mr-1"></i>Education
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="employmentChart">
                        <i class="fas fa-briefcase mr-1"></i>Employment
                      </button>
                      
                      <!-- Row 3 (4) -->
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="incomeChart">
                        <i class="fas fa-money-bill mr-1"></i>Income
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="attendanceChart">
                        <i class="fas fa-calendar-check mr-1"></i>Attendance
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="fpMethodsMaleChart">
                        <i class="fas fa-male mr-1"></i>FP Methods (Male)
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="fpMethodsFemaleChart">
                        <i class="fas fa-female mr-1"></i>FP Methods (Female)
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="fpIntentMaleChart">
                        <i class="fas fa-male mr-1"></i>FP Intention (Male)
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="fpIntentFemaleChart">
                        <i class="fas fa-female mr-1"></i>FP Intention (Female)
                      </button>
                      
                      <!-- Row 4 (3) -->
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="topBarangaysChart">
                        <i class="fas fa-map-marker mr-1"></i>Top Barangays
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="marriageSeasonalityChart">
                        <i class="fas fa-chart-line mr-1"></i>Marriage Seasonality
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm chart-toggle-btn" data-chart="sessionsMonthlyChart">
                        <i class="fas fa-chart-bar mr-1"></i>Monthly Sessions
                      </button>
                     </div>
                 </div>

                 <!-- Filters -->
                 <div class="filters-section mt-3">
                   <div class="row">
                     <div class="col-md-4">
                       <label class="form-label">Barangay Filter:</label>
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
                     <div class="col-md-8">
                       <label class="form-label">Time Range:</label>
                      <div class="time-range-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline-primary btn-sm active" data-range="present_week">Present Week</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="this_month">This Month</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="this_year">This Year</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-range="all_time">All Time</button>
                      </div>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>

        <!-- Chart Content Areas -->
        <div class="row chart-content-areas">
          
          <!-- DEMOGRAPHIC OVERVIEW SECTION -->
          <div class="col-12 chart-section" id="demographic-section">
            
            <div class="row">
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="populationPyramidChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-chart-pie mr-2"></i>Age Population Pyramid</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('populationPyramidChart', 'Age Population Pyramid')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('populationPyramidChart', 'Age Population Pyramid', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('populationPyramidChart', 'Age Population Pyramid')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('populationPyramidChart', 'Age Population Pyramid')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 350px;">
                      <div class="chart-loading" id="populationPyramidChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="populationPyramidChart"></canvas>
                    </div>
                    <div class="legend-row" id="pyramidLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="civilChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-heart mr-2"></i>Civil Status</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('civilChart', 'Civil Status')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('civilChart', 'Civil Status', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('civilChart', 'Civil Status')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('civilChart', 'Civil Status')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 350px;">
                      <div class="chart-loading" id="civilChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="civilChart"></canvas>
                    </div>
                    <div class="legend-list" id="civilLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- RELIGION & WEDDING SECTION -->
          <div class="col-12 chart-section" id="religion-wedding-section">
            
            <div class="row">
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="religionChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-pray mr-2"></i>Religion Distribution</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('religionChart', 'Religion Distribution')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('religionChart', 'Religion Distribution', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('religionChart', 'Religion Distribution')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('religionChart', 'Religion Distribution')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="religionChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="religionChart"></canvas>
                    </div>
                    <div class="legend-grid" id="religionLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="weddingChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-ring mr-2"></i>Wedding Type Distribution</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('weddingChart', 'Wedding Type Distribution')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('weddingChart', 'Wedding Type Distribution', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('weddingChart', 'Wedding Type Distribution')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('weddingChart', 'Wedding Type Distribution')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="weddingChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="weddingChart"></canvas>
                    </div>
                    <div class="legend-list" id="weddingLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- HEALTH & PREGNANCY SECTION -->
          <div class="col-12 chart-section" id="health-section">
            <div class="row">
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="pregnancyStatusChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-female mr-2"></i>Pregnancy Status</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('pregnancyStatusChart', 'Pregnancy Status')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('pregnancyStatusChart', 'Pregnancy Status', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('pregnancyStatusChart', 'Pregnancy Status')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('pregnancyStatusChart', 'Pregnancy Status')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="pregnancyStatusChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="pregnancyStatusChart"></canvas>
                    </div>
                    <div class="legend-list" id="pregnancyStatusLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="philhealthChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-heartbeat mr-2"></i>PhilHealth Membership</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('philhealthChart', 'PhilHealth Membership')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('philhealthChart', 'PhilHealth Membership', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('philhealthChart', 'PhilHealth Membership')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('philhealthChart', 'PhilHealth Membership')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="philhealthChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="philhealthChart"></canvas>
                    </div>
                    <div class="legend-list" id="philhealthLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- EDUCATION & EMPLOYMENT SECTION -->
          <div class="col-12 chart-section" id="education-employment-section">
            <div class="row">
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="educationChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-book mr-2"></i>Highest Education Attainment</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('educationChart', 'Highest Education Attainment')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('educationChart', 'Highest Education Attainment', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('educationChart', 'Highest Education Attainment')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('educationChart', 'Highest Education Attainment')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="educationChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="educationChart"></canvas>
                    </div>
                    <div class="legend-list" id="educationLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="employmentChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-briefcase mr-2"></i>Employment Status</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('employmentChart', 'Employment Status')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('employmentChart', 'Employment Status', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('employmentChart', 'Employment Status')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('employmentChart', 'Employment Status')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="employmentChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="employmentChart"></canvas>
                    </div>
                    <div class="legend-list" id="employmentLegend"></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="fpMethodsMaleChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-male mr-2"></i>Preferred FP Methods (Male)</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('fpMethodsMaleChart', 'Preferred FP Methods (Male)')">
                            <i class="fas fa-file-image mr-2"></i>Export Image
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportCSVFromChart('fpMethodsMaleChart','fp_methods_male')">
                            <i class="fas fa-file-csv mr-2"></i>Export CSV
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('fpMethodsMaleChart', 'Preferred FP Methods (Male)')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('fpMethodsMaleChart', 'Preferred FP Methods (Male)')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="fpMethodsMaleChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="fpMethodsMaleChart"></canvas>
                    </div>
                    <div class="legend-grid" id="fpMethodsMaleLegend"></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="fpMethodsFemaleChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-female mr-2"></i>Preferred FP Methods (Female)</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('fpMethodsFemaleChart', 'Preferred FP Methods (Female)')">
                            <i class="fas fa-file-image mr-2"></i>Export Image
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportCSVFromChart('fpMethodsFemaleChart','fp_methods_female')">
                            <i class="fas fa-file-csv mr-2"></i>Export CSV
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('fpMethodsFemaleChart', 'Preferred FP Methods (Female)')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('fpMethodsFemaleChart', 'Preferred FP Methods (Female)')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="fpMethodsFemaleChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="fpMethodsFemaleChart"></canvas>
                    </div>
                    <div class="legend-grid" id="fpMethodsFemaleLegend"></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="fpIntentMaleChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-male mr-2"></i>FP Intention (Male)</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('fpIntentMaleChart', 'FP Intention (Male)')">
                            <i class="fas fa-file-image mr-2"></i>Export Image
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportCSVFromChart('fpIntentMaleChart','fp_intention_male')">
                            <i class="fas fa-file-csv mr-2"></i>Export CSV
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('fpIntentMaleChart', 'FP Intention (Male)')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('fpIntentMaleChart', 'FP Intention (Male)')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="fpIntentMaleChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="fpIntentMaleChart"></canvas>
                    </div>
                    <div class="legend-list" id="fpIntentMaleLegend"></div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="fpIntentFemaleChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-female mr-2"></i>FP Intention (Female)</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('fpIntentFemaleChart', 'FP Intention (Female)')">
                            <i class="fas fa-file-image mr-2"></i>Export Image
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportCSVFromChart('fpIntentFemaleChart','fp_intention_female')">
                            <i class="fas fa-file-csv mr-2"></i>Export CSV
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('fpIntentFemaleChart', 'FP Intention (Female)')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('fpIntentFemaleChart', 'FP Intention (Female)')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="fpIntentFemaleChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="fpIntentFemaleChart"></canvas>
                    </div>
                    <div class="legend-list" id="fpIntentFemaleLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FINANCIAL & ATTENDANCE SECTION -->
          <div class="col-12 chart-section" id="financial-section">
            <div class="row">
              <div class="col-lg-12 col-md-12 mb-3 chart-item hidden" id="incomeChart-item">
                <div class="card statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Income Bracket Distribution</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('incomeChart', 'Income Bracket Distribution')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('incomeChart', 'Income Bracket Distribution', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('incomeChart', 'Income Bracket Distribution')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('incomeChart', 'Income Bracket Distribution')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="incomeChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="incomeChart"></canvas>
                    </div>
                    <div class="legend-inline" id="incomeLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="attendanceChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-calendar-check mr-2"></i>Attendance Rate</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('attendanceChart', 'Attendance Rate')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('attendanceChart', 'Attendance Rate', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('attendanceChart', 'Attendance Rate')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('attendanceChart', 'Attendance Rate')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="attendanceChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="attendanceChart"></canvas>
                    </div>
                    <div class="legend-list" id="attendanceLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- GEOGRAPHIC & TREND STATISTICS SECTION -->
          <div class="col-12 chart-section" id="geographic-section">
            <div class="row">
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="topBarangaysChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-map mr-2"></i>Top 5 Barangays </h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('topBarangaysChart', 'Top 5 Barangays')">
                            <i class="fas fa-file-image mr-2"></i>Export Image
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportCSVFromChart('topBarangaysChart','top_barangays')">
                            <i class="fas fa-file-csv mr-2"></i>Export CSV
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('topBarangaysChart', 'Top 5 Barangays')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('topBarangaysChart', 'Top 5 Barangays')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="topBarangaysChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="topBarangaysChart"></canvas>
                    </div>
                    <div class="legend-ordered" id="topBarangaysLegend"></div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6 col-md-12 mb-3 chart-item hidden" id="marriageSeasonalityChart-item">
                <div class="card h-100 statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-calendar-alt mr-2"></i>Marriage Seasonality</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('marriageSeasonalityChart', 'Marriage Seasonality')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('marriageSeasonalityChart', 'Marriage Seasonality', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('marriageSeasonalityChart', 'Marriage Seasonality')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('marriageSeasonalityChart', 'Marriage Seasonality')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="marriageSeasonalityChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="marriageSeasonalityChart"></canvas>
                    </div>
                    <div class="legend-inline" id="marriageSeasonalityLegend"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SESSION TRENDS SECTION -->
          <div class="col-12 chart-section" id="sessions-section">
            <div class="row">
              <div class="col-lg-12 col-md-12 mb-3 chart-item hidden" id="sessionsMonthlyChart-item">
                <div class="card statistics-chart">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0"><i class="fas fa-chart-area mr-2"></i>Monthly Session Trends (Last 12 Months)</h4>
                    <div class="export-print-options">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-toggle="dropdown">
                          <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <div class="dropdown-menu">
                          <a class="dropdown-item" href="#" onclick="exportChart('sessionsMonthlyChart', 'Monthly Session Trends')">
                            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
                          </a>
                          <a class="dropdown-item" href="#" onclick="exportChart('sessionsMonthlyChart', 'Monthly Session Trends', 'excel')">
                            <i class="fas fa-file-excel mr-2"></i>Export as Excel
                          </a>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="printChart('sessionsMonthlyChart', 'Monthly Session Trends')">
                        <i class="fas fa-print mr-1"></i>Print
                      </button>
                      <button type="button" class="btn btn-outline-light btn-sm" onclick="showAnalysisModal('sessionsMonthlyChart', 'Monthly Session Trends')">
                        <i class="fas fa-chart-line mr-1"></i>Analysis
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                      <div class="chart-loading" id="sessionsMonthlyChartLoading" style="display: none;">
                        <div class="chart-loading-spinner"></div>
                      </div>
                      <canvas id="sessionsMonthlyChart"></canvas>
                    </div>
                    <div class="legend-inline" id="sessionsMonthlyLegend"></div>
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
</div>
<?php include '../includes/scripts.php'; ?>

<!-- Analysis Modal -->
<div class="modal fade" id="analysisModal" tabindex="-1" role="dialog" aria-labelledby="analysisModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="analysisModalLabel">
          <i class="fas fa-chart-line mr-2"></i>Analysis
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="analysisModalBody">
        <!-- Analysis content will be populated by JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<style>
  .legend-row{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;position:relative;z-index:2}
  .legend-list{display:flex;flex-direction:column;gap:6px;margin-top:10px;position:relative;z-index:2}
  .legend-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;margin-top:10px;position:relative;z-index:2}
  .legend-ordered{display:flex;flex-direction:column;gap:8px;margin-top:20px;position:relative;z-index:1;clear:both}
  .legend-inline{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;position:relative;z-index:2}
  .legend-item{display:flex;align-items:center;gap:8px;font-size:0.95rem;line-height:1.2}
  .legend-color{width:14px;height:14px;border-radius:3px;border:1px solid rgba(0,0,0,0.1)}
  .legend-rank{width:22px;height:22px;border-radius:6px;background:#f1f3f5;color:#333;display:inline-flex;align-items:center;justify-content:center;font-weight:600}
  
  /* Enhanced card styling */
  .card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
    transition: all 0.3s ease;
  }
  
  .card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }
  
  .card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    font-weight: 600;
  }
  
  /* Section headers styling */
  .text-muted.border-bottom {
    border-bottom: 2px solid #dee2e6 !important;
    font-weight: 600;
    color: #6c757d !important;
  }
  
  /* Chart container improvements */
  .chart-container {
    position: relative;
    margin: auto;
    margin-bottom: 0;
    padding-bottom: 0;
    overflow: visible;
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
  /* When a single chart is visible, give it more height so it doesn't collide with the footer */
  .single-full .chart-container { height: 480px !important; }
  /* ensure room for legends under charts */
  .statistics-chart .card-body { padding-bottom: 16px; }

  /* Add breathing room above footer */
  .content-wrapper { padding-bottom: 40px; }
  /* Section nav links */
  .section-nav .section-link { transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
  .section-nav .section-link.active { background-color: #6c757d; color: #fff; border-color: #6c757d; }
  
  /* Responsive improvements */
  @media (max-width: 768px) {
    .col-md-12 {
      margin-bottom: 1rem;
    }
  }
  
  /* controlled spacing inside content to avoid margin-collapsing */
  .content-wrapper .content{padding-top:12px}
  

  

  


  /* Individual Chart Toggle Buttons */
  .individual-chart-controls {
    margin-bottom: 20px;
  }

  .chart-toggle-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
  }

  /* Chart toggle buttons now use Bootstrap classes - only keep icon styling */
  .chart-toggle-btn i {
    font-size: 14px !important;
    width: 1.25rem;
    text-align: center;
    line-height: 1;
  }

  /* Filters Section */
  .filters-section {
    border-top: 1px solid #e9ecef;
    padding-top: 20px;
  }

  .filters-section .form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
  }

  .form-control {
    border-radius: 4px;
    border: 1px solid #dee2e6;
    padding: 8px 12px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    min-height: 38px;
    background-color: #f8f9fa;
    color: #343a40;
  }

  .form-control:focus {
    border-color: #6c757d;
    box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    background-color: #ffffff;
    color: #343a40;
  }

  .form-control:hover {
    border-color: #6c757d;
    background-color: #ffffff;
  }

  /* Time Range Buttons (Simplified) */
  .time-range-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
  }

  /* Time range buttons now use Bootstrap classes - removed custom styling */

  /* Export and Print Options Styling */
  .export-print-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
  }

  .export-print-options .btn-sm {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
  }

  .export-print-options .btn {
    border-radius: 20px;
    font-weight: 500;
    transition: all 0.2s ease;
    padding: 6px 12px;
    font-size: 0.8rem;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #6c757d;
    min-height: 32px;
  }

  .export-print-options .btn:hover {
    background: #f8f9fa;
    border-color: #6c757d;
    color: #6c757d;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(108, 117, 125, 0.15);
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

  /* Statistics Chart Styling */
  .statistics-chart {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
    transition: all 0.3s ease;
  }
  
  .statistics-chart:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }
  
  .statistics-chart .card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    font-weight: 600;
  }
  

  
  /* Chart toggle buttons now use Bootstrap classes - removed custom styling */
  
  /* Chart Item Visibility */
  .chart-item { transition: transform .3s ease, opacity .3s ease, box-shadow .3s ease; opacity: 0; transform: translateY(10px); }
  .chart-item.visible { opacity: 1; transform: translateY(0); }
  
  .chart-item.hidden {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
  }
  
  /* Ensure hidden chart containers don't take up space */
  .chart-item.hidden * {
    display: none !important;
  }
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .section-header {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .inline-chart-controls {
      width: 100%;
      justify-content: flex-start;
    }
    
    .chart-toggle-row { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
    .chart-toggle-btn { justify-content: center; }
  }

  /* Legend clamp functionality removed */
  
  /* Chart Analysis Section Styling */
  .chart-analysis {
    margin-top: 15px;
  }
  
  .chart-analysis .card-info {
    border-left: 4px solid #17a2b8;
  }
  
  .chart-analysis .info-box {
    display: flex;
    margin-bottom: 15px;
  }
  
  .chart-analysis .info-box-icon {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: white;
    border-radius: 4px;
  }
  
  .chart-analysis .info-box-content {
    flex: 1;
    padding: 10px;
  }
  
  .chart-analysis .info-box-text {
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
  }
  
  .chart-analysis .info-box-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
  }
  
  .chart-analysis .callout {
    padding: 15px;
    border-left: 4px solid;
    margin-bottom: 15px;
    border-radius: 4px;
  }
  
  .chart-analysis .callout-success {
    background: #d4edda;
    border-color: #28a745;
  }
  
  .chart-analysis .callout-warning {
    background: #fff3cd;
    border-color: #ffc107;
  }
  
  .chart-analysis .callout-danger {
    background: #f8d7da;
    border-color: #dc3545;
  }
  
  .chart-analysis .callout-info {
    background: #d1ecf1;
    border-color: #17a2b8;
  }
  
  .chart-analysis .callout h5 {
    margin: 0 0 10px 0;
    font-size: 16px;
  }
  
  .chart-analysis .callout p {
    margin: 0;
  }
  
  .chart-analysis .callout ul {
    margin: 10px 0 0 0;
    padding-left: 20px;
  }
  
  .chart-analysis .callout li {
    margin-bottom: 8px;
  }
  
  body.dark-mode .chart-analysis .info-box-text {
    color: #adb5bd;
  }
  
  body.dark-mode .chart-analysis .info-box-number {
    color: #f8f9fa;
  }
  
  body.dark-mode .chart-analysis .callout-success {
    background: rgba(40, 167, 69, 0.2);
    border-color: #28a745;
  }
  
  body.dark-mode .chart-analysis .callout-warning {
    background: rgba(255, 193, 7, 0.2);
    border-color: #ffc107;
  }
  
  body.dark-mode .chart-analysis .callout-danger {
    background: rgba(220, 53, 69, 0.2);
    border-color: #dc3545;
  }
  
  body.dark-mode .chart-analysis .callout-info {
    background: rgba(23, 162, 184, 0.2);
    border-color: #17a2b8;
  }
</style>
<script>
$(function(){
  const makeChart = (el, cfg)=> new Chart(document.getElementById(el).getContext('2d'), cfg);

  // color helpers (must be defined before charts that use them) - Gold and Blue theme
  const HSL = (h,s,l,a=1)=>`hsla(${h}, ${s}%, ${l}%, ${a})`;
  // Gold to Blue gradient (gold: ~45deg, blue: ~210deg)
  const generateGradient = (n, start=45, end=210)=>{ // gold->blue
    const colors=[]; for(let i=0;i<n;i++){const h=start + (end-start)*(i/(Math.max(1,n-1))); colors.push(HSL(Math.round(h), 70, 50, 0.8));} return colors;
  };
  // Gold and Blue categorical palette
  const categoricalPalette = (n)=>{
    // Gold variations: #FFD700 (gold), #FFA500 (orange-gold), #FFC125 (goldenrod), #DAA520 (goldenrod), #B8860B (dark goldenrod)
    // Blue variations: #0066CC (blue), #1E90FF (dodger blue), #4169E1 (royal blue), #0000CD (medium blue), #000080 (navy)
    const base=[
      'rgba(255,215,0,0.8)',    // Gold
      'rgba(0,102,204,0.8)',    // Blue
      'rgba(255,165,0,0.8)',    // Orange-Gold
      'rgba(30,144,255,0.8)',   // Dodger Blue
      'rgba(255,193,37,0.8)',   // Goldenrod
      'rgba(65,105,225,0.8)',   // Royal Blue
      'rgba(218,165,32,0.8)',   // Goldenrod
      'rgba(0,0,205,0.8)',     // Medium Blue
      'rgba(184,134,11,0.8)',   // Dark Goldenrod
      'rgba(0,0,128,0.8)'       // Navy Blue
    ];
    const out=[]; for(let i=0;i<n;i++){out.push(base[i%base.length]);} return out;
  };

  // Simple value labels plugin (shows values when enabled and small counts)
  const ValueLabelPlugin = {
    id:'valueLabel', afterDatasetsDraw(chart, args, opts){
      if(!chart.options || !chart.options._showValues) return;
      const datasets = chart.data && chart.data.datasets ? chart.data.datasets : [];
      if(!datasets.length) return;
      const first = datasets[0].data || [];
      const max = first.reduce((m,v)=>Math.max(m, Number(v)||0), 0);
      if(max > 10) return;
      const ctx = chart.ctx; ctx.save(); ctx.fillStyle = (getComputedStyle(document.body).getPropertyValue('--text-color')||'#111').trim() || '#111'; ctx.font = '12px sans-serif'; ctx.textAlign='center'; ctx.textBaseline='bottom';
      datasets.forEach((ds,di)=>{
        const meta = chart.getDatasetMeta(di);
        meta.data.forEach((el,idx)=>{
          const val = ds.data[idx]; if(val==null) return;
          const pos = el.tooltipPosition();
          const y = Math.min(el.y, pos.y) - 2;
          ctx.fillText(String(val), pos.x, y);
        });
      });
      ctx.restore();
    }
  };
  if (window.Chart && !Chart.registry.plugins.get('valueLabel')) { Chart.register(ValueLabelPlugin); }

  const commonBarOpts = {
    maintainAspectRatio: false, 
    responsive: true, 
    plugins: {legend: {display: false}}, 
    scales: {
      x: {ticks: {autoSkip: false}, grid:{color: document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)'}},
      y: {
        grid:{color: document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)'},
        ticks: {
          stepSize: 10,
          callback: function(value) {
            return Math.round(value);
          }
        }
      }
    }
  };
  const pyramidChart = makeChart('populationPyramidChart', {type:'bar', data:{labels:[],datasets:[]}, options:{maintainAspectRatio:false, responsive:true, indexAxis:'y',plugins:{legend:{display:false},tooltip:{callbacks:{label:function(context){return context.dataset.label+': '+Math.abs(context.parsed.x).toLocaleString('en-US');}}}},scales:{x:{stacked:true,ticks:{callback:(v)=>Math.abs(v).toLocaleString('en-US'), stepSize: 10}},y:{stacked:true}}}});
  const civilChart = makeChart('civilChart', {type:'bar', data:{labels:[],datasets:[]}, options:{...commonBarOpts, indexAxis:'y'}});
  // Ensure category names show on the y-axis for Civil Status
  if (civilChart && civilChart.options && civilChart.options.scales && civilChart.options.scales.y) {
    civilChart.options.scales.y.ticks.callback = function(value){
      return civilChart.data && civilChart.data.labels && civilChart.data.labels.length
        ? (civilChart.data.labels[value] ?? value)
        : value;
    };
  }
  const religionChart = makeChart('religionChart', {type:'bar', data:{labels:[],datasets:[]}, options:{...commonBarOpts, indexAxis:'y'}});
  if (religionChart && religionChart.options && religionChart.options.scales && religionChart.options.scales.y) {
    religionChart.options.scales.y.ticks.callback = function(value){
      return religionChart.data && religionChart.data.labels && religionChart.data.labels.length
        ? (religionChart.data.labels[value] ?? value)
        : value;
    };
  }
  const weddingChart = makeChart('weddingChart', {type:'bar', data:{labels:[],datasets:[]}, options:{...commonBarOpts, indexAxis:'y'}});
  if (weddingChart && weddingChart.options && weddingChart.options.scales && weddingChart.options.scales.y) {
    weddingChart.options.scales.y.ticks.callback = function(value){
      return weddingChart.data && weddingChart.data.labels && weddingChart.data.labels.length
        ? (weddingChart.data.labels[value] ?? value)
        : value;
    };
  }
  const pregnancyStatusChart = makeChart('pregnancyStatusChart', {type:'doughnut', data:{labels:[],datasets:[]}, options:{maintainAspectRatio:false, responsive:true, plugins:{legend:{display:false}}, rotation: 0}}); // Start from 12 o'clock (top)
  const philhealthChart = makeChart('philhealthChart', {type:'doughnut', data:{labels:[],datasets:[]}, options:{maintainAspectRatio:false, responsive:true, plugins:{legend:{display:false}}, rotation: 0}}); // Start from 12 o'clock (top)
  const educationChart = makeChart('educationChart', {type:'bar', data:{labels:[],datasets:[]}, options:{...commonBarOpts, indexAxis:'y'}});
  if (educationChart && educationChart.options && educationChart.options.scales && educationChart.options.scales.y) {
    educationChart.options.scales.y.ticks.callback = function(value){
      return educationChart.data && educationChart.data.labels && educationChart.data.labels.length
        ? (educationChart.data.labels[value] ?? value)
        : value;
    };
  }
  const employmentChart = makeChart('employmentChart', {type:'bar', data:{labels:[],datasets:[]}, options:{...commonBarOpts, indexAxis:'y'}});
  if (employmentChart && employmentChart.options && employmentChart.options.scales && employmentChart.options.scales.y) {
    employmentChart.options.scales.y.ticks.callback = function(value){
      return employmentChart.data && employmentChart.data.labels && employmentChart.data.labels.length
        ? (employmentChart.data.labels[value] ?? value)
        : value;
    };
  }
  const incomeChart = makeChart('incomeChart', {type:'bar', data:{labels:[],datasets:[]}, options:commonBarOpts});
  const attendanceChart = makeChart('attendanceChart', {type:'doughnut', data:{labels:[],datasets:[]}, options:{maintainAspectRatio:false, responsive:true, plugins:{legend:{display:false}}, rotation: 0}}); // Start from 12 o'clock (top)
  const fpMethodsMaleChart = makeChart('fpMethodsMaleChart', {
    type:'bar', 
    data:{labels:[],datasets:[{label:'Male', data:[], backgroundColor:'rgba(0,102,204,0.7)'}]}, // Blue
    options:{maintainAspectRatio:false, responsive:true, indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{autoSkip:false}}, y:{beginAtZero:true, ticks:{stepSize:1}}}}
  });
  const fpMethodsFemaleChart = makeChart('fpMethodsFemaleChart', {
    type:'bar', 
    data:{labels:[],datasets:[{label:'Female', data:[], backgroundColor:'rgba(255,215,0,0.7)'}]}, // Gold
    options:{maintainAspectRatio:false, responsive:true, indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{autoSkip:false}}, y:{beginAtZero:true, ticks:{stepSize:1}}}}
  });
  const fpIntentMaleChart = makeChart('fpIntentMaleChart', {
    type:'bar', 
    data:{labels:['Yes','No'],datasets:[{label:'Male', data:[0,0], backgroundColor:'rgba(0,102,204,0.7)'}]}, // Blue
    options:{maintainAspectRatio:false, responsive:true, indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{autoSkip:false}}, y:{beginAtZero:true, ticks:{stepSize:1}}}}
  });
  const fpIntentFemaleChart = makeChart('fpIntentFemaleChart', {
    type:'bar', 
    data:{labels:['Yes','No'],datasets:[{label:'Female', data:[0,0], backgroundColor:'rgba(255,215,0,0.7)'}]}, // Gold
    options:{maintainAspectRatio:false, responsive:true, indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{autoSkip:false}}, y:{beginAtZero:true, ticks:{stepSize:1}}}}
  });
  const topBarangaysChart = makeChart('topBarangaysChart', {
    type:'bar', 
    data:{
      labels:[],
      datasets:[{
        label:'Registrations',
        data:[],
        backgroundColor:categoricalPalette(5)
      }]
    }, 
    options:{
      maintainAspectRatio: false,
      responsive: true,
      indexAxis: 'y',
      layout: {
        padding: {
          bottom: 10
        }
      },
      plugins:{legend:{display:false}}, 
      scales:{
        x:{
          beginAtZero:true,
          ticks:{
            stepSize:10,
            callback:function(value){return Math.round(value);}
          }
        }, 
        y:{
          ticks:{autoSkip:false}
        }
      }
    }
  });
  const marriageSeasonalityChart = makeChart('marriageSeasonalityChart', {type:'bar', data:{labels:[],datasets:[{label:'Couples',data:[],backgroundColor:generateGradient(12, 45, 210)}]}, options:commonBarOpts}); // Gold to Blue gradient
  const sessionsMonthlyChart = makeChart('sessionsMonthlyChart', {
    type:'line', 
    data:{
      labels:[],
      datasets:[{
        label:'Sessions',
        data:[],
        borderColor:'rgba(0,102,204,0.9)', // Blue
        backgroundColor:'rgba(0,102,204,0.15)', // Light blue fill
        tension:0.25,
        fill:true
      }]
    }, 
    options:{
      maintainAspectRatio:false,
      responsive:true,
      plugins:{legend:{display:false}}, 
      scales:{
        y:{
          grid:{color: document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)'},
          ticks:{
            stepSize:10, 
            callback:function(value){
              return Math.round(value);
            }
          }
        }
      }
    }
  });
  
  // Predefined categories for consistent display (matching form dropdowns)
  const religionCategories = ['Aglipay', 'Bible Baptist Church', 'Church of Christ', 'Jehovas Witness', 'Iglesia ni Cristo', 'Islam', 'Roman Catholic', 'Seventh Day Adventist', 'Iglesia Filipina Independente', 'United Church of Christ in the PH', 'None', 'Other'];
  const weddingCategories = ['Civil', 'Church'];
  const employmentCategories = ['Employed', 'Self-employed', 'Unemployed'];
  const educationCategories = ['No Education', 'Pre School', 'Elementary Level', 'Elementary Graduate', 'High School Level', 'High School Graduate', 'Junior HS Level', 'Junior HS Graduate', 'Senior HS Level', 'Senior HS Graduate', 'College Level', 'College Graduate', 'Vocational/Technical', 'ALS', 'Post Graduate'];
  const incomeCategories = ['5000 below', '5999-9999', '10000-14999', '15000-19999', '20000-24999', '25000 above'];

  // Function to check if chart container is visible before rendering
  function isChartVisible(chartId) {
    const container = document.getElementById(chartId + '-item');
    return container && !container.classList.contains('hidden');
  }
  
  // Function to safely update chart data only if visible
  function safeUpdateChart(chart, chartId, updateFunction) {
    if (isChartVisible(chartId)) {
      updateFunction();
    }
  }

  function renderLegend(containerId, entries){
    const el=document.getElementById(containerId); el.innerHTML='';
    entries.forEach(e=>{
      const item=document.createElement('div'); item.className='legend-item';
      const color=document.createElement('div'); color.className='legend-color'; color.style.backgroundColor=e.color;
      const text=document.createElement('span'); text.textContent=e.text;
      if(e.rank){ const r=document.createElement('span'); r.className='legend-rank'; r.textContent=e.rank; item.appendChild(r); }
      item.appendChild(color); item.appendChild(text); el.appendChild(item);
    });
  }
  
  // Store analysis data for each chart
  const analysisDataStore = new Map();
  
  // Function to store analysis data
  function storeAnalysisData(chartId, html) {
    analysisDataStore.set(chartId, html);
  }
  
  // Function to show analysis modal
  window.showAnalysisModal = function(chartId, chartTitle) {
    const analysisHtml = analysisDataStore.get(chartId);
    if (!analysisHtml) {
      Swal.fire({
        icon: 'info',
        title: 'No Analysis Available',
        text: 'Analysis data is not yet available for this chart. Please wait for the data to load.',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
      });
      return;
    }
    
    document.getElementById('analysisModalLabel').innerHTML = `<i class="fas fa-chart-line mr-2"></i>Analysis - ${chartTitle}`;
    document.getElementById('analysisModalBody').innerHTML = analysisHtml;
    $('#analysisModal').modal('show');
  };
  
  // Enhanced analysis function that routes to chart-specific analyzers
  function renderDistributionAnalysis(chartId, data, chartTitle) {
    if (!data || !data.labels || !data.values) return;
    
    // Route to specific analysis functions based on chart type
    if (chartId.includes('civil')) {
      renderCivilStatusAnalysis(chartId, data);
    } else if (chartId.includes('religion')) {
      renderReligionAnalysis(chartId, data);
    } else if (chartId.includes('wedding')) {
      renderWeddingTypeAnalysis(chartId, data);
    } else if (chartId.includes('pregnancy')) {
      renderPregnancyAnalysis(chartId, data);
    } else if (chartId.includes('philhealth')) {
      renderPhilHealthAnalysis(chartId, data);
    } else if (chartId.includes('education')) {
      renderEducationAnalysis(chartId, data);
    } else if (chartId.includes('employment')) {
      renderEmploymentAnalysis(chartId, data);
    } else if (chartId.includes('income')) {
      renderIncomeAnalysis(chartId, data);
    } else if (chartId.includes('attendance')) {
      renderAttendanceAnalysis(chartId, data);
    } else if (chartId.includes('topBarangays')) {
      renderTopBarangaysAnalysis(chartId, data);
    } else if (chartId.includes('marriageSeasonality')) {
      renderMarriageSeasonalityAnalysis(chartId, data);
    } else if (chartId.includes('sessionsMonthly')) {
      renderSessionsMonthlyAnalysis(chartId, data);
    } else if (chartId.includes('fpMethods')) {
      renderFPMethodsAnalysis(chartId, data);
    } else if (chartId.includes('fpIntent')) {
      renderFPIntentAnalysis(chartId, data);
    } else {
      // Generic fallback
      renderGenericAnalysis(chartId, data, chartTitle);
    }
  }
  
  // ========== EDUCATION ANALYSIS ==========
  function renderEducationAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    // Categorize education levels
    const lowEducation = ['No Education', 'Pre School', 'Elementary Level', 'Elementary Graduate'];
    const midEducation = ['High School Level', 'High School Graduate', 'Junior HS Level', 'Junior HS Graduate', 'Senior HS Level', 'Senior HS Graduate'];
    const highEducation = ['College Level', 'College Graduate', 'Vocational/Technical', 'Post Graduate'];
    
    const lowEdTotal = labels.reduce((sum, label, idx) => sum + (lowEducation.includes(label) ? (values[idx] || 0) : 0), 0);
    const midEdTotal = labels.reduce((sum, label, idx) => sum + (midEducation.includes(label) ? (values[idx] || 0) : 0), 0);
    const highEdTotal = labels.reduce((sum, label, idx) => sum + (highEducation.includes(label) ? (values[idx] || 0) : 0), 0);
    
    const lowEdPercentage = total > 0 ? (lowEdTotal / total * 100).toFixed(1) : 0;
    const midEdPercentage = total > 0 ? (midEdTotal / total * 100).toFixed(1) : 0;
    const highEdPercentage = total > 0 ? (highEdTotal / total * 100).toFixed(1) : 0;
    
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    
    let html = `
      <div class="row">
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-book-reader"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Elementary & Below</span>
              <span class="info-box-number">${lowEdTotal}</span>
              <span class="info-box-text">${lowEdPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-graduation-cap"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">High School</span>
              <span class="info-box-number">${midEdTotal}</span>
              <span class="info-box-text">${midEdPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-university"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">College & Above</span>
              <span class="info-box-number">${highEdTotal}</span>
              <span class="info-box-text">${highEdPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Participants</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-chart-bar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Most Common</span>
              <span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span>
              <span class="info-box-text">${maxPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Calculate couple count (assuming balanced gender distribution) - declared once at top
    const totalCouples = Math.round(total / 2);
    const educationDiversity = labels.filter((label, idx) => values[idx] > 0).length;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (lowEdPercentage > 30 && total > 0) {
      const lowEdCouples = Math.round(totalCouples * parseFloat(lowEdPercentage) / 100);
      html += `<li class="mb-2"><strong>Lower Education Levels (${lowEdPercentage}%, ~${lowEdCouples} couples):</strong> Significant portion with elementary education or below. For PMOC programs, this requires: (1) Simplified pre-marriage counseling materials using 6th-8th grade reading level, (2) Visual aids, diagrams, and interactive demonstrations for relationship concepts, (3) Oral presentations and group discussions rather than text-heavy materials, (4) Practical, hands-on activities for communication skills and conflict resolution, (5) Staff trained in plain language communication and cultural sensitivity. These couples may need extra support understanding marriage requirements, legal aspects, and relationship skills.</li>`;
    } else if (lowEdPercentage > 15 && lowEdPercentage <= 30 && total > 0) {
      const lowEdCouples = Math.round(totalCouples * parseFloat(lowEdPercentage) / 100);
      html += `<li class="mb-2"><strong>Moderate Lower Education (${lowEdPercentage}%, ~${lowEdCouples} couples):</strong> Ensure PMOC materials include visual supports, clear language, and multiple learning formats. Provide simplified versions of key concepts while maintaining educational value for pre-marriage counseling.</li>`;
    }
    
    if (highEdPercentage > 50 && total > 0) {
      const highEdCouples = Math.round(totalCouples * parseFloat(highEdPercentage) / 100);
      html += `<li class="mb-2"><strong>College-Educated Majority (${highEdPercentage}%, ~${highEdCouples} couples):</strong> High proportion with college education indicates an educated participant base for PMOC. This group may: (1) Benefit from advanced counseling approaches and evidence-based interventions, (2) Engage with detailed information on relationship psychology and communication theories, (3) Participate in specialized workshops on advanced relationship skills, (4) Serve as peer mentors for other couples, (5) Provide valuable feedback for program improvement. However, maintain accessible options for all education levels.</li>`;
    } else if (highEdPercentage > 30 && highEdPercentage <= 50 && total > 0) {
      const highEdCouples = Math.round(totalCouples * parseFloat(highEdPercentage) / 100);
      html += `<li class="mb-2"><strong>Substantial College Education (${highEdPercentage}%, ~${highEdCouples} couples):</strong> Balance advanced PMOC content with accessible materials. Offer tiered program options: basic pre-marriage counseling for all and advanced modules for those seeking deeper engagement with relationship concepts.</li>`;
    }
    
    if (midEdPercentage > 40 && total > 0) {
      const midEdCouples = Math.round(totalCouples * parseFloat(midEdPercentage) / 100);
      html += `<li class="mb-2"><strong>High School Majority (${midEdPercentage}%, ~${midEdCouples} couples):</strong> High school education represents the majority, indicating typical educational profile for PMOC participants. Design primary program materials for 9th-12th grade reading levels with: (1) Clear, practical pre-marriage counseling content, (2) Relatable examples and case studies, (3) Interactive activities for relationship skills, (4) Culturally relevant materials, (5) Balance of theory and practical application for marriage preparation.</li>`;
    }
    
    if (maxIndex >= 0 && maxValue > 0 && total > 0) {
      const maxEdCouples = Math.round(totalCouples * parseFloat(maxPercentage) / 100);
      html += `<li class="mb-2"><strong>Most Common Education Level:</strong> ${labels[maxIndex]} (${maxPercentage}%, ~${maxEdCouples} couples). Tailor primary PMOC materials, communication style, and delivery methods to this education level. Develop supplementary resources for other education groups to ensure all couples receive appropriate pre-marriage counseling.</li>`;
    }
    
    // Education diversity insight (already declared above)
    if (educationDiversity >= 8 && total > 0) {
      html += `<li class="mb-2"><strong>High Education Diversity:</strong> ${educationDiversity} different education levels represented. This diversity requires flexible PMOC program design with materials and approaches suitable for various literacy levels. Consider offering multiple program formats (basic, standard, advanced) to accommodate all couples effectively.</li>`;
    }
    
    if (total < 30) {
      html += `<li class="mb-2"><strong>Small Sample Size:</strong> ${total} participants (~${totalCouples} couples). Consider expanding outreach to improve data representation and ensure all education levels are adequately represented in PMOC programs.</li>`;
    } else if (total >= 100) {
      html += `<li class="mb-2"><strong>Good Sample Size:</strong> ${total} participants (~${totalCouples} couples). Education distribution patterns are statistically significant for PMOC program planning, material development, and staff training. Use this data to inform evidence-based program design.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations (totalCouples already declared above)
    const recommendations = [];
    
    if (lowEdPercentage > 30 && total > 0) {
      const lowEdCouples = Math.round(totalCouples * parseFloat(lowEdPercentage) / 100);
      recommendations.push(`<strong>Lower Education PMOC Strategy (${lowEdPercentage}%, ~${lowEdCouples} couples):</strong> (1) Develop simplified pre-marriage counseling materials using 6th-8th grade reading level, (2) Create visual aids, diagrams, and illustrated guides for relationship concepts, (3) Use oral presentations, videos, and group discussions instead of text-heavy materials, (4) Design hands-on activities for communication skills, conflict resolution, and marriage preparation, (5) Train PMOC staff in plain language communication and cultural sensitivity, (6) Consider peer educators from similar backgrounds, (7) Provide simplified versions of legal requirements and marriage procedures, (8) Use storytelling and real-life examples rather than abstract concepts.`);
    } else if (lowEdPercentage > 15 && lowEdPercentage <= 30 && total > 0) {
      const lowEdCouples = Math.round(totalCouples * parseFloat(lowEdPercentage) / 100);
      recommendations.push(`<strong>Moderate Lower Education Support (${lowEdPercentage}%, ~${lowEdCouples} couples):</strong> (1) Ensure PMOC materials include visual supports, clear language, and multiple learning formats, (2) Provide simplified versions of key pre-marriage counseling concepts while maintaining educational value, (3) Offer assistance with reading materials if needed.`);
    }
    
    if (highEdPercentage > 50 && total > 0) {
      const highEdCouples = Math.round(totalCouples * parseFloat(highEdPercentage) / 100);
      recommendations.push(`<strong>College-Educated PMOC Strategy (${highEdPercentage}%, ~${highEdCouples} couples):</strong> (1) Offer advanced counseling approaches and evidence-based interventions, (2) Provide detailed information on relationship psychology, communication theories, and marriage dynamics, (3) Develop specialized workshops on advanced relationship skills, (4) Consider offering continuing education credits or certificates, (5) Provide access to research-based resources and literature, (6) Engage this group as peer mentors for other couples, (7) Solicit feedback for program improvement, (8) However, maintain accessible options for all education levels to ensure inclusivity.`);
    } else if (highEdPercentage > 30 && highEdPercentage <= 50 && total > 0) {
      const highEdCouples = Math.round(totalCouples * parseFloat(highEdPercentage) / 100);
      recommendations.push(`<strong>Substantial College Education (${highEdPercentage}%, ~${highEdCouples} couples):</strong> Balance advanced PMOC content with accessible materials. Offer tiered program options: (1) Basic pre-marriage counseling for all participants, (2) Advanced modules for those seeking deeper engagement, (3) Optional reading materials and resources for further learning, (4) Discussion groups for advanced topics.`);
    }
    
    if (midEdPercentage > 40 && total > 0) {
      const midEdCouples = Math.round(totalCouples * parseFloat(midEdPercentage) / 100);
      recommendations.push(`<strong>High School Majority PMOC Design (${midEdPercentage}%, ~${midEdCouples} couples):</strong> Design primary PMOC materials for high school reading levels (9th-12th grade) with: (1) Clear, practical pre-marriage counseling content, (2) Relatable examples and case studies of couples, (3) Interactive activities for relationship skills development, (4) Culturally relevant materials and examples, (5) Balance of theory and practical application, (6) Engaging formats (videos, discussions, role-plays), (7) Real-world scenarios for marriage preparation.`);
    }
    
    if (maxIndex >= 0 && maxValue > 0 && total > 0) {
      const maxEdCouples = Math.round(totalCouples * parseFloat(maxPercentage) / 100);
      recommendations.push(`<strong>Primary Education Level Focus (${labels[maxIndex]}, ${maxPercentage}%, ~${maxEdCouples} couples):</strong> Tailor primary PMOC materials, communication style, and delivery methods to this education level. (1) Develop core program content appropriate for ${labels[maxIndex]} reading level, (2) Use language and examples relevant to this group, (3) Design activities that match their learning preferences, (4) Create supplementary resources for other education groups to ensure inclusivity, (5) Train staff to adapt communication to different education levels.`);
    }
    
    const zeroEducation = labels.filter((label, idx) => values[idx] === 0);
    if (zeroEducation.length > 0 && zeroEducation.length < labels.length && total > 0) {
      recommendations.push(`<strong>Missing Education Levels:</strong> ${zeroEducation.length} education levels have no representation: ${zeroEducation.join(', ')}. (1) Conduct targeted outreach to these groups through appropriate channels (schools, community centers, social media), (2) Identify barriers preventing their participation in PMOC, (3) Develop specific outreach strategies for each missing education level, (4) Ensure PMOC programs are welcoming and accessible to all education backgrounds.`);
    }
    
    // Education diversity recommendation (educationDiversity already declared above)
    if (educationDiversity >= 8 && total > 0) {
      recommendations.push(`<strong>High Education Diversity Strategy:</strong> ${educationDiversity} different education levels represented. (1) Develop flexible PMOC program design with materials suitable for various literacy levels, (2) Offer multiple program formats (basic, standard, advanced), (3) Train staff to adapt to different education levels within the same session, (4) Use universal design principles in material development, (5) Provide individualized support as needed.`);
    }
    
    if (total < 30) {
      recommendations.push(`<strong>Sample Size Expansion:</strong> Small sample (${total} participants, ~${totalCouples} couples). (1) Expand outreach through schools, community centers, and social media, (2) Partner with educational institutions for referrals, (3) Remove barriers to participation (transportation, scheduling, language), (4) Improve representation across all education levels for comprehensive PMOC program planning.`);
    } else if (total >= 100) {
      recommendations.push(`<strong>Data Utilization:</strong> Good sample size (${total} participants, ~${totalCouples} couples). Education distribution is statistically significant. (1) Use this data to inform PMOC material development, (2) Guide staff training on working with different education levels, (3) Plan program delivery methods, (4) Allocate resources appropriately, (5) Develop evidence-based program design.`);
    }
    
    // General PMOC recommendation
    recommendations.push(`<strong>General PMOC Education Strategy:</strong> (1) Ensure all pre-marriage counseling materials are accessible across education levels, (2) Provide multiple learning formats (visual, auditory, kinesthetic), (3) Train staff to recognize and adapt to different education levels, (4) Regularly review program effectiveness for different education groups, (5) Monitor trends and adjust materials as needed, (6) Ensure no couple is excluded due to education level.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`<strong>Balanced Education Distribution:</strong> (1) Continue monitoring trends, (2) Ensure PMOC materials are accessible across all education levels, (3) Regularly review program effectiveness for different education groups, (4) Maintain inclusive program design that serves all couples regardless of education background.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== EMPLOYMENT ANALYSIS ==========
  function renderEmploymentAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const employedIndex = labels.indexOf('Employed');
    const selfEmployedIndex = labels.indexOf('Self-employed');
    const unemployedIndex = labels.indexOf('Unemployed');
    
    const employedCount = employedIndex >= 0 ? values[employedIndex] : 0;
    const selfEmployedCount = selfEmployedIndex >= 0 ? values[selfEmployedIndex] : 0;
    const unemployedCount = unemployedIndex >= 0 ? values[unemployedIndex] : 0;
    
    const employedPercentage = total > 0 ? (employedCount / total * 100).toFixed(1) : 0;
    const selfEmployedPercentage = total > 0 ? (selfEmployedCount / total * 100).toFixed(1) : 0;
    const unemployedPercentage = total > 0 ? (unemployedCount / total * 100).toFixed(1) : 0;
    
    const employmentRate = total > 0 ? ((employedCount + selfEmployedCount) / total * 100).toFixed(1) : 0;
    
    // Calculate couple count - declared once at top
    const totalCouples = Math.round(total / 2);
    const employedCouples = Math.round(totalCouples * parseFloat(employedPercentage) / 100);
    const selfEmployedCouples = Math.round(totalCouples * parseFloat(selfEmployedPercentage) / 100);
    const unemployedCouples = Math.round(totalCouples * parseFloat(unemployedPercentage) / 100);
    
    let html = `
      <div class="row">
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-briefcase"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Employed</span>
              <span class="info-box-number">${employedCount}</span>
              <span class="info-box-text">${employedPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-user-tie"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Self-employed</span>
              <span class="info-box-number">${selfEmployedCount}</span>
              <span class="info-box-text">${selfEmployedPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-user-slash"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Unemployed</span>
              <span class="info-box-number">${unemployedCount}</span>
              <span class="info-box-text">${unemployedPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Participants</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-chart-line"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Employment Rate</span>
              <span class="info-box-number">${employmentRate}%</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights (couple counts already declared above)
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (employmentRate >= 80 && total > 0) {
      html += `<li class="mb-2"><strong>High Employment Rate (${employmentRate}%, ~${employedCouples + selfEmployedCouples} couples):</strong> Economic stability among PMOC participants indicates: (1) Couples have financial capacity for marriage preparation and related expenses, (2) Participants may have limited availability during work hours, requiring flexible PMOC scheduling, (3) Higher likelihood of financial readiness for marriage and family planning, (4) May benefit from programs addressing work-life balance and relationship management for working couples, (5) Potential for premium or extended PMOC services for those with stable income.</li>`;
    } else if (employmentRate < 60 && total > 0) {
      html += `<li class="mb-2"><strong>Low Employment Rate (${employmentRate}%, ~${employedCouples + selfEmployedCouples} couples):</strong> Economic vulnerability may impact: (1) Financial capacity for marriage preparation and wedding expenses, (2) Access to healthcare and family planning services, (3) Ability to attend PMOC sessions if transportation costs are a barrier, (4) Financial stress affecting relationship quality and marriage readiness. Consider offering free or subsidized PMOC services, flexible payment options, and financial counseling as part of pre-marriage preparation.</li>`;
    } else if (total > 0) {
      html += `<li class="mb-2"><strong>Moderate Employment Rate (${employmentRate}%, ~${employedCouples + selfEmployedCouples} couples):</strong> Mixed economic situation requires flexible PMOC program design: (1) Offer both free and paid service options, (2) Provide flexible scheduling for working and non-working participants, (3) Include financial planning and budgeting as part of pre-marriage counseling, (4) Address economic concerns in relationship counseling sessions.</li>`;
    }
    
    if (unemployedPercentage > 25 && total > 0) {
      html += `<li class="mb-2"><strong>High Unemployment (${unemployedPercentage}%, ~${unemployedCouples} couples):</strong> Significant unemployment may: (1) Create financial stress affecting relationship quality and marriage readiness, (2) Require free or heavily subsidized PMOC services, (3) Benefit from job placement assistance and skills training as part of marriage preparation, (4) Need financial counseling and budgeting support, (5) Require flexible scheduling since unemployed participants may have more availability. Consider integrating economic empowerment programs with PMOC services.</li>`;
    } else if (unemployedPercentage > 10 && unemployedPercentage <= 25 && total > 0) {
      html += `<li class="mb-2"><strong>Moderate Unemployment (${unemployedPercentage}%, ~${unemployedCouples} couples):</strong> Some couples face unemployment challenges. Ensure PMOC programs: (1) Address financial stress in relationships, (2) Provide information on free or low-cost services, (3) Include financial planning in pre-marriage counseling, (4) Offer flexible payment options, (5) Consider job placement resources and referrals.</li>`;
    }
    
    if (selfEmployedPercentage > 30 && total > 0) {
      html += `<li class="mb-2"><strong>Significant Self-Employment (${selfEmployedPercentage}%, ~${selfEmployedCouples} couples):</strong> High self-employment indicates: (1) Entrepreneurial activity but potentially irregular income, (2) Need for flexible PMOC scheduling to accommodate business demands, (3) Financial planning challenges due to income variability, (4) Potential for business-related stress affecting relationships, (5) May benefit from programs addressing work-life balance for entrepreneurs. Consider flexible payment plans and scheduling options.</li>`;
    } else if (selfEmployedPercentage > 15 && selfEmployedPercentage <= 30 && total > 0) {
      html += `<li class="mb-2"><strong>Moderate Self-Employment (${selfEmployedPercentage}%, ~${selfEmployedCouples} couples):</strong> Some couples are self-employed. PMOC programs should: (1) Offer flexible scheduling for business owners, (2) Address financial planning for irregular income, (3) Include work-life balance topics, (4) Consider payment flexibility for variable income situations.</li>`;
    }
    
    if (employedPercentage > 50 && total > 0) {
      html += `<li class="mb-2"><strong>Employed Majority (${employedPercentage}%, ~${employedCouples} couples):</strong> Most participants are employed, indicating: (1) Need for PMOC sessions during evenings, weekends, or flexible hours, (2) Potential for work-related stress affecting relationships, (3) Financial stability for marriage preparation, (4) May benefit from programs addressing dual-career couple dynamics, (5) Consider offering online or hybrid PMOC options for busy working couples.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations (couple counts already declared above)
    const recommendations = [];
    
    if (unemployedPercentage > 25 && total > 0) {
      recommendations.push(`<strong>High Unemployment PMOC Strategy (${unemployedPercentage}%, ~${unemployedCouples} couples):</strong> (1) Provide free or heavily subsidized PMOC services for unemployed couples, (2) Integrate job placement assistance and skills training with pre-marriage counseling, (3) Partner with employment agencies and vocational training centers, (4) Include financial counseling and budgeting as core PMOC components, (5) Address financial stress and its impact on relationships in counseling sessions, (6) Offer flexible scheduling since unemployed participants may have more availability, (7) Consider economic empowerment programs alongside PMOC services, (8) Provide information on government assistance programs and resources.`);
    } else if (unemployedPercentage > 10 && unemployedPercentage <= 25 && total > 0) {
      recommendations.push(`<strong>Moderate Unemployment Support (${unemployedPercentage}%, ~${unemployedCouples} couples):</strong> (1) Ensure PMOC programs address financial stress in relationships, (2) Provide information on free or low-cost services, (3) Include financial planning in pre-marriage counseling, (4) Offer flexible payment options, (5) Consider job placement resources and referrals, (6) Train staff to recognize and address economic stress in relationships.`);
    }
    
    if (employmentRate >= 80 && total > 0) {
      recommendations.push(`<strong>High Employment PMOC Strategy (${employmentRate}%, ~${employedCouples + selfEmployedCouples} couples):</strong> (1) Schedule PMOC sessions during evenings, weekends, or offer flexible hours, (2) Consider online or hybrid PMOC options for busy working couples, (3) Focus on comprehensive pre-marriage counseling and relationship skills, (4) Address work-life balance and dual-career couple dynamics, (5) Offer premium or extended PMOC services for those with stable income, (6) Include financial planning for working couples, (7) Address work-related stress and its impact on relationships, (8) Ensure services are accessible during non-working hours.`);
    } else if (employmentRate < 60 && total > 0) {
      recommendations.push(`<strong>Low Employment PMOC Strategy (${employmentRate}%):</strong> (1) Offer free or subsidized PMOC services, (2) Provide flexible payment options and sliding fee scales, (3) Include financial counseling as part of pre-marriage preparation, (4) Address economic concerns in relationship counseling, (5) Partner with social services for additional support, (6) Consider transportation assistance for PMOC attendance, (7) Integrate economic empowerment with relationship counseling.`);
    }
    
    if (selfEmployedPercentage > 30 && total > 0) {
      recommendations.push(`<strong>High Self-Employment PMOC Strategy (${selfEmployedPercentage}%, ~${selfEmployedCouples} couples):</strong> (1) Offer highly flexible PMOC scheduling to accommodate business demands, (2) Provide flexible payment plans for irregular income situations, (3) Include financial planning for variable income in pre-marriage counseling, (4) Address business-related stress and its impact on relationships, (5) Offer programs on work-life balance for entrepreneurs, (6) Consider online or hybrid options for busy business owners, (7) Include business partnership dynamics in relationship counseling, (8) Provide resources on managing business and relationship priorities.`);
    } else if (selfEmployedPercentage > 15 && selfEmployedPercentage <= 30 && total > 0) {
      recommendations.push(`<strong>Moderate Self-Employment Support (${selfEmployedPercentage}%, ~${selfEmployedCouples} couples):</strong> (1) Offer flexible scheduling for business owners, (2) Address financial planning for irregular income, (3) Include work-life balance topics in PMOC, (4) Consider payment flexibility for variable income, (5) Provide resources on managing business and relationship priorities.`);
    }
    
    if (employedPercentage > 50 && total > 0) {
      recommendations.push(`<strong>Employed Majority PMOC Strategy (${employedPercentage}%, ~${employedCouples} couples):</strong> (1) Schedule PMOC sessions during evenings, weekends, or offer flexible hours, (2) Consider online or hybrid PMOC options for busy working couples, (3) Address dual-career couple dynamics in counseling, (4) Include work-life balance and time management in pre-marriage counseling, (5) Address work-related stress and its impact on relationships, (6) Offer programs on managing career and relationship priorities, (7) Ensure services are accessible during non-working hours, (8) Consider offering extended or intensive weekend PMOC programs.`);
    }
    
    // General PMOC employment recommendations
    recommendations.push(`<strong>General PMOC Employment Strategy:</strong> (1) Offer flexible scheduling options (evenings, weekends, online) to accommodate all employment statuses, (2) Include financial planning and budgeting as core PMOC components regardless of employment status, (3) Address work-related stress and its impact on relationships in all counseling sessions, (4) Provide sliding fee scales and flexible payment options, (5) Train staff to recognize and address economic stress in relationships, (6) Ensure PMOC services are accessible to all employment statuses, (7) Monitor employment trends and adjust program design accordingly.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`<strong>Balanced Employment Distribution:</strong> (1) Continue monitoring to ensure PMOC services are accessible to all employment statuses, (2) Maintain flexible scheduling and payment options to accommodate diverse employment situations.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== INCOME ANALYSIS ==========
  function renderIncomeAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    // Categorize income brackets
    const lowIncome = ['5000 below'];
    const lowerMiddle = ['5999-9999', '10000-14999'];
    const upperMiddle = ['15000-19999', '20000-24999'];
    const highIncome = ['25000 above'];
    
    const lowIncomeTotal = labels.reduce((sum, label, idx) => sum + (lowIncome.includes(label) ? (values[idx] || 0) : 0), 0);
    const lowerMiddleTotal = labels.reduce((sum, label, idx) => sum + (lowerMiddle.includes(label) ? (values[idx] || 0) : 0), 0);
    const upperMiddleTotal = labels.reduce((sum, label, idx) => sum + (upperMiddle.includes(label) ? (values[idx] || 0) : 0), 0);
    const highIncomeTotal = labels.reduce((sum, label, idx) => sum + (highIncome.includes(label) ? (values[idx] || 0) : 0), 0);
    
    const lowIncomePercentage = total > 0 ? (lowIncomeTotal / total * 100).toFixed(1) : 0;
    const lowerMiddlePercentage = total > 0 ? (lowerMiddleTotal / total * 100).toFixed(1) : 0;
    const upperMiddlePercentage = total > 0 ? (upperMiddleTotal / total * 100).toFixed(1) : 0;
    const highIncomePercentage = total > 0 ? (highIncomeTotal / total * 100).toFixed(1) : 0;
    
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    
    let html = `
      <div class="row">
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-peso-sign"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Low Income</span>
              <span class="info-box-text">< 5,000</span>
              <span class="info-box-number">${lowIncomeTotal}</span>
              <span class="info-box-text">${lowIncomePercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-peso-sign"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Lower Middle</span>
              <span class="info-box-text">6K-15K</span>
              <span class="info-box-number">${lowerMiddleTotal}</span>
              <span class="info-box-text">${lowerMiddlePercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-peso-sign"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Upper Middle</span>
              <span class="info-box-text">15K-25K</span>
              <span class="info-box-number">${upperMiddleTotal}</span>
              <span class="info-box-text">${upperMiddlePercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-peso-sign"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">High Income</span>
              <span class="info-box-text">> 25,000</span>
              <span class="info-box-number">${highIncomeTotal}</span>
              <span class="info-box-text">${highIncomePercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Couples</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-chart-bar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Most Common Bracket</span>
              <span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span>
              <span class="info-box-text">${maxPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (lowIncomePercentage > 40 && total > 0) {
      html += `<li class="mb-2">High proportion in low-income bracket (${lowIncomePercentage}%), indicating financial vulnerability that may impact access to family planning services.</li>`;
    }
    
    if ((lowerMiddlePercentage + upperMiddlePercentage) > 60 && total > 0) {
      html += `<li class="mb-2">Middle-income brackets dominate (${(lowerMiddlePercentage + upperMiddlePercentage).toFixed(1)}%), representing the typical economic profile of participants.</li>`;
    }
    
    if (highIncomePercentage > 20 && total > 0) {
      html += `<li class="mb-2">Significant high-income representation (${highIncomePercentage}%), indicating diverse economic backgrounds.</li>`;
    }
    
    if (maxIndex >= 0 && maxValue > 0) {
      html += `<li class="mb-2">Most common income bracket: ${labels[maxIndex]} (${maxPercentage}%). Tailor program pricing and payment options to this income level.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations
    const recommendations = [];
    if (lowIncomePercentage > 40) {
      recommendations.push(`High percentage in low-income bracket (${lowIncomePercentage}%). Implement sliding scale fees, provide financial assistance programs, and ensure free or subsidized family planning services are available.`);
    }
    
    if ((lowIncomePercentage + lowerMiddlePercentage) > 60) {
      recommendations.push(`Majority in lower income brackets (${(lowIncomePercentage + lowerMiddlePercentage).toFixed(1)}%). Consider partnerships with government programs, NGOs, and health insurance providers to reduce financial barriers.`);
    }
    
    if (highIncomePercentage > 20) {
      recommendations.push(`Significant high-income representation (${highIncomePercentage}%). Offer premium service options and consider private-pay programs to generate revenue for subsidizing low-income participants.`);
    }
    
    if (maxIndex >= 0 && maxValue > 0) {
      const bracketLabel = labels[maxIndex];
      if (bracketLabel.includes('5000') || bracketLabel.includes('below')) {
        recommendations.push(`Most common bracket is low income (${bracketLabel}, ${maxPercentage}%). Prioritize free services and financial assistance programs.`);
      } else if (bracketLabel.includes('25000') || bracketLabel.includes('above')) {
        recommendations.push(`Most common bracket is high income (${bracketLabel}, ${maxPercentage}%). Participants can afford comprehensive services; consider offering premium options and use revenue to subsidize low-income participants.`);
      } else if (bracketLabel.includes('15000') || bracketLabel.includes('20000')) {
        recommendations.push(`Most common bracket is middle income (${bracketLabel}, ${maxPercentage}%). Offer flexible payment plans and sliding scale fees to accommodate this group.`);
      } else {
        recommendations.push(`Most common bracket is ${bracketLabel} (${maxPercentage}%). Tailor program pricing and payment options to this income level.`);
      }
    }
    
    if (recommendations.length === 0) {
      recommendations.push(`<strong>Balanced Income Distribution:</strong> (1) Continue monitoring to ensure services are accessible across all income levels, (2) Maintain flexible payment options and sliding fee scales, (3) Regularly review income trends and adjust program pricing accordingly.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== ATTENDANCE ANALYSIS ==========
  function renderAttendanceAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const presentIndex = labels.indexOf('Present');
    const absentIndex = labels.indexOf('Absent');
    const presentCount = presentIndex >= 0 ? values[presentIndex] : 0;
    const absentCount = absentIndex >= 0 ? values[absentIndex] : 0;
    
    const presentPercentage = total > 0 ? (presentCount / total * 100).toFixed(1) : 0;
    const absentPercentage = total > 0 ? (absentCount / total * 100).toFixed(1) : 0;
    const attendanceRate = presentPercentage;
    
    let html = `
      <div class="row">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Present</span>
              <span class="info-box-number">${presentCount}</span>
              <span class="info-box-text">${presentPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Absent</span>
              <span class="info-box-number">${absentCount}</span>
              <span class="info-box-text">${absentPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-12">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-chart-line"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Overall Attendance Rate</span>
              <span class="info-box-number">${attendanceRate}%</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (attendanceRate >= 90 && total > 0) {
      html += `<li class="mb-2">Excellent attendance rate (${attendanceRate}%), indicating high engagement and successful program delivery.</li>`;
    } else if (attendanceRate >= 70 && attendanceRate < 90 && total > 0) {
      html += `<li class="mb-2">Good attendance rate (${attendanceRate}%), with room for improvement to reach optimal levels.</li>`;
    } else if (attendanceRate < 70 && total > 0) {
      html += `<li class="mb-2">Low attendance rate (${attendanceRate}%), suggesting barriers to participation that need to be addressed.</li>`;
    }
    
    if (absentPercentage > 30 && total > 0) {
      html += `<li class="mb-2">High absence rate (${absentPercentage}%) may indicate scheduling conflicts, accessibility issues, or lack of engagement.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations
    const recommendations = [];
    if (attendanceRate < 70) {
      recommendations.push(`Low attendance rate (${attendanceRate}%). Investigate barriers: improve session scheduling (evening/weekend options), enhance reminder systems (SMS, calls), ensure venue accessibility, and consider transportation assistance.`);
    } else if (attendanceRate >= 70 && attendanceRate < 85) {
      recommendations.push(`Moderate attendance rate (${attendanceRate}%). Implement targeted follow-ups for absent participants, offer make-up sessions, and gather feedback on scheduling preferences.`);
    } else if (attendanceRate >= 90) {
      recommendations.push(`Excellent attendance rate (${attendanceRate}%). Maintain current practices, document successful strategies, and consider expanding capacity to accommodate more participants.`);
    }
    
    if (absentCount > 0 && absentCount < 10) {
      recommendations.push(`Small number of absences (${absentCount}). Conduct individual follow-ups to understand reasons and offer alternative session times.`);
    } else if (absentCount >= 10) {
      recommendations.push(`Significant number of absences (${absentCount}, ${absentPercentage}%). Analyze patterns (day of week, time, location) and adjust scheduling accordingly.`);
    }
    
    if (presentCount > 0 && presentCount < 20) {
      recommendations.push(`Small attendance numbers (${presentCount}). Consider consolidating sessions or implementing group-based approaches to improve efficiency.`);
    }
    
    if (recommendations.length === 0) {
      recommendations.push(`<strong>Ongoing Monitoring:</strong> (1) Continue monitoring attendance patterns, (2) Maintain engagement strategies, (3) Identify and address barriers to attendance, (4) Regularly review and update engagement approaches based on attendance data.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // Generic analysis function as fallback
  function renderGenericAnalysis(chartId, data, chartTitle) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const nonZeroValues = values.filter(v => v > 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const minIndex = nonZeroValues.length > 0 ? values.indexOf(Math.min(...nonZeroValues)) : -1;
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const minValue = minIndex >= 0 ? values[minIndex] : 0;
    const average = nonZeroValues.length > 0 ? (total / nonZeroValues.length).toFixed(2) : 0;
    
    let html = `
      <div class="row">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Count</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-chart-bar"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Categories with Data</span>
              <span class="info-box-number">${nonZeroValues.length} / ${labels.length}</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    if (maxIndex >= 0 && maxValue > 0) {
      html += `
        <div class="row mt-3">
          <div class="col-md-6">
            <div class="callout callout-success">
              <h5><i class="fas fa-arrow-up mr-2"></i>Highest Category</h5>
              <p class="mb-0">
                <strong>${labels[maxIndex]}</strong> with <strong>${maxValue}</strong> ${maxValue === 1 ? 'count' : 'counts'}
                ${total > 0 ? `(${Math.round((maxValue/total)*100)}%)` : ''}
              </p>
            </div>
          </div>
      `;
      
      if (minIndex >= 0 && minValue > 0 && minIndex !== maxIndex) {
        html += `
          <div class="col-md-6">
            <div class="callout callout-warning">
              <h5><i class="fas fa-arrow-down mr-2"></i>Lowest Category (Active)</h5>
              <p class="mb-0">
                <strong>${labels[minIndex]}</strong> with <strong>${minValue}</strong> ${minValue === 1 ? 'count' : 'counts'}
                ${total > 0 ? `(${Math.round((minValue/total)*100)}%)` : ''}
              </p>
            </div>
          </div>
        </div>
        `;
      } else {
        html += `</div>`;
      }
    }
    
    const recommendations = generateRecommendations(chartId, labels, values, total, nonZeroValues.length, maxIndex, minIndex, maxValue, minValue, average);
    if (recommendations.length > 0) {
      html += `
        <div class="row mt-4">
          <div class="col-12">
            <div class="callout callout-info">
              <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
              <ul class="mb-0">
      `;
      recommendations.forEach(rec => {
        html += `<li class="mb-2">${rec}</li>`;
      });
      html += `
              </ul>
            </div>
          </div>
        </div>
      `;
    }
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== CIVIL STATUS ANALYSIS ==========
  function renderCivilStatusAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const totalIndividuals = values.reduce((a, b) => a + (b || 0), 0);
    // Get total couples from backend, or calculate as fallback (divide by 2, rounded)
    const totalCouples = data.total_couples || Math.round(totalIndividuals / 2);
    
    const singleIndex = labels.indexOf('Single');
    const livingInIndex = labels.indexOf('Living In');
    const singleCount = singleIndex >= 0 ? values[singleIndex] : 0;
    const livingInCount = livingInIndex >= 0 ? values[livingInIndex] : 0;
    const otherStatuses = labels.filter((l, i) => l !== 'Single' && l !== 'Living In' && values[i] > 0);
    const otherCount = labels.reduce((sum, label, idx) => {
      return sum + ((label !== 'Single' && label !== 'Living In') ? (values[idx] || 0) : 0);
    }, 0);
    
    const singlePercentage = totalIndividuals > 0 ? (singleCount / totalIndividuals * 100).toFixed(1) : 0;
    const livingInPercentage = totalIndividuals > 0 ? (livingInCount / totalIndividuals * 100).toFixed(1) : 0;
    
    // Break down other statuses for better visibility
    const widowedIndex = labels.indexOf('Widowed');
    const divorcedIndex = labels.indexOf('Divorced');
    const separatedIndex = labels.indexOf('Separated');
    const widowedCount = widowedIndex >= 0 ? values[widowedIndex] : 0;
    const divorcedCount = divorcedIndex >= 0 ? values[divorcedIndex] : 0;
    const separatedCount = separatedIndex >= 0 ? values[separatedIndex] : 0;
    
    // Format numbers properly (no dots, use commas for thousands)
    const formatNumber = (num) => num.toLocaleString('en-US');
    const singleCouplesEst = Math.round(singleCount / 2);
    const livingInCouplesEst = Math.round(livingInCount / 2);
    const otherPercentage = totalIndividuals > 0 ? (otherCount / totalIndividuals * 100).toFixed(1) : 0;
    
    let html = `
      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-user"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Single</span>
              <span class="info-box-number">${formatNumber(singleCount)}</span>
              <span class="info-box-text">${singlePercentage}% of individuals</span>
              <span class="info-box-text" style="font-size: 0.9em; margin-top: 4px;">~${formatNumber(singleCouplesEst)} couples</span>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-home"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Living In</span>
              <span class="info-box-number">${formatNumber(livingInCount)}</span>
              <span class="info-box-text">${livingInPercentage}% of individuals</span>
              <span class="info-box-text" style="font-size: 0.9em; margin-top: 4px;">~${formatNumber(livingInCouplesEst)} couples</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Other Statuses</span>
              <span class="info-box-number">${formatNumber(otherCount)}</span>
              <span class="info-box-text">${otherPercentage}% of individuals</span>
              <span class="info-box-text" style="font-size: 0.85em; margin-top: 4px; line-height: 1.3;">Widowed: ${formatNumber(widowedCount)} | Divorced: ${formatNumber(divorcedCount)} | Separated: ${formatNumber(separatedCount)}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-heart"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Couples</span>
              <span class="info-box-number">${formatNumber(totalCouples)}</span>
              <span class="info-box-text">Registered couples</span>
              <span class="info-box-text" style="font-size: 0.9em; margin-top: 4px;">${formatNumber(totalIndividuals)} total individuals</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (singleCount > livingInCount && singleCount > 0 && totalIndividuals > 0) {
      html += `<li class="mb-2">Single individuals represent the majority (${singlePercentage}%), suggesting potential for pre-marriage counseling and relationship readiness programs.</li>`;
    } else if (livingInCount > singleCount && livingInCount > 0 && totalIndividuals > 0) {
      html += `<li class="mb-2">Living In individuals represent the majority (${livingInPercentage}%), indicating many couples in cohabiting relationships who may benefit from relationship counseling and legal guidance.</li>`;
    }
    
    if (livingInCount > 0 && totalIndividuals > 0) {
      html += `<li class="mb-2">Living In individuals account for ${livingInPercentage}% of participants. These couples may need information about legal rights, relationship stability, and options for formalizing their relationship.</li>`;
    }
    
    if (otherCount > 0 && totalIndividuals > 0) {
      html += `<li class="mb-2">Other civil statuses (Widowed, Divorced, Separated) account for ${(otherCount/totalIndividuals*100).toFixed(1)}% of participants, indicating diverse relationship backgrounds requiring specialized support.</li>`;
    }
    
    if (totalCouples < 25) {
      html += `<li class="mb-2">Sample size is relatively small (${totalCouples} couples). Consider expanding outreach to improve data representation and statistical significance.</li>`;
    } else if (totalCouples >= 50) {
      html += `<li class="mb-2">Good sample size (${totalCouples} couples). Data patterns are statistically significant for program planning.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations
    const recommendations = [];
    const singleCouples = Math.round(singleCount / 2);
    const livingInCouples = Math.round(livingInCount / 2);
    
    if (singleCount > livingInCount && singleCount > 0 && totalIndividuals > 0) {
      recommendations.push(`<strong>Single Individuals (${singlePercentage}%, ~${singleCouples} couples):</strong> Develop specialized pre-marriage and relationship readiness programs. Key focus areas: (1) Communication skills training and conflict resolution workshops, (2) Financial planning and goal setting for couples, (3) Relationship expectations and compatibility assessment, (4) Intimacy and relationship building skills, (5) Pre-marriage counseling sessions covering commitment, roles, and responsibilities. Use modern communication channels (social media, mobile apps) preferred by this demographic. Schedule sessions at convenient times for working individuals. Consider peer-led programs and couple mentoring initiatives.`);
    } else if (livingInCount > singleCount && livingInCount > 0 && totalIndividuals > 0) {
      recommendations.push(`<strong>Living In Couples (${livingInPercentage}%, ~${livingInCouples} couples):</strong> Develop programs specifically for cohabiting couples. Key focus areas: (1) Legal rights and responsibilities information sessions, (2) Relationship stability and commitment counseling, (3) Options for formalizing relationships (marriage, civil union), (4) Financial planning for cohabiting couples, (5) Addressing cohabitation challenges and benefits of legal recognition, (6) Provide resources on property rights, inheritance, and legal protections, (7) Consider partnerships with legal aid organizations.`);
    }
    
    if (livingInCount > 0 && livingInCount / totalIndividuals > 0.2 && totalIndividuals > 0) {
      recommendations.push(`<strong>Living In Support Services (${livingInPercentage}%):</strong> Establish comprehensive support for cohabiting couples: (1) Create information materials on legal rights, property ownership, and inheritance laws, (2) Offer relationship counseling addressing unique cohabitation challenges, (3) Provide guidance on formalizing relationships if desired, (4) Develop programs addressing relationship stability and long-term commitment, (5) Partner with legal services for consultations on rights and protections. Consider creating a "Living In Couples" support group for peer learning and shared experiences.`);
    } else if (livingInCount > 0 && livingInCount / totalIndividuals <= 0.2 && totalIndividuals > 0) {
      recommendations.push(`<strong>Living In Couples (${livingInPercentage}%):</strong> While smaller in number, ensure these couples receive appropriate support: (1) Provide information about legal rights and relationship options, (2) Offer counseling services addressing cohabitation-specific needs, (3) Ensure programs are inclusive and welcoming to all relationship types.`);
    }
    
    if (otherCount > 0 && otherCount / totalIndividuals > 0.15 && totalIndividuals > 0) {
      recommendations.push(`<strong>Other Civil Statuses (${(otherCount/totalIndividuals*100).toFixed(1)}%):</strong> Develop specialized support programs for diverse relationship backgrounds: (1) <strong>Widowed (${widowedCount} individuals):</strong> Grief counseling, support groups, and guidance on navigating new relationships, (2) <strong>Divorced (${divorcedCount} individuals):</strong> Co-parenting support, relationship rebuilding skills, and guidance on healthy future relationships, (3) <strong>Separated (${separatedCount} individuals):</strong> Reconciliation counseling if appropriate, or support for moving forward, decision-making assistance, and legal guidance. Ensure counselors are trained in trauma-informed care and understand unique challenges of each status.`);
    } else if (otherCount > 0 && otherCount / totalIndividuals <= 0.15 && totalIndividuals > 0) {
      recommendations.push(`<strong>Other Civil Statuses (${(otherCount/totalIndividuals*100).toFixed(1)}%):</strong> While smaller in number, ensure specialized support is available: (1) Provide grief support resources for widowed individuals, (2) Offer co-parenting guidance for divorced participants, (3) Provide reconciliation or forward-moving support for separated couples. Train staff to recognize and address unique needs of each status.`);
    }
    
    // Specific recommendations based on dominant status
    if (singleCount > 0 && singleCount / totalIndividuals > 0.5 && totalIndividuals > 0) {
      recommendations.push(`<strong>Program Development Priority - Single Majority:</strong> With ${singlePercentage}% single individuals, prioritize: (1) Pre-marriage counseling as a core program offering, (2) Relationship readiness workshops and seminars, (3) Communication and conflict resolution skills training, (4) Financial planning for couples preparing for marriage, (5) Engagement with religious institutions and community organizations for referrals. Develop marketing materials highlighting benefits of pre-marriage counseling. Consider offering package deals or incentives for couples completing full programs.`);
    }
    
    if (livingInCount > 0 && livingInCount / totalIndividuals > 0.3 && totalIndividuals > 0) {
      recommendations.push(`<strong>Program Development Priority - Living In Majority:</strong> With ${livingInPercentage}% living in couples, prioritize: (1) Legal rights education as a core component, (2) Relationship stability and commitment counseling, (3) Information sessions on formalizing relationships, (4) Financial planning for cohabiting couples, (5) Addressing cohabitation-specific challenges. Partner with legal aid organizations and family law attorneys. Create resource materials on property rights and legal protections.`);
    }
    
    if (totalCouples < 15 && totalCouples > 0) {
      recommendations.push(`<strong>Sample Size Expansion (${totalCouples} couples):</strong> Small sample size limits statistical significance. Expand outreach through: (1) Community partnerships with barangay officials, religious leaders, and community organizations, (2) Social media campaigns targeting different civil status groups, (3) Referrals from religious institutions, schools, and healthcare providers, (4) Collaboration with local government units for program promotion, (5) Incentives for early registration and program completion, (6) Mobile registration and outreach events in communities. Aim for at least 50-100 couples for reliable analysis.`);
    } else if (totalCouples >= 15 && totalCouples < 50 && totalCouples > 0) {
      recommendations.push(`<strong>Moderate Sample Size (${totalCouples} couples):</strong> Continue expanding outreach while maintaining data quality. Focus on: (1) Maintaining current outreach strategies that are working, (2) Identifying and addressing barriers to participation, (3) Ensuring diverse representation across all civil status categories, (4) Regular monitoring of registration trends.`);
    } else if (totalCouples >= 50 && totalCouples > 0) {
      recommendations.push(`<strong>Excellent Sample Size (${totalCouples} couples):</strong> Data is statistically significant for evidence-based decision making. Use this data for: (1) Program planning and resource allocation, (2) Policy development and strategic planning, (3) Budget justification and funding requests, (4) Program evaluation and impact assessment, (5) Identifying trends and emerging needs. Continue monitoring and updating analysis regularly.`);
    }
    
    // Cross-cutting recommendations
    recommendations.push(`<strong>General Program Recommendations:</strong> (1) Ensure all programs are inclusive and welcoming to all civil status categories, (2) Train staff to understand unique needs of each civil status group, (3) Develop flexible program schedules to accommodate different life situations, (4) Create safe spaces for participants to discuss their relationship status without judgment, (5) Regularly review and update program offerings based on participant feedback and changing demographics, (6) Maintain confidentiality and respect for diverse relationship backgrounds.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`Data distribution appears balanced across civil status categories. Continue monitoring trends, maintain current service delivery approaches, and ensure programs remain responsive to changing participant demographics. Regularly review program effectiveness and participant satisfaction.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== RELIGION ANALYSIS ==========
  function renderReligionAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const catholicIndex = labels.indexOf('Roman Catholic');
    const catholicCount = catholicIndex >= 0 ? values[catholicIndex] : 0;
    const catholicPercentage = total > 0 ? (catholicCount / total * 100).toFixed(1) : 0;
    
    const noneIndex = labels.indexOf('None');
    const noneCount = noneIndex >= 0 ? values[noneIndex] : 0;
    const nonePercentage = total > 0 ? (noneCount / total * 100).toFixed(1) : 0;
    
    const otherReligionsCount = labels.reduce((sum, label, idx) => {
      return sum + ((label !== 'Roman Catholic' && label !== 'None') ? (values[idx] || 0) : 0);
    }, 0);
    
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    
    // Calculate diversity index (number of religions with >5% representation)
    const diverseReligions = labels.filter((label, idx) => {
      const percentage = total > 0 ? (values[idx] / total * 100) : 0;
      return percentage > 5;
    }).length;
    
    let html = `
      <div class="row">
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-church"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Roman Catholic</span>
              <span class="info-box-number">${catholicCount}</span>
              <span class="info-box-text">${catholicPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-star"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Other Religions</span>
              <span class="info-box-number">${otherReligionsCount}</span>
              <span class="info-box-text">${total > 0 ? (otherReligionsCount / total * 100).toFixed(1) : 0}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-question"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">No Religion</span>
              <span class="info-box-number">${noneCount}</span>
              <span class="info-box-text">${nonePercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Participants</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-layer-group"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Religious Diversity</span>
              <span class="info-box-number">${diverseReligions} groups</span>
              <span class="info-box-text">with >5% representation</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (catholicPercentage > 70 && total > 0) {
      html += `<li class="mb-2">Roman Catholic is the dominant religion (${catholicPercentage}%), reflecting the local demographic. This strong representation suggests opportunities for partnerships with Catholic parishes, schools, and organizations for program promotion, venue access, and volunteer recruitment.</li>`;
    } else if (catholicPercentage < 50 && total > 0) {
      html += `<li class="mb-2">Religious diversity is notable with Roman Catholic at ${catholicPercentage}%. The presence of multiple faith communities indicates a need for culturally sensitive, interfaith approaches to counseling and program delivery.</li>`;
    } else if (catholicPercentage >= 50 && catholicPercentage <= 70 && total > 0) {
      html += `<li class="mb-2">Roman Catholic represents a majority (${catholicPercentage}%) while other religions also have significant presence. Balance targeted Catholic outreach with inclusive programming for all faiths.</li>`;
    }
    
    if (diverseReligions >= 5 && total > 0) {
      html += `<li class="mb-2">High religious diversity (${diverseReligions} groups with >5% representation). This diversity requires culturally sensitive program materials, interfaith dialogue components, and staff training on religious sensitivity to ensure all participants feel respected and included.</li>`;
    } else if (diverseReligions >= 3 && diverseReligions < 5 && total > 0) {
      html += `<li class="mb-2">Moderate religious diversity (${diverseReligions} groups with significant representation). Ensure program content respects diverse religious perspectives while maintaining universal values.</li>`;
    }
    
    if (nonePercentage > 15 && total > 0) {
      html += `<li class="mb-2">Significant non-religious population (${nonePercentage}%). Ensure program content focuses on universal values, relationship skills, and practical life skills rather than religious doctrine. Consider secular counseling approaches for this group.</li>`;
    } else if (nonePercentage > 5 && nonePercentage <= 15 && total > 0) {
      html += `<li class="mb-2">Some participants have no religious affiliation (${nonePercentage}%). Program materials should be accessible and relevant to both religious and non-religious participants.</li>`;
    }
    
    if (otherReligionsCount > 0 && (otherReligionsCount / total) > 0.25 && total > 0) {
      html += `<li class="mb-2">Other religions represent a substantial portion (${(otherReligionsCount/total*100).toFixed(1)}%) of participants. Consider forming an interfaith advisory committee to guide program development and ensure all faith communities have equal access to services.</li>`;
    }
    
    if (maxIndex >= 0 && maxValue > 0 && total > 0) {
      html += `<li class="mb-2">Largest religious group: ${labels[maxIndex]} (${maxPercentage}%). This group may benefit from targeted outreach, specialized programs aligned with their values, and partnerships with their religious institutions for program promotion.</li>`;
    }
    
    if (total < 30) {
      html += `<li class="mb-2">Sample size is relatively small (${total} participants). Consider expanding outreach to improve data representation and ensure all religious communities are adequately represented.</li>`;
    } else if (total >= 100) {
      html += `<li class="mb-2">Good sample size (${total} participants). Religious distribution patterns are statistically significant for program planning and resource allocation.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations
    const recommendations = [];
    
    if (catholicPercentage > 70 && total > 0) {
      recommendations.push(`Strong Roman Catholic representation (${catholicPercentage}%). Establish formal partnerships with local parishes, Catholic schools, and Catholic organizations for program promotion, venue access, volunteer recruitment, and referral networks. Consider offering programs at church facilities during convenient times.`);
    } else if (catholicPercentage >= 50 && catholicPercentage <= 70 && total > 0) {
      recommendations.push(`Roman Catholic majority (${catholicPercentage}%) with diverse representation. Maintain Catholic partnerships while ensuring inclusive programming. Develop interfaith components that respect all faith traditions.`);
    }
    
    if (diverseReligions >= 4 && total > 0) {
      recommendations.push(`High religious diversity (${diverseReligions} groups with >5% representation). Develop interfaith dialogue components in programs, ensure all materials respect diverse religious perspectives, train staff on religious sensitivity and cultural competence, and create safe spaces for interfaith discussions.`);
    } else if (diverseReligions >= 2 && diverseReligions < 4 && total > 0) {
      recommendations.push(`Moderate religious diversity (${diverseReligions} groups). Ensure program materials are culturally sensitive and non-denominational while respecting religious values. Train staff to be aware of different faith perspectives.`);
    }
    
    if (nonePercentage > 15 && total > 0) {
      recommendations.push(`Significant non-religious population (${nonePercentage}%). Ensure program content focuses on universal values, relationship skills, and practical life skills rather than religious doctrine. Offer secular counseling approaches and avoid assumptions about religious beliefs. Create inclusive materials that don't require religious affiliation.`);
    } else if (nonePercentage > 5 && nonePercentage <= 15 && total > 0) {
      recommendations.push(`Some non-religious participants (${nonePercentage}%). Ensure program content is accessible to both religious and non-religious participants, using universal values and practical approaches.`);
    }
    
    if (otherReligionsCount > 0 && (otherReligionsCount / total) > 0.2 && total > 0) {
      recommendations.push(`Other religions represent a substantial portion (${(otherReligionsCount/total*100).toFixed(1)}%) of participants. Form an interfaith advisory committee with representatives from different faith communities to guide program development, review materials for inclusivity, and ensure all faith communities have equal access to services.`);
    }
    
    const zeroReligions = labels.filter((label, idx) => values[idx] === 0);
    if (zeroReligions.length > 0 && zeroReligions.length < labels.length && total > 0) {
      recommendations.push(`${zeroReligions.length} religious groups have no representation: ${zeroReligions.join(', ')}. Conduct targeted outreach to these communities through their places of worship, community leaders, and cultural organizations to ensure inclusive participation and address potential barriers.`);
    }
    
    if (maxIndex >= 0 && maxValue > 0 && maxPercentage > 50 && total > 0) {
      recommendations.push(`Strong concentration in ${labels[maxIndex]} (${maxPercentage}%). While leveraging this group for partnerships and outreach, ensure programs remain inclusive and welcoming to all faith backgrounds. Avoid making assumptions that all participants share the same religious values.`);
    }
    
    if (recommendations.length === 0) {
      recommendations.push(`Religious distribution appears balanced. Continue monitoring trends, maintain partnerships with diverse faith communities, and ensure all faith communities have equal access to services. Regularly review program materials for religious inclusivity.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      if (pointNumbers.length > 0) {
        for (let i = 0; i < pointNumbers.length; i++) {
          const start = pointNumbers[i].start;
          const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
          let point = content.substring(start, end).trim();
          // Remove trailing comma if present
          point = point.replace(/,\s*$/, '');
          // Make numbered points bold
          point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
          points.push(point);
        }
        // Join points with line breaks
        const formattedPoints = points.join('<br>');
        html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
      } else {
        // No numbered points found, add the entire recommendation as-is
        html += `<li class="mb-2" style="line-height: 1.8;">${formattedRec}</li>`;
      }
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== WEDDING TYPE ANALYSIS ==========
  function renderWeddingTypeAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const civilIndex = labels.indexOf('Civil');
    const churchIndex = labels.indexOf('Church');
    const civilCountIndividuals = civilIndex >= 0 ? values[civilIndex] : 0;
    const churchCountIndividuals = churchIndex >= 0 ? values[churchIndex] : 0;
    
    // Wedding type data counts individuals, so divide by 2 to get couples
    // Each couple has 2 individuals (one male, one female with same wedding_type)
    const civilCount = Math.round(civilCountIndividuals / 2);
    const churchCount = Math.round(churchCountIndividuals / 2);
    const totalCouples = civilCount + churchCount;
    
    const civilPercentage = totalCouples > 0 ? (civilCount / totalCouples * 100).toFixed(1) : 0;
    const churchPercentage = totalCouples > 0 ? (churchCount / totalCouples * 100).toFixed(1) : 0;
    
    // Format numbers properly (no dots, use commas for thousands)
    const formatNumber = (num) => num.toLocaleString('en-US');
    
    let html = `
      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-gavel"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Civil Wedding</span>
              <span class="info-box-number">${formatNumber(civilCount)}</span>
              <span class="info-box-text">${civilPercentage}% of couples</span>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-church"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Church Wedding</span>
              <span class="info-box-number">${formatNumber(churchCount)}</span>
              <span class="info-box-text">${churchPercentage}% of couples</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Couples</span>
              <span class="info-box-number">${formatNumber(totalCouples)}</span>
              <span class="info-box-text">Registered couples</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (churchCount > civilCount && totalCouples > 0) {
      html += `<li class="mb-2"><strong>Church Wedding Preference (${churchPercentage}%, ${formatNumber(churchCount)} couples):</strong> Strong preference for church weddings indicates: (1) Religious values and traditions are important to most PMOC participants, (2) Couples may expect PMOC programs to align with religious teachings and values, (3) Opportunities for partnerships with religious institutions for program promotion and venue access, (4) May benefit from faith-based pre-marriage counseling approaches, (5) Religious leaders can be valuable referral sources and program partners, (6) Consider offering PMOC programs at church facilities or in collaboration with religious organizations.</li>`;
    } else if (civilCount > churchCount && totalCouples > 0) {
      html += `<li class="mb-2"><strong>Civil Wedding Preference (${civilPercentage}%, ${formatNumber(civilCount)} couples):</strong> Civil weddings are more common, suggesting: (1) Practical considerations or preference for non-religious ceremonies, (2) PMOC programs should accommodate both religious and secular approaches, (3) Focus on legal and practical aspects of marriage preparation, (4) Ensure PMOC content is inclusive and doesn't assume religious affiliation, (5) May benefit from partnerships with civil registry offices and government agencies, (6) Consider offering secular pre-marriage counseling options alongside faith-based programs.</li>`;
    } else if (totalCouples > 0) {
      html += `<li class="mb-2"><strong>Balanced Wedding Preferences (Civil: ${civilPercentage}%, Church: ${churchPercentage}%):</strong> Diverse preferences indicate: (1) PMOC programs must accommodate both religious and secular couples, (2) Offer flexible program options that respect different values and traditions, (3) Maintain partnerships with both religious institutions and civil authorities, (4) Ensure PMOC content is inclusive and welcoming to all couples, (5) Provide both faith-based and secular pre-marriage counseling approaches, (6) Train staff to work effectively with diverse couple preferences.</li>`;
    }
    
    if (totalCouples < 30) {
      html += `<li class="mb-2"><strong>Small Sample Size:</strong> ${formatNumber(totalCouples)} couples. Consider expanding data collection to better understand wedding preferences and tailor PMOC programs accordingly. This will help in developing appropriate program content and partnerships.</li>`;
    } else if (totalCouples >= 50) {
      html += `<li class="mb-2"><strong>Good Sample Size:</strong> ${formatNumber(totalCouples)} couples. Wedding preference patterns are statistically significant for PMOC program planning, partnership development, and content design.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations
    const recommendations = [];
    
    if (churchCount > civilCount && churchCount > 0 && totalCouples > 0) {
      recommendations.push(`<strong>Church Wedding PMOC Strategy (${churchPercentage}%, ${formatNumber(churchCount)} couples):</strong> (1) Establish formal partnerships with local churches and religious institutions for program promotion, venue access, and referrals, (2) Develop faith-based pre-marriage counseling modules aligned with religious teachings and values, (3) Train PMOC staff on religious sensitivity and faith-based counseling approaches, (4) Consider offering PMOC programs at church facilities during convenient times, (5) Engage religious leaders as program partners and referral sources, (6) Develop materials that integrate religious values with relationship skills, (7) Offer programs that respect and incorporate religious traditions, (8) Create opportunities for couples to discuss faith and values in their relationship.`);
    }
    
    if (civilCount > churchCount && civilCount > 0 && totalCouples > 0) {
      recommendations.push(`<strong>Civil Wedding PMOC Strategy (${civilPercentage}%, ${formatNumber(civilCount)} couples):</strong> (1) Ensure PMOC programs accommodate non-religious couples and focus on legal and practical aspects of marriage, (2) Develop secular pre-marriage counseling approaches that don't require religious affiliation, (3) Partner with civil registry offices and government agencies for referrals, (4) Focus on universal relationship skills, communication, and conflict resolution, (5) Provide information on legal rights, responsibilities, and marriage procedures, (6) Ensure PMOC content is inclusive and doesn't assume religious beliefs, (7) Train staff to work effectively with secular couples, (8) Create materials that focus on practical relationship skills rather than religious doctrine.`);
    }
    
    if (Math.abs(parseFloat(civilPercentage) - parseFloat(churchPercentage)) < 10 && totalCouples > 0) {
      recommendations.push(`<strong>Balanced Wedding Preferences Strategy:</strong> (1) Maintain flexible PMOC service offerings that accommodate both religious and civil ceremonies, (2) Offer both faith-based and secular pre-marriage counseling options, (3) Develop partnerships with both religious institutions and civil authorities, (4) Train staff to work effectively with diverse couple preferences, (5) Ensure all PMOC content is inclusive and welcoming, (6) Provide materials that can be adapted for different value systems, (7) Create program modules that respect both religious and secular approaches, (8) Regularly review program content for inclusivity and cultural sensitivity.`);
    }
    
    // General PMOC wedding type recommendations
    recommendations.push(`<strong>General PMOC Wedding Type Strategy:</strong> (1) Monitor wedding type preferences to ensure PMOC services meet participant needs, (2) Maintain partnerships with both religious and civil institutions, (3) Offer flexible program options that respect diverse values and traditions, (4) Train staff to work effectively with all couple types, (5) Ensure PMOC content is inclusive and culturally sensitive, (6) Regularly review and update program offerings based on participant preferences, (7) Provide information on both religious and civil wedding requirements and procedures.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`<strong>General Monitoring:</strong> (1) Continue monitoring wedding type preferences to ensure PMOC services meet participant needs, (2) Maintain flexible program offerings that accommodate diverse couple preferences and values.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== PREGNANCY STATUS ANALYSIS ==========
  function renderPregnancyAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const pregnantIndex = labels.indexOf('Pregnant');
    const notPregnantIndex = labels.indexOf('Not Pregnant');
    const pregnantCount = pregnantIndex >= 0 ? values[pregnantIndex] : 0;
    const notPregnantCount = notPregnantIndex >= 0 ? values[notPregnantIndex] : 0;
    const pregnantPercentage = total > 0 ? (pregnantCount / total * 100).toFixed(1) : 0;
    const notPregnantPercentage = total > 0 ? (notPregnantCount / total * 100).toFixed(1) : 0;
    
    // Calculate couple count (assuming each female represents one couple) - declared once at top
    const pregnantCouples = pregnantCount;
    const notPregnantCouples = notPregnantCount;
    
    let html = `
      <div class="row">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-baby"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Pregnant</span>
              <span class="info-box-number">${pregnantCount}</span>
              <span class="info-box-text">${pregnantPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Not Pregnant</span>
              <span class="info-box-number">${notPregnantCount}</span>
              <span class="info-box-text">${notPregnantPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-12">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-female"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Female Participants</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights (couple counts already declared above)
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (pregnantPercentage > 20 && total > 0) {
      html += `<li class="mb-2"><strong>High Pregnancy Rate (${pregnantPercentage}%, ${pregnantCouples} couples):</strong> Significant proportion of pregnant participants indicates: (1) Urgent need for expedited PMOC services and pre-marriage counseling, (2) Time-sensitive situation requiring immediate support, (3) May need specialized PMOC sessions accommodating physical needs of pregnancy, (4) Critical opportunity for prenatal care information and health services integration, (5) Financial and legal preparation for marriage and parenthood, (6) Relationship counseling addressing the stress of unplanned pregnancy, (7) Consider priority scheduling for these couples in PMOC programs.</li>`;
    } else if (pregnantPercentage > 10 && pregnantPercentage <= 20 && total > 0) {
      html += `<li class="mb-2"><strong>Moderate Pregnancy Rate (${pregnantPercentage}%, ${pregnantCouples} couples):</strong> Notable number of pregnant participants require: (1) Expedited PMOC services and priority scheduling, (2) Specialized support for their unique needs, (3) Integration of prenatal care information with pre-marriage counseling, (4) Accommodation for physical needs during PMOC sessions, (5) Relationship counseling addressing pregnancy-related stress and changes.</li>`;
    } else if (pregnantPercentage > 0 && pregnantPercentage <= 10 && total > 0) {
      html += `<li class="mb-2"><strong>Small Pregnant Group (${pregnantPercentage}%, ${pregnantCouples} couples):</strong> Small but important group requiring: (1) Specialized PMOC support for their unique needs, (2) Expedited services and priority scheduling, (3) Integration of health services with pre-marriage counseling, (4) Personalized attention and support, (5) Connection with appropriate healthcare providers.</li>`;
    }
    
    if (notPregnantPercentage > 80 && total > 0) {
      html += `<li class="mb-2"><strong>Most Not Pregnant (${notPregnantPercentage}%, ${notPregnantCouples} couples):</strong> Majority are not currently pregnant, indicating: (1) Excellent opportunity for proactive family planning education in PMOC programs, (2) Time to plan and prepare for marriage and family, (3) Focus on contraceptive counseling and family planning methods, (4) Education on healthy pregnancy timing and spacing, (5) Opportunity for comprehensive pre-marriage counseling without time pressure, (6) Can address relationship readiness and financial preparation before starting a family.</li>`;
    } else if (notPregnantPercentage > 60 && notPregnantPercentage <= 80 && total > 0) {
      html += `<li class="mb-2"><strong>Majority Not Pregnant (${notPregnantPercentage}%, ${notPregnantCouples} couples):</strong> Most participants are not pregnant, providing opportunity for: (1) Comprehensive family planning education in PMOC, (2) Proactive relationship and financial preparation, (3) Education on healthy pregnancy timing, (4) Focus on relationship readiness before starting a family.</li>`;
    }
    
    if (total < 30) {
      html += `<li class="mb-2"><strong>Small Sample Size:</strong> ${total} female participants. Consider expanding data collection to better understand pregnancy status patterns and tailor PMOC programs accordingly.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2"><strong>Good Sample Size:</strong> ${total} female participants. Pregnancy status patterns are statistically significant for PMOC program planning and service delivery.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations (couple counts already declared above)
    const recommendations = [];
    
    if (pregnantCount > 0 && total > 0) {
      recommendations.push(`<strong>Pregnant Participants PMOC Strategy (${pregnantCount} couples, ${pregnantPercentage}%):</strong> (1) Provide immediate access to expedited PMOC services and priority scheduling, (2) Integrate prenatal care information and health services with pre-marriage counseling, (3) Ensure PMOC sessions accommodate physical needs of pregnancy (comfortable seating, breaks, shorter sessions if needed), (4) Provide nutritional counseling and health information relevant to pregnancy, (5) Address relationship stress and changes related to pregnancy in counseling sessions, (6) Connect couples with appropriate healthcare providers and prenatal care services, (7) Include financial planning for pregnancy and parenthood in PMOC, (8) Provide legal information on marriage requirements and birth registration, (9) Offer support groups for pregnant couples preparing for marriage.`);
    }
    
    if (pregnantPercentage > 15 && total > 0) {
      recommendations.push(`<strong>High Pregnancy Rate Priority (${pregnantPercentage}%, ${pregnantCouples} couples):</strong> (1) Establish expedited PMOC processing for pregnant couples, (2) Create priority scheduling system to ensure timely services, (3) Develop specialized PMOC modules addressing pregnancy-related relationship challenges, (4) Integrate health services and prenatal care information, (5) Provide urgent support for couples facing unplanned pregnancy, (6) Address financial and legal preparation for marriage and parenthood, (7) Train staff to recognize and address pregnancy-related stress, (8) Partner with healthcare providers for comprehensive support.`);
    } else if (pregnantPercentage > 0 && pregnantPercentage <= 15 && total > 0) {
      recommendations.push(`<strong>Moderate Pregnancy Rate Support (${pregnantPercentage}%, ${pregnantCouples} couples):</strong> (1) Ensure specialized PMOC support is available for pregnant couples, (2) Offer expedited services and priority scheduling, (3) Integrate health services with pre-marriage counseling, (4) Provide personalized attention and support, (5) Connect with appropriate healthcare providers.`);
    }
    
    if (notPregnantCount > 0 && notPregnantCount / total > 0.8 && total > 0) {
      recommendations.push(`<strong>Not Pregnant Majority PMOC Strategy (${notPregnantPercentage}%, ${notPregnantCouples} couples):</strong> (1) Use this opportunity for comprehensive family planning education in PMOC programs, (2) Provide detailed contraceptive counseling and method selection, (3) Educate on healthy pregnancy timing and spacing, (4) Focus on relationship readiness and financial preparation before starting a family, (5) Address family planning goals and preferences in counseling sessions, (6) Provide information on preconception health and planning, (7) Include discussions on desired family size and timing, (8) Offer comprehensive pre-marriage counseling without time pressure.`);
    } else if (notPregnantCount > 0 && notPregnantCount / total > 0.6 && notPregnantCount / total <= 0.8 && total > 0) {
      recommendations.push(`<strong>Not Pregnant Majority (${notPregnantPercentage}%, ${notPregnantCouples} couples):</strong> (1) Provide comprehensive family planning education in PMOC, (2) Focus on proactive relationship and financial preparation, (3) Educate on healthy pregnancy timing, (4) Include contraceptive counseling and method selection, (5) Address family planning goals in counseling sessions.`);
    }
    
    if (pregnantCount > 0 && pregnantCount < 5 && total > 0) {
      recommendations.push(`<strong>Small Pregnant Group Support (${pregnantCount} couples):</strong> (1) Ensure personalized attention for each pregnant couple, (2) Provide specialized PMOC support tailored to their needs, (3) Connect with appropriate healthcare providers, (4) Offer priority scheduling and expedited services, (5) Create support network for these couples.`);
    }
    
    // General PMOC pregnancy status recommendations
    recommendations.push(`<strong>General PMOC Pregnancy Status Strategy:</strong> (1) Monitor pregnancy status to ensure appropriate services are available for all participants, (2) Integrate health services and family planning education into all PMOC programs, (3) Train staff to recognize and address pregnancy-related needs, (4) Provide flexible scheduling and priority services for pregnant couples, (5) Partner with healthcare providers for comprehensive support, (6) Ensure PMOC programs address both pregnant and not-pregnant couple needs, (7) Regularly review and update services based on pregnancy status patterns.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`Continue monitoring pregnancy status to ensure PMOC services are appropriate and available for all participants. Maintain flexible program design that accommodates both pregnant and not-pregnant couples.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== PHILHEALTH ANALYSIS ==========
  function renderPhilHealthAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    
    const yesIndex = labels.indexOf('Yes');
    const noIndex = labels.indexOf('No');
    const yesCount = yesIndex >= 0 ? values[yesIndex] : 0;
    const noCount = noIndex >= 0 ? values[noIndex] : 0;
    const yesPercentage = total > 0 ? (yesCount / total * 100).toFixed(1) : 0;
    const noPercentage = total > 0 ? (noCount / total * 100).toFixed(1) : 0;
    
    // Calculate couple count - declared once at top
    const totalCouples = Math.round(total / 2);
    const withPhilHealthCouples = Math.round(totalCouples * parseFloat(yesPercentage) / 100);
    const withoutPhilHealthCouples = Math.round(totalCouples * parseFloat(noPercentage) / 100);
    
    let html = `
      <div class="row">
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">With PhilHealth</span>
              <span class="info-box-number">${yesCount}</span>
              <span class="info-box-text">${yesPercentage}%</span>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Without PhilHealth</span>
              <span class="info-box-number">${noCount}</span>
              <span class="info-box-text">${noPercentage}%</span>
            </div>
          </div>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-12">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Participants</span>
              <span class="info-box-number">${total}</span>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Key Insights (couple counts already declared above)
    html += `
      <div class="row mt-3">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
            <ul class="mb-0">
    `;
    
    if (yesPercentage >= 80 && total > 0) {
      html += `<li class="mb-2"><strong>High PhilHealth Coverage (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> Excellent healthcare access indicates: (1) Good financial protection for healthcare services, (2) Couples can access family planning and maternal health services through PhilHealth, (3) Reduced financial barriers to healthcare during PMOC and pregnancy, (4) Opportunity to integrate PhilHealth benefits information into PMOC programs, (5) Can refer couples to PhilHealth-accredited facilities for health services, (6) Lower financial stress related to healthcare costs, (7) Better access to comprehensive pre-marriage health services.</li>`;
    } else if (yesPercentage < 50 && total > 0) {
      html += `<li class="mb-2"><strong>Low PhilHealth Coverage (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> Limited coverage suggests: (1) Potential financial vulnerability and limited healthcare access, (2) Higher out-of-pocket costs for family planning and maternal health services, (3) Financial barriers to accessing healthcare during PMOC and pregnancy, (4) Need for PhilHealth enrollment assistance as part of PMOC services, (5) May require free or subsidized health services, (6) Financial stress affecting relationship quality and marriage preparation, (7) Critical need to integrate PhilHealth enrollment into PMOC programs.</li>`;
    } else if (total > 0) {
      html += `<li class="mb-2"><strong>Moderate PhilHealth Coverage (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> Room for improvement indicates: (1) Some couples have healthcare access while others face barriers, (2) Need for targeted PhilHealth enrollment assistance, (3) Mixed financial capacity for healthcare services, (4) Opportunity to improve coverage through PMOC program integration, (5) Should provide information on PhilHealth benefits and enrollment, (6) Consider offering enrollment assistance during PMOC sessions.</li>`;
    }
    
    if (noPercentage > 30 && total > 0) {
      html += `<li class="mb-2"><strong>Significant Without PhilHealth (${noPercentage}%, ~${withoutPhilHealthCouples} couples):</strong> Large portion without coverage may face: (1) Barriers to healthcare access and financial protection, (2) Higher out-of-pocket costs for family planning and maternal health, (3) Financial stress affecting relationship quality, (4) Limited access to comprehensive health services during PMOC, (5) Need for urgent PhilHealth enrollment assistance, (6) May require free or subsidized services, (7) Critical opportunity to integrate enrollment into PMOC programs.</li>`;
    } else if (noPercentage > 15 && noPercentage <= 30 && total > 0) {
      html += `<li class="mb-2"><strong>Moderate Without PhilHealth (${noPercentage}%, ~${withoutPhilHealthCouples} couples):</strong> Some couples lack coverage. PMOC programs should: (1) Provide PhilHealth enrollment information and assistance, (2) Address healthcare access barriers, (3) Include information on benefits for family planning and maternal health, (4) Consider offering enrollment support during PMOC sessions.</li>`;
    }
    
    if (total < 30) {
      html += `<li class="mb-2"><strong>Small Sample Size:</strong> ${total} participants (~${totalCouples} couples). Consider expanding data collection to better understand PhilHealth coverage patterns and tailor PMOC programs accordingly.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2"><strong>Good Sample Size:</strong> ${total} participants (~${totalCouples} couples). PhilHealth coverage patterns are statistically significant for PMOC program planning and health service integration.</li>`;
    }
    
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    // Recommendations (couple counts already declared above)
    const recommendations = [];
    
    if (noPercentage > 30 && total > 0) {
      recommendations.push(`<strong>High Without PhilHealth PMOC Strategy (${noPercentage}%, ~${withoutPhilHealthCouples} couples):</strong> (1) Integrate PhilHealth enrollment assistance into PMOC programs as a core component, (2) Provide information sessions on PhilHealth enrollment, benefits, and registration processes, (3) Partner with PhilHealth for on-site enrollment during PMOC sessions, (4) Train PMOC staff to assist with enrollment and answer questions, (5) Create materials explaining PhilHealth benefits for family planning and maternal health, (6) Address healthcare access barriers in relationship counseling, (7) Provide information on free or low-cost services for uninsured couples, (8) Make PhilHealth enrollment a priority service for PMOC participants.`);
    } else if (noPercentage > 15 && noPercentage <= 30 && total > 0) {
      recommendations.push(`<strong>Moderate Without PhilHealth Support (${noPercentage}%, ~${withoutPhilHealthCouples} couples):</strong> (1) Provide PhilHealth enrollment information and assistance in PMOC programs, (2) Address healthcare access barriers, (3) Include information on benefits for family planning and maternal health, (4) Offer enrollment support during PMOC sessions, (5) Partner with PhilHealth for enrollment assistance.`);
    }
    
    if (yesPercentage < 60 && total > 0) {
      recommendations.push(`<strong>Low PhilHealth Coverage PMOC Strategy (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> (1) Develop comprehensive outreach programs to educate PMOC participants about PhilHealth benefits, (2) Emphasize benefits for family planning and maternal health services, (3) Integrate enrollment assistance into all PMOC programs, (4) Partner with PhilHealth for on-site enrollment, (5) Create materials highlighting healthcare cost savings, (6) Address financial barriers to healthcare in relationship counseling, (7) Provide information on enrollment requirements and procedures, (8) Make improving coverage a priority goal for PMOC programs.`);
    } else if (yesPercentage >= 60 && yesPercentage < 80 && total > 0) {
      recommendations.push(`<strong>Moderate PhilHealth Coverage (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> (1) Continue providing enrollment information and assistance, (2) Target outreach to uninsured couples, (3) Emphasize benefits for family planning and maternal health, (4) Partner with PhilHealth for enrollment support, (5) Monitor coverage trends and adjust strategies.`);
    }
    
    if (yesPercentage >= 80 && total > 0) {
      recommendations.push(`<strong>High PhilHealth Coverage PMOC Strategy (${yesPercentage}%, ~${withPhilHealthCouples} couples):</strong> (1) Leverage high coverage for referrals to PhilHealth-accredited facilities, (2) Ensure PMOC participants understand how to maximize their PhilHealth benefits, (3) Integrate information on accessing family planning and maternal health services through PhilHealth, (4) Provide materials on covered services and procedures, (5) Partner with PhilHealth-accredited facilities for comprehensive health services, (6) Use coverage as a selling point for PMOC program benefits, (7) Continue monitoring to maintain high coverage rates, (8) Focus on helping couples navigate the healthcare system effectively.`);
    }
    
    if (noCount > 0 && noCount < 10 && total > 0) {
      recommendations.push(`<strong>Small Without PhilHealth Group (${noCount} couples):</strong> (1) Provide personalized assistance to help these couples enroll, (2) Offer one-on-one enrollment support during PMOC sessions, (3) Ensure they understand PhilHealth benefits and enrollment process, (4) Follow up to confirm successful enrollment, (5) Address any barriers preventing enrollment.`);
    }
    
    // General PMOC PhilHealth recommendations
    recommendations.push(`<strong>General PMOC PhilHealth Strategy:</strong> (1) Integrate PhilHealth information and enrollment assistance into all PMOC programs, (2) Train staff on PhilHealth benefits and enrollment procedures, (3) Partner with PhilHealth for on-site enrollment and information sessions, (4) Provide materials on benefits for family planning and maternal health, (5) Address healthcare access in relationship counseling, (6) Monitor coverage trends and adjust strategies, (7) Ensure all PMOC participants understand healthcare access options, (8) Make improving PhilHealth coverage a program goal.`);
    
    if (recommendations.length === 0) {
      recommendations.push(`Continue monitoring PhilHealth coverage to ensure PMOC participants have access to healthcare services. Integrate PhilHealth information and enrollment assistance into PMOC programs to improve healthcare access for all couples.`);
    }
    
    html += `
      <div class="row mt-4">
        <div class="col-12">
          <div class="callout callout-info">
            <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
            <ul class="mb-0">
    `;
    recommendations.forEach(rec => {
      // Split recommendations by numbered format and display each point on a new line
      // Format: (1) text, (2) text -> each on separate line in ascending order
      let formattedRec = rec;
      
      // Extract the title (text before first numbered point)
      const titleMatch = formattedRec.match(/^(<strong>.*?<\/strong>:\s*)/);
      const title = titleMatch ? titleMatch[1] : '';
      let content = titleMatch ? formattedRec.substring(titleMatch[0].length) : formattedRec;
      
      // Split by pattern: look for ", (number)" pattern and split before each number
      // This will properly separate: "(1) text, (2) text, (3) text" into separate items
      const points = [];
      const regex = /\((\d+)\)/g;
      let lastIndex = 0;
      let match;
      let pointNumbers = [];
      
      // Find all numbered points and their positions
      while ((match = regex.exec(content)) !== null) {
        pointNumbers.push({
          number: parseInt(match[1]),
          start: match.index,
          end: match.index + match[0].length
        });
      }
      
      // Extract each point based on the numbered positions
      for (let i = 0; i < pointNumbers.length; i++) {
        const start = pointNumbers[i].start;
        const end = (i < pointNumbers.length - 1) ? pointNumbers[i + 1].start : content.length;
        let point = content.substring(start, end).trim();
        // Remove trailing comma if present
        point = point.replace(/,\s*$/, '');
        // Make numbered points bold
        point = point.replace(/\((\d+)\)/g, '<strong>($1)</strong>');
        points.push(point);
      }
      
      // Join points with line breaks
      const formattedPoints = points.join('<br>');
      
      html += `<li class="mb-2" style="line-height: 1.8;">${title}${formattedPoints}</li>`;
    });
    html += `
            </ul>
          </div>
        </div>
      </div>
    `;
    
    storeAnalysisData(chartId, html);
  }
  
  // ========== TOP BARANGAYS ANALYSIS ==========
  function renderTopBarangaysAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    const sortedValues = [...values].sort((a, b) => b - a);
    const top2Total = sortedValues.slice(0, 2).reduce((a, b) => a + b, 0);
    const concentration = total > 0 ? (top2Total / total * 100).toFixed(1) : 0;
    const top3Total = sortedValues.slice(0, 3).reduce((a, b) => a + b, 0);
    const top3Concentration = total > 0 ? (top3Total / total * 100).toFixed(1) : 0;
    const avgPerBarangay = total > 0 ? (total / labels.length).toFixed(1) : 0;
    let html = `<div class="row"><div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-map-marker-alt"></i></span><div class="info-box-content"><span class="info-box-text">Top Barangay</span><span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span><span class="info-box-text">${maxValue} (${maxPercentage}%)</span></div></div></div><div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total Couples</span><span class="info-box-number">${total}</span></div></div></div><div class="col-md-4"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-chart-pie"></i></span><div class="info-box-content"><span class="info-box-text">Top 3 Concentration</span><span class="info-box-number">${top3Concentration}%</span></div></div></div></div>`;
    html += `<div class="row mt-3"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5><ul class="mb-0">`;
    if (maxPercentage > 40 && total > 0) {
      html += `<li class="mb-2">High geographic concentration in ${maxIndex >= 0 ? labels[maxIndex] : 'top barangay'} (${maxPercentage}%), indicating strong program awareness or accessibility in this area. This may reflect effective local outreach, community partnerships, or proximity to services.</li>`;
    } else if (maxPercentage > 25 && maxPercentage <= 40 && total > 0) {
      html += `<li class="mb-2">Moderate concentration in ${maxIndex >= 0 ? labels[maxIndex] : 'top barangay'} (${maxPercentage}%), with other barangays also showing significant participation.</li>`;
    }
    if (concentration > 60 && total > 0) {
      html += `<li class="mb-2">Top 2 barangays account for ${concentration}% of participants, indicating geographic clustering. This suggests successful outreach in these areas but may indicate underutilization in other barangays.</li>`;
    } else if (concentration > 40 && concentration <= 60 && total > 0) {
      html += `<li class="mb-2">Top 2 barangays represent ${concentration}% of participants, showing moderate geographic concentration with reasonable distribution across other areas.</li>`;
    }
    if (parseFloat(top3Concentration) > 70 && total > 0) {
      html += `<li class="mb-2">Top 3 barangays account for ${top3Concentration}% of participants, indicating strong geographic clustering. Consider whether this reflects program effectiveness in these areas or barriers to access in others.</li>`;
    }
    if (avgPerBarangay > 0 && total > 0) {
      html += `<li class="mb-2">Average of ${avgPerBarangay} couples per barangay. This baseline helps identify barangays that are significantly above or below average, indicating potential success stories or areas needing targeted outreach.</li>`;
    }
    const lowParticipationBarangays = labels.filter((label, idx) => values[idx] > 0 && values[idx] < parseFloat(avgPerBarangay) * 0.5);
    if (lowParticipationBarangays.length > 0 && total > 0) {
      html += `<li class="mb-2">${lowParticipationBarangays.length} barangay(s) have participation below 50% of average: ${lowParticipationBarangays.join(', ')}. These areas may need targeted outreach, barrier assessment, or improved accessibility.</li>`;
    }
    if (total < 20) {
      html += `<li class="mb-2">Sample size is relatively small (${total} couples). Consider expanding outreach to improve geographic representation and statistical significance.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2">Good sample size (${total} couples). Geographic distribution patterns are statistically significant for program planning and resource allocation.</li>`;
    }
    html += `</ul></div></div></div>`;
    const recommendations = [];
    if (maxPercentage > 40 && total > 0) {
      recommendations.push(`High concentration in ${maxIndex >= 0 ? labels[maxIndex] : 'top barangay'} (${maxPercentage}%). Establish a satellite office or regular service delivery point in this area to meet demand and reduce travel barriers. Leverage successful outreach strategies from this barangay to replicate in other areas. However, also assess why other barangays have lower participation.`);
    } else if (maxPercentage > 25 && maxPercentage <= 40 && total > 0) {
      recommendations.push(`Moderate concentration in ${maxIndex >= 0 ? labels[maxIndex] : 'top barangay'} (${maxPercentage}%). Consider establishing regular service delivery in this area while maintaining outreach to other barangays. Share successful strategies from this barangay with other areas.`);
    }
    if (concentration > 60 && total > 0) {
      recommendations.push(`Geographic concentration in top 2 barangays (${concentration}%). While maintaining services in these high-demand areas, expand outreach to under-served barangays. Conduct barrier assessments in low-participation areas: transportation, awareness, cultural factors, or service accessibility. Develop targeted outreach strategies for each barangay. Consider mobile services or community-based delivery for remote areas.`);
    } else if (concentration > 40 && concentration <= 60 && total > 0) {
      recommendations.push(`Moderate geographic concentration (${concentration}% in top 2 barangays). Maintain strong services in high-demand areas while continuing outreach to other barangays. Identify and replicate successful strategies from high-participation barangays.`);
    }
    if (parseFloat(top3Concentration) > 70 && total > 0) {
      recommendations.push(`Strong clustering in top 3 barangays (${top3Concentration}%). Establish regular service delivery points in these areas. Simultaneously, investigate barriers in other barangays: conduct community assessments, engage local leaders, address transportation issues, and improve awareness through targeted campaigns.`);
    }
    if (lowParticipationBarangays.length > 0 && total > 0) {
      recommendations.push(`Low participation in ${lowParticipationBarangays.length} barangay(s): ${lowParticipationBarangays.join(', ')}. Conduct targeted barrier assessments: transportation, awareness, cultural factors, service accessibility, or trust issues. Develop barangay-specific outreach plans. Engage local leaders, community health workers, and barangay officials. Consider mobile services or community-based delivery.`);
    }
    if (total < 20) {
      recommendations.push(`Small sample size (${total} couples). Expand outreach through community partnerships, social media, barangay announcements, and collaboration with local government units to improve geographic representation.`);
    } else if (total >= 50) {
      recommendations.push(`Good sample size (${total} couples). Use geographic distribution data to inform resource allocation, service delivery planning, and targeted outreach strategies. Regularly monitor participation trends by barangay.`);
    }
    if (recommendations.length === 0) {
      recommendations.push(`Geographic distribution appears balanced across barangays. Continue monitoring trends, maintain services in all areas, and ensure equitable access to programs. Regularly assess and address barriers in any under-served areas.`);
    }
    html += `<div class="row mt-4"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5><ul class="mb-0">`;
    recommendations.forEach(rec => html += `<li class="mb-2">${rec}</li>`);
    html += `</ul></div></div></div>`;
    storeAnalysisData(chartId, html);
  }
  
  // ========== MARRIAGE SEASONALITY ANALYSIS ==========
  function renderMarriageSeasonalityAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    let peakSeason = '';
    let peakValue = 0;
    for (let i = 0; i <= labels.length - 3; i++) {
      const seasonTotal = values.slice(i, i + 3).reduce((a, b) => a + b, 0);
      if (seasonTotal > peakValue) { peakValue = seasonTotal; peakSeason = labels.slice(i, i + 3).join(', '); }
    }
    const average = total / labels.length;
    const variance = values.reduce((sum, val) => sum + Math.pow(val - average, 2), 0) / labels.length;
    const stdDev = Math.sqrt(variance).toFixed(1);
    const minIndex = values.indexOf(Math.min(...values.filter(v => v > 0)));
    const minValue = minIndex >= 0 ? values[minIndex] : 0;
    const minPercentage = total > 0 ? (minValue / total * 100).toFixed(1) : 0;
    const peakToTroughRatio = minValue > 0 ? (maxValue / minValue).toFixed(1) : 0;
    let html = `<div class="row"><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-calendar-check"></i></span><div class="info-box-content"><span class="info-box-text">Peak Month</span><span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span><span class="info-box-text">${maxValue}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-calendar-times"></i></span><div class="info-box-content"><span class="info-box-text">Lowest Month</span><span class="info-box-number">${minIndex >= 0 ? labels[minIndex] : 'N/A'}</span><span class="info-box-text">${minValue}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total Marriages</span><span class="info-box-number">${total}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-chart-line"></i></span><div class="info-box-content"><span class="info-box-text">Peak Season</span><span class="info-box-number">${peakSeason || 'N/A'}</span></div></div></div></div>`;
    html += `<div class="row mt-3"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5><ul class="mb-0">`;
    if (maxPercentage > 15 && total > 0) {
      html += `<li class="mb-2">Strong peak in ${maxIndex >= 0 ? labels[maxIndex] : 'peak month'} (${maxPercentage}%), indicating seasonal preferences for marriage. This may be influenced by cultural traditions, weather, holidays, or economic factors (harvest seasons, bonuses).</li>`;
    } else if (maxPercentage > 10 && maxPercentage <= 15 && total > 0) {
      html += `<li class="mb-2">Moderate peak in ${maxIndex >= 0 ? labels[maxIndex] : 'peak month'} (${maxPercentage}%), showing some seasonal variation in marriage patterns.</li>`;
    }
    if (parseFloat(stdDev) > 10 && total > 0) {
      html += `<li class="mb-2">High variability in marriage patterns (standard deviation: ${stdDev}), indicating significant seasonal fluctuations. This requires flexible resource planning and capacity management throughout the year.</li>`;
    } else if (parseFloat(stdDev) > 5 && parseFloat(stdDev) <= 10 && total > 0) {
      html += `<li class="mb-2">Moderate variability in marriage patterns (standard deviation: ${stdDev}), showing some seasonal trends that can be planned for.</li>`;
    } else if (parseFloat(stdDev) <= 5 && total > 0) {
      html += `<li class="mb-2">Low variability (standard deviation: ${stdDev}), indicating relatively consistent marriage patterns throughout the year, which facilitates stable resource planning.</li>`;
    }
    if (peakSeason && peakValue > 0 && total > 0) {
      html += `<li class="mb-2">Peak season identified: ${peakSeason} with ${peakValue} marriages. This concentrated period may require increased staffing, venue capacity, and service availability.</li>`;
    }
    if (minValue > 0 && maxValue > 0 && parseFloat(peakToTroughRatio) > 2) {
      html += `<li class="mb-2">Significant difference between peak (${maxValue}) and lowest (${minValue}) months (${peakToTroughRatio}x ratio). This seasonal pattern suggests the need for flexible staffing and resource allocation.</li>`;
    }
    if (total < 20) {
      html += `<li class="mb-2">Sample size is relatively small (${total} marriages). Consider expanding data collection period to better understand seasonal patterns and improve statistical significance.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2">Good sample size (${total} marriages). Seasonal patterns are statistically significant for capacity planning and resource allocation.</li>`;
    }
    html += `</ul></div></div></div>`;
    const recommendations = [];
    if (maxPercentage > 15 && total > 0) {
      recommendations.push(`Strong peak in ${maxIndex >= 0 ? labels[maxIndex] : 'peak month'} (${maxPercentage}%). Increase staffing, venue capacity, and service availability during this period. Consider advance booking systems, extended hours, and additional service delivery points. However, maintain baseline capacity during low-demand months.`);
    } else if (maxPercentage > 10 && maxPercentage <= 15 && total > 0) {
      recommendations.push(`Moderate peak in ${maxIndex >= 0 ? labels[maxIndex] : 'peak month'} (${maxPercentage}%). Plan for slightly increased capacity during this period while maintaining consistent services year-round.`);
    }
    if (parseFloat(stdDev) > 10 && total > 0) {
      recommendations.push(`High variability in marriage patterns (standard deviation: ${stdDev}). Implement flexible staffing models: hire part-time staff for peak periods, cross-train existing staff, establish a pool of on-call counselors, and use temporary venues during peak months. Develop capacity management protocols that can scale up and down based on demand.`);
    } else if (parseFloat(stdDev) > 5 && parseFloat(stdDev) <= 10 && total > 0) {
      recommendations.push(`Moderate variability (standard deviation: ${stdDev}). Plan for seasonal fluctuations with some flexibility in staffing and resource allocation. Maintain core capacity while having ability to scale during peak periods.`);
    }
    if (peakSeason && peakValue > 0 && total > 0) {
      recommendations.push(`Peak season identified: ${peakSeason}. Develop specific capacity plans for this period: increase counselor availability, secure additional venues, extend service hours, and implement advance scheduling. Consider offering special programs or packages during peak season to manage demand effectively.`);
    }
    if (minValue > 0 && maxValue > 0 && parseFloat(peakToTroughRatio) > 2 && total > 0) {
      recommendations.push(`Significant seasonal variation (${peakToTroughRatio}x between peak and lowest months). Implement flexible resource allocation: use peak period revenue to maintain services during low-demand months, develop off-peak promotional campaigns to balance demand, and consider offering special programs during low-demand periods to maintain engagement.`);
    }
    if (total < 20) {
      recommendations.push(`Small sample size (${total} marriages). Expand data collection period to at least 2-3 years to better understand seasonal patterns, cultural trends, and long-term marriage seasonality. This will improve capacity planning accuracy.`);
    } else if (total >= 50) {
      recommendations.push(`Good sample size (${total} marriages). Use seasonal patterns to inform annual capacity planning, budget allocation, and staffing decisions. Regularly review and adjust plans based on emerging trends.`);
    }
    if (recommendations.length === 0) {
      recommendations.push(`Seasonal distribution appears relatively balanced. Continue monitoring trends, maintain consistent service capacity, and be prepared to adjust resources if seasonal patterns emerge or change.`);
    }
    html += `<div class="row mt-4"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5><ul class="mb-0">`;
    recommendations.forEach(rec => html += `<li class="mb-2">${rec}</li>`);
    html += `</ul></div></div></div>`;
    storeAnalysisData(chartId, html);
  }
  
  // ========== SESSIONS MONTHLY ANALYSIS ==========
  function renderSessionsMonthlyAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const average = (total / labels.length).toFixed(1);
    const firstHalf = values.slice(0, Math.floor(values.length / 2)).reduce((a, b) => a + b, 0);
    const secondHalf = values.slice(Math.floor(values.length / 2)).reduce((a, b) => a + b, 0);
    const trend = firstHalf > 0 ? ((secondHalf - firstHalf) / firstHalf * 100).toFixed(1) : 0;
    let trendDirection = 'stable';
    if (parseFloat(trend) > 10) trendDirection = 'increasing';
    else if (parseFloat(trend) < -10) trendDirection = 'decreasing';
    const minIndex = values.indexOf(Math.min(...values.filter(v => v > 0)));
    const minValue = minIndex >= 0 ? values[minIndex] : 0;
    const recentMonths = values.slice(-3);
    const recentAverage = recentMonths.reduce((a, b) => a + b, 0) / recentMonths.length;
    const growthRate = firstHalf > 0 ? ((secondHalf - firstHalf) / firstHalf * 100).toFixed(1) : 0;
    let html = `<div class="row"><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-calendar-check"></i></span><div class="info-box-content"><span class="info-box-text">Peak Month</span><span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span><span class="info-box-text">${maxValue} sessions</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-chart-line"></i></span><div class="info-box-content"><span class="info-box-text">Monthly Average</span><span class="info-box-number">${average}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-arrow-${trendDirection === 'increasing' ? 'up' : trendDirection === 'decreasing' ? 'down' : 'right'}"></i></span><div class="info-box-content"><span class="info-box-text">Trend</span><span class="info-box-number">${trendDirection}</span><span class="info-box-text">${trend !== '0' ? (trend > 0 ? '+' : '') + trend + '%' : ''}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total Sessions</span><span class="info-box-number">${total}</span></div></div></div></div>`;
    html += `<div class="row mt-3"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5><ul class="mb-0">`;
    if (trendDirection === 'increasing' && parseFloat(trend) > 10) {
      html += `<li class="mb-2">Strong positive trend (${trend}% increase from first half to second half), indicating growing program utilization and effectiveness. This growth suggests successful outreach, increased awareness, or improved service quality.</li>`;
    } else if (trendDirection === 'increasing' && parseFloat(trend) > 0) {
      html += `<li class="mb-2">Positive trend (${trend}% increase), showing gradual growth in session participation. This indicates steady program development and increasing community engagement.</li>`;
    } else if (trendDirection === 'decreasing' && parseFloat(trend) < -10) {
      html += `<li class="mb-2">Significant declining trend (${Math.abs(parseFloat(trend))}% decrease), indicating potential issues requiring investigation: reduced outreach effectiveness, service quality concerns, scheduling barriers, or external factors affecting participation.</li>`;
    } else if (trendDirection === 'decreasing' && parseFloat(trend) < 0) {
      html += `<li class="mb-2">Declining trend (${Math.abs(parseFloat(trend))}% decrease), suggesting a need to investigate causes and implement corrective measures to reverse the trend.</li>`;
    } else {
      html += `<li class="mb-2">Stable trend, indicating consistent session participation over time. This suggests reliable program delivery and steady community engagement.</li>`;
    }
    if (maxIndex >= 0 && maxValue > 0 && total > 0) {
      html += `<li class="mb-2">Peak month: ${labels[maxIndex]} with ${maxValue} sessions (${(maxValue/total*100).toFixed(1)}% of total). This peak may reflect seasonal factors, successful outreach campaigns, or special program offerings during that period.</li>`;
    }
    if (minIndex >= 0 && minValue > 0 && maxValue > 0) {
      const variance = ((maxValue - minValue) / average * 100).toFixed(1);
      if (parseFloat(variance) > 50) {
        html += `<li class="mb-2">High month-to-month variability (${variance}% difference between peak and lowest months), indicating fluctuating demand that requires flexible capacity planning.</li>`;
      }
    }
    if (recentAverage > parseFloat(average) * 1.1) {
      html += `<li class="mb-2">Recent months show above-average activity (recent 3-month average: ${recentAverage.toFixed(1)} vs overall average: ${average}), indicating current momentum and positive program trajectory.</li>`;
    } else if (recentAverage < parseFloat(average) * 0.9) {
      html += `<li class="mb-2">Recent months show below-average activity (recent 3-month average: ${recentAverage.toFixed(1)} vs overall average: ${average}), suggesting a need to investigate recent changes and implement interventions.</li>`;
    }
    if (parseFloat(average) < 5 && total > 0) {
      html += `<li class="mb-2">Low average monthly sessions (${average}), indicating potential underutilization. Consider expanding outreach, improving accessibility, or addressing barriers to participation.</li>`;
    } else if (parseFloat(average) >= 10 && total > 0) {
      html += `<li class="mb-2">Good average monthly session volume (${average}), indicating healthy program utilization and consistent service delivery.</li>`;
    }
    if (total < 30) {
      html += `<li class="mb-2">Limited data period (${total} total sessions over ${labels.length} months). Extend monitoring period to better understand long-term trends and seasonal patterns.</li>`;
    } else if (total >= 100) {
      html += `<li class="mb-2">Substantial data (${total} sessions over ${labels.length} months). Trend analysis is statistically significant for program planning and resource allocation.</li>`;
    }
    html += `</ul></div></div></div>`;
    const recommendations = [];
    if (trendDirection === 'increasing' && parseFloat(trend) > 10 && total > 0) {
      recommendations.push(`Strong positive growth trend (${trend}% increase). Capitalize on momentum by: maintaining quality services, expanding successful outreach strategies, increasing capacity to meet growing demand, and documenting best practices. However, ensure growth is sustainable and quality is maintained.`);
    } else if (trendDirection === 'increasing' && parseFloat(trend) > 0 && total > 0) {
      recommendations.push(`Positive growth trend (${trend}% increase). Continue current successful strategies, identify what's driving growth, and scale effective approaches. Monitor capacity to ensure quality is maintained as demand increases.`);
    } else if (trendDirection === 'decreasing' && parseFloat(trend) < -10 && total > 0) {
      recommendations.push(`Significant declining trend (${Math.abs(parseFloat(trend))}% decrease) requires immediate investigation and intervention. Assess: outreach effectiveness, service quality, scheduling accessibility, staff availability, participant satisfaction, external factors (competition, economic conditions), and program relevance. Develop action plan to reverse trend: improve outreach, enhance service quality, address barriers, and re-engage past participants.`);
    } else if (trendDirection === 'decreasing' && parseFloat(trend) < 0 && total > 0) {
      recommendations.push(`Declining trend (${Math.abs(parseFloat(trend))}% decrease). Investigate causes: review participant feedback, assess outreach effectiveness, evaluate service quality, check for scheduling or accessibility barriers. Implement targeted interventions to reverse the trend: enhanced marketing, improved scheduling options, quality improvements, or program adjustments based on feedback.`);
    } else {
      recommendations.push(`Stable trend indicates consistent program delivery. Maintain current service quality, continue monitoring, and look for opportunities to grow while preserving stability. Consider small improvements or pilot programs to test growth strategies.`);
    }
    if (maxIndex >= 0 && maxValue > 0 && (maxValue / parseFloat(average)) > 1.5 && total > 0) {
      recommendations.push(`Peak month (${labels[maxIndex]}) had ${((maxValue/parseFloat(average)-1)*100).toFixed(0)}% above average activity. Analyze factors contributing to this peak (outreach campaigns, seasonal factors, special programs) and replicate successful strategies. Ensure capacity can handle similar peaks in the future.`);
    }
    if (recentAverage < parseFloat(average) * 0.9 && total > 0) {
      recommendations.push(`Recent months show below-average activity (recent average: ${recentAverage.toFixed(1)} vs overall: ${average}). Investigate recent changes: staff turnover, policy changes, external factors, or program modifications. Implement immediate interventions: re-engagement campaigns, outreach to past participants, service quality review, and barrier assessment.`);
    } else if (recentAverage > parseFloat(average) * 1.1 && total > 0) {
      recommendations.push(`Recent months show above-average activity (recent average: ${recentAverage.toFixed(1)} vs overall: ${average}). Maintain momentum by continuing successful strategies, ensuring adequate capacity, and documenting what's working well.`);
    }
    if (parseFloat(average) < 5 && total > 0) {
      recommendations.push(`Low average monthly sessions (${average}) indicates underutilization. Expand outreach through multiple channels (social media, community partnerships, referrals), improve accessibility (location, hours, scheduling), address barriers (transportation, cost, awareness), and enhance program visibility. Consider offering incentives or special programs to boost participation.`);
    }
    if (total < 30) {
      recommendations.push(`Limited data period (${total} sessions). Extend monitoring to at least 12-24 months to better understand long-term trends, seasonal patterns, and program sustainability. Continue data collection and regular trend analysis.`);
    } else if (total >= 100) {
      recommendations.push(`Substantial data (${total} sessions) provides reliable trend analysis. Use this data for: capacity planning, budget allocation, staffing decisions, program evaluation, and strategic planning. Regularly update trend analysis and adjust strategies based on emerging patterns.`);
    }
    if (recommendations.length === 0) {
      recommendations.push(`Session trends appear healthy. Continue monitoring, maintain service quality, and look for opportunities to optimize program delivery and participant engagement.`);
    }
    html += `<div class="row mt-4"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5><ul class="mb-0">`;
    recommendations.forEach(rec => html += `<li class="mb-2">${rec}</li>`);
    html += `</ul></div></div></div>`;
    storeAnalysisData(chartId, html);
  }
  
  // ========== FP METHODS ANALYSIS ==========
  function renderFPMethodsAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const maxIndex = values.indexOf(Math.max(...values));
    const maxValue = maxIndex >= 0 ? values[maxIndex] : 0;
    const maxPercentage = total > 0 ? (maxValue / total * 100).toFixed(1) : 0;
    const isMale = chartId.includes('Male');
    const genderLabel = isMale ? 'Male' : 'Female';
    const permanentMethods = ['Bilateral Tubal Ligation', 'Vasectomy'];
    const longActing = ['IUD', 'Implant'];
    const shortActing = ['Pills', 'Injectables', 'Condom'];
    const traditional = ['Rhythm', 'Withdrawal', 'Calendar'];
    const permanentTotal = labels.reduce((sum, label, idx) => sum + (permanentMethods.some(m => label.includes(m)) ? (values[idx] || 0) : 0), 0);
    const longActingTotal = labels.reduce((sum, label, idx) => sum + (longActing.some(m => label.includes(m)) ? (values[idx] || 0) : 0), 0);
    const shortActingTotal = labels.reduce((sum, label, idx) => sum + (shortActing.some(m => label.includes(m)) ? (values[idx] || 0) : 0), 0);
    const traditionalTotal = labels.reduce((sum, label, idx) => sum + (traditional.some(m => label.includes(m)) ? (values[idx] || 0) : 0), 0);
    const modernTotal = permanentTotal + longActingTotal + shortActingTotal;
    const modernPercentage = total > 0 ? ((modernTotal / total * 100).toFixed(1)) : 0;
    let html = `<div class="row"><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">Most Preferred</span><span class="info-box-number">${maxIndex >= 0 ? labels[maxIndex] : 'N/A'}</span><span class="info-box-text">${maxPercentage}%</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total ${genderLabel}</span><span class="info-box-number">${total}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-shield-alt"></i></span><div class="info-box-content"><span class="info-box-text">Modern Methods</span><span class="info-box-number">${modernTotal}</span><span class="info-box-text">${modernPercentage}%</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-leaf"></i></span><div class="info-box-content"><span class="info-box-text">Traditional</span><span class="info-box-number">${traditionalTotal}</span><span class="info-box-text">${total > 0 ? ((traditionalTotal / total * 100).toFixed(1)) : 0}%</span></div></div></div></div>`;
    html += `<div class="row mt-3"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5><ul class="mb-0">`;
    if (maxPercentage > 40 && total > 0) {
      html += `<li class="mb-2">Strong preference for ${maxIndex >= 0 ? labels[maxIndex] : 'top method'} among ${genderLabel.toLowerCase()}s (${maxPercentage}%). This indicates a clear method preference that should be prioritized in supply chain management and counseling discussions.</li>`;
    } else if (maxPercentage > 25 && maxPercentage <= 40 && total > 0) {
      html += `<li class="mb-2">Moderate preference for ${maxIndex >= 0 ? labels[maxIndex] : 'top method'} (${maxPercentage}%) among ${genderLabel.toLowerCase()}s, with other methods also having significant representation.</li>`;
    }
    if (parseFloat(modernPercentage) > 70 && total > 0) {
      html += `<li class="mb-2">High modern method usage among ${genderLabel.toLowerCase()}s (${modernPercentage}%), indicating good awareness and acceptance of effective family planning methods. This is a positive indicator for reproductive health outcomes.</li>`;
    } else if (parseFloat(modernPercentage) >= 50 && parseFloat(modernPercentage) <= 70 && total > 0) {
      html += `<li class="mb-2">Moderate modern method usage (${modernPercentage}%) among ${genderLabel.toLowerCase()}s. There's room for improvement in promoting modern, highly effective methods.</li>`;
    } else if (parseFloat(modernPercentage) < 50 && total > 0) {
      html += `<li class="mb-2">Low modern method usage among ${genderLabel.toLowerCase()}s (${modernPercentage}%), indicating a need for education and awareness campaigns about the benefits and availability of modern family planning methods.</li>`;
    }
    if (parseFloat(traditionalTotal / total * 100) > 30 && total > 0) {
      html += `<li class="mb-2">Significant traditional method usage (${(traditionalTotal/total*100).toFixed(1)}%) among ${genderLabel.toLowerCase()}s. While these methods are less effective, they may reflect cultural preferences or barriers to accessing modern methods.</li>`;
    }
    if (permanentTotal > 0 && total > 0) {
      html += `<li class="mb-2">Permanent methods (${(permanentTotal/total*100).toFixed(1)}%) are represented among ${genderLabel.toLowerCase()}s, indicating some couples have completed their family planning goals.</li>`;
    }
    if (longActingTotal > 0 && total > 0) {
      html += `<li class="mb-2">Long-acting reversible methods (${(longActingTotal/total*100).toFixed(1)}%) are used by some ${genderLabel.toLowerCase()}s, providing effective protection with minimal user compliance required.</li>`;
    }
    if (total < 20) {
      html += `<li class="mb-2">Sample size is relatively small (${total} ${genderLabel.toLowerCase()}s). Consider expanding outreach to improve data representation.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2">Good sample size (${total} ${genderLabel.toLowerCase()}s). Method preference patterns are statistically significant for program planning.</li>`;
    }
    html += `</ul></div></div></div>`;
    const recommendations = [];
    if (maxPercentage > 40 && total > 0) {
      recommendations.push(`<strong>Strong Method Preference (${maxPercentage}%):</strong> Strong preference for ${maxIndex >= 0 ? labels[maxIndex] : 'top method'} among ${genderLabel.toLowerCase()}s. (1) Ensure adequate supply chain management and stock sufficient quantities, (2) Train counselors to provide comprehensive information about this method, (3) Promote method mix to ensure couples have options, (4) Monitor supply levels to prevent stockouts.`);
    } else if (maxPercentage > 25 && maxPercentage <= 40 && total > 0) {
      recommendations.push(`<strong>Moderate Method Preference (${maxPercentage}%):</strong> Moderate preference for ${maxIndex >= 0 ? labels[maxIndex] : 'top method'}. (1) Maintain adequate supply while ensuring other methods are also available, (2) Promote method mix counseling to help couples choose the best method for their needs, (3) Provide information on alternative methods for couples who may not be suitable for the preferred method.`);
    }
    if (parseFloat(modernPercentage) < 50 && total > 0) {
      recommendations.push(`<strong>Low Modern Method Usage (${modernPercentage}%):</strong> Low modern method usage among ${genderLabel.toLowerCase()}s. (1) Implement comprehensive education campaigns highlighting the effectiveness, safety, and availability of modern methods, (2) Address misconceptions and provide accurate information, (3) Ensure easy access to modern methods, (4) Consider peer education and community health worker training, (5) Provide method mix counseling to help couples transition to modern methods.`);
    } else if (parseFloat(modernPercentage) >= 50 && parseFloat(modernPercentage) < 70 && total > 0) {
      recommendations.push(`<strong>Moderate Modern Method Usage (${modernPercentage}%):</strong> (1) Continue promoting modern methods while addressing barriers, (2) Provide method mix counseling to help couples transition from traditional to modern methods if desired, (3) Identify and address specific barriers preventing increased modern method adoption.`);
    } else if (parseFloat(modernPercentage) >= 70 && total > 0) {
      recommendations.push(`<strong>High Modern Method Usage (${modernPercentage}%):</strong> Excellent modern method usage. (1) Maintain this level by ensuring consistent supply, (2) Provide quality counseling, (3) Address any method discontinuation issues, (4) Continue monitoring for method satisfaction and side effects, (5) Ensure adequate provider training and support.`);
    }
    if (parseFloat(traditionalTotal / total * 100) > 30 && total > 0) {
      recommendations.push(`<strong>Significant Traditional Method Usage (${(traditionalTotal/total*100).toFixed(1)}%):</strong> Significant traditional method usage among ${genderLabel.toLowerCase()}s. (1) While respecting cultural preferences, provide education about the lower effectiveness of traditional methods, (2) Offer modern alternatives, (3) Address barriers to modern method access (cost, availability, misconceptions), (4) Provide method mix counseling to help couples make informed choices.`);
    }
    if (permanentTotal > 0 && total > 0 && (permanentTotal / total) < 0.1) {
      recommendations.push(`<strong>Low Permanent Method Usage (${(permanentTotal/total*100).toFixed(1)}%):</strong> Low permanent method representation among ${genderLabel.toLowerCase()}s. (1) For couples who have completed their families, provide information about permanent methods (vasectomy, tubal ligation) as highly effective, long-term options, (2) Address misconceptions about permanent methods, (3) Ensure trained providers are available for permanent method services, (4) Provide counseling on family completion and permanent method benefits.`);
    }
    if (longActingTotal > 0 && total > 0 && (longActingTotal / total) < 0.15) {
      recommendations.push(`<strong>Low Long-Acting Method Usage (${(longActingTotal/total*100).toFixed(1)}%):</strong> Low long-acting reversible method usage among ${genderLabel.toLowerCase()}s. (1) Promote IUDs and implants as highly effective options requiring minimal user compliance, (2) Address misconceptions about long-acting methods, (3) Ensure trained providers are available, (4) Provide information on insertion procedures and follow-up care, (5) Address cost and accessibility barriers.`);
    }
    if (recommendations.length === 0) {
      recommendations.push(`Method preferences appear balanced among ${genderLabel.toLowerCase()}s. Continue monitoring trends, ensure adequate supply of all preferred methods, and maintain quality counseling services.`);
    }
    html += `<div class="row mt-4"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5><ul class="mb-0">`;
    recommendations.forEach(rec => html += `<li class="mb-2">${rec}</li>`);
    html += `</ul></div></div></div>`;
    storeAnalysisData(chartId, html);
  }
  
  // ========== FP INTENTION ANALYSIS ==========
  function renderFPIntentAnalysis(chartId, data) {
    const labels = data.labels || [];
    const values = data.values || [];
    const total = values.reduce((a, b) => a + (b || 0), 0);
    const yesIndex = labels.indexOf('Yes');
    const noIndex = labels.indexOf('No');
    const yesCount = yesIndex >= 0 ? values[yesIndex] : 0;
    const noCount = noIndex >= 0 ? values[noIndex] : 0;
    const yesPercentage = total > 0 ? (yesCount / total * 100).toFixed(1) : 0;
    const noPercentage = total > 0 ? (noCount / total * 100).toFixed(1) : 0;
    const isMale = chartId.includes('Male');
    const genderLabel = isMale ? 'Male' : 'Female';
    let html = `<div class="row"><div class="col-md-6"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">Intend to Use FP</span><span class="info-box-number">${yesCount}</span><span class="info-box-text">${yesPercentage}%</span></div></div></div><div class="col-md-6"><div class="info-box"><span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span><div class="info-box-content"><span class="info-box-text">Do Not Intend</span><span class="info-box-number">${noCount}</span><span class="info-box-text">${noPercentage}%</span></div></div></div></div><div class="row mt-3"><div class="col-12"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total ${genderLabel}</span><span class="info-box-number">${total}</span></div></div></div></div>`;
    html += `<div class="row mt-3"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5><ul class="mb-0">`;
    if (yesPercentage >= 80 && total > 0) {
      html += `<li class="mb-2">High FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%), indicating strong motivation for family planning. This positive intention should be leveraged to convert into actual method adoption through accessible services and quality counseling.</li>`;
    } else if (yesPercentage >= 60 && yesPercentage < 80 && total > 0) {
      html += `<li class="mb-2">Moderate to good FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%). There's potential to increase this through targeted education and addressing barriers to family planning access.</li>`;
    } else if (yesPercentage < 60 && total > 0) {
      html += `<li class="mb-2">Low FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%), indicating a need for comprehensive education campaigns, addressing misconceptions, and understanding barriers to family planning acceptance.</li>`;
    }
    if (noPercentage > 30 && total > 0) {
      html += `<li class="mb-2">Significant non-intention rate among ${genderLabel.toLowerCase()}s (${noPercentage}%). Investigate underlying reasons: cultural beliefs, religious concerns, desire for more children, partner opposition, or lack of information. Understanding these barriers is crucial for effective program design.</li>`;
    } else if (noPercentage > 15 && noPercentage <= 30 && total > 0) {
      html += `<li class="mb-2">Some ${genderLabel.toLowerCase()}s do not intend to use FP (${noPercentage}%). Conduct qualitative research to understand their perspectives and address specific concerns or barriers.</li>`;
    }
    if (yesPercentage >= 60 && yesPercentage < 80 && total > 0) {
      html += `<li class="mb-2">There's a gap between intention (${yesPercentage}%) and likely action. Focus on removing barriers to access, ensuring method availability, and providing quality counseling to convert intention into actual FP use.</li>`;
    }
    if (total < 20) {
      html += `<li class="mb-2">Sample size is relatively small (${total} ${genderLabel.toLowerCase()}s). Consider expanding outreach to improve data representation and understanding of FP intentions.</li>`;
    } else if (total >= 50) {
      html += `<li class="mb-2">Good sample size (${total} ${genderLabel.toLowerCase()}s). FP intention patterns are statistically significant for program planning and resource allocation.</li>`;
    }
    html += `</ul></div></div></div>`;
    const recommendations = [];
    if (yesPercentage >= 80 && total > 0) {
      recommendations.push(`High FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%) is excellent. Focus on converting intention to action by ensuring easy access to methods, removing barriers (cost, distance, provider availability), providing quality counseling, and addressing method-specific concerns. Implement follow-up systems to track method adoption and continuation.`);
    } else if (yesPercentage >= 60 && yesPercentage < 80 && total > 0) {
      recommendations.push(`Moderate FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%). Implement targeted education campaigns highlighting benefits of family planning, address misconceptions, ensure method availability, and provide quality counseling. Focus on removing practical barriers to access. Consider peer education and community-based distribution.`);
    } else if (yesPercentage < 60 && total > 0) {
      recommendations.push(`Low FP intention among ${genderLabel.toLowerCase()}s (${yesPercentage}%) requires comprehensive intervention. Implement multi-faceted education campaigns addressing cultural beliefs, religious concerns, and misconceptions. Engage community leaders, religious leaders, and peer educators. Conduct qualitative research to understand specific barriers. Provide accurate information about FP benefits, safety, and method options. Address partner communication and gender dynamics.`);
    }
    if (noPercentage > 30 && total > 0) {
      recommendations.push(`High non-intention rate among ${genderLabel.toLowerCase()}s (${noPercentage}%) requires investigation. Conduct qualitative research (focus groups, interviews) to understand reasons: desire for more children, religious/cultural beliefs, partner opposition, fear of side effects, or lack of information. Develop targeted interventions addressing specific barriers. Engage community and religious leaders in dialogue. Consider couple counseling to address partner concerns.`);
    } else if (noPercentage > 15 && noPercentage <= 30 && total > 0) {
      recommendations.push(`Some ${genderLabel.toLowerCase()}s do not intend to use FP (${noPercentage}%). Investigate their specific concerns through surveys or interviews. Provide accurate information addressing misconceptions. Ensure programs respect diverse perspectives while promoting informed decision-making.`);
    }
    if (yesPercentage >= 60 && yesPercentage < 80 && total > 0) {
      recommendations.push(`Intention-action gap exists (${yesPercentage}% intention). Remove practical barriers: ensure method availability at convenient locations, reduce costs through subsidies, train more providers, improve service quality, and provide follow-up support. Implement reminder systems and method continuation support.`);
    }
    if (recommendations.length === 0) {
      recommendations.push(`FP intention appears positive among ${genderLabel.toLowerCase()}s. Continue monitoring trends, maintain quality services, and ensure easy access to family planning methods. Regularly assess barriers and address them proactively.`);
    }
    html += `<div class="row mt-4"><div class="col-12"><div class="callout callout-info"><h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5><ul class="mb-0">`;
    recommendations.forEach(rec => html += `<li class="mb-2">${rec}</li>`);
    html += `</ul></div></div></div>`;
    storeAnalysisData(chartId, html);
  }
  
  // Function to generate recommendations based on chart data (kept for backward compatibility)
  function generateRecommendations(chartId, labels, values, total, categoriesWithData, maxIndex, minIndex, maxValue, minValue, average) {
    const recommendations = [];
    
    // Check if total is low
    if (total < 10) {
      recommendations.push(`Low total count (${total}). Consider reviewing data collection processes or expanding outreach efforts.`);
    }
    
    // Check for categories with no data
    const zeroCategories = labels.filter((label, idx) => values[idx] === 0);
    if (zeroCategories.length > 0 && zeroCategories.length < labels.length) {
      recommendations.push(`${zeroCategories.length} categor${zeroCategories.length === 1 ? 'y' : 'ies'} (${zeroCategories.join(', ')}) have no data. Consider targeted outreach for these categories.`);
    }
    
    // Check for significant variance between highest and lowest
    if (maxIndex >= 0 && minIndex >= 0 && maxValue > 0 && minValue > 0 && maxIndex !== minIndex) {
      const variance = maxValue - minValue;
      if (variance > (maxValue * 0.5)) {
        recommendations.push(`Significant variance detected between highest (${labels[maxIndex]}: ${maxValue}) and lowest (${labels[minIndex]}: ${minValue}) categories. Consider balancing distribution.`);
      }
    }
    
    // Check average per category
    if (average > 0 && average < 2 && categoriesWithData > 0) {
      recommendations.push(`Average per active category (${average}) is relatively low. Review data collection and consider expanding sample size.`);
    }
    
    // Chart-specific recommendations
    if (chartId.includes('attendance')) {
      const presentIndex = labels.indexOf('Present');
      const absentIndex = labels.indexOf('Absent');
      if (presentIndex >= 0 && absentIndex >= 0) {
        const presentCount = values[presentIndex] || 0;
        const absentCount = values[absentIndex] || 0;
        const attendanceRate = total > 0 ? (presentCount / total * 100).toFixed(1) : 0;
        if (attendanceRate < 70) {
          recommendations.push(`Attendance rate (${attendanceRate}%) is below optimal levels. Consider improving session scheduling, reminders, or accessibility.`);
        } else if (attendanceRate >= 90) {
          recommendations.push(`Excellent attendance rate (${attendanceRate}%). Maintain current practices and consider sharing successful strategies.`);
        }
      }
    }
    
    if (chartId.includes('income')) {
      const lowIncomeCategories = labels.filter((label, idx) => {
        const labelLower = label.toLowerCase();
        return (labelLower.includes('5000') || labelLower.includes('below')) && values[idx] > 0;
      });
      if (lowIncomeCategories.length > 0) {
        const lowIncomeTotal = labels.reduce((sum, label, idx) => {
          const labelLower = label.toLowerCase();
          return sum + ((labelLower.includes('5000') || labelLower.includes('below')) ? (values[idx] || 0) : 0);
        }, 0);
        const lowIncomePercentage = total > 0 ? (lowIncomeTotal / total * 100).toFixed(1) : 0;
        if (lowIncomePercentage > 40) {
          recommendations.push(`High percentage (${lowIncomePercentage}%) of couples in low-income brackets. Consider financial assistance programs or sliding scale fees.`);
        }
      }
    }
    
    if (chartId.includes('education')) {
      const lowEducationCategories = ['No Education', 'Pre School', 'Elementary Level', 'Elementary Graduate'];
      const lowEducationTotal = labels.reduce((sum, label, idx) => {
        return sum + (lowEducationCategories.includes(label) ? (values[idx] || 0) : 0);
      }, 0);
      const lowEducationPercentage = total > 0 ? (lowEducationTotal / total * 100).toFixed(1) : 0;
      if (lowEducationPercentage > 30) {
        recommendations.push(`Significant portion (${lowEducationPercentage}%) have lower education levels. Consider providing educational materials in simpler formats or additional support.`);
      }
    }
    
    if (chartId.includes('employment')) {
      const unemployedIndex = labels.indexOf('Unemployed');
      if (unemployedIndex >= 0) {
        const unemployedCount = values[unemployedIndex] || 0;
        const unemployedPercentage = total > 0 ? (unemployedCount / total * 100).toFixed(1) : 0;
        if (unemployedPercentage > 25) {
          recommendations.push(`High unemployment rate (${unemployedPercentage}%). Consider providing job placement assistance or skills training programs.`);
        }
      }
    }
    
    // If no specific recommendations, provide general guidance
    if (recommendations.length === 0) {
      if (total > 0) {
        recommendations.push(`Data patterns look healthy. Continue monitoring and maintain current practices.`);
      } else {
        recommendations.push(`No data available. Begin data collection and review data entry processes.`);
      }
    }
    
    return recommendations;
  }

  function updatePyramid(data){
    const lbl=[...data.labels].reverse(), m=[...data.male].reverse(), f=[...data.female].reverse();
    pyramidChart.data.labels=lbl;
    pyramidChart.data.datasets=[{label:'Male',data:m.map(v=>-v),backgroundColor:'rgba(0,102,204,0.7)'},{label:'Female',data:f,backgroundColor:'rgba(255,215,0,0.7)'}]; // Blue for Male, Gold for Female
    pyramidChart.update();
    const container = document.getElementById('pyramidLegend');
    if (container) {
      container.innerHTML = '';
      const list = document.createElement('div');
      list.className = 'legend-list';
      lbl.forEach((age, i) => {
        const row = document.createElement('div');
        row.className = 'legend-item';
        const maleSwatch = document.createElement('div');
        maleSwatch.className = 'legend-color';
        maleSwatch.style.backgroundColor = 'rgba(0,102,204,0.7)';
        const maleText = document.createElement('span');
        maleText.textContent = `${age}: ${Math.abs(Number(m[i])||0)} male`;
        const spacer = document.createElement('span');
        spacer.style.margin = '0 10px';
        const femaleSwatch = document.createElement('div');
        femaleSwatch.className = 'legend-color';
        femaleSwatch.style.backgroundColor = 'rgba(255,215,0,0.7)';
        const femaleText = document.createElement('span');
        femaleText.textContent = `${age}: ${(Number(f[i])||0)} female`;
        row.appendChild(maleSwatch); row.appendChild(maleText); row.appendChild(spacer); row.appendChild(femaleSwatch); row.appendChild(femaleText);
        list.appendChild(row);
      });
      container.appendChild(list);
    }
    
    // Render analysis for population pyramid
    if (data && data.labels && data.male && data.female) {
      const totalMale = data.male.reduce((a,b)=>a+(b||0),0);
      const totalFemale = data.female.reduce((a,b)=>a+(b||0),0);
      const total = totalMale + totalFemale;
      const maxMaleAge = data.male.indexOf(Math.max(...data.male));
      const maxFemaleAge = data.female.indexOf(Math.max(...data.female));
      
      let html = `
        <div class="row">
          <div class="col-md-6">
            <div class="info-box">
              <span class="info-box-icon bg-info"><i class="fas fa-male"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Male</span>
                <span class="info-box-number">${totalMale}</span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-box">
              <span class="info-box-icon bg-danger"><i class="fas fa-female"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Female</span>
                <span class="info-box-number">${totalFemale}</span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-box">
              <span class="info-box-icon bg-success"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Population</span>
                <span class="info-box-number">${total}</span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-box">
              <span class="info-box-icon bg-primary"><i class="fas fa-balance-scale"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Gender Ratio</span>
                <span class="info-box-number">${total > 0 ? (totalMale/total*100).toFixed(1) : 0}% M / ${total > 0 ? (totalFemale/total*100).toFixed(1) : 0}% F</span>
              </div>
            </div>
          </div>
        </div>
      `;
      
      if (maxMaleAge >= 0 && data.male[maxMaleAge] > 0) {
        html += `
          <div class="row mt-3">
            <div class="col-md-6">
              <div class="callout callout-info">
                <h5><i class="fas fa-male mr-2"></i>Peak Male Age Group</h5>
                <p class="mb-0">
                  <strong>${data.labels[maxMaleAge]}</strong> with <strong>${data.male[maxMaleAge]}</strong> individuals
                </p>
              </div>
            </div>
        `;
        
        if (maxFemaleAge >= 0 && data.female[maxFemaleAge] > 0) {
          html += `
            <div class="col-md-6">
              <div class="callout callout-info">
                <h5><i class="fas fa-female mr-2"></i>Peak Female Age Group</h5>
                <p class="mb-0">
                  <strong>${data.labels[maxFemaleAge]}</strong> with <strong>${data.female[maxFemaleAge]}</strong> individuals
                </p>
              </div>
            </div>
          </div>
          `;
        } else {
          html += `</div>`;
        }
      }
      
      // Key Insights
      html += `
        <div class="row mt-3">
          <div class="col-12">
            <div class="callout callout-info">
              <h5><i class="fas fa-chart-line mr-2"></i>Key Insights</h5>
              <ul class="mb-0">
      `;
      
      if (total > 0) {
        const genderRatio = (totalMale / total * 100).toFixed(1);
        const ageGroups = data.labels || [];
        const youngAdults = ageGroups.slice(0, 2); // 18-25, 26-30
        const middleAge = ageGroups.slice(2, 5); // 31-35, 36-40, 41-45
        const olderAdults = ageGroups.slice(5); // 46-50, 51+
        
        const youngMaleTotal = youngAdults.reduce((sum, _, idx) => sum + (data.male[idx] || 0), 0);
        const youngFemaleTotal = youngAdults.reduce((sum, _, idx) => sum + (data.female[idx] || 0), 0);
        const youngTotal = youngMaleTotal + youngFemaleTotal;
        const youngPercentage = total > 0 ? (youngTotal / total * 100).toFixed(1) : 0;
        
        const middleMaleTotal = middleAge.reduce((sum, _, idx) => sum + (data.male[idx + 2] || 0), 0);
        const middleFemaleTotal = middleAge.reduce((sum, _, idx) => sum + (data.female[idx + 2] || 0), 0);
        const middleTotal = middleMaleTotal + middleFemaleTotal;
        const middlePercentage = total > 0 ? (middleTotal / total * 100).toFixed(1) : 0;
        
        const olderMaleTotal = olderAdults.reduce((sum, _, idx) => sum + (data.male[idx + 5] || 0), 0);
        const olderFemaleTotal = olderAdults.reduce((sum, _, idx) => sum + (data.female[idx + 5] || 0), 0);
        const olderTotal = olderMaleTotal + olderFemaleTotal;
        const olderPercentage = total > 0 ? (olderTotal / total * 100).toFixed(1) : 0;
        
        // Since couples should always be 50/50, any imbalance indicates data quality issues
        if (Math.abs(genderRatio - 50) > 2) {
          html += `<li class="mb-2"><strong>Data Quality Alert:</strong> Gender distribution shows imbalance (${genderRatio}% male, ${(100 - genderRatio).toFixed(1)}% female). Since all participants are couples (male-female pairs), this should be exactly 50/50. The imbalance suggests data entry errors, incomplete couple profiles, or missing partner information. Review data collection processes and ensure both partners are properly recorded.</li>`;
        } else {
          html += `<li class="mb-2">Gender distribution is balanced (${genderRatio}% male, ${(100 - genderRatio).toFixed(1)}% female), confirming that couple data is properly recorded with both partners represented. This validates data quality and ensures accurate couple-based analysis.</li>`;
        }
        
        if (parseFloat(youngPercentage) > 50) {
          html += `<li class="mb-2">Young adults (18-30 years) represent the majority (${youngPercentage}%), indicating strong participation from couples in early relationship stages. These couples may benefit most from pre-marriage counseling, relationship readiness programs, communication skills training, and future planning guidance. This age group often seeks to establish strong foundations for their relationships.</li>`;
        } else if (parseFloat(middlePercentage) > 50) {
          html += `<li class="mb-2">Middle-aged adults (31-45 years) dominate (${middlePercentage}%), representing couples in established relationships. These couples may be seeking to strengthen their bond, address relationship challenges, improve communication, or navigate life transitions together. They often have more relationship experience and may benefit from advanced counseling approaches.</li>`;
        } else if (parseFloat(olderPercentage) > 30) {
          html += `<li class="mb-2">Older adults (46+ years) represent a significant portion (${olderPercentage}%), indicating participation from mature couples. These couples may be navigating later-life transitions, empty nest adjustments, retirement planning, or long-term relationship maintenance. They bring valuable life experience and may benefit from specialized programs addressing their unique needs.</li>`;
        }
        
        if (maxMaleAge >= 0 && maxFemaleAge >= 0) {
          const ageDiff = Math.abs(maxMaleAge - maxFemaleAge);
          if (ageDiff <= 1) {
            html += `<li class="mb-2">Peak age groups are similar for both genders (${data.labels[maxMaleAge]} for males, ${data.labels[maxFemaleAge]} for females), indicating that couples typically participate together when they are in similar life stages. This suggests age-appropriate program design can effectively serve both partners simultaneously.</li>`;
          } else {
            html += `<li class="mb-2">Notable age difference between peak groups: ${data.labels[maxMaleAge]} (males) vs ${data.labels[maxFemaleAge]} (females). This may indicate age gaps within couples or different participation patterns. Consider programs that address age-specific needs while maintaining couple-focused approaches. Ensure materials and activities are relevant to both age groups within couples.</li>`;
          }
        }
        
        // Calculate couple count (should be total/2 if perfectly balanced)
        const coupleCount = Math.min(totalMale, totalFemale);
        const coupleCountFromTotal = Math.round(total / 2);
        if (Math.abs(coupleCount - coupleCountFromTotal) > 2) {
          html += `<li class="mb-2"><strong>Data Consistency Check:</strong> Estimated couple count (${coupleCount} based on gender balance, ${coupleCountFromTotal} based on total) shows discrepancy. This suggests incomplete couple profiles or data entry issues. Ensure all couples have both partners fully registered.</li>`;
        } else {
          html += `<li class="mb-2">Data indicates approximately ${coupleCount} couples are represented, with both partners properly recorded. This ensures accurate couple-based program planning and resource allocation.</li>`;
        }
        
        if (total < 30) {
          html += `<li class="mb-2">Sample size is relatively small (${total} individuals, approximately ${coupleCount} couples). Consider expanding outreach to improve data representation and statistical significance. Small samples may not accurately reflect the broader population demographics.</li>`;
        } else if (total >= 100) {
          html += `<li class="mb-2">Good sample size (${total} individuals, approximately ${coupleCount} couples). Age and gender patterns are statistically significant for program planning, resource allocation, and evidence-based decision making. This data provides reliable insights for demographic targeting and program design.</li>`;
        }
      } else {
        html += `<li class="mb-2">No data available. Begin data collection to analyze age and gender distribution patterns. Ensure both partners of each couple are properly recorded to maintain data quality.</li>`;
      }
      
      html += `
              </ul>
            </div>
          </div>
        </div>
      `;
      
      // Generate recommended actions for population pyramid
      const recommendations = [];
      if (total > 0) {
        const genderRatio = (totalMale / total * 100).toFixed(1);
        const coupleCount = Math.min(totalMale, totalFemale);
        
        // Since couples should always be 50/50, any imbalance is a data quality issue
        if (Math.abs(genderRatio - 50) > 2) {
          recommendations.push(`<strong>CRITICAL DATA QUALITY ISSUE:</strong> Gender imbalance detected (${genderRatio}% male, ${(100 - genderRatio).toFixed(1)}% female). Since all participants are couples (male-female pairs), this should be exactly 50/50. <strong>Immediate actions required:</strong> (1) Review data entry processes and identify missing partner records, (2) Conduct data audit to find incomplete couple profiles, (3) Implement validation checks to ensure both partners are recorded, (4) Train staff on proper couple registration procedures, (5) Update incomplete records to include missing partner information. This imbalance compromises data accuracy and couple-based analysis.`);
        } else if (Math.abs(genderRatio - 50) <= 2) {
          recommendations.push(`Gender distribution is properly balanced (${genderRatio}% male, ${(100 - genderRatio).toFixed(1)}% female), confirming accurate couple data recording. Maintain this quality by: (1) Implementing validation checks during registration to ensure both partners are recorded, (2) Regular data audits to identify and correct incomplete couple profiles, (3) Staff training on couple registration procedures, (4) System alerts for incomplete couple records.`);
        }
        
        const ageGroups = data.labels || [];
        const youngAdults = ageGroups.slice(0, 2);
        const middleAge = ageGroups.slice(2, 5);
        const olderAdults = ageGroups.slice(5);
        
        const youngMaleTotal = youngAdults.reduce((sum, _, idx) => sum + (data.male[idx] || 0), 0);
        const youngFemaleTotal = youngAdults.reduce((sum, _, idx) => sum + (data.female[idx] || 0), 0);
        const youngTotal = youngMaleTotal + youngFemaleTotal;
        const youngPercentage = total > 0 ? (youngTotal / total * 100).toFixed(1) : 0;
        
        const middleMaleTotal = middleAge.reduce((sum, _, idx) => sum + (data.male[idx + 2] || 0), 0);
        const middleFemaleTotal = middleAge.reduce((sum, _, idx) => sum + (data.female[idx + 2] || 0), 0);
        const middleTotal = middleMaleTotal + middleFemaleTotal;
        const middlePercentage = total > 0 ? (middleTotal / total * 100).toFixed(1) : 0;
        
        const olderMaleTotal = olderAdults.reduce((sum, _, idx) => sum + (data.male[idx + 5] || 0), 0);
        const olderFemaleTotal = olderAdults.reduce((sum, _, idx) => sum + (data.female[idx + 5] || 0), 0);
        const olderTotal = olderMaleTotal + olderFemaleTotal;
        const olderPercentage = total > 0 ? (olderTotal / total * 100).toFixed(1) : 0;
        
        if (parseFloat(youngPercentage) > 50) {
          recommendations.push(`Young adults (18-30 years) represent the majority (${youngPercentage}%, approximately ${Math.round(coupleCount * parseFloat(youngPercentage) / 100)} couples). Develop age-appropriate couple programs focusing on: (1) Pre-marriage counseling and relationship readiness, (2) Communication skills and conflict resolution, (3) Financial planning and goal setting, (4) Intimacy and relationship building. Use modern communication channels (social media, mobile apps, online platforms) preferred by this age group. Consider peer-led sessions and interactive workshops. Schedule sessions at times convenient for working young adults.`);
        } else if (parseFloat(middlePercentage) > 50) {
          recommendations.push(`Middle-aged adults (31-45 years) dominate (${middlePercentage}%, approximately ${Math.round(coupleCount * parseFloat(middlePercentage) / 100)} couples). Design programs for established couples: (1) Relationship enrichment and strengthening bonds, (2) Navigating life transitions (parenthood, career changes), (3) Advanced communication and problem-solving skills, (4) Balancing family, work, and relationship priorities. These couples may benefit from evidence-based interventions, deeper counseling approaches, and programs addressing long-term relationship maintenance.`);
        } else if (parseFloat(olderPercentage) > 30) {
          recommendations.push(`Older adults (46+ years) represent a significant portion (${olderPercentage}%, approximately ${Math.round(coupleCount * parseFloat(olderPercentage) / 100)} couples). Develop specialized programs for mature couples: (1) Later-life transitions and adjustments, (2) Empty nest and retirement planning, (3) Long-term relationship maintenance and renewal, (4) Health and aging considerations. These couples bring valuable experience and may serve as mentors or peer supporters for younger couples.`);
        }
        
        if (maxMaleAge >= 0 && maxFemaleAge >= 0) {
          const ageDiff = Math.abs(maxMaleAge - maxFemaleAge);
          if (ageDiff > 1) {
            recommendations.push(`Notable age difference between peak groups: ${data.labels[maxMaleAge]} (males) vs ${data.labels[maxFemaleAge]} (females). This may indicate age gaps within couples or different participation patterns. Design couple programs that: (1) Address age-specific needs while maintaining couple focus, (2) Use materials and activities relevant to both age groups, (3) Acknowledge and work with age differences as relationship strengths, (4) Ensure both partners feel equally engaged regardless of age.`);
          } else {
            recommendations.push(`Peak age groups are similar for both genders (${data.labels[maxMaleAge]} for males, ${data.labels[maxFemaleAge]} for females), indicating couples typically participate together at similar life stages. This allows for unified program design that effectively serves both partners simultaneously. Continue developing age-appropriate content that resonates with this demographic.`);
          }
        }
        
        if (total < 30) {
          recommendations.push(`Small sample size (${total} individuals, approximately ${coupleCount} couples). Expand data collection through: (1) Enhanced outreach and community partnerships, (2) Multiple registration channels (online, in-person, mobile), (3) Incentives for couple participation, (4) Removing barriers to registration (simplified forms, assistance with data entry), (5) Follow-up on incomplete couple profiles. Aim for at least 50-100 couples for statistically significant analysis.`);
        } else if (total >= 100) {
          recommendations.push(`Good sample size (${total} individuals, approximately ${coupleCount} couples). Data patterns are statistically significant for: (1) Program planning and resource allocation, (2) Age-specific and couple-focused program design, (3) Policy development and evidence-based decision making, (4) Demographic targeting and outreach strategies. Use this data to inform strategic planning and regularly update analysis as new data becomes available.`);
        }
        
        // Data quality recommendations
        recommendations.push(`Maintain data quality by: (1) Implementing automated validation to ensure both partners are recorded for each couple, (2) Regular data audits to identify incomplete couple profiles, (3) Staff training on couple registration procedures, (4) System alerts for gender imbalances or missing partner data, (5) Periodic review of data collection processes to ensure accuracy and completeness.`);
      } else {
        recommendations.push(`No data available. Begin data collection immediately with focus on couple registration: (1) Implement systematic data collection protocols ensuring both partners are recorded, (2) Train staff on proper couple data entry procedures, (3) Create validation checks to prevent incomplete couple profiles, (4) Develop clear guidelines for couple registration, (5) Establish quality control measures to maintain data accuracy.`);
      }
      
      if (recommendations.length > 0) {
        html += `
          <div class="row mt-4">
            <div class="col-12">
              <div class="callout callout-info">
                <h5><i class="fas fa-lightbulb mr-2"></i>Recommended Actions</h5>
                <ul class="mb-0">
        `;
        recommendations.forEach(rec => {
          html += `<li class="mb-2">${rec}</li>`;
        });
        html += `
                </ul>
              </div>
            </div>
          </div>
        `;
      }
      
      // Store the analysis HTML for modal display
      storeAnalysisData('populationPyramidChart', html);
    }
  }
  function updateCivil(data){
    // Ensure all civil status categories are always shown - Gold and Blue theme
    const allCategories = ['Single', 'Living In', 'Widowed', 'Divorced', 'Separated'];
    const colors = allCategories.map((l)=>{ 
      l=(l||'').toLowerCase(); 
      if(l.includes('single'))return'rgba(0,102,204,0.7)'; // Blue
      if(l.includes('living in'))return'rgba(255,215,0,0.7)'; // Gold
      if(l.includes('widowed'))return'rgba(30,144,255,0.7)'; // Dodger Blue
      if(l.includes('divorced'))return'rgba(255,165,0,0.7)'; // Orange-Gold
      if(l.includes('separated'))return'rgba(65,105,225,0.7)'; // Royal Blue
      return'rgba(255,193,37,0.7)'; // Goldenrod
    });
    
    // Map data to all categories, defaulting to 0 for missing ones
    const values = allCategories.map(category => {
      const index = data.labels ? data.labels.indexOf(category) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    civilChart.data.labels = allCategories; 
    civilChart.data.datasets = [{label:'Current', data:values, backgroundColor:colors}]; 
    civilChart.update();
    renderLegend('civilLegend', allCategories.map((label,i)=>({text:`${label}: ${values[i]}`, color:colors[i]})));
    
    // Render analysis - pass full data object including total_couples
    renderDistributionAnalysis('civilChart', {labels: allCategories, values: values, total_couples: data.total_couples}, 'Civil Status');
  }
  function updateReligion(data){
    // Ensure all religion categories are always shown
    const colors = categoricalPalette(religionCategories.length);
    
    // Map data to all categories, defaulting to 0 for missing ones
    const values = religionCategories.map(religion => {
      const index = data.labels ? data.labels.indexOf(religion) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    religionChart.data.labels = religionCategories;
    religionChart.data.datasets = [{label:'Count', data: values, backgroundColor: colors}]; 
    religionChart.update();
    const total = values.reduce((a,b)=>a+b,0);
    const entries = religionCategories.map((label,i)=>({label, value: values[i], color: colors[i]})).sort((a,b)=>a.label.localeCompare(b.label));
    renderLegend('religionLegend', entries.map(({label,value,color})=>({text:`${label}: ${value} (${total?Math.round((value/total)*100):0}%)`, color})));
    
    // Render analysis
    renderDistributionAnalysis('religionChart', {labels: religionCategories, values: values}, 'Religion Distribution');
  }
  function updateWedding(data){
    // Ensure all wedding types are always shown (only Civil and Church as per form)
    const colors = categoricalPalette(weddingCategories.length);
    
    // Map data to all types, defaulting to 0 for missing ones
    const values = weddingCategories.map(type => {
      const index = data.labels ? data.labels.indexOf(type) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    weddingChart.data.labels = weddingCategories;
    weddingChart.data.datasets = [{data: values, backgroundColor: colors}]; 
    weddingChart.update();
    const total = values.reduce((a,b)=>a+b,0);
    const entries = weddingCategories.map((label,i)=>({label, value: values[i], color: colors[i]})).sort((a,b)=>a.label.localeCompare(b.label));
    renderLegend('weddingLegend', entries.map(({label,value,color})=>({text:`${label}: ${value} (${total?Math.round((value/total)*100):0}%)`, color})));
    
    // Render analysis
    renderDistributionAnalysis('weddingChart', {labels: weddingCategories, values: values}, 'Wedding Type Distribution');
  }
  function updatePregnancy(preg){
    // Ensure all pregnancy statuses are always shown - Gold and Blue theme
    const allStatuses = ['Pregnant', 'Not Pregnant'];
    const statusColors = ['rgba(255,215,0,0.85)', 'rgba(0,102,204,0.85)']; // Gold for Pregnant, Blue for Not Pregnant
    
    // Map data to all statuses, defaulting to 0 for missing ones
    const values = allStatuses.map(status => {
      const index = preg.status.labels ? preg.status.labels.indexOf(status) : -1;
      return index >= 0 ? (preg.status.values[index] || 0) : 0;
    });
    
    // Sort by value (smallest first) so larger segment appears on the right
    // Chart starts at 12 o'clock (rotation: 0), draws clockwise:
    // - First segment (smaller) starts at 12 o'clock and goes clockwise (right side)
    // - Second segment (larger) continues from first, taking more space (right side)
    const sortedData = allStatuses.map((status, i) => ({
      label: status,
      value: values[i],
      color: statusColors[i]
    })).sort((a, b) => a.value - b.value); // Sort ascending: smallest first, largest second (larger on right)
    
    // Reorder labels, values, and colors based on sorted data
    const sortedLabels = sortedData.map(d => d.label);
    const sortedValues = sortedData.map(d => d.value);
    const sortedColors = sortedData.map(d => d.color);
    
    pregnancyStatusChart.data.labels = sortedLabels;
    pregnancyStatusChart.data.datasets = [{data: sortedValues, backgroundColor: sortedColors}];
    pregnancyStatusChart.update();
    const total = sortedValues.reduce((a,b)=>a+b,0);
    renderLegend('pregnancyStatusLegend', sortedLabels.map((l,i)=>({text:`${l}: ${sortedValues[i]} (${total?Math.round(sortedValues[i]/total*100):0}%)`, color:sortedColors[i]})));
    
    // Render analysis - use original order for analysis
    renderPregnancyAnalysis('pregnancyStatusChart', {labels: allStatuses, values: values});
  }
  
  function updatePhilhealth(data){
    // Ensure all PhilHealth statuses are always shown
    const allStatuses = ['Yes', 'No'];
    const colors = ['rgba(255,215,0,0.8)', 'rgba(0,102,204,0.8)']; // Gold for Yes, Blue for No
    
    // Map data to all statuses, defaulting to 0 for missing ones
    const values = allStatuses.map(status => {
      const index = data.labels ? data.labels.indexOf(status) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    // Sort by value (smallest first) so larger segment appears on the right
    // Chart starts at 12 o'clock (rotation: 0), draws clockwise:
    // - First segment (smaller) starts at 12 o'clock and goes clockwise (right side)
    // - Second segment (larger) continues from first, taking more space (right side)
    const sortedData = allStatuses.map((status, i) => ({
      label: status,
      value: values[i],
      color: colors[i]
    })).sort((a, b) => a.value - b.value); // Sort ascending: smallest first, largest second (larger on right)
    
    // Reorder labels, values, and colors based on sorted data
    const sortedLabels = sortedData.map(d => d.label);
    const sortedValues = sortedData.map(d => d.value);
    const sortedColors = sortedData.map(d => d.color);
    
    philhealthChart.data.labels = sortedLabels;
    philhealthChart.data.datasets = [{data: sortedValues, backgroundColor: sortedColors}];
    philhealthChart.update();
    const total = sortedValues.reduce((a,b)=>a+b,0);
    renderLegend('philhealthLegend', sortedLabels.map((l,i)=>({text:`${l}: ${sortedValues[i]} (${total?Math.round(sortedValues[i]/total*100):0}%)`, color:sortedColors[i]})));
    
    // Render analysis - use original order for analysis
    renderPhilHealthAnalysis('philhealthChart', {labels: allStatuses, values: values});
  }
  function updateSimpleBar(chart, legendId, data, colors, allCategories = null){
    // Use predefined categories if provided, otherwise use data labels
    const labels = allCategories || data.labels;
    const cols = colors && colors.length ? colors : categoricalPalette(labels.length);
    
    // Map data to all categories, defaulting to 0 for missing ones
    const values = labels.map(category => {
      const index = data.labels ? data.labels.indexOf(category) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    chart.data.labels = labels;
    chart.data.datasets = [{ label:'Count', data: values, backgroundColor: cols }];

    // Dynamic Y-axis tick sizing to avoid full-height bars for small counts
    try {
      const maxVal = Math.max.apply(null, values.map(v => Number(v) || 0));
      const step = maxVal <= 5 ? 1 : Math.ceil(maxVal / 5);
      const suggested = maxVal <= 3 ? 3 : maxVal + Math.ceil(maxVal * 0.2);
      if (chart.options && chart.options.scales && chart.options.scales.y && chart.options.scales.y.ticks) {
        chart.options.scales.y.ticks.stepSize = step;
        chart.options.scales.y.beginAtZero = true;
        chart.options.scales.y.suggestedMax = suggested;
      }
    } catch (e) { /* no-op */ }

    chart.update();
    renderLegend(legendId, labels.map((l,i)=>({text:`${l}: ${values[i]}`, color:cols[i]})));
    // Removed clampLegend call - show more/less functionality removed
    
    // Render analysis for charts that use updateSimpleBar
    if (legendId === 'educationLegend') {
      renderEducationAnalysis('educationChart', {labels: labels, values: values});
    } else if (legendId === 'employmentLegend') {
      renderEmploymentAnalysis('employmentChart', {labels: labels, values: values});
    } else if (legendId === 'incomeLegend') {
      renderIncomeAnalysis('incomeChart', {labels: labels, values: values});
    } else if (legendId === 'topBarangaysLegend') {
      renderTopBarangaysAnalysis('topBarangaysChart', {labels: labels, values: values});
    }
  }
  function updateAttendance(data){
    // Ensure all attendance statuses are always shown - Gold and Blue theme
    const allStatuses = ['Present', 'Absent'];
    const colors = ['rgba(255,215,0,0.8)', 'rgba(0,102,204,0.8)']; // Gold for Present, Blue for Absent
    
    // Map data to all statuses, defaulting to 0 for missing ones
    const values = allStatuses.map(status => {
      const index = data.labels ? data.labels.indexOf(status) : -1;
      return index >= 0 ? (data.values[index] || 0) : 0;
    });
    
    // Sort by value (smallest first) so larger segment appears on the right
    // Chart starts at 12 o'clock (rotation: 0), draws clockwise:
    // - First segment (smaller) starts at 12 o'clock and goes clockwise (right side)
    // - Second segment (larger) continues from first, taking more space (right side)
    const sortedData = allStatuses.map((status, i) => ({
      label: status,
      value: values[i],
      color: colors[i]
    })).sort((a, b) => a.value - b.value); // Sort ascending: smallest first, largest second (larger on right)
    
    // Reorder labels, values, and colors based on sorted data
    const sortedLabels = sortedData.map(d => d.label);
    const sortedValues = sortedData.map(d => d.value);
    const sortedColors = sortedData.map(d => d.color);
    
    attendanceChart.data.labels = sortedLabels; 
    attendanceChart.data.datasets = [{data: sortedValues, backgroundColor: sortedColors}]; 
    attendanceChart.update();
    const total = sortedValues.reduce((a,b)=>a+b,0);
    renderLegend('attendanceLegend', sortedLabels.map((l,i)=>({text:`${l}: ${sortedValues[i]} (${total?Math.round(sortedValues[i]/total*100):0}%)`, color:sortedColors[i]})));
    
    // Render analysis - use original order for analysis
    renderAttendanceAnalysis('attendanceChart', {labels: allStatuses, values: values});
  }
  function updateSimpleYBar(chart, legendId, labels, values, colors){
    const cols = colors && colors.length ? colors : categoricalPalette(labels.length);
    chart.data.labels = labels; chart.data.datasets[0].data = values; chart.data.datasets[0].backgroundColor = cols;
    // ensure space for legend
    if (chart.canvas && chart.canvas.parentElement) {
      chart.canvas.parentElement.style.paddingBottom = '8px';
    }
    chart.update();
    renderLegend(legendId, labels.map((l,i)=>({rank:i+1, text:`${l}: ${values[i]}`, color:cols[i]})));
  }

  function updateFpMethods(data){
    // Use separate labels for male and female methods
    const maleLabels = data.male_labels || [];
    const maleData = data.male || [];
    const femaleLabels = data.female_labels || [];
    const femaleData = data.female || [];
    
    // Update male chart with male methods only
    fpMethodsMaleChart.data.labels = maleLabels;
    fpMethodsMaleChart.data.datasets[0].data = maleData;
    fpMethodsMaleChart.update();
    renderLegend('fpMethodsMaleLegend', maleLabels.map((l,i)=>({text:`${l}: ${maleData[i]||0}`, color:'rgba(0,102,204,0.7)'}))); // Blue
    
    // Render analysis for male FP methods
    renderFPMethodsAnalysis('fpMethodsMaleChart', {labels: maleLabels, values: maleData});
    
    // Update female chart with female methods only
    fpMethodsFemaleChart.data.labels = femaleLabels;
    fpMethodsFemaleChart.data.datasets[0].data = femaleData;
    fpMethodsFemaleChart.update();
    renderLegend('fpMethodsFemaleLegend', femaleLabels.map((l,i)=>({text:`${l}: ${femaleData[i]||0}`, color:'rgba(255,215,0,0.7)'}))); // Gold
    
    // Render analysis for female FP methods
    renderFPMethodsAnalysis('fpMethodsFemaleChart', {labels: femaleLabels, values: femaleData});
  }

  function updateFpIntent(data){
    // Ensure all FP intention statuses are always shown
    const labels = ['Yes', 'No'];
    const maleData = [0, 0];
    const femaleData = [0, 0];
    
    // Map data to all statuses, defaulting to 0 for missing ones
    if (data.labels && data.male && data.female) {
      labels.forEach((label, i) => {
        const index = data.labels.indexOf(label);
        if (index >= 0) {
          maleData[i] = data.male[index] || 0;
          femaleData[i] = data.female[index] || 0;
        }
      });
    }
    
    // Update male chart
    fpIntentMaleChart.data.labels = labels;
    fpIntentMaleChart.data.datasets[0].data = maleData;
    fpIntentMaleChart.update();
    renderLegend('fpIntentMaleLegend', labels.map((l,i)=>({text:`${l}: ${maleData[i]||0}`, color:'rgba(0,102,204,0.7)'}))); // Blue
    
    // Render analysis for male FP intent
    renderFPIntentAnalysis('fpIntentMaleChart', {labels: labels, values: maleData});
    
    // Update female chart
    fpIntentFemaleChart.data.labels = labels;
    fpIntentFemaleChart.data.datasets[0].data = femaleData;
    fpIntentFemaleChart.update();
    renderLegend('fpIntentFemaleLegend', labels.map((l,i)=>({text:`${l}: ${femaleData[i]||0}`, color:'rgba(255,215,0,0.7)'}))); // Gold
    
    // Render analysis for female FP intent
    renderFPIntentAnalysis('fpIntentFemaleChart', {labels: labels, values: femaleData});
  }
  

          // Initial data load removed - using fetchStatisticsData('present_week') instead (called at line 1772)
    

    
    // Initialize with demographic section visible
    $('#demographic-section').show();
    
    
     
    // Time Range Controls Functionality
    $('.time-range-buttons button[data-range]').on('click', function() {
      const range = $(this).data('range');
      
      // Update active button - match export button style
      $('.time-range-buttons button[data-range]').removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
      $(this).removeClass('btn-outline-secondary').addClass('btn-outline-primary active');
      
      // Fetch data for the selected range
      fetchStatisticsData(range);
    });
     
     // Simple cache and debounce utilities
     const _statsCache = new Map();
     function _cacheKey(range, barangay){ return `${range}|${barangay||'all'}`; }
     function _debounce(fn, wait){ let t; return function(){ const ctx=this, args=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), wait); }; }

    // All charts start hidden by default - no default visible charts
    // Set all chart toggle buttons to inactive state
    $('.chart-toggle-btn').removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
    updateChartLayout();

     // Barangay Filter (debounced)
     const _debouncedFetch = _debounce(function(range){ fetchStatisticsData(range); }, 300);
    $('#barangayFilter').on('change', function() {
      const currentRange = $('.time-range-buttons button.btn-outline-primary.active').data('range') || 'present_week';
      _debouncedFetch(currentRange);
    });
    
    // Function to fetch statistics data
    function fetchStatisticsData(range = 'present_week', startDate = null, endDate = null) {
      const barangay = $('#barangayFilter').val();
      const data = {
        range: range,
        barangay: barangay
      };
      
      if (startDate && endDate) {
        data.start_date = startDate;
        data.end_date = endDate;
      }
      
      const _key = _cacheKey(range, barangay);
      if (_statsCache.has(_key)) { _renderAll(_statsCache.get(_key)); return; }
      
      // Show loading spinners for all charts
      $('.chart-loading').show();
      
      $.ajax({
        url: '../includes/fetch_statistics.php',
        method: 'POST',
        data: data,
        dataType: 'json'
      })
      .done(function(response) {
        if (!response || typeof response !== 'object') {
          console.error('Invalid response received:', response);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Invalid data received from server',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
          });
          return;
        }
        _statsCache.set(_key, response);
        _renderAll(response);
        
        // Hide loading spinners after data is rendered
        $('.chart-loading').hide();
      })
      .fail(function(xhr, status, error) {
        console.error('AJAX Error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
        let errorMessage = 'Failed to load statistics data';
        if (xhr.responseText) {
          try {
            const errorData = JSON.parse(xhr.responseText);
            errorMessage = errorData.error || errorData.message || errorMessage;
          } catch(e) {
            if (xhr.responseText.length < 200) {
              errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
            }
          }
        }
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage,
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 5000
        });
        
        // Hide loading spinners on error
        $('.chart-loading').hide();
      });

      function _renderAll(response){
        if (!response || typeof response !== 'object') {
          console.error('Invalid response in _renderAll:', response);
          return;
        }
        
        // Update all charts with new data (with null safety)
        updatePyramid(response.population || {male:[],female:[],labels:[]});
        updateCivil(response.civil || {labels:[],values:[]});
        updateReligion(response.religion || {labels:[],values:[]});
        updateWedding(response.wedding || {labels:[],values:[]});
        updatePregnancy(response.pregnancy || {labels:[],values:[]});
        updatePhilhealth(response.philhealth || {labels:['Yes','No'],values:[0,0]});
        updateSimpleBar(educationChart, 'educationLegend', response.education || {labels:[],values:[]}, categoricalPalette(educationCategories.length), educationCategories);
        updateSimpleBar(employmentChart, 'employmentLegend', response.employment || {labels:[],values:[]}, categoricalPalette(employmentCategories.length), employmentCategories);
        
        // Gold and Blue income color map
        const incomeColorMap = {
          '5000 below': 'rgba(0,0,128,0.85)',      // Navy Blue (lowest)
          '5999-9999': 'rgba(0,0,205,0.85)',       // Medium Blue
          '10000-14999': 'rgba(0,102,204,0.85)',   // Blue
          '15000-19999': 'rgba(30,144,255,0.85)',  // Dodger Blue
          '20000-24999': 'rgba(255,215,0,0.85)',   // Gold
          '25000 above': 'rgba(255,165,0,0.85)'    // Orange-Gold (highest)
        };
        const incomeColors = incomeCategories.map(l => incomeColorMap[l] || 'rgba(0,102,204,0.8)'); // Default to blue
        updateSimpleBar(incomeChart, 'incomeLegend', response.income || {labels:[],values:[]}, incomeColors, incomeCategories);
        updateAttendance(response.attendance || {labels:[],values:[]});
        updateFpMethods(response.fp_methods || {male_labels:[],male:[],female_labels:[],female:[]});
        updateFpIntent(response.fp_intent || {labels:['Yes','No'],male:[0,0],female:[0,0]});
        updateSimpleBar(topBarangaysChart, 'topBarangaysLegend', response.top_barangays || {labels:[],values:[]}, categoricalPalette((response.top_barangays?.labels || []).length || 0));
        
        marriageSeasonalityChart.data.labels = (response.marriage_seasonality?.labels || []);
        marriageSeasonalityChart.data.datasets[0].data = (response.marriage_seasonality?.values || []);
        marriageSeasonalityChart.update();
        renderLegend('marriageSeasonalityLegend', (response.marriage_seasonality?.labels || []).map((l,i) => ({
          text: `${l}: ${(response.marriage_seasonality?.values || [])[i] || 0}`,
          color: generateGradient(12, 45, 210)[i] // Gold to Blue gradient
        })));
        
        // Render analysis for marriage seasonality
        renderMarriageSeasonalityAnalysis('marriageSeasonalityChart', {
          labels: response.marriage_seasonality?.labels || [],
          values: response.marriage_seasonality?.values || []
        });
        
        sessionsMonthlyChart.data.labels = (response.sessions_monthly?.labels || []);
        sessionsMonthlyChart.data.datasets[0].data = (response.sessions_monthly?.values || []);
        sessionsMonthlyChart.update();
        renderLegend('sessionsMonthlyLegend', (response.sessions_monthly?.labels || []).map((l,i) => ({
          text: `${l}: ${(response.sessions_monthly?.values || [])[i] || 0}`,
          color: HSL(210 + (i * 30) % 360, 70, 55, 0.8)
        })));
        
        // Render analysis for sessions monthly
        renderSessionsMonthlyAnalysis('sessionsMonthlyChart', {
          labels: response.sessions_monthly?.labels || [],
          values: response.sessions_monthly?.values || []
        });
      }
    }
    // Expose to global scope so external handlers (outside this closure) can call it
    if (typeof window !== 'undefined') { window.fetchStatisticsData = fetchStatisticsData; }
    
    // Export Chart Functions
    window.exportChart = function(chartId, title, format = 'pdf') {
      const canvas = document.getElementById(chartId);
      if (format === 'pdf') {
        // PDF export logic
        const link = document.createElement('a');
        link.download = title + '.png';
        link.href = canvas.toDataURL();
        link.click();
      } else {
        // Excel export logic
        alert('Excel export functionality will be implemented here');
      }
    };
    
    window.printChart = function(chartId, title) {
      const canvas = document.getElementById(chartId);
      const chart = Chart.getChart(chartId);
      if (chart) { try { chart.resize(); chart.update(); } catch(e){} }
      const iframe = document.createElement('iframe');
      iframe.style.position = 'fixed';
      iframe.style.right = '0';
      iframe.style.bottom = '0';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = '0';
      document.body.appendChild(iframe);
      const printDoc = iframe.contentDocument || iframe.contentWindow.document;
      printDoc.open();
      printDoc.write(`
        <html>
          <head>
            <title>${title}</title>
            <style>
              @page { size: A4; margin: 12mm; }
              body{font-family:Arial,sans-serif;text-align:center;padding:20px;}
              .chart-title{font-size:1.4rem;margin-bottom:12px;color:#007bff;}
              .chart-container{margin:10px 0; page-break-inside: avoid;}
              .chart-container img{max-width:100%; height:auto; max-height:420px;}
              .legend{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:8px; page-break-inside: avoid;}
              .legend-item{display:flex;align-items:center;margin:3px 6px; font-size:12px;}
              .legend-age-grid{display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:6px; justify-items:center;}
              @media print {
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
              }
            </style>
          </head>
          <body>
            <div class="chart-title">${title}</div>
            <div class="chart-container"></div>
            <div class="legend" id="print-legend"></div>
          </body>
        </html>
      `);
      printDoc.close();
      function buildLegend(){
        const dst = printDoc.getElementById('print-legend');
        if (!chart || !dst) return;
        const datasets = chart.data.datasets || [];
        // Special legend for population pyramid: show Male/Female with their dataset colors
        if (chartId === 'populationPyramidChart' && datasets.length >= 2) {
          const dsMale = datasets[0], dsFemale = datasets[1];
          const maleColor = Array.isArray(dsMale.backgroundColor) ? dsMale.backgroundColor[0] : dsMale.backgroundColor;
          const femaleColor = Array.isArray(dsFemale.backgroundColor) ? dsFemale.backgroundColor[0] : dsFemale.backgroundColor;
          const labels = chart.data.labels || [];
          const maleData = dsMale.data || [];
          const femaleData = dsFemale.data || [];
          const maleSwatch = `<svg width="12" height="12" style="margin-right:6px"><rect width="12" height="12" fill="${maleColor||'rgba(0,0,0,0.2)'}" stroke="rgba(0,0,0,0.2)"/></svg>`;
          const femaleSwatch = `<svg width="12" height="12" style="margin-right:6px"><rect width="12" height="12" fill="${femaleColor||'rgba(0,0,0,0.2)'}" stroke="rgba(0,0,0,0.2)"/></svg>`;
          const ageLines = labels.map((l,i)=>{
            const m = Math.abs(Number(maleData[i])||0);
            const f = Number(femaleData[i])||0;
            return `
              <div class="legend-item" style="justify-content:center;">
                ${maleSwatch}<span>${l}: ${m} male</span>
                <span style="margin:0 10px;"></span>
                ${femaleSwatch}<span>${l}: ${f} female</span>
              </div>
            `;
          }).join('');
          dst.innerHTML = `<div class="legend-age-grid">${ageLines}</div>`;
          return;
        }
        // Default legend: per-category with values and percentages
        const labels = chart.data.labels || [];
        const ds = datasets[0] || {};
        const colors = ds.backgroundColor || [];
        const values = ds.data || [];
        const total = values.reduce((a,b)=>a+(Number(b)||0),0);
        dst.innerHTML = labels.map((l,i)=>{
          const color = Array.isArray(colors) ? colors[i] : colors;
          const val = values[i] ?? 0;
          const pct = total ? Math.round((val/total)*100) : 0;
          const swatch = `<svg width="12" height="12" style="margin-right:6px"><rect width="12" height="12" fill="${color||'rgba(0,0,0,0.2)'}" stroke="rgba(0,0,0,0.2)"/></svg>`;
          return `<div class="legend-item">${swatch}<span>${l}: ${val}${total?` (${pct}%)`:''}</span></div>`;
        }).join('');
      }
      const img = new Image();
      img.onload = function(){
        const container = printDoc.querySelector('.chart-container');
        if (container) container.appendChild(img);
        buildLegend();
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(()=>{ try { document.body.removeChild(iframe); } catch(e){} }, 1000);
      };
      const kickoff = function(){
        try {
          img.src = canvas.toDataURL();
        } catch(e) {
          img.src = '';
        }
      };
      if (chart) {
        setTimeout(kickoff, chartId==='populationPyramidChart' ? 200 : 100);
      } else {
        kickoff();
      }
    };
    
    // Export CSV from current chart data
    window.exportCSVFromChart = function(chartId, baseName) {
      const chart = Chart.getChart(chartId);
      if (!chart) return;
      const labels = chart.data.labels || [];
      const datasets = chart.data.datasets || [];
      let csv = '';
      if (datasets.length <= 1) {
        csv += 'Label,Value\n';
        const data = (datasets[0] && datasets[0].data) ? datasets[0].data : [];
        labels.forEach((l,i)=>{ csv += `"${l}",${data[i] ?? 0}\n`; });
      } else {
        csv += 'Label,' + datasets.map(d=>`"${d.label||''}"`).join(',') + '\n';
        labels.forEach((l,i)=>{
          const row = [`"${l}"`].concat(datasets.map(d=>d.data[i] ?? 0));
          csv += row.join(',') + '\n';
        });
      }
      const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url; link.download = `${baseName||chartId}.csv`;
      document.body.appendChild(link); link.click(); document.body.removeChild(link);
      URL.revokeObjectURL(url);
    };

    
    // Initialize with present week data
    fetchStatisticsData('present_week');
    
    // Inline Chart Toggle Functionality
    $('.chart-toggle-btn').on('click', function() {
      const chartId = $(this).data('chart');
      const chartItem = $(`#${chartId}-item`);
      
      // Toggle active state using Bootstrap button classes
      if ($(this).hasClass('btn-outline-primary')) {
        // Currently active, make inactive
        $(this).removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
        chartItem.addClass('hidden');
      } else {
        // Currently inactive, make active
        $(this).removeClass('btn-outline-secondary').addClass('btn-outline-primary active');
        chartItem.removeClass('hidden');
        // Trigger chart resize to ensure proper rendering
        setTimeout(() => {
          const chart = Chart.getChart(chartId);
          if (chart) {
            chart.resize();
          }
        }, 100);
      }
      
      // Update layout for remaining visible charts
      updateChartLayout();
      
      // Check if no charts are visible and show/hide message
      checkNoChartsVisible();
    });
    
    // Add a message when no charts are visible
    function checkNoChartsVisible() {
      const visibleCharts = $('.chart-item:not(.hidden)');
      if (visibleCharts.length === 0) {
        // Show a message that no charts are visible
        if ($('#no-charts-message').length === 0) {
          $('.chart-content-areas').append(`
            <div class="col-12 text-center" id="no-charts-message">
              <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                No charts are currently visible. Use the chart controls above to show the charts you want to see.
              </div>
            </div>
          `);
        }
      } else {
        // Remove the no charts message if charts are visible
        $('#no-charts-message').remove();
      }
    }
    
    // Check initial state
    checkNoChartsVisible();
    
    // Force hide all charts on page load
    $('.chart-item').addClass('hidden');
    $('.chart-toggle-btn').removeClass('active');
    
    // Ensure no charts are visible initially
    setTimeout(() => {
      checkNoChartsVisible();
    }, 100);
    
    // Show All Charts Button Functionality
    $('#showAllChartsBtn').on('click', function() {
      // Show all charts
      $('.chart-item').removeClass('hidden');
      $('.chart-toggle-btn').removeClass('btn-outline-secondary').addClass('btn-outline-primary active');
      
      // Update button visibility
      $(this).hide();
      $('#hideAllChartsBtn').show();
      
      // Update chart layout
      updateChartLayout();
      
      // Remove no charts message
      $('#no-charts-message').remove();
      
      // Trigger chart resize for all charts
      setTimeout(() => {
        const chartIds = [
          'populationPyramidChart', 'civilChart', 'religionChart', 'weddingChart',
          'pregnancyStatusChart', 'philhealthChart', 'educationChart', 'employmentChart', 'incomeChart',
          'attendanceChart', 'fpMethodsMaleChart', 'fpMethodsFemaleChart', 'fpIntentMaleChart', 'fpIntentFemaleChart',
          'topBarangaysChart', 'marriageSeasonalityChart', 'sessionsMonthlyChart'
        ];
        
        chartIds.forEach(chartId => {
          const chart = Chart.getChart(chartId);
          if (chart && typeof chart.resize === 'function') {
            chart.resize();
          }
        });
      }, 200);
    });
    
    // Hide All Charts Button Functionality
    $('#hideAllChartsBtn').on('click', function() {
      // Hide all charts
      $('.chart-item').addClass('hidden');
      $('.chart-toggle-btn').removeClass('btn-outline-primary active').addClass('btn-outline-secondary');
      
      // Update button visibility
      $(this).hide();
      $('#showAllChartsBtn').show();
      
      // Show no charts message
      checkNoChartsVisible();
    });
    
    // Function to update chart layout when charts are hidden/shown
    function updateChartLayout() {
      $('.chart-section:visible').each(function() {
        const section = $(this);
        const visibleCharts = section.find('.chart-item:not(.hidden)');
        
        // Adjust column classes based on visible charts
        visibleCharts.each(function(index) {
          const chartItem = $(this);
          if (visibleCharts.length === 1) {
            // Single chart - make it full width
            chartItem.removeClass('col-lg-6 col-md-12').addClass('col-12');
            chartItem.addClass('single-full');
          } else if (visibleCharts.length === 2) {
            // Two charts - side by side
            chartItem.removeClass('col-12 single-full').addClass('col-lg-6 col-md-12');
          }
        });
      });
      // If only one chart across the whole page, ensure it resizes (fixes canvas height glitches)
      setTimeout(() => {
        const onlyChart = $('.chart-item:not(.hidden)').first();
        if (onlyChart.length === 1) {
          const canvasEl = onlyChart.find('canvas')[0];
          if (canvasEl) {
            const chart = Chart.getChart(canvasEl.id);
            if (chart) chart.resize();
          }
        }
      }, 150);
    }
    
    // Initialize chart layout
    updateChartLayout();

    // Smooth section nav highlighting
    const sectionIds = ['demographic-section','religion-wedding-section','health-section','education-employment-section','financial-section','geographic-section','sessions-section'];
    const links = document.querySelectorAll('.section-link');
    const observer = new IntersectionObserver((entries)=>{
      entries.forEach(entry=>{
        if (entry.isIntersecting) {
          links.forEach(a=>a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id));
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });
    sectionIds.forEach(id=>{ const el=document.getElementById(id); if (el) observer.observe(el); });

    // Reveal charts on scroll
    const chartObserver = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); } });
    }, { threshold: 0.1 });
    document.querySelectorAll('.chart-item').forEach(ci=> chartObserver.observe(ci));
  });

  // Toggle handlers for compare
  (function(){
    // Compare button removed - functionality no longer needed
    window.__compareMode = false;
  })();

  // Legend clamp helper removed - show more/less functionality disabled
  // Remove any existing show more/less buttons
  $(document).ready(function() {
    $('.legend-toggle').remove();
    $('.legend-clamp').removeClass('legend-clamp').css('max-height', 'none');
  });
</script>
</body>
</html>



