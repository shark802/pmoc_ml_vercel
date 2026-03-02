<?php 
$defaultImage = '../images/profiles/default.jpg';
$image = !empty($_SESSION['image']) ? htmlspecialchars($_SESSION['image']) : $defaultImage;
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'User');
$position = htmlspecialchars($_SESSION['position'] ?? 'Administrator');
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light sticky-top">
  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="../admin/admin_dashboard.php" class="nav-link">
        <i class="fas fa-home mr-1"></i>Home
      </a>
    </li>
  </ul>

  <!-- Right navbar links -->
  <ul class="navbar-nav ml-auto align-items-center">
    <!-- Dark Mode Toggle -->
    <li class="nav-item">
      <a class="nav-link" href="#" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
        <i class="fas fa-moon" id="themeToggleIcon"></i>
      </a>
    </li>
    <!-- Notification Bell (hidden for counselor) -->
    <?php if (strtolower($position) !== 'counselor'): ?>
    <li class="nav-item dropdown">
      <a class="nav-link d-flex align-items-center dropdown-toggle" href="#" role="button" data-toggle="dropdown" data-display="static" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span class="notification-badge" id="notificationCount" style="display:none;"></span>
      </a>
      <div class="dropdown-menu dropdown-menu-right notification-dropdown">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
          <h5 class="dropdown-title mb-0">Notifications</h5>
          <div class="dropdown-menu-toggle">
            <button type="button" class="btn btn-sm" id="meatballsBtn" aria-expanded="false">
              <i class="fas fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-right meatballs-dropdown" id="meatballsMenu" role="menu" aria-label="Notification menu">
              <a class="dropdown-item d-flex align-items-center" href="#" id="seeAllBtn" role="menuitem">
                <i class="fas fa-eye menu-icon mr-2" aria-hidden="true"></i>
                <span class="menu-label">See all</span>
                <span class="menu-kbd ml-auto">View</span>
              </a>
              <a class="dropdown-item d-flex align-items-center" href="#" id="markAllRead" role="menuitem">
                <i class="fas fa-check-double menu-icon mr-2" aria-hidden="true"></i>
                <span class="menu-label">Mark all read</span>
                <span class="menu-kbd ml-auto">Clear</span>
              </a>
              <div class="dropdown-divider my-1"></div>
              <a class="dropdown-item d-flex align-items-center" href="#" id="refreshNotifications" role="menuitem">
                <i class="fas fa-sync-alt menu-icon mr-2" aria-hidden="true"></i>
                <span class="menu-label">Refresh</span>
                <span class="menu-kbd ml-auto">Reload</span>
              </a>
              <a class="dropdown-item d-flex align-items-center" href="#" id="notificationSettings" role="menuitem">
                <i class="fas fa-cog menu-icon mr-2" aria-hidden="true"></i>
                <span class="menu-label">Settings</span>
              </a>
            </div>
          </div>
        </div>
        <div class="dropdown-filter mb-2 px-3 pt-2">
          <div class="btn-group btn-group-sm w-100" role="group">
            <button type="button" class="btn btn-outline-primary active" id="filterAll" data-filter="all">
              <i class="fas fa-list mr-1"></i>All
            </button>
            <button type="button" class="btn btn-outline-warning" id="filterUnread" data-filter="unread">
              <i class="fas fa-bell mr-1"></i>Unread
            </button>
          </div>
        </div>
        <div id="notificationList" class="notification-list">
          <!-- Notifications will be loaded here dynamically -->
          <div class="dropdown-item text-center text-muted">
            <small><i class="fas fa-spinner fa-spin mr-1"></i>Loading notifications...</small>
          </div>
        </div>
        <div class="dropdown-divider"></div>
        <div class="dropdown-footer">
          <a class="dropdown-item text-center" href="#" id="loadPreviousNotifications">
            <i class="fas fa-history mr-1"></i>See previous notifications
          </a>
        </div>
      </div>
    </li>
    <?php endif; ?>


    
    <!-- Single User Dropdown -->
    <li class="nav-item dropdown">
      <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-toggle="dropdown" aria-expanded="false">
        <img src="<?= $image ?>" class="user-image img-circle elevation-2" alt="User Image"
             onerror="this.onerror=null; this.src='<?= $defaultImage ?>';">
      </a>
      <div class="dropdown-menu dropdown-menu-right profile-dropdown">
        <!-- Profile Header -->
        <div class="dropdown-header profile-header">
          <div class="d-flex align-items-center">
            <img src="<?= $image ?>" class="profile-dropdown-image" alt="Profile Image"
                 onerror="this.onerror=null; this.src='<?= $defaultImage ?>';">
            <div class="profile-info">
              <div class="profile-name"><?= $admin_name ?></div>
              <div class="profile-role"><?= $position ?></div>
            </div>
          </div>
        </div>
        <div class="dropdown-divider"></div>
        
        <!-- Profile Actions -->
        <a class="dropdown-item profile-item" href="../admin/profile.php">
          <i class="fas fa-user-circle text-primary"></i>
          <span>My Profile</span>
        </a>
        <a class="dropdown-item profile-item" href="../admin/settings.php">
          <i class="fas fa-cog text-secondary"></i>
          <span>Settings</span>
        </a>
        
        <!-- Logout -->
        <a class="dropdown-item profile-item text-danger" href="#" id="logoutLink">
          <i class="fas fa-sign-out-alt"></i>
          <span>Sign Out</span>
        </a>
      </div>
    </li>
    <!-- Mobile menu toggle -->
    <li class="nav-item d-sm-none">
      <a class="nav-link" data-widget="navbar-search" href="#" role="button">
        <i class="fas fa-search"></i>
      </a>
    </li>
  </ul>
</nav>



<style>
.user-image {
  width: 2rem;
  height: 2rem;
  object-fit: cover;
  border: 2px solid #dee2e6;
}

.navbar-badge, .notification-badge {
  position: absolute;
  top: 6px;
  right: 2px;
  background: #dc3545;
  color: #fff;
  border-radius: 50%;
  font-size: 0.75rem;
  padding: 2px 6px;
  min-width: 18px;
  text-align: center;
  font-weight: 600;
  z-index: 1000;
}

/* Make navbar sticky - ensure proper z-index and no content overlap */
.main-header.navbar.sticky-top {
  position: -webkit-sticky;
  position: sticky;
  top: 0;
  z-index: 1031 !important; /* Higher than content to stay on top - matches header.php */
  box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Add subtle shadow when sticky */
}

/* Light mode: ensure white opaque background (always, not just when sticky) */
.main-header.navbar.navbar-white,
.main-header.navbar.navbar-white.navbar-light {
  background-color: #ffffff !important;
  background-image: none !important;
}

/* Dark mode: ensure dark opaque background (always, not just when sticky) */
body.dark-mode .main-header.navbar,
.main-header.navbar.navbar-dark,
.main-header.navbar.navbar-gray-dark {
  background-color: var(--dark, #343a40) !important;
  background-image: none !important;
}

/* Dark mode sticky navbar shadow */
body.dark-mode .main-header.navbar.sticky-top,
.main-header.navbar.sticky-top.navbar-dark {
  box-shadow: 0 2px 4px rgba(0,0,0,0.3); /* Darker shadow in dark mode */
}

/* Subtle elevation on scroll */
.main-header.navbar { transition: box-shadow 0.2s ease; }
.main-header.navbar.navbar-scrolled { box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
body.dark-mode .main-header.navbar.navbar-scrolled { box-shadow: 0 2px 12px rgba(0,0,0,0.5); }

/* Better dropdown scrolling */
.dropdown-menu {
  scrollbar-width: thin;
}

.dropdown-menu::-webkit-scrollbar {
  width: 8px;
}

.dropdown-menu::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 4px;
}

.dropdown-menu::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.dropdown-menu::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

/* Notification dropdown styles */
.notification-dropdown {
  width: 320px;
  max-height: none;
  max-width: 92vw; /* keep within viewport like a proper dropdown */
  overflow-x: hidden;
  overflow-y: visible;
  margin-top: 0.5rem;
  border: none;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  background: #ffffff;
  padding: 0;
}

/* Prevent horizontal scrollbar inside dropdown entirely */
.notification-dropdown * {
  box-sizing: border-box;
}
.notification-dropdown .notification-list {
  overflow-x: hidden;
  overflow-y: visible;
  max-width: 100%;
}
.notification-dropdown .dropdown-item { max-width: 100%; }
.notification-dropdown .dropdown-item .d-flex { flex-wrap: nowrap; }
.notification-dropdown .flex-grow-1 { min-width: 0; }
.notification-dropdown .flex-shrink-0 { flex-shrink: 0; max-width: 56px; }
.notification-dropdown .btn-group { display: flex; flex-wrap: nowrap; width: 100%; }
.notification-dropdown .btn-group .btn { flex: 1 1 0; min-width: 0; }

/* Ensure dropdown itself never scrolls internally (use page scroll only) */
.dropdown-menu.notification-dropdown {
  max-height: none !important;
  overflow-y: visible !important;
  overflow-x: hidden !important;
  right: 0;
  left: auto;
}

.notification-dropdown .dropdown-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px 12px 0 0;
  border-bottom: none;
  position: relative;
}

