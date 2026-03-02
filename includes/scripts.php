<!-- REQUIRED SCRIPTS -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script>
    // Ensure jQuery is loaded before any other scripts
    (function() {
        // Define $ immediately to prevent "undefined" errors
        if (typeof window.$ === 'undefined') {
            window.$ = function() {
                if (typeof window.jQuery !== 'undefined') {
                    return window.jQuery.apply(window.jQuery, arguments);
                }
                console.error('jQuery not loaded yet');
                return null;
            };
        }
        
        // Check if jQuery loaded
        if (typeof jQuery === 'undefined') {
            console.error('jQuery failed to load');
        } else {
            console.log('jQuery loaded successfully');
            // Ensure $ alias is available globally and properly set
            window.$ = window.jQuery;
        }
    })();
</script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" 
        onerror="console.warn('SweetAlert2 CDN failed, loading local fallback'); 
                 this.src='../plugins/sweetalert2/sweetalert2.all.min.js';"></script>
<!-- DataTables & Plugins -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<!-- <script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script> -->

<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js' 
        onerror="console.warn('Moment.js CDN failed, loading local fallback'); 
                 this.src='../plugins/moment/moment.min.js';"></script>


<!-- Add these before your custom scripts -->
<!-- jQuery UI JS with improved fallback handling -->
<script>
(function() {
    // Prefer local jQuery UI in development to avoid CDN 502 errors
    var isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname.includes('localhost');
    
    var jqueryUiSrc = isLocalhost 
        ? '../plugins/jquery-ui/jquery-ui.min.js'  // Use local in development
        : 'https://code.jquery.com/ui/1.12.1/jquery-ui.min.js';  // Use CDN in production
    
    var script = document.createElement('script');
    script.src = jqueryUiSrc;
    script.async = false;
    
    // Fallback handler
    script.onerror = function() {
        if (this.src.includes('code.jquery.com')) {
            // CDN failed, try local
            this.src = '../plugins/jquery-ui/jquery-ui.min.js';
        } else {
            console.warn('jQuery UI failed to load from both CDN and local fallback');
        }
    };
    
    // Ensure jQuery UI is present even if both CDN and local fail
    (function ensureJqueryUi(){
        var attempts = 0;
        function tryLoad(){
            attempts++;
            if (window.jQuery && jQuery.ui && jQuery.ui.autocomplete) return; // already ok
            if (attempts === 1 && !isLocalhost) {
                // Only inject local fallback if we tried CDN first
                var s = document.createElement('script');
                s.src = '../plugins/jquery-ui/jquery-ui.min.js';
                s.async = false;
                s.onerror = function(){ console.warn('Local jQuery UI fallback failed to load'); };
                document.head.appendChild(s);
            }
            if (attempts < 20) setTimeout(tryLoad, 150); // retry for ~3s
        }
        setTimeout(tryLoad, 200);
    })();
    
    document.head.appendChild(script);
})();
</script>
<!-- Chart.js already loaded in header as well; keep here as fallback for pages that omit header -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js" 
        onerror="console.warn('Chart.js CDN failed, loading local fallback'); 
                 this.src='../plugins/chart.js/Chart.min.js';"></script>

<!-- Export Libraries - Using working CDNs with fallbacks -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" 
        onerror="console.warn('html2pdf CDN failed, using alternative'); 
                 this.src='https://unpkg.com/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js';"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" 
        onerror="console.warn('xlsx CDN failed, using alternative'); 
                 this.src='https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js';"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" 
        onerror="console.warn('html2canvas CDN failed, using alternative'); 
                 this.src='https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js';"></script>
