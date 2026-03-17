<?php
$position = $_SESSION['position'] ?? '';
// Get current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
// Debug: Uncomment the line below to see what position is being used
// echo "<!-- DEBUG: Position = " . $position . " -->";
// echo "<!-- DEBUG: Current Page = " . $current_page . " -->";
?>
<?php
// Load image helper for secure image paths
require_once __DIR__ . '/image_helper.php';
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="../admin/admin_dashboard.php" class="brand-link">
    <img src="<?= getSecureImagePath('../images/bcpdo.png') ?>" alt="BCPDO Logo" class="brand-image img-circle elevation-3">
    <span class="brand-text font-weight-light">BCPDO System</span>
  </a>

  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
        <?php if ($position === 'counselor'): ?>
          <!-- Counselor Sidebar -->
          <li class="nav-item">
            <a href="../counselor/counselor_dashboard.php" class="nav-link <?php echo ($current_page === 'counselor_dashboard.php' && $current_dir === 'counselor') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-user-tie"></i>
              <p>Counselor Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../counselor/completed_sessions.php" class="nav-link <?php echo ($current_page === 'completed_sessions.php' && $current_dir === 'counselor') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-check-circle"></i>
              <p>Completed Sessions</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../couple_list/couple_list.php" class="nav-link <?php echo ($current_page === 'couple_list.php' && $current_dir === 'couple_list') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-user-friends"></i>
              <p>Couples List</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../certificates/certificates.php" class="nav-link <?php echo ($current_page === 'certificates.php' && $current_dir === 'certificates') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-certificate"></i>
              <p>Certificates</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../pmoc_ml_vercel/ml_dashboard.php" class="nav-link <?php echo ($current_page === 'ml_dashboard.php' && $current_dir === 'ml_model') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-brain"></i>
              <p>ML Recommendations</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../reports/reports.php" class="nav-link <?php echo ($current_page === 'reports.php' && $current_dir === 'reports') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-file-alt"></i>
              <p>Reports</p>
            </a>
          </li>
        <?php else: ?>
          <!-- Admin / Superadmin Sidebar -->
          <li class="nav-item">
            <a href="../admin/admin_dashboard.php" class="nav-link <?php echo ($current_page === 'admin_dashboard.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../couple_list/couple_list.php" class="nav-link <?php echo ($current_page === 'couple_list.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-user-friends"></i>
              <p>Couples List</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../admin/access_codes.php" class="nav-link <?php echo ($current_page === 'access_codes.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-key"></i>
              <p>Access Codes</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../couple_scheduling/couple_scheduling.php" class="nav-link <?php echo ($current_page === 'couple_scheduling.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-calendar-alt"></i>
              <p>Couple Schedule</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../certificates/certificates.php" class="nav-link <?php echo ($current_page === 'certificates.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-certificate"></i>
              <p>Certificates</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../question_assessment/question_assessment.php" class="nav-link <?php echo ($current_page === 'question_assessment.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-poll"></i>
              <p>Question Assessment</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../question_category/question_category.php" class="nav-link <?php echo ($current_page === 'question_category.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-tags"></i>
              <p>Category</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../statistics/statistics.php" class="nav-link <?php echo ($current_page === 'statistics.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-chart-bar"></i>
              <p>Statistics</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../reports/reports.php" class="nav-link <?php echo ($current_page === 'reports.php' && $current_dir === 'reports') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-file-alt"></i>
              <p>Reports</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="../pmoc_ml_vercel/ml_dashboard.php" class="nav-link <?php echo ($current_page === 'ml_dashboard.php' && $current_dir === 'ml_model') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-brain"></i>
              <p>ML Recommendations</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="../admin/sms_logs.php" class="nav-link <?php echo ($current_page === 'sms_logs.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-envelope"></i>
              <p>Email and SMS Logs</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../notifications/notifications.php" class="nav-link <?php echo ($current_page === 'notifications.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-bell"></i>
              <p>Notifications</p>
            </a>
          </li>
          <?php if ($position === 'superadmin'): ?>
          <li class="nav-item">
            <a href="../admin/database_backup.php" class="nav-link <?php echo ($current_page === 'database_backup.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-database"></i>
              <p>Database Backup</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../admin/audit_logs.php" class="nav-link <?php echo ($current_page === 'audit_logs.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-clipboard-list"></i>
              <p>Audit Logs</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="../admin/admin.php" class="nav-link <?php echo ($current_page === 'admin.php') ? 'active' : ''; ?>">
              <i class="nav-icon fas fa-user-shield"></i>
              <p>Manage Admins</p>
            </a>
          </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</aside>