.notification-dropdown .dropdown-header::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
}

.notification-dropdown .dropdown-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: #ffffff;
  text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification-dropdown .dropdown-menu-toggle {
  position: relative;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 0.25rem;
  min-width: 220px;
  z-index: 1001;
  display: none;
  border: none;
  border-radius: 10px;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  background: #ffffff;
  overflow: visible;
  max-height: none;
}

/* Dark mode override for meatball dropdown */
body.dark-mode .notification-dropdown .dropdown-menu-toggle .dropdown-menu {
  background: #2d3748 !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
  overflow: visible !important;
  max-height: none !important;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-menu.show {
  display: block;
  animation: slideIn 0.3s ease-out;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-item {
  padding: 0.6rem 0.9rem;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, transform 0.06s ease;
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
  color: #2d3748;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-item:hover {
  background: #f7fafc;
  color: #4c51bf;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-item:active {
  background: #edf2f7;
}

.notification-dropdown .dropdown-menu-toggle .dropdown-item .menu-icon { width: 16px; text-align: center; opacity: .9; }
.notification-dropdown .dropdown-menu-toggle .dropdown-item .menu-label { font-weight: 500; }
.notification-dropdown .dropdown-menu-toggle .dropdown-item .menu-kbd {
  font-size: 0.7rem;
  color: #718096;
  background: #edf2f7;
  border-radius: 4px;
  padding: 2px 6px;
}

/* Dark mode override for meatball dropdown items */
body.dark-mode .notification-dropdown .dropdown-menu-toggle .dropdown-item { color: #e2e8f0 !important; border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important; }
body.dark-mode .notification-dropdown .dropdown-menu-toggle .dropdown-item:hover { background: rgba(255,255,255,0.06) !important; color: #fff !important; }
body.dark-mode .notification-dropdown .dropdown-menu-toggle .dropdown-item .menu-kbd { background: rgba(255,255,255,0.08); color: #cbd5e1; }

.notification-dropdown .dropdown-menu-toggle .dropdown-item:last-child {
  border-bottom: none;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Notification count pulse animation */
@keyframes notification-pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

.notification-pulse {
  animation: notification-pulse 0.6s ease-in-out;
}

.notification-dropdown .dropdown-menu-toggle .btn {
  padding: 6px 8px;
  border-radius: 4px;
  width: auto;
  height: auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: #ffffff;
  transition: color 0.2s ease;
  min-width: 32px;
  min-height: 32px;
  cursor: pointer;
}

.notification-dropdown .dropdown-menu-toggle .btn:hover {
  background: transparent;
  border-color: transparent;
  transform: none;
  box-shadow: none;
  color: #e2e8f0;
}

.notification-dropdown .dropdown-item {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  transition: background-color 0.15s ease-in-out;
  position: relative;
}

/* Ensure proper spacing for notification items */
.notification-dropdown #notificationList .dropdown-item {
  margin: 0;
  border-radius: 0;
}

.notification-dropdown .dropdown-item:hover {
  background-color: #f8f9fa;
}

.notification-dropdown .dropdown-item:last-child {
  border-bottom: none;
}

.notification-dropdown .notification-content {
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
  max-width: 100%;
  overflow: hidden;
  line-height: 1.5;
  color: #2d3748;
  font-size: 0.9rem;
}

.notification-dropdown .notification-item {
  max-width: 100%;
}

.notification-dropdown .flex-grow-1 {
  min-width: 0;
  flex: 1;
}

.notification-dropdown .flex-shrink-0 {
  flex-shrink: 0;
}

.notification-dropdown .badge {
  font-size: 0.7rem;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-weight: 600;
}

.notification-dropdown .text-muted {
  color: #718096 !important;
  font-size: 0.8rem;
  font-weight: 500;
}

.notification-dropdown .fa-lg {
  color: #667eea;
  opacity: 0.8;
}

.notification-dropdown .dropdown-item:last-child {
  border-bottom: none;
}

.notification-dropdown .text-center {
  text-align: center;
}

/* Notification bell positioning */
.nav-item.dropdown .fa-bell {
  font-size: 1.1rem;
  position: relative;
}

.nav-item.dropdown .nav-link {
  position: relative;
}

/* Align notification bell and profile dropdown */
.navbar-nav .nav-item {
  display: flex;
  align-items: center;
}

.navbar-nav .nav-link {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0.5rem 0.75rem;
}

/* Ensure dropdowns work properly */
.dropdown-toggle::after {
  display: none;
}

/* Fix dropdown positioning */
.dropdown-menu-right {
  right: 0;
  left: auto;
}

/* Profile dropdown styles */
.profile-dropdown {
  width: 280px;
  padding: 0;
}

.profile-header {
  padding: 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 0.375rem 0.375rem 0 0;
}

.profile-dropdown-image {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.3);
  margin-right: 0.75rem;
}

.profile-info {
  flex: 1;
}

.profile-name {
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 0.25rem;
}

.profile-role {
  font-size: 0.875rem;
  opacity: 0.9;
}

.profile-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  transition: background-color 0.15s ease-in-out;
}

.profile-item:hover {
  background-color: #f8f9fa;
}

.profile-item i {
  margin-right: 0.75rem;
  width: 1rem;
  text-align: center;
}

.profile-item.text-danger:hover {
  background-color: #f8d7da;
}

/* Loading and error states */
.notification-dropdown .text-muted small {
  font-style: italic;
  color: #6c757d;
}

/* Ensure placeholder rows (loading/empty/error) match item sizing */
.notification-dropdown #notificationList .dropdown-item.text-center {
  padding: 0.75rem 1rem;
  min-height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.notification-dropdown #notificationList .dropdown-item.text-center small {
  font-size: 0.9rem;
}
.notification-dropdown #notificationList .dropdown-item.text-danger,
.notification-dropdown #notificationList .dropdown-item.text-muted {
  padding: 0.75rem 1rem;
  min-height: 52px;
}

.notification-dropdown .text-danger small {
  color: #dc3545;
  font-weight: 500;
}

.notification-dropdown .text-info small {
  color: #17a2b8;
  font-weight: 500;
}

/* Loading animation */
@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}