<!-- jsPDF for direct PDF generation and AutoTable for tables -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
<script>
    // Resource loading detection and fallback
    function checkResourceLoading() {
        const resources = [
            { name: 'Chart.js', check: () => typeof Chart !== 'undefined' },
            { name: 'SweetAlert2', check: () => typeof Swal !== 'undefined' },
            { name: 'Moment.js', check: () => typeof moment !== 'undefined' },
            { name: 'jQuery UI', check: () => typeof $.ui !== 'undefined' }
        ];
        
        let failedResources = [];
        resources.forEach(resource => {
            if (!resource.check()) {
                failedResources.push(resource.name);
                console.warn(`${resource.name} failed to load`);
            }
        });
        
        if (failedResources.length > 0) {
            console.error('Failed to load resources:', failedResources);
            // Show user-friendly message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Loading Issue',
                    text: 'Some resources failed to load. Please refresh the page or check your internet connection.',
                    icon: 'warning',
                    confirmButtonText: 'Refresh Page',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.reload();
                });
            }
        }
    }
    
    // Check resources after a delay
    setTimeout(checkResourceLoading, 3000);
    
    
    $(document).ready(function() {
        // Image fallback handling
        $('img').on('error', function() {
            $(this).attr('src', '../images/profiles/default.jpg');
        });
        
        // Active link highlighting
        const current = location.pathname.split('/').pop();
        $('.nav-link').each(function() {
            const $this = $(this);
            if ($this.attr('href').indexOf(current) !== -1) {
                $this.addClass('active');
            }
        });

        // Print from notification dropdown
        $('.print-btn-navbar').click(function(e) {
            e.preventDefault();
            const code = $(this).data('id');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html><head><title>Couple Access Code</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:20px;}.code{font-size:2rem;font-weight:bold;margin:20px 0;}.title{font-size:1.5rem;margin-bottom:10px;}.instructions{font-size:0.9rem;color:#666;}</style></head><body><div class='title'>BCPDO Couple Access Code</div><div class='code'>${code}</div><div class='instructions'>Share this code with both partners to register for the questionnaire.<br>Generated on: ${new Date().toLocaleDateString()}</div></body></html>
            `);
            printWindow.document.close();
            printWindow.print();
        });
        
        // Logout with confirmation and enhanced loading design
        $('#logoutLink').on('click', function(e){
            e.preventDefault();
            Swal.fire({
                title: 'Log out?',
                text: 'You will be logged out of the system.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-out-alt mr-2"></i>Yes, logout',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then((res)=>{
                if(res.isConfirmed){
                    // Create enhanced loading overlay
                    const overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed;
                        inset: 0;
                        background: linear-gradient(135deg, rgba(52, 58, 64, 0.95) 0%, rgba(33, 37, 41, 0.98) 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        animation: fadeIn 0.3s ease-in;
                    `;
                    
                    overlay.innerHTML = `
                        <style>
                            @keyframes fadeIn {
                                from { opacity: 0; }
                                to { opacity: 1; }
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            @keyframes pulse {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0.5; }
                            }
                            .logout-container {
                                text-align: center;
                                color: white;
                                animation: fadeIn 0.5s ease-in;
                            }
                            .logout-spinner {
                                width: 60px;
                                height: 60px;
                                border: 4px solid rgba(255, 255, 255, 0.2);
                                border-top: 4px solid #ffffff;
                                border-radius: 50%;
                                animation: spin 0.8s linear infinite;
                                margin: 0 auto 20px;
                            }
                            .logout-text {
                                font-size: 1.2rem;
                                font-weight: 500;
                                margin-bottom: 10px;
                            }
                            .logout-subtext {
                                font-size: 0.9rem;
                                color: rgba(255, 255, 255, 0.7);
                                animation: pulse 2s ease-in-out infinite;
                            }
                            .logout-icon {
                                font-size: 3rem;
                                margin-bottom: 20px;
                                opacity: 0.9;
                            }
                        </style>
                        <div class="logout-container">
                            <div class="logout-icon">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <div class="logout-spinner"></div>
                            <div class="logout-text">Logging out...</div>
                            <div class="logout-subtext">Please wait a moment</div>
                        </div>
                    `;
                    
                    document.body.appendChild(overlay);
                    
                    // Redirect after animation
                    setTimeout(() => { 
                        window.location.href = '../logout.php'; 
                    }, 1500);
                }
            });
        });

        // SweetAlert notifications from session (generic)
        <?php if (isset($_SESSION['swal'])): ?>
        Swal.fire({
            icon: '<?php echo $_SESSION['swal']['icon']; ?>',
            title: '<?php echo $_SESSION['swal']['title']; ?>',
            text: '<?php echo $_SESSION['swal']['text']; ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['swal']); endif; ?>

        // Global success_message toast
        <?php if (isset($_SESSION['success_message'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($_SESSION['success_message']) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success_message']); endif; ?>

        // Initialize notification dropdown functionality
        $('.notification-dropdown').on('show.bs.dropdown', function() {
            // Ensure proper positioning
            $(this).css('display', 'block');
        });

        // Handle notification badge click
        $('.notification-badge').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.dropdown').find('.dropdown-toggle').dropdown('toggle');
        });

        // Ensure dropdowns work on mobile
        if ($(window).width() <= 768) {
            $('.dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                $(this).dropdown('toggle');
            });
        }

        // Enhanced Notification System
        function getIncludesPath() {
            const currentPath = window.location.pathname;
            
            // Use relative paths based on current directory structure
            // This works in both development and production
            
            // Check for ml_model folder first (it's 2 levels deep)
            if (currentPath.includes('/ml_model/')) {
                return '../includes/';
            }
            
            // For subdirectories (admin, couple_list, etc.), go up one level to includes
            if (currentPath.includes('/admin/') || currentPath.includes('/couple_list/') || 
                currentPath.includes('/question_assessment/') || currentPath.includes('/question_category/') ||
                currentPath.includes('/questionnaire/') || currentPath.includes('/couple_scheduling/') || 
                currentPath.includes('/notifications/') || currentPath.includes('/statistics/') || 
                currentPath.includes('/predictive_analytics/') || currentPath.includes('/certificates/') || 
                currentPath.includes('/couple_response/') || currentPath.includes('/couple_profile/') || 
                currentPath.includes('/reports/') || currentPath.includes('/counselor/')) {
                return '../includes/';
            }
            
            // For root level pages, includes is in the same directory
            return 'includes/';
        }

        // Enhanced notification count loading
        function loadNotificationCount() {
            const path = getIncludesPath();
            // Only log in development mode
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Loading notification count from:', path + 'get_notifications.php');
            }
            
            $.ajax({
                url: path + 'get_notifications.php',
                type: 'POST',
                data: { 
                    action: 'get_count',
                    _t: Date.now() // Cache buster
                },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        console.log('Notification count response:', response);
                    }
                    const badge = $('#notificationCount');
                    
                    if (response.success && response.count !== undefined) {
                        const count = response.count;
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.log('Setting notification count to:', count);
                        }
                        
                        if (count > 0) {
                            const displayText = count > 99 ? '99+' : count.toString();
                            badge.text(displayText).show();
                            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                                console.log('Badge text set to:', displayText);
                            }
                            
                            // Add pulse animation for new notifications
                            if (count > parseInt(badge.data('previous-count') || 0)) {
                                badge.addClass('notification-pulse');
                                setTimeout(() => badge.removeClass('notification-pulse'), 3000);
                            }
                            badge.data('previous-count', count);
                        } else {
                            badge.hide();
                            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                                console.log('Badge hidden - no notifications');
                            }
                        }
                    } else {
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.log('Invalid response format:', response);
                        }
                        badge.hide();
                    }
                },
                error: function(xhr, status, error) {
                    // Silently handle errors - notifications are not critical
                    // Only log 404 errors in development
                    if (xhr.status === 404 && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
                        console.warn('Notification endpoint not found (404) - this is non-critical');
                    }
                    $('#notificationCount').hide();
                }
            });
        }

        // Helper function to update notification count immediately
        function updateNotificationCount(newCount) {
            const badge = $('#notificationCount');
            
            if (newCount > 0) {
                badge.text(newCount > 99 ? '99+' : newCount).show();
            } else {
                badge.hide();
            }
        }

        // Enhanced notification loading with filtering
        function loadNotifications(filter = 'all', limit = 10) {
            const path = getIncludesPath();
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Loading notifications from:', path + 'get_notifications.php');
            }
            
            // Show loading state with spinner
            $('#notificationList').html(`
                <div class="dropdown-item text-center text-muted">
                    <small><i class="fas fa-spinner fa-spin mr-1"></i>Loading notifications...</small>
                </div>
            `);
            
            // Update filter button states
            $('.dropdown-filter .btn').removeClass('active');
            $(`#filter${filter.charAt(0).toUpperCase() + filter.slice(1)}`).addClass('active');
            
            $.ajax({
                url: path + 'get_notifications.php',
                type: 'POST',
                data: { 
                    action: 'get_recent', 
                    limit: limit,
                    filter: filter,
                    _t: Date.now() // Cache buster to ensure fresh data
                },
                dataType: 'json',
                timeout: 15000,
                success: function(response) {
                    if (response.success && response.notifications) {
                        let html = '';
                        if (response.notifications.length > 0) {
                            response.notifications.forEach(function(notification) {
                                let timeAgo = 'Just now';
                                try {
                                    if (typeof moment !== 'undefined') {
                                        timeAgo = moment(notification.created_at).fromNow();
                                    } else {
                                        // Fallback if moment.js is not loaded
                                        const date = new Date(notification.created_at);
                                        const now = new Date();
                                        const diffMs = now - date;
                                        const diffMins = Math.floor(diffMs / 60000);
                                        const diffHours = Math.floor(diffMs / 3600000);
                                        const diffDays = Math.floor(diffMs / 86400000);
                                        
                                        if (diffMins < 1) timeAgo = 'Just now';
                                        else if (diffMins < 60) timeAgo = diffMins + 'm ago';
                                        else if (diffHours < 24) timeAgo = diffHours + 'h ago';
                                        else timeAgo = diffDays + 'd ago';
                                    }
                                } catch (e) {
                                    console.error('Error formatting time:', e);
                                    timeAgo = 'Recently';
                                }
                                
                                const notificationConfig = getNotificationConfig(notification.recipients);
                                const isUnread = notification.is_read === '0';
                                const unreadClass = isUnread ? 'unread-notification' : '';
                                
                                html += `
                                    <div class="dropdown-item notification-item ${unreadClass}" data-id="${notification.notification_id}" data-access-id="${notification.access_id||''}" data-read="${notification.is_read}">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 mr-3">
                                                <i class="fas ${notificationConfig.icon} fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge ${notificationConfig.badge} badge-sm mr-2">
                                                        ${notificationConfig.label}
                                                    </span>
                                                    <small class="text-muted">${timeAgo}</small>
                                                    ${isUnread ? '<span class="badge badge-danger badge-sm ml-2">New</span>' : ''}
                                                </div>
                                                <div class="notification-content">${notification.content}</div>
                                            </div>
                                            <div class="flex-shrink-0 ml-2">
                                                ${isUnread ? `
                                                    <button class="btn btn-sm btn-outline-success mark-read-btn" 
                                                            data-id="${notification.notification_id}" 
                                                            title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                ` : `
                                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Already read">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                `}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            html = `
                                <div class="dropdown-item text-center text-muted">
                                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                    <div><small>No ${filter} notifications</small></div>
                                </div>
                            `;
                        }
                        // Add "Show Less" button if loading more than default limit
                        if (limit > 10) {
                            html += `
                                <div class="dropdown-item text-center">
                                    <button class="btn btn-sm btn-outline-secondary" id="showLessNotifications">
                                        <i class="fas fa-chevron-up mr-1"></i>Show Less
                                    </button>
                                </div>
                            `;
                        }
                        
                        $('#notificationList').html(html);
                        
                        // Add handler for "Show Less" button
                        if (limit > 10) {
                            $('#showLessNotifications').on('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                loadNotifications('all', 10);
                            });
                        }
                    } else {
                        $('#notificationList').html(`
                            <div class="dropdown-item text-center text-muted">
                                <small>No notifications available</small>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    // Handle 404 errors gracefully (non-critical)
                    if (xhr.status === 404) {
                        $('#notificationList').html(`
                            <div class="dropdown-item text-center text-muted">
                                <small>Notifications service unavailable</small>
                            </div>
                        `);
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.warn('Notification endpoint not found (404) - this is non-critical');
                        }
                        return;
                    }
                    // Only log other errors in development
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        console.error('Notification loading error:', status, error);
                        console.error('Response text:', xhr.responseText);
                        console.error('Status code:', xhr.status);
                    }
                    let errorMessage = 'Error loading notifications';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Loading timeout - please try again';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred';
                    }
                    
                    $('#notificationList').html(`
                        <div class="dropdown-item text-center text-muted">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <small>${errorMessage}</small>
                        </div>
                    `);
                }
            });
        }

        // Get notification configuration based on type
        function getNotificationConfig(recipients) {
            const configs = {
                'couple_registration': {
                    icon: 'fa-user-plus text-success',
                    badge: 'badge-success',
                    label: 'Registration'
                },
                'schedule_update': {
                    icon: 'fa-calendar-alt text-info',
                    badge: 'badge-info',
                    label: 'Schedule'
                },
                'email': {
                    icon: 'fa-envelope text-warning',
                    badge: 'badge-warning',
                    label: 'Email'
                },
                'sms': {
                    icon: 'fa-sms text-success',
                    badge: 'badge-success',
                    label: 'SMS'
                },
                'system': {
                    icon: 'fa-cog text-primary',
                    badge: 'badge-primary',
                    label: 'System'
                }
            };
            
            return configs[recipients] || configs['system'];
        }

        // Load notifications when dropdown is shown
        $('li.nav-item.dropdown > a.dropdown-toggle').on('show.bs.dropdown', function() {
            // Get the current active filter (default to 'all' if none is active)
            const currentFilter = $('.dropdown-filter .btn.active').data('filter') || 
                                 ($('#filterUnread').hasClass('active') ? 'unread' : 'all');
            
            // Load notifications with the current filter
            loadNotifications(currentFilter, 10);
            
            // If it takes too long, show a retry option
            setTimeout(function() {
                if ($('#notificationList').text().includes('Loading')) {
                    $('#notificationList').html(`
                        <div class="dropdown-item text-center">
                            <small class="text-muted">Loading is taking longer than expected...</small>
                            <br>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadNotifications('${currentFilter}', 10)">
                                <i class="fas fa-redo mr-1"></i>Retry
                            </button>
                        </div>
                    `);
                }
            }, 8000); // Show retry after 8 seconds
        });

        // Add a manual refresh button to the notification dropdown header


        // See All button handler - navigate to notification table
        $('#seeAllBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Navigate to the notification table page
            window.location.href = '../notifications/notifications.php';
        });

        // Meatballs menu toggle functionality
        $('#meatballsBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $menu = $('#meatballsMenu');
            const $button = $(this);
            const isExpanded = $button.attr('aria-expanded') === 'true';
            
            if (isExpanded) {
                // Close menu
                $menu.removeClass('show');
                $button.attr('aria-expanded', 'false');
            } else {
                // Open menu
                $menu.addClass('show');
                $button.attr('aria-expanded', 'true');
            }
        });

        // Close menu when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown-menu-toggle').length) {
                $('#meatballsMenu').removeClass('show');
                $('#meatballsBtn').attr('aria-expanded', 'false');
            }
        });

        // Three-dot menu handlers
        $('#refreshNotifications').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Refresh notifications
            loadNotifications();
            
            // Show feedback
            Swal.fire({
                icon: 'success',
                title: 'Refreshed',
                text: 'Notifications refreshed successfully',
                timer: 1500,
                showConfirmButton: false
            });
        });

        $('#notificationSettings').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Navigate to notification settings or show settings modal
            Swal.fire({
                icon: 'info',
                title: 'Notification Settings',
                text: 'Notification settings feature coming soon!',
                confirmButtonText: 'OK'
            });
        });

        // Filter buttons handlers
        $('#filterAll').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            loadNotifications('all');
        });

        $('#filterUnread').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            loadNotifications('unread');
        });

        // Mark all notifications as read
        $('#markAllRead').on('click', function(e) {
            e.preventDefault();
            
            // Show loading state
            const $button = $(this);
            const originalText = $button.text();
            $button.text('Marking...').prop('disabled', true);
            
            $.ajax({
                url: getIncludesPath() + 'get_notifications.php',
                type: 'POST',
                data: { action: 'mark_all_read' },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        // Update notification count to 0 immediately
                        $('#notificationCount').hide();
                        
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'All notifications marked as read',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Reload notifications to show updated status
                        loadNotifications();
                        
                        // Reload notification count to ensure it's accurate
                        setTimeout(() => {
                            loadNotificationCount();
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to mark notifications as read'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Silently handle 404 errors (non-critical)
                    if (xhr.status === 404) {
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.warn('Notification endpoint not found (404) - this is non-critical');
                        }
                        return;
                    }
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        console.error('Mark all read error:', status, error);
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to mark notifications as read. Please try again.'
                    });
                },
                complete: function() {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Enhanced notification click handlers
        $(document).on('click', '.notification-item', function(e) {
            e.preventDefault();
            const notificationId = $(this).data('id');
            const notificationContent = $(this).find('.notification-content').text();
            const accessId = $(this).data('access-id');
            const notificationTime = $(this).find('.text-muted').text();
            const notificationIcon = $(this).find('.fas').attr('class');
            const notificationBadge = $(this).find('.badge').text();
            
            console.log('Notification clicked:', {
                id: notificationId,
                content: notificationContent,
                time: notificationTime,
                icon: notificationIcon,
                badge: notificationBadge
            });
            
            // Show notification detail modal
            // If this is a reschedule request, jump straight to scheduling page filtered/highlighted
            if (typeof notificationContent === 'string' && notificationContent.toLowerCase().includes('reschedule request') && accessId) {
                window.location.href = '../couple_scheduling/couple_scheduling.php?access_id=' + accessId + '&action=reschedule';
                return;
            }
            showNotificationDetail(notificationId, notificationIcon, notificationContent, notificationTime, notificationBadge);
        });

        // Function to show notification detail modal
        function showNotificationDetail(notificationId, notificationIcon, notificationContent, notificationTime, notificationBadge) {
            // Determine icon and type based on the icon class
            let iconClass = 'fa-info-circle text-primary';
            let typeText = 'System';
            
            if (notificationIcon.includes('fa-envelope')) {
                iconClass = 'fa-envelope text-info';
                typeText = 'Email';
            } else if (notificationIcon.includes('fa-sms')) {
                iconClass = 'fa-sms text-success';
                typeText = 'SMS';
            } else if (notificationIcon.includes('fa-user-plus')) {
                iconClass = 'fa-user-plus text-success';
                typeText = 'Registration';
            } else if (notificationIcon.includes('fa-calendar-alt')) {
                iconClass = 'fa-calendar-alt text-info';
                typeText = 'Schedule';
            } else if (notificationIcon.includes('fa-cog')) {
                iconClass = 'fa-cog text-primary';
                typeText = 'System';
            }
            
            // Enhanced modal content
            const modalContent = `
                <div class="notification-detail">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0 mr-3">
                                    <i class="fas ${iconClass} fa-2x"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${typeText} Notification</h6>
                                    <small class="text-muted">${notificationTime}</small>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge badge-primary">${notificationBadge}</span>
                                </div>
                            </div>
                            <div class="notification-content p-3 bg-light rounded border-left border-primary">
                                <h6 class="mb-2 text-primary">Content:</h6>
                                <p class="mb-0">${notificationContent}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Populate modal content
            $('#notificationDetailContent').html(modalContent);
            
            // Store notification ID for mark as read functionality
            $('#markAsReadBtn').data('notification-id', notificationId);
            
            console.log('Showing modal for notification ID:', notificationId);
            
            // Show modal
            $('#notificationDetailModal').modal('show');
        }

        // Mark as read button handler
        $(document).on('click', '#markAsReadBtn', function() {
            const notificationId = $(this).data('notification-id');
            if (notificationId) {
                const $button = $(this);
                const originalText = $button.html();
                
                // Show loading state
                $button.html('<i class="fas fa-spinner fa-spin mr-1"></i>Marking...').prop('disabled', true);
                
                $.ajax({
                    url: getIncludesPath() + 'get_notifications.php',
                    type: 'POST',
                    data: { action: 'mark_read', notification_id: notificationId },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(response) {
                        if (response.success) {
                            // Close modal
                            $('#notificationDetailModal').modal('hide');
                            
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Notification marked as read',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            
                            // Refresh notification count and list
                            loadNotificationCount();
                            loadNotifications();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to mark notification as read'
                            });
                        }
                    },
                    error: function(xhr) {
                        // Silently handle 404 errors (non-critical)
                        if (xhr && xhr.status === 404) {
                            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                                console.warn('Notification endpoint not found (404) - this is non-critical');
                            }
                            return;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to mark notification as read. Please try again.'
                        });
                    },
                    complete: function() {
                        $button.html(originalText).prop('disabled', false);
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Notification ID not found'
                });
            }
        });

        // Focus management for notification modal
        $('#notificationDetailModal').on('shown.bs.modal', function() {
            const modal = this;
            
            // Focus on the modal title for accessibility
            const modalTitle = $(modal).find('.modal-title');
            if (modalTitle.length) {
                setTimeout(() => {
                    modalTitle.focus();
                }, 100);
            }
            
            // Set up focus trap within the modal
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length > 0) {
                const firstFocusableElement = focusableElements[0];
                const lastFocusableElement = focusableElements[focusableElements.length - 1];
                
                // Handle tab key to trap focus
                $(modal).off('keydown').on('keydown', function(e) {
                    if (e.key === 'Tab') {
                        if (e.shiftKey) {
                            if (document.activeElement === firstFocusableElement) {
                                e.preventDefault();
                                lastFocusableElement.focus();
                            }
                        } else {
                            if (document.activeElement === lastFocusableElement) {
                                e.preventDefault();
                                firstFocusableElement.focus();
                            }
                        }
                    }
                });
            }
        });

        // Clean up focus trap when modal is hidden
        $('#notificationDetailModal').on('hidden.bs.modal', function() {
            $(this).off('keydown');
        });

        // Core function to mark a single notification as read
        function markNotificationAsRead(notificationId, $button) {
            if (!$button || $button.prop('disabled')) return;
            const $notificationItem = $button.closest('.notification-item');
            
            console.log('Marking notification as read:', notificationId);
            
            // Show loading state
            $button.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            
            $.ajax({
                url: getIncludesPath() + 'get_notifications.php',
                type: 'POST',
                data: { action: 'mark_read', notification_id: notificationId },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    console.log('Mark as read response:', response);
                    if (response.success) {
                        // Update the notification item in place to show it as read
                        // Remove "New" badge if present
                        $notificationItem.find('.badge-danger').remove();
                        
                        // Remove unread styling
                        $notificationItem.removeClass('unread-notification');
                        
                        // Update the mark as read button to show it's already read
                        const $markReadBtn = $notificationItem.find('.mark-read-btn');
                        
                        // Remove all event handlers to prevent re-triggering
                        $markReadBtn.off('click mouseenter mouseleave');
                        
                        // Update button appearance and state
                        $markReadBtn.removeClass('btn-outline-success mark-read-btn')
                                   .addClass('btn-outline-secondary')
                                   .prop('disabled', true)
                                   .attr('title', 'Already read')
                                   .html('<i class="fas fa-check-double"></i>')
                                   .removeClass('mark-read-btn'); // Remove class so it won't be targeted by event handlers
                        
                        // Update data-read attribute
                        $notificationItem.attr('data-read', '1');
                        
                        // If filter is set to "Unread", remove the notification from view
                        const currentFilter = $('.dropdown-filter .btn.active').data('filter') || 'all';
                        if (currentFilter === 'unread') {
                            $notificationItem.fadeOut(300, function() {
                                $(this).remove();
                                if ($('.notification-item').length === 0) {
                                    $('#notificationList').html(`
                                        <div class="dropdown-item text-center text-muted">
                                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                            <div><small>No unread notifications</small></div>
                                        </div>
                                    `);
                                }
                            });
                        }
                        
                        // Immediately reload the count from server
                        loadNotificationCount();
                        
                        // Don't reload the list immediately - trust the visual update
                        // The list will be refreshed when the dropdown is opened again or user manually refreshes
                        // This prevents the reload from overwriting our visual update
                    } else {
                        console.log('Mark as read failed:', response);
                        $button.html('<i class="fas fa-check"></i>').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    // Silently handle 404 errors (non-critical)
                    if (xhr.status === 404) {
                        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                            console.warn('Notification endpoint not found (404) - this is non-critical');
                        }
                        $button.html('<i class="fas fa-check"></i>').prop('disabled', false);
                        return;
                    }
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        console.error('Mark as read error:', status, error);
                        console.error('Response text:', xhr.responseText);
                    }
                    $button.html('<i class="fas fa-check"></i>').prop('disabled', false);
                }
            });
        }

        // Hover-to-mark-as-read (with small delay to avoid accidental triggers)
        $(document).on('mouseenter', '.mark-read-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            if (!id) return;
            // store timeout handle to the element to allow cancel
            const handle = setTimeout(() => markNotificationAsRead(id, $btn), 350);
            $btn.data('hover-timeout', handle);
        });
        $(document).on('mouseleave', '.mark-read-btn', function() {
            const handle = $(this).data('hover-timeout');
            if (handle) clearTimeout(handle);
        });
        // Keep click handler for accessibility (optional)
        $(document).on('click', '.mark-read-btn', function(e){
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const id = $btn.data('id');
            if (id) markNotificationAsRead(id, $btn);
        });





        // Initialize notification system (disabled on counselor pages)
        $(document).ready(function() {
            var isCounselor = (window.location.pathname.indexOf('/counselor/') !== -1);
            if (!isCounselor) {
                // Load initial notification count
                loadNotificationCount();

                // Load notifications when dropdown is shown
                $('.notification-dropdown').on('show.bs.dropdown', function() {
                    // Get the current active filter (default to 'all' if none is active)
                    const currentFilter = $('.dropdown-filter .btn.active').data('filter') || 
                                         ($('#filterUnread').hasClass('active') ? 'unread' : 'all');
                    loadNotifications(currentFilter, 10);
                });
            }



        // Load previous notifications handler (append older items)
        $('#loadPreviousNotifications').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const path = getIncludesPath();
            const currentItems = $('#notificationList .notification-item').length;
            const nextLimit = currentItems + 10; // load 10 more each click
            
            // Show inline loader at the bottom
            const loaderId = 'prev-loader';
            if (!document.getElementById(loaderId)) {
                $('#notificationList').append(`
                    <div id="${loaderId}" class="dropdown-item text-center text-muted">
                        <small><i class="fas fa-spinner fa-spin mr-1"></i>Loading more...</small>
                    </div>
                `);
            }
            
            $.ajax({
                url: path + 'get_notifications.php',
                type: 'POST',
                data: { action: 'get_recent', limit: nextLimit, filter: 'all' },
                dataType: 'json',
                timeout: 15000,
                success: function(response) {
                    if (response.success && Array.isArray(response.notifications)) {
                        // Build only the additional items (avoid duplicates)
                        const existingIds = new Set($('#notificationList .notification-item').map(function(){ return $(this).data('id'); }).get());
                        let extraHtml = '';
                        response.notifications.forEach(function(notification){
                            if (existingIds.has(notification.notification_id)) return;
                            
                            let timeAgo = 'Just now';
                            try {
                                if (typeof moment !== 'undefined') {
                                    timeAgo = moment(notification.created_at).fromNow();
                                } else {
                                    // Fallback if moment.js is not loaded
                                    const date = new Date(notification.created_at);
                                    const now = new Date();
                                    const diffMs = now - date;
                                    const diffMins = Math.floor(diffMs / 60000);
                                    const diffHours = Math.floor(diffMs / 3600000);
                                    const diffDays = Math.floor(diffMs / 86400000);
                                    
                                    if (diffMins < 1) timeAgo = 'Just now';
                                    else if (diffMins < 60) timeAgo = diffMins + 'm ago';
                                    else if (diffHours < 24) timeAgo = diffHours + 'h ago';
                                    else timeAgo = diffDays + 'd ago';
                                }
                            } catch (e) {
                                console.error('Error formatting time:', e);
                                timeAgo = 'Recently';
                            }
                            
                            const cfg = getNotificationConfig(notification.recipients);
                            const isUnread = notification.is_read === '0';
                            const unreadClass = isUnread ? 'unread-notification' : '';
                            extraHtml += `
                                <div class="dropdown-item notification-item ${unreadClass}" data-id="${notification.notification_id}" data-read="${notification.is_read}">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 mr-3">
                                            <i class="fas ${cfg.icon} fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="badge ${cfg.badge} badge-sm mr-2">${cfg.label}</span>
                                                <small class="text-muted">${timeAgo}</small>
                                                ${isUnread ? '<span class="badge badge-danger badge-sm ml-2">New</span>' : ''}
                                            </div>
                                            <div class="notification-content">${notification.content}</div>
                                        </div>
                                        <div class="flex-shrink-0 ml-2">
                                            ${isUnread ? `
                                                <button class="btn btn-sm btn-outline-success mark-read-btn" data-id="${notification.notification_id}" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            ` : `
                                                <button class="btn btn-sm btn-outline-secondary" disabled title="Already read">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            `}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        // Remove loader and append extra items
                        $('#' + loaderId).remove();
                        if (extraHtml.length) {
                            $('#notificationList').append(extraHtml);
                        } else {
                            $('#notificationList').append(`
                                <div class="dropdown-item text-center text-muted">
                                    <small>No older notifications</small>
                                </div>
                            `);
                        }
                    } else {
                        $('#' + loaderId).remove();
                    }
                },
                error: function(){
                    $('#' + loaderId).remove();
                }
            });
        });

        // Preload notifications in the background for faster dropdown opening
        // Use 'all' filter by default for preload (user can filter when dropdown opens)
        setTimeout(function() {
            loadNotifications('all', 10);
        }, 2000);

            // Refresh notification count every 30 seconds (not on counselor pages)
            if (!isCounselor) setInterval(loadNotificationCount, 30000);
        });

        // Make loader callable from inline buttons
        window.loadNotifications = loadNotifications;

        // Client-side idle timeout helper: warn at 9 minutes, logout at 10
        (function setupIdleTimeout(){
            var idleMs = 0;
            var warnAt = 9 * 60 * 1000;  // 9 minutes
            var logoutAt = 10 * 60 * 1000; // 10 minutes
            var warned = false;
            var lastPing = 0;
            function resetIdle(){ idleMs = 0; warned = false; }

            // Listen to broad set of interactions (capture scrolls on any element)
            var events = ['pointermove','mousemove','mousedown','keydown','keyup','click','wheel','touchstart','touchmove','input','change','focus'];
            events.forEach(function(evt){ document.addEventListener(evt, resetIdle, { passive: true, capture: true }); });
            document.addEventListener('scroll', resetIdle, { passive: true, capture: true });
            document.addEventListener('visibilitychange', function(){ if (!document.hidden) resetIdle(); }, { capture: true });

            // Heartbeat: ping server every 4 minutes while active to refresh PHP session
            function heartbeat(){
                var now = Date.now();
                if (idleMs < warnAt && (now - lastPing) > 4 * 60 * 1000){
                    lastPing = now;
                    fetch(getIncludesPath() + 'ping.php', { cache: 'no-store', credentials: 'same-origin' })
                        .catch(function(err){
                            // Silently handle ping errors - not critical for functionality
                            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                                console.warn('Ping failed (non-critical):', err);
                            }
                        });
                }
            }

            setInterval(function(){
                idleMs += 1000;
                heartbeat();
                if (!warned && idleMs >= warnAt && idleMs < logoutAt){
                    warned = true;
                    Swal.fire({
                        icon: 'warning',
                        title: 'You will be logged out soon',
                        text: 'No activity detected. You will be logged out in 1 minute.',
                        showConfirmButton: false,
                        timer: 5000
                    });
                }
                if (idleMs >= logoutAt){
                    window.location.href = '../logout.php';
                }
            }, 1000);
        })();
    });
    
    // Global DataTables sorting arrow fix
    // This fixes the "âà+" corruption issue across all DataTables in the project
    $(document).ready(function() {
        // Override DataTables default sorting arrows with proper Unicode characters
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                /* Fix DataTables sorting arrows globally */
                .dataTables_wrapper .dataTables_thead .sorting:after,
                .dataTables_wrapper .dataTables_thead .sorting_asc:after,
                .dataTables_wrapper .dataTables_thead .sorting_desc:after {
                    content: "";
                    display: none;
                }
                
                .dataTables_wrapper .dataTables_thead .sorting:before {
                    content: "↕";
                    font-size: 12px;
                    color: #6c757d;
                    margin-left: 5px;
                    position: absolute;
                    right: 1em;
                    bottom: 0.9em;
                }
                
                .dataTables_wrapper .dataTables_thead .sorting_asc:before {
                    content: "↑";
                    font-size: 12px;
                    color: #007bff;
                    margin-left: 5px;
                    position: absolute;
                    right: 1em;
                    bottom: 0.9em;
                    opacity: 1;
                }
                
                .dataTables_wrapper .dataTables_thead .sorting_desc:before {
                    content: "↓";
                    font-size: 12px;
                    color: #007bff;
                    margin-left: 5px;
                    position: absolute;
                    right: 1em;
                    bottom: 0.9em;
                    opacity: 1;
                }
            `)
            .appendTo('head');
    });
</script>