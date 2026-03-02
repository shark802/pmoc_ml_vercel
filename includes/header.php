<?php
// Set security headers
require_once __DIR__ . '/security_headers.php';
// Load image helper for secure image paths
require_once __DIR__ . '/image_helper.php';
?>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<!-- Theme color meta tag to prevent white flash on mobile browsers -->
<meta name="theme-color" content="#343a40" id="theme-color-meta">
<!-- Favicon -->
<link rel="icon" href="<?= getSecureImagePath('../images/bcpdo.png') ?>" type="image/png">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Google Font: Source Sans Pro -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Enhanced theme bootstrapping to prevent flickering - CRITICAL: Must run before any rendering -->
<script>
  (function() {
    try {
      var theme = localStorage.getItem('app-theme');
      var html = document.documentElement;
      
      // Apply theme to HTML element IMMEDIATELY (before body exists)
      if (theme === 'dark') {
        html.classList.add('dark-boot');
        // Set inline style to prevent white flash during page load
        html.style.backgroundColor = 'var(--dark, #343a40)';
        html.style.color = '#f8f9fa';
        // Update theme-color meta tag for mobile browsers
        var themeMeta = document.getElementById('theme-color-meta');
        if (themeMeta) themeMeta.setAttribute('content', '#343a40');
      } else {
        html.classList.remove('dark-boot');
        // Update theme-color meta tag for light mode
        var themeMeta = document.getElementById('theme-color-meta');
        if (themeMeta) themeMeta.setAttribute('content', '#ffffff');
      }
    } catch (e) { /* silent */ }
  })();
</script>
<!-- Inline critical CSS to prevent white flash - must be before any external CSS -->
<style id="dark-mode-critical">
  /* Prevent white flash - apply immediately if dark mode */
  html.dark-boot {
    background-color: var(--dark, #343a40) !important;
    color: #f8f9fa !important;
  }
  html.dark-boot body {
    background-color: var(--dark, #343a40) !important;
    color: #f8f9fa !important;
  }
  html.dark-boot .main-header {
    background-color: var(--dark, #343a40) !important;
    color: #f8f9fa !important;
  }
  /* Let AdminLTE3 handle content-wrapper background */
  /* Sidebar: Keep AdminLTE default - don't override */
  /* Ensure all major containers are dark immediately */
  html.dark-boot .wrapper {
    background-color: var(--dark, #343a40) !important;
  }
</style>
<script>
  // Apply theme to body and other elements once DOM is ready
  (function() {
    try {
      var theme = localStorage.getItem('app-theme');
      var html = document.documentElement;
      var body = document.body;
      
      // Wait for body to exist
      if (!body) {
        // Use DOMContentLoaded as fallback
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function() {
            applyDarkModeTheme();
          });
        } else {
          applyDarkModeTheme();
        }
        return;
      }
      
      function applyDarkModeTheme() {
        var body = document.body;
        var html = document.documentElement;
        
        if (theme === 'dark') {
          html.classList.add('dark-boot');
          body.classList.add('dark-mode');
          
          // Apply navbar classes immediately (sidebar stays with AdminLTE default)
          var navbar = document.querySelector('.main-header.navbar');
          
          if (navbar) {
            navbar.classList.remove('navbar-white', 'navbar-light');
            navbar.classList.add('navbar-dark', 'navbar-gray-dark');
          }
          
          // Sidebar: Keep AdminLTE default (sidebar-dark-primary) - don't change
          
        } else {
          // Ensure light mode is properly set
          html.classList.remove('dark-boot');
          body.classList.remove('dark-mode');
          
          var navbar = document.querySelector('.main-header.navbar');
          
          if (navbar) {
            navbar.classList.remove('navbar-dark', 'navbar-gray-dark');
            navbar.classList.add('navbar-white', 'navbar-light');
          }
          
          // Sidebar: Keep AdminLTE default (sidebar-dark-primary) - don't change
        }
      }
      
      // Apply immediately if body exists
      applyDarkModeTheme();
    } catch (e) { /* silent */ }
  })();
</script>

<!-- Font Awesome -->
<link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

<!-- AdminLTE CSS -->
<link rel="stylesheet" href="../dist/css/adminlte.min.css">

<!-- DataTables -->
<link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/rowgroup/1.1.2/css/rowGroup.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">



<!-- Use local Font Awesome (v5) bundled with AdminLTE to avoid version conflicts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" 
      onerror="this.onerror=null; this.href='../plugins/animate.css/animate.min.css';">
<!-- jQuery UI CSS with improved fallback handling -->
<script>
(function() {
    // Prefer local jQuery UI CSS in development to avoid CDN 502 errors
    var isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname.includes('localhost');
    
    var jqueryUiCss = isLocalhost 
        ? '../plugins/jquery-ui/jquery-ui.min.css'  // Use local in development
        : 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css';  // Use CDN in production
    
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = jqueryUiCss;
    
    // Fallback handler
    link.onerror = function() {
        if (this.href.includes('code.jquery.com')) {
            // CDN failed, try local
            this.href = '../plugins/jquery-ui/jquery-ui.min.css';
        }
    };
    
    document.head.appendChild(link);
})();
</script>
<style>
/* Optimized theme transitions - avoid universal selector to prevent flicker */
body, .card, .kpi-card, .small-box, .content-wrapper, .main-header, .main-sidebar {
  transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Specific component transitions */
.card-header, .card-body, .kpi-title, .kpi-value {
  transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Specific AdminLTE3 components with optimized transitions */
.main-header, .main-sidebar, .content-wrapper, .card, .card-header, .card-body,
.table, .table thead th, .table tbody td, .dropdown-menu, .navbar-nav,
.sidebar, .nav-sidebar, .nav-sidebar .nav-item, .nav-sidebar .nav-link,
.btn, .form-control, .input-group, .input-group-text, .badge, .alert,
.modal, .modal-header, .modal-body, .modal-footer, .toast, .toast-header {
  transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Enhanced transitions during theme switching */
body.theme-transitioning * {
  transition-duration: 0.3s !important;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important;
}

/* Prevent layout shifts during theme transitions */
body.theme-transitioning .main-sidebar,
body.theme-transitioning .content-wrapper,
body.theme-transitioning .main-header {
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

/* Make readonly and disabled fields visually uniform (white background) */
.readonly-white[readonly],
input[readonly].readonly-white,
textarea[readonly].readonly-white,
select[readonly].readonly-white,
input:disabled.readonly-white,
textarea:disabled.readonly-white,
select:disabled.readonly-white {
  background-color: #ffffff !important;
  opacity: 1; /* ensure full opacity */
}

/* Keep mouse pointer and caret stable on inputs */
input, textarea, .form-control {
  cursor: text !important;
  caret-color: currentColor !important;
}

/* Prevent validation helpers or adornments from stealing hover events */
.form-control + .invalid-feedback,
.form-control + .valid-feedback,
.required-field::after {
  pointer-events: none;
}

/* Ensure input-group buttons/adornments remain clickable */
.input-group .input-group-append,
.input-group .input-group-prepend {
  pointer-events: auto;
}

/* Global SweetAlert2 toast theme: light green background */
.swal2-popup.swal2-toast {
  background-color: #d4edda !important; /* light green */
  color: #155724 !important; /* dark green text */
  border: 1px solid #c3e6cb !important;
  box-shadow: 0 0 0 1px rgba(21, 87, 36, 0.05), 0 4px 12px rgba(21, 87, 36, 0.15) !important;
}
.swal2-popup.swal2-toast .swal2-title,
.swal2-popup.swal2-toast .swal2-html-container {
  color: #155724 !important;
}
.swal2-popup.swal2-toast .swal2-timer-progress-bar {
  background: rgba(40, 167, 69, 0.6) !important; /* medium green */
}

/* Navbar ↔ Sidebar alignment: enforce consistent header height */
:root {
  --app-header-height: 57px; /* match AdminLTE brand link default */
}

.main-header {
  min-height: var(--app-header-height);
  padding-top: 0;
  padding-bottom: 0;
}

.main-header .navbar-nav .nav-link {
  padding-top: 0.375rem;
  padding-bottom: 0.375rem;
}

/* Align sidebar brand height with navbar */
.main-sidebar .brand-link {
  height: var(--app-header-height);
  display: flex;
  align-items: center;
}

/* Remove extra offset to prevent large gap under navbar */
/* AdminLTE handles offsets when using layout-navbar-fixed; for sticky nav, no extra margin */
</style>

<!-- Removed duplicate Font Awesome 6 include to prevent conflicts -->
<!-- Chart.js (header load to ensure availability for early inline scripts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<style>
/* Prevent flash: minimal dark palette when html has dark-boot before DOM is ready */
/* Use AdminLTE native dark palette */
html.dark-boot body { background-color: var(--dark, #343a40) !important; color: #f8f9fa !important; }
html.dark-boot .main-header { background-color: var(--dark, #343a40) !important; color: #f8f9fa !important; }
html.dark-boot .main-sidebar { background-color: var(--dark, #343a40) !important; }
/* Let AdminLTE3 handle content-wrapper background - don't override */

/* Prevent theme flickering during navigation */
html.dark-boot .main-header.navbar {
  background-color: var(--dark, #343a40) !important;
  color: #f8f9fa !important;
}

/* CRITICAL: Ensure body stays lighter (using AdminLTE3's --dark variable) than cards */
html.dark-boot body.dark-mode,
body.dark-mode {
  background-color: var(--dark, #343a40) !important;
  color: #f8f9fa !important;
}

/* Let AdminLTE3 handle content-wrapper background naturally */

html.dark-boot body.dark-mode .main-header,
body.dark-mode .main-header {
  background-color: var(--dark, #343a40) !important;
  color: #f8f9fa !important;
}

html.dark-boot body.dark-mode .wrapper,
body.dark-mode .wrapper {
  background-color: var(--dark, #343a40) !important;
}

/* Ensure body background doesn't get overridden by card styles or transitions */
body.dark-mode {
  background-color: var(--dark, #343a40) !important;
  background-image: none !important;
}

/* Let AdminLTE3 handle content-wrapper background - remove override */

/* Sidebar: Keep AdminLTE default styling - no overrides */
/* The sidebar will maintain its default AdminLTE sidebar-dark-primary appearance */

/* Ensure smooth transitions without flicker */
body {
  transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.main-header, .main-sidebar, .content-wrapper {
  transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* CRITICAL: Ensure background stays lighter (using AdminLTE3's --dark variable) than cards - prevent flicker */
body.dark-mode {
  background-color: var(--dark, #343a40) !important;
  color: #f8f9fa !important;
}

/* Let AdminLTE3 handle content-wrapper background naturally */

body.dark-mode .main-header {
  background-color: #343a40 !important;
  color: #f8f9fa !important;
}

/* Dark mode: KPI cards and headers - prevent flicker */
html.dark-boot .kpi-card,
body.dark-mode .kpi-card {
  background: #2a2e33 !important; /* Darker than background */
  box-shadow: 0 6px 20px rgba(0,0,0,.45) !important;
  color: #f8f9fa !important;
}

html.dark-boot .kpi-title,
body.dark-mode .kpi-title { 
  color: #c2c7d0 !important; 
}

html.dark-boot .kpi-value,
body.dark-mode .kpi-value { 
  color: #ffffff !important; 
}

/* Prevent white flash during theme transition */
html.dark-boot .kpi-card {
  background: #2a2e33 !important;
  color: #f8f9fa !important;
}

/* Preserve colorful KPI icon circles as-is for contrast */

/* Dark mode: card and table headers */
body.dark-mode .card-header,
body.dark-mode .table thead th,
body.dark-mode table thead th {
  background-color: #2a2e33;
  color: #ffffff;
  border-color: rgba(255,255,255,0.1);
}

/* Generic card text in dark theme - prevent flicker */
html.dark-boot .card,
body.dark-mode .card { 
  background-color: #2a2e33 !important; 
  color: #f8f9fa !important; 
  border-color: rgba(255,255,255,0.08) !important;
}

html.dark-boot .card .card-title,
body.dark-mode .card .card-title { 
  color: #ffffff !important; 
}

html.dark-boot .card-header,
body.dark-mode .card-header {
  background-color: #2a2e33 !important;
  color: #ffffff !important;
  border-color: rgba(255,255,255,0.1) !important;
}

/* AdminLTE3 Unified Color Scheme - Works in both light and dark modes */
/* Primary AdminLTE3 colors that work universally */
:root {
  --adminlte-primary: #007bff;
  --adminlte-secondary: #6c757d;
  --adminlte-success: #28a745;
  --adminlte-info: #17a2b8;
  --adminlte-warning: #ffc107;
  --adminlte-danger: #dc3545;
  --adminlte-light: #f8f9fa;
  --adminlte-dark: #343a40;
}

/* Original AdminLTE3 Button Styling */
.btn {
  transition: all 0.2s ease;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}


/* Dark mode button styling */
html.dark-boot .btn, body.dark-mode .btn {
  transition: all 0.2s ease;
}

html.dark-boot .btn:hover, body.dark-mode .btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}


/* Dark mode: small-box contrast (AdminLTE 3 style) - prevent flicker */
html.dark-boot .small-box,
body.dark-mode .small-box {
  background: #2f3640 !important;
  color: #f8f9fa !important;
  border-radius: .5rem;
  box-shadow: 0 6px 20px rgba(0,0,0,.45) !important;
  border: 1px solid rgba(255,255,255,0.08) !important;
}

html.dark-boot .small-box .inner h3,
html.dark-boot .small-box .inner p,
body.dark-mode .small-box .inner h3,
body.dark-mode .small-box .inner p { 
  color: #ffffff !important; 
}

html.dark-boot .small-box .icon,
body.dark-mode .small-box .icon { 
  color: rgba(255,255,255,0.35) !important; 
}

/* Info boxes dark mode - prevent flicker */
html.dark-boot .info-box,
body.dark-mode .info-box {
  background: #2a2e33 !important;
  color: #f8f9fa !important;
  border: 1px solid rgba(255,255,255,0.08) !important;
}

html.dark-boot .info-box .info-box-content,
body.dark-mode .info-box .info-box-content {
  color: #f8f9fa !important;
}

html.dark-boot .info-box .info-box-text,
body.dark-mode .info-box .info-box-text {
  color: #c2c7d0 !important;
}

html.dark-boot .info-box .info-box-number,
body.dark-mode .info-box .info-box-number {
  color: #ffffff !important;
}
</style>

<style>
/* Dark mode: DataTables */
body.dark-mode .dataTables_wrapper .dataTables_length label,
body.dark-mode .dataTables_wrapper .dataTables_filter label,
body.dark-mode .dataTables_wrapper .dataTables_info,
body.dark-mode .dataTables_wrapper .dataTables_paginate a,
body.dark-mode .table { color: #f8f9fa; }

body.dark-mode .table thead th,
body.dark-mode table.dataTable thead th {
  background-color: #343a40;
  color: #ffffff;
  border-bottom-color: rgba(255,255,255,0.12);
}

body.dark-mode table.dataTable tbody tr { background-color: #343a40; }
body.dark-mode table.dataTable tbody tr:nth-child(odd) { background-color: #2f3640; }
body.dark-mode table.dataTable tbody td { border-top-color: rgba(255,255,255,0.06); }

body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button {
  color: #f8f9fa !important;
  border-color: rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.04);
}
body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button.current,
body.dark-mode .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  color: #fff !important;
  background: #495057 !important;
  border-color: transparent !important;
}







body.dark-mode .fc .fc-daygrid-day.fc-day-today { background: #2f3640; }


/* Dark mode: FullCalendar */
body.dark-mode .fc-theme-standard .fc-scrollgrid,
body.dark-mode .fc-theme-standard td,
body.dark-mode .fc-theme-standard th { border-color: rgba(255,255,255,0.12); }

body.dark-mode .fc .fc-toolbar-title { color: #ffffff; }
body.dark-mode .fc .fc-button { background: #495057; border-color: transparent; color: #fff; }
body.dark-mode .fc .fc-button:hover { background: #6c757d; }
body.dark-mode .fc .fc-button-primary:not(:disabled).fc-button-active,
body.dark-mode .fc .fc-button-primary:not(:disabled):active { background: #1f2937; }

body.dark-mode .fc .fc-col-header-cell { background: #343a40; color: #ffffff; }
body.dark-mode .fc .fc-daygrid-day { background: #343a40; color: #f8f9fa; }
body.dark-mode .fc-daygrid-day.fc-day-today { background: #2f3640; }
body.dark-mode .fc .fc-highlight { background: rgba(99,102,241,0.25); }

/* Dark mode: generic alerts */
body.dark-mode .alert-info {
  background-color: #0c4a6e;
  border-color: #075985;
  color: #ecfeff;
}
</style>

<style>
/* Dark mode: Access Codes page blocks (neutral AdminLTE dark, no blue) */
body.dark-mode .page-header,
body.dark-mode .code-generator,
body.dark-mode .card { background-color: #2a2e33; color: #f8f9fa; }
body.dark-mode .page-header { box-shadow: 0 6px 20px rgba(0,0,0,.45); }
body.dark-mode .page-header .page-text { color: #ffffff; }
body.dark-mode .page-header .page-sub { color: #c2c7d0; }
body.dark-mode .code-generator h4 { color: #f8f9fa; }

/* Buttons and outlines on dark - prevent flicker */
html.dark-boot .btn,
html.dark-boot .btn-outline-primary,
html.dark-boot .btn-outline-primary,
html.dark-boot .btn-outline-danger,
html.dark-boot .btn-outline-secondary,
body.dark-mode .btn,
body.dark-mode .btn-outline-primary,
body.dark-mode .btn-outline-danger,
body.dark-mode .btn-outline-secondary {
  transition: all 0.2s ease !important;
}

html.dark-boot .btn-outline-primary,
body.dark-mode .btn-outline-primary { 
  color: #9ec5fe !important; 
  border-color: #9ec5fe !important; 
  background: transparent !important;
}

html.dark-boot .btn-outline-primary:hover,
body.dark-mode .btn-outline-primary:hover { 
  color: #343a40 !important; 
  background: #9ec5fe !important; 
  border-color: #9ec5fe !important; 
}

html.dark-boot .btn-outline-danger,
body.dark-mode .btn-outline-danger { 
  color: #f8d7da !important; 
  border-color: #f5c2c7 !important; 
  background: transparent !important;
}

html.dark-boot .btn-outline-danger:hover,
body.dark-mode .btn-outline-danger:hover { 
  color: #343a40 !important; 
  background: #f5c2c7 !important; 
  border-color: #f5c2c7 !important; 
}

html.dark-boot .btn-outline-secondary,
body.dark-mode .btn-outline-secondary { 
  color: #c2c7d0 !important; 
  border-color: #adb5bd !important; 
  background: transparent !important;
}

html.dark-boot .btn-outline-secondary:hover,
body.dark-mode .btn-outline-secondary:hover { 
  color: #343a40 !important; 
  background: #adb5bd !important; 
  border-color: #adb5bd !important; 
}

/* Primary buttons dark mode */
html.dark-boot .btn-primary,
body.dark-mode .btn-primary {
  background-color: #007bff !important;
  border-color: #007bff !important;
  color: #ffffff !important;
}

html.dark-boot .btn-primary:hover,
body.dark-mode .btn-primary:hover {
  background-color: #0056b3 !important;
  border-color: #0056b3 !important;
  color: #ffffff !important;
}

/* Badges readability - prevent flicker */
html.dark-boot .badge-secondary,
body.dark-mode .badge-secondary { 
  background-color: #6c757d !important; 
  color: #ffffff !important; 
}

html.dark-boot .badge-success,
body.dark-mode .badge-success { 
  color: #052e16 !important; 
}

html.dark-boot .badge-warning,
body.dark-mode .badge-warning { 
  color: #343a40 !important; 
}

/* Navbar icons and elements - prevent flicker */
html.dark-boot .navbar-nav .nav-link,
html.dark-boot .navbar-nav .nav-link i,
html.dark-boot .navbar-nav .nav-link .fas,
html.dark-boot .navbar-nav .nav-link .far,
html.dark-boot .navbar-nav .nav-link .fab,
body.dark-mode .navbar-nav .nav-link,
body.dark-mode .navbar-nav .nav-link i,
body.dark-mode .navbar-nav .nav-link .fas,
body.dark-mode .navbar-nav .nav-link .far,
body.dark-mode .navbar-nav .nav-link .fab {
  color: #f8f9fa !important;
  transition: color 0.2s ease !important;
}

html.dark-boot .navbar-nav .nav-link:hover,
html.dark-boot .navbar-nav .nav-link:hover i,
html.dark-boot .navbar-nav .nav-link:hover .fas,
html.dark-boot .navbar-nav .nav-link:hover .far,
html.dark-boot .navbar-nav .nav-link:hover .fab,
body.dark-mode .navbar-nav .nav-link:hover,
body.dark-mode .navbar-nav .nav-link:hover i,
body.dark-mode .navbar-nav .nav-link:hover .fas,
body.dark-mode .navbar-nav .nav-link:hover .far,
body.dark-mode .navbar-nav .nav-link:hover .fab {
  color: #ffffff !important;
}

/* Code generator elements - prevent flicker */
html.dark-boot .code-generator,
html.dark-boot .code-generator .form-control,
html.dark-boot .code-generator .input-group-text,
html.dark-boot .code-generator .btn,
body.dark-mode .code-generator,
body.dark-mode .code-generator .form-control,
body.dark-mode .code-generator .input-group-text,
body.dark-mode .code-generator .btn {
  background-color: #343a40 !important;
  color: #f8f9fa !important;
  border-color: rgba(255,255,255,0.12) !important;
  transition: all 0.2s ease !important;
}

html.dark-boot .code-generator .form-control:focus,
body.dark-mode .code-generator .form-control:focus {
  background-color: #343a40 !important;
  color: #f8f9fa !important;
  border-color: #007bff !important;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
}

html.dark-boot .code-generator .input-group-text,
body.dark-mode .code-generator .input-group-text {
  background-color: #495057 !important;
  color: #f8f9fa !important;
  border-color: rgba(255,255,255,0.12) !important;
}
</style>

<style>
/* Synchronize AdminLTE pushmenu transitions to remove lag between sidebar and content/header */
.main-sidebar,
.content-wrapper,
.main-header {
  transition-property: margin-left, width, background-color, color, border-color, box-shadow;
  transition-duration: .25s, .25s, .25s, .25s, .25s, .25s;
  transition-timing-function: cubic-bezier(.4, 0, .2, 1);
  transition-delay: 0s;
  will-change: margin-left, width;
}

/* Ensure sidebar and header stay connected during transitions - prevent cut off */
.main-sidebar {
  position: relative;
  z-index: 1030;
  /* Prevent sidebar from being cut off during transition */
  overflow: visible !important;
}

.main-header {
  position: relative;
  z-index: 1031;
  /* Ensure header stays above sidebar */
  background-color: inherit;
}

/* Ensure content-wrapper transitions smoothly and stays below navbar */
.content-wrapper {
  position: relative;
  z-index: 1; /* Lower than navbar to prevent overlap */
}

/* Expanded vs mini margins (ensure both move together) */
body.sidebar-mini .main-header,
body.sidebar-mini .content-wrapper { margin-left: 250px; }
body.sidebar-mini.sidebar-collapse .main-header,
body.sidebar-mini.sidebar-collapse .content-wrapper { margin-left: 4.6rem; }

/* Sidebar width transition - synchronized with header/content */
body.sidebar-mini .main-sidebar { 
  transition: width .25s cubic-bezier(.4,0,.2,1) !important;
  will-change: width;
}

/* Prevent sidebar from being cut off during transition */
body.sidebar-mini .main-sidebar,
body.sidebar-mini.sidebar-collapse .main-sidebar {
  overflow: visible !important;
  min-width: 0;
}

/* Ensure smooth transition without gaps - synchronize sidebar and header */
body.sidebar-mini .main-sidebar::before {
  transition: width .25s cubic-bezier(.4,0,.2,1) !important;
}

/* Ensure header and sidebar move together smoothly */
body.sidebar-mini .main-header,
body.sidebar-mini .content-wrapper {
  transition: margin-left .25s cubic-bezier(.4,0,.2,1) !important;
  will-change: margin-left;
}
</style>

<style>
/* Dark mode: generic tables (ensure zebra rows readable) */
body.dark-mode table,
body.dark-mode .table { color: #f8f9fa; }
body.dark-mode .table tbody td,
body.dark-mode table tbody td { color: #f8f9fa; }
body.dark-mode .table tbody tr,
body.dark-mode table tbody tr { background-color: #343a40; }
body.dark-mode .table tbody tr:nth-child(odd),
body.dark-mode table tbody tr:nth-child(odd) { background-color: #3b4147; }
body.dark-mode .table tbody tr:nth-child(even),
body.dark-mode table tbody tr:nth-child(even) { background-color: #2f3640; }
body.dark-mode .table tbody tr:hover,
body.dark-mode table tbody tr:hover { background-color: #454d55; }
body.dark-mode .table td, body.dark-mode .table th,
body.dark-mode table td, body.dark-mode table th { border-color: rgba(255,255,255,0.12); }

/* Fix specific registration table conflicting light stripes */
body.dark-mode #registrationTable tbody tr:nth-child(even) { background-color: #2f3640 !important; }
body.dark-mode #registrationTable tbody tr:nth-child(odd) { background-color: #3b4147 !important; }
body.dark-mode #registrationTable td { color: #f8f9fa !important; border-color: rgba(255,255,255,0.12) !important; }
body.dark-mode #registrationTable td:nth-child(2) { color: #9ec5fe !important; }
</style>

<style>
/* Navbar and Sidebar alignment tweaks */
:root { --app-header-height: 57px; }
.main-header { min-height: var(--app-header-height); }
.main-header .navbar { min-height: var(--app-header-height); align-items: center; }
.main-sidebar .brand-link { height: var(--app-header-height); display:flex; align-items:center; }
.main-sidebar .brand-link .brand-image { height: 34px; width: 34px; line-height: 34px; }

/* Card borders and progress background in dark */
body.dark-mode .card { border: 1px solid rgba(255,255,255,0.08); }
body.dark-mode .card .progress { background: rgba(255,255,255,0.08); }
body.dark-mode .progress-bar { box-shadow: none; }
</style>

<style>
/* Global table shadows */
.table,
table.table {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  border-radius: .35rem;
  overflow: hidden;
}
.table-responsive {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
  border-radius: .35rem;
}
/* Dark mode: stronger, softer-edged shadows */
body.dark-mode .table,
body.dark-mode table.table {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.45);
}
body.dark-mode .table-responsive {
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.40);
}
</style>

<style>
/* Smooth but controlled theme animation */
.theme-anim, .theme-anim * {
  transition-property: background-color, color, border-color, fill, stroke;
  transition-duration: .22s;
  transition-timing-function: cubic-bezier(.4,0,.2,1);
}
</style>
<script>
  // Optimized theme switching to prevent flicker
  (function(){
    try {
      var body = document.body;
      var isTransitioning = false;
      
      // Only proceed if body element exists
      if (!body) { return; }
      
      // Optimize transitions during theme switch
      function optimizeThemeTransition() {
        if (isTransitioning) return;
        isTransitioning = true;
        
        // Temporarily optimize transitions for specific components
        var style = document.createElement('style');
        style.id = 'theme-transition-optimizer';
        style.textContent = `
          .kpi-card, .card, .small-box, .info-box, .code-generator { 
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease !important; 
          }
          .main-sidebar, .content-wrapper, .main-header { 
            transition: margin-left 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important; 
          }
          .kpi-title, .kpi-value, .card-title, .info-box-text, .info-box-number { 
            transition: color 0.2s ease !important; 
          }
          .btn, .navbar-nav .nav-link, .navbar-nav .nav-link i { 
            transition: all 0.2s ease !important; 
          }
          .form-control, .input-group-text { 
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease !important; 
          }
        `;
        document.head.appendChild(style);
        
        // Remove optimizer after transition completes
        setTimeout(function() {
          var optimizer = document.getElementById('theme-transition-optimizer');
          if (optimizer) optimizer.remove();
          isTransitioning = false;
        }, 250);
      }
      
      // Watch for theme changes
      var obs = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            var currentClasses = body.className;
            if (currentClasses.indexOf('dark-mode') !== -1 || 
                (mutation.oldValue && mutation.oldValue.indexOf('dark-mode') !== -1)) {
              optimizeThemeTransition();
            }
          }
        });
      });
      
      obs.observe(body, { 
        attributes: true, 
        attributeFilter: ['class'],
        attributeOldValue: true 
      });
    } catch(e) { /* silent */ }
  })();
</script>