.notification-dropdown .text-muted small:contains("Loading") {
  color: #007bff;
  animation: pulse 1.5s ease-in-out infinite;
}

/* Button loading states */
#markAllRead:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Notification badges */
.badge-sm {
  font-size: 0.7rem;
  padding: 0.25rem 0.5rem;
}

.badge-primary {
  background-color: #007bff;
}

.badge-success {
  background-color: #28a745;
}

.badge-info {
  background-color: #17a2b8;
}

.badge-warning {
  background-color: #ffc107;
  color: #212529;
}

.badge-danger {
  background-color: #dc3545;
}

/* Notification item improvements */
.notification-item {
  transition: background-color 0.15s ease-in-out;
  border-bottom: 1px solid #f8f9fa;
  padding: 0.75rem 1rem;
}

.notification-item:hover {
  background-color: #f8f9fa;
  text-decoration: none;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item .flex-shrink-0 i {
  font-size: 1.1rem;
  width: 1.5rem;
  text-align: center;
}

.notification-item .flex-grow-1 {
  min-width: 0;
}

.notification-item .text-muted {
  font-size: 0.75rem;
  line-height: 1.2;
}

.notification-item .badge {
  text-transform: capitalize;
  font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .notification-dropdown {
    width: 280px;
    right: -100px;
  }
  
  .profile-dropdown {
    width: 250px;
    right: -50px;
  }
}

/* Notification detail modal styles */
.notification-detail .notification-content {
  border-left: 4px solid #007bff;
  background-color: #f8f9fa !important;
}

.notification-detail .fa-2x {
  width: 3rem;
  text-align: center;
}

.notification-detail .flex-shrink-0 {
  flex-shrink: 0;
}

/* Modal improvements */
#notificationDetailModal .modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-bottom: none;
}

#notificationDetailModal .modal-header .close {
  color: white;
  opacity: 0.8;
}

#notificationDetailModal .modal-header .close:hover {
  opacity: 1;
}

#notificationDetailModal .modal-footer {
  border-top: 1px solid #dee2e6;
  background-color: #f8f9fa;
}

/* Button improvements */
#markAsReadBtn {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  border: none;
  transition: all 0.3s ease;
}

#markAsReadBtn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Responsive modal */
@media (max-width: 768px) {
  #notificationDetailModal .modal-dialog {
    margin: 1rem;
    max-width: calc(100% - 2rem);
  }
}

/* Enhanced Notification Styles */
.notification-dropdown {
  width: 350px;
  max-height: 400px;
  overflow-y: auto;
}

.notification-list {
  max-height: 300px;
  overflow-y: auto;
  width: 100%;
}

.notification-item {
  cursor: pointer;
  transition: all 0.2s ease;
  border-bottom: 1px solid #f8f9fa;
  padding: 12px 15px;
}

.notification-item:hover {
  background-color: #f8f9fa;
  transform: translateX(2px);
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-content {
  font-size: 0.9rem;
  line-height: 1.4;
  color: #495057;
}

.mark-read-btn {
  opacity: 0.6;
  transition: all 0.2s ease;
}

.notification-item:hover .mark-read-btn {
  opacity: 1;
}

.mark-read-btn:hover {
  transform: scale(1.1);
}

/* Notification badge pulse animation */
.notification-badge.notification-pulse {
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

/* Dropdown header improvements */
.notification-dropdown .dropdown-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 8px 8px 0 0;
  margin: -1px -1px 0 -1px;
}

.notification-dropdown .dropdown-header .btn {
  color: white;
  border-color: rgba(255,255,255,0.3);
}

.notification-dropdown .dropdown-header .btn:hover {
  background-color: rgba(255,255,255,0.1);
  border-color: rgba(255,255,255,0.5);
}

/* Notification filters below the bell */
.notification-filters {
  margin-left: 0.5rem;
  margin-top: 0.25rem;
}

.notification-filters .btn-group {
  gap: 0.5rem;
}

.notification-filters .btn {
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.75rem;
  padding: 0.4rem 0.6rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 2px solid transparent;
  background: rgba(255, 255, 255, 0.9);
  color: #5a67d8;
  position: relative;
  overflow: hidden;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.notification-filters .btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
  transition: left 0.5s ease;
}

.notification-filters .btn:hover {
  background: rgba(255, 255, 255, 1);
  border-color: rgba(102, 126, 234, 0.4);
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
  color: #4c51bf;
}

.notification-filters .btn:hover::before {
  left: 100%;
}

.notification-filters .btn.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: transparent;
  color: white;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
  transform: translateY(-1px);
  position: relative;
}

.notification-filters .btn.active::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 50%;
  transform: translateX(-50%);
  width: 60%;
  height: 2px;
  background: linear-gradient(90deg, #ffd700 0%, #ffed4e 100%);
  border-radius: 1px;
  box-shadow: 0 1px 4px rgba(255, 215, 0, 0.4);
}

/* Unread notification styling */
.notification-item.unread-notification {
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.08) 0%, rgba(255, 193, 7, 0.12) 100%);
  border-left: 4px solid #ffc107;
  position: relative;
}

.notification-item.unread-notification::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, rgba(255, 193, 7, 0.3) 50%, transparent 100%);
}

.notification-item.unread-notification:hover {
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.12) 0%, rgba(255, 193, 7, 0.18) 100%);
  border-left: 4px solid #ffa000;
}

/* Dropdown footer */
.notification-dropdown .dropdown-footer {
  background-color: #f8f9fa;
  border-radius: 0 0 8px 8px;
  margin: 0;
  border-top: 1px solid rgba(0,0,0,0.05);
}

.notification-dropdown .dropdown-footer .dropdown-item {
  text-align: center;
  padding: 0.75rem 1rem;
  color: #667eea;
  font-weight: 500;
  transition: background-color 0.15s ease-in-out;
}

.notification-dropdown .dropdown-footer .dropdown-item:hover {
  background-color: #e9ecef;
}

/* Enhanced Modal Focus Styles */
#notificationDetailModal.modal-focused {
  outline: 3px solid #007bff;
  outline-offset: 3px;
  box-shadow: 0 0 20px rgba(0, 123, 255, 0.3);
}

#notificationDetailModal .modal-title:focus {
  outline: 3px solid #007bff;
  outline-offset: 3px;
  border-radius: 6px;
  background-color: rgba(0, 123, 255, 0.1);
  padding: 2px 4px;
  margin: -2px -4px;
}

#notificationDetailModal .modal-title {
  cursor: pointer;
  transition: all 0.2s ease;
}

#notificationDetailModal .modal-title:hover {
  background-color: rgba(0, 123, 255, 0.05);
  border-radius: 4px;
  padding: 2px 4px;
  margin: -2px -4px;
}

/* Ensure modal is properly centered and visible */
#notificationDetailModal.show {
  display: block !important;
  background-color: rgba(0, 0, 0, 0.5);
}

#notificationDetailModal .modal-content {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  border: none;
  border-radius: 8px;
}

/* Notification detail modal improvements */
.notification-detail .notification-content {
  border-left: 4px solid #007bff !important;
  background-color: #f8f9fa !important;
}

.notification-detail .fa-2x {
  width: 3rem;
  text-align: center;
}

/* Responsive improvements */
@media (max-width: 768px) {
  .notification-dropdown {
    width: 300px;
    right: -50px;
  }
  
  .notification-item {
    padding: 10px 12px;
  }
  
  .notification-content {
    font-size: 0.85rem;
  }
}


</style>

<style>
/* Dark mode overrides for notification dropdown */
body.dark-mode .notification-dropdown {
  background: #343a40; /* AdminLTE dark */
  color: #f8f9fa; /* AdminLTE text */
  box-shadow: 0 8px 25px rgba(0,0,0,.5);
}

body.dark-mode .notification-dropdown .dropdown-header { background: #343a40; }

body.dark-mode .notification-dropdown .dropdown-item { color: #f8f9fa; border-bottom: 1px solid rgba(255,255,255,0.06); }

body.dark-mode .notification-dropdown .dropdown-item:hover { background-color: rgba(255,255,255,0.06); }

body.dark-mode .notification-dropdown .text-muted { color: #c2c7d0 !important; }

body.dark-mode .notification-dropdown .badge {
  color: #0b1220;
}

/* Filter buttons inside dropdown */
body.dark-mode .notification-dropdown .btn-group .btn { background: rgba(255,255,255,0.06); color: #f8f9fa; border-color: rgba(255,255,255,0.12); }

body.dark-mode .notification-dropdown .btn-group .btn:hover { background: rgba(255,255,255,0.12); color: #fff; }

body.dark-mode .notification-dropdown .btn-group .btn.active { background: #495057; border-color: transparent; color: #fff; }

/* Unread highlight should contrast in dark */
body.dark-mode .notification-item.unread-notification { background: rgba(255,193,7,0.08); border-left: 4px solid #ffc107; }

/* Strengthen base text inside items */
body.dark-mode .notification-dropdown .notification-item .notification-content,
body.dark-mode .notification-dropdown .notification-item .flex-grow-1,
body.dark-mode .notification-dropdown .notification-item {
  color: #f3f4f6; /* gray-100 */
}

/* Timestamps and subtle text */
body.dark-mode .notification-dropdown .notification-item .text-muted {
  color: #cbd5e1 !important; /* slate-300 */
}

/* Leading icons */
body.dark-mode .notification-dropdown .notification-item .flex-shrink-0 i {
  color: #93c5fd; /* blue-300 */
}

/* Footer area */
body.dark-mode .notification-dropdown .dropdown-footer { background-color: #343a40; border-top: 1px solid rgba(255,255,255,0.06); }

body.dark-mode .notification-dropdown .dropdown-footer .dropdown-item { color: #9ec5fe; }

/* Icons in dark mode */
body.dark-mode .notification-dropdown .fa-lg { color: #9ec5fe; opacity: .9; }

/* Meatballs menu dark mode styles */
body.dark-mode .meatballs-dropdown,
body.dark-mode #meatballsMenu {
  background-color: #2d3748 !important;
  border: 2px solid rgba(255,255,255,0.2) !important;
  box-shadow: 0 8px 32px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1) !important;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

body.dark-mode .meatballs-dropdown .dropdown-item,
body.dark-mode #meatballsMenu .dropdown-item {
  color: #ffffff !important;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  font-weight: 500;
  padding: 10px 15px;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover,
body.dark-mode #meatballsMenu .dropdown-item:hover {
  background-color: rgba(255,255,255,0.15) !important;
  color: #ffffff !important;
  transform: translateX(2px);
  transition: all 0.2s ease;
}

body.dark-mode .meatballs-dropdown .dropdown-divider,
body.dark-mode #meatballsMenu .dropdown-divider {
  border-top-color: rgba(255,255,255,0.2);
  margin: 8px 0;
}

body.dark-mode .meatballs-dropdown .dropdown-item i,
body.dark-mode #meatballsMenu .dropdown-item i {
  color: #60a5fa !important;
  font-weight: 600;
  margin-right: 8px;
  width: 16px;
  text-align: center;
}

/* Override Bootstrap's default white background for meatballs dropdown in dark mode */
body.dark-mode .meatballs-dropdown {
  background-color: #2d3748 !important;
  border-color: rgba(255,255,255,0.2) !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item {
  color: #ffffff !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover {
  background-color: rgba(255,255,255,0.15) !important;
  color: #ffffff !important;
}

/* Force dark styling for meatballs dropdown - highest specificity */
body.dark-mode .dropdown-menu.meatballs-dropdown {
  background-color: #2d3748 !important;
  border: 2px solid rgba(255,255,255,0.2) !important;
  box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important;
}

body.dark-mode .dropdown-menu.meatballs-dropdown .dropdown-item {
  color: #000000 !important;
  background-color: transparent !important;
}

body.dark-mode .dropdown-menu.meatballs-dropdown .dropdown-item:hover {
  background-color: rgba(0,0,0,0.1) !important;
  color: #000000 !important;
}

/* Enhanced meatball menu styling for dark mode */
body.dark-mode .meatballs-dropdown .dropdown-item {
  color: #000000 !important;
  font-weight: 600 !important;
  text-shadow: none !important;
  padding: 12px 16px !important;
  border-radius: 8px !important;
  margin: 2px 4px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
  position: relative !important;
  overflow: hidden !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
  color: #ffffff !important;
  transform: translateX(4px) scale(1.02) !important;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4) !important;
  border-radius: 8px !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover::before {
  content: '' !important;
  position: absolute !important;
  top: 0 !important;
  left: -100% !important;
  width: 100% !important;
  height: 100% !important;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent) !important;
  transition: left 0.5s !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover::before {
  left: 100% !important;
}

/* Enhanced icons with hover effects */
body.dark-mode .meatballs-dropdown .dropdown-item i {
  color: #0066cc !important;
  font-weight: 600 !important;
  transition: all 0.3s ease !important;
  margin-right: 10px !important;
  width: 18px !important;
  text-align: center !important;
}

body.dark-mode .meatballs-dropdown .dropdown-item:hover i {
  color: #ffffff !important;
  transform: scale(1.1) rotate(5deg) !important;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
}

/* All menu items use the same hover effect */

/* Enhanced divider styling */
body.dark-mode .meatballs-dropdown .dropdown-divider {
  border-top: 2px solid rgba(0,0,0,0.1) !important;
  margin: 8px 8px !important;
  border-radius: 1px !important;
}
</style>

<script>
  (function() {
    function hasClass(el, cls) { return el.classList.contains(cls); }
    function swap(el, remove, add) { if (el) { el.classList.remove.apply(el.classList, remove); el.classList.add.apply(el.classList, add); } }

    var savedTheme = null;
    try { savedTheme = localStorage.getItem('app-theme'); } catch (e) {}

    var body = document.body;
    var navbar = document.querySelector('.main-header.navbar');
    var sidebar = document.querySelector('.main-sidebar');
    var icon = document.getElementById('themeToggleIcon');

    function applyTheme(theme) {
      var isDark = theme === 'dark';

      // Add transition class for smooth switching
      body.classList.add('theme-transitioning');
      
      // Update theme-color meta tag immediately
      var themeMeta = document.getElementById('theme-color-meta');
      if (themeMeta) {
        themeMeta.setAttribute('content', isDark ? '#343a40' : '#ffffff');
      }
      
      // Use requestAnimationFrame for smooth transitions
      requestAnimationFrame(function() {
        // Body: AdminLTE dark mode
        body.classList.toggle('dark-mode', isDark);
        // Remove early boot class after applying real theme
        document.documentElement.classList.toggle('dark-boot', false);

        // Navbar: switch light/dark variants with smooth transition
        if (navbar) {
          if (isDark) {
            navbar.classList.remove('navbar-white', 'navbar-light');
            navbar.classList.add('navbar-dark', 'navbar-gray-dark');
          } else {
            navbar.classList.remove('navbar-dark', 'navbar-gray-dark');
            navbar.classList.add('navbar-white', 'navbar-light');
          }
        }

        // Sidebar: Keep AdminLTE default (sidebar-dark-primary) - don't change with theme toggle
        // Sidebar remains with its default AdminLTE styling regardless of dark/light mode

        // Toggle icon with smooth transition
        if (icon) {
          if (isDark) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
          } else {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
          }
        }

        // Remove transition class after animation completes
        setTimeout(function() {
          body.classList.remove('theme-transitioning');
        }, 300);

        try { localStorage.setItem('app-theme', isDark ? 'dark' : 'light'); } catch (e) {}
      });
    }

    // Initialize ASAP with enhanced flicker prevention
    if (savedTheme === null) {
      try { localStorage.setItem('app-theme', 'light'); } catch (e) {}
    }
    var initial = (savedTheme === 'dark') ? 'dark' : 'light';
    
    // Apply theme immediately to prevent any flash
    applyTheme(initial);
    
    // Also apply on DOM ready as backup
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() { 
        // Only reapply if theme changed during load
        var currentTheme = localStorage.getItem('app-theme');
        if (currentTheme !== initial) {
          applyTheme(currentTheme);
        }
      });
    }

    var toggle = document.getElementById('themeToggle');
    if (toggle) {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var next = body.classList.contains('dark-mode') ? 'light' : 'dark';
        applyTheme(next);
      });
    }
  })();
</script>

<script>
  (function() {
    var header = document.querySelector('.main-header.navbar');
    if (!header) return;

    function updateShadow() {
      if (window.scrollY > 8) {
        header.classList.add('navbar-scrolled');
      } else {
        header.classList.remove('navbar-scrolled');
      }
    }

    updateShadow();
    window.addEventListener('scroll', updateShadow, { passive: true });
  })();
</script>