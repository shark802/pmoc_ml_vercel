<?php
require_once '../includes/session.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BCPDO | <?= htmlspecialchars($_SESSION['position'] ?? 'Counselor') ?> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Dashboard') ?> Dashboard</title>
    <?php include '../includes/header.php'; ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <style>
            .kpi-row { display:flex; flex-wrap:wrap; margin-right:-7.5px; margin-left:-7.5px; margin-bottom:20px; }
            .kpi-row>[class*="col-"] { padding-right:7.5px; padding-left:7.5px; margin-bottom:15px; }
            .kpi-card { flex:1 1 0; min-width:220px; display:flex; align-items:center; background:#fff; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,.15); padding:18px 20px; height:120px; position:relative; transition:all .3s ease; cursor:pointer; }
            body.dark-mode .kpi-card { background:#343a40; }
            .kpi-card:hover { transform:translateY(-3px); box-shadow:0 8px 25px rgba(0,123,255,.25); }
            .kpi-icon { width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:1.8rem; margin-right:18px; color:#fff; transition:all .3s ease; }
            .kpi-card:hover .kpi-icon { transform:scale(1.1); }
            .kpi-couples { background-color:#007bff; }
            .kpi-orientations { background-color:#dc3545; }
            .kpi-counselings { background-color:#ffc107; }
            .kpi-certificates { background-color:#28a745; }
            .kpi-info { flex:1; position:relative; padding-right:0; }
            .kpi-title { font-size:1rem; color:#888; margin-bottom:2px; }
            .kpi-value { font-size:1.5rem; font-weight:700; color:#222; margin-bottom:8px; }
            .kpi-info .small-box-footer { position:absolute; right:0; bottom:0; margin:0; }
            @media (max-width: 768px){ .kpi-row{ flex-direction:column; } .kpi-card{ margin-bottom:12px; } }
        </style>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="d-flex align-items-center mb-4" style="gap:10px;">
                        <i class="fas fa-user-md text-primary"></i>
                        <h4 class="mb-0"><?= htmlspecialchars($_SESSION['position'] ?? 'Counselor') ?> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Dashboard') ?> Dashboard</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Manage your counseling sessions and provide feedback</p>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>

                    <!-- Counselor KPI Cards (Admin-style) -->
                    <div class="row kpi-row">
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_list/couple_list.php" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-couples">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Total Couples Registered</div>
                                        <div class="kpi-value" id="totalCouples">0</div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#007bff;">
                                            View All <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_scheduling/couple_scheduling.php" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-orientations">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Sessions This Week</div>
                                        <div class="kpi-value" id="upcomingSessions">0</div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#dc3545;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <a href="../couple_scheduling/couple_scheduling.php" style="text-decoration: none;">
                                <div class="kpi-card">
                                    <div class="kpi-icon kpi-counselings">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="kpi-info">
                                        <div class="kpi-title">Completed Sessions</div>
                                        <div class="kpi-value" id="completedSessions">0</div>
                                        <div class="small-box-footer d-block mt-1" style="font-size:0.95rem; color:#ffc107;">
                                            View <i class="fas fa-arrow-circle-right ml-1"></i>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h3 class="card-title mb-0">Quick Actions</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap" style="gap:10px;">
                                        <a href="../couple_list/couple_list.php" class="btn btn-primary flex-fill" style="min-width:220px;">
                                            <i class="fas fa-users mr-1"></i> View Couples
                                        </a>
                                        <a href="../certificates/certificates.php" class="btn btn-info flex-fill" style="min-width:220px;">
                                            <i class="fas fa-certificate mr-1"></i> Certificates
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Upcoming Sessions</h3>
                                    <div class="card-tools" style="display: flex; gap: 10px; align-items: center;">
                                        <button type="button" class="btn btn-sm btn-primary active" data-filter="week" id="filter-week">
                                            <i class="fas fa-calendar-week"></i> This Week
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="month" id="filter-month">
                                            <i class="fas fa-calendar-alt"></i> This Month
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="all" id="filter-all">
                                            <i class="fas fa-list"></i> All
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div></div>
                                        <button id="markAllPresentBtn" class="btn btn-sm btn-success" onclick="markAllPresent()">
                                            <i class="fas fa-check mr-1"></i>Mark All Present
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-hover" id="upcomingSessionsTable">
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
                                                <!-- Sessions will be loaded via AJAX -->
                                            </tbody>
                                        </table>
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
    </div>

    <script>
        $(document).ready(function() {
            // Show welcome toast
            showWelcomeToast();
            
            loadCounselorStats();
            loadUpcomingSessions('week'); // Load with default filter
            
            // Filter button handlers
            $('button[data-filter]').on('click', function(e) {
                e.preventDefault();
                
                // Update active button
                $('button[data-filter]').removeClass('btn-primary active').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
                
                var filter = $(this).data('filter');
                loadUpcomingSessions(filter);
            });
            
            // recent activity removed
        });

        function showWelcomeToast() {
            <?php if (!isset($_SESSION['counselor_welcome_shown'])): ?>
            const counselorName = '<?php echo htmlspecialchars($_SESSION['admin_name']); ?>';
            Swal.fire({
                title: 'Welcome, ' + counselorName + '!',
                text: 'Manage your counseling sessions and provide feedback',
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#f8f9fa',
                color: '#495057'
            });
            <?php $_SESSION['counselor_welcome_shown'] = true; ?>
            <?php endif; ?>
        }

        function loadCounselorStats() {
            $.ajax({
                url: 'counselor_stats.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#totalCouples').text(response.data.totalCouples);
                        $('#upcomingSessions').text(response.data.upcomingSessions);
                        $('#completedSessions').text(response.data.completedSessions);
                    }
                },
                error: function() {
                    console.log('Error loading counselor stats');
                }
            });
        }

        let upcomingSessionsCache = [];
        let currentFilter = 'week'; // Default filter

        function loadUpcomingSessions(filter = 'week') {
            currentFilter = filter;
            $.ajax({
                url: 'upcoming_sessions.php',
                method: 'GET',
                data: { filter: filter },
                dataType: 'json',
                success: function(response) {
                    console.log('Upcoming sessions response:', response);
                    if (response.status === 'success') {
                        upcomingSessionsCache = Array.isArray(response.data) ? response.data : [];
                        console.log('Found ' + upcomingSessionsCache.length + ' upcoming sessions');
                        let html = '';
                        if (upcomingSessionsCache.length === 0) {
                            html = '<tr><td colspan="6" class="text-center">No upcoming sessions found</td></tr>';
                        }
                        upcomingSessionsCache.forEach(function(session) {
                            let attendanceBadge = '';
                            let actionButtons = '';
                            
                            // Check if session date is today (only today's sessions can be marked)
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const sessionDate = session.session_date_raw ? new Date(session.session_date_raw) : null;
                            if (sessionDate) {
                                sessionDate.setHours(0, 0, 0, 0);
                            }
                            const isToday = sessionDate && sessionDate.getTime() === today.getTime();
                            
                            // Attendance badge
                            if (session.attendance_status === 'present') {
                                attendanceBadge = '<span class="badge badge-success">Present</span>';
                            } else if (session.attendance_status === 'absent') {
                                attendanceBadge = '<span class="badge badge-danger">Absent</span>';
                            } else {
                                attendanceBadge = '<span class="badge badge-warning">Pending</span>';
                            }
                            
                            // Action buttons based on attendance status and date
                            if (session.attendance_status === 'pending') {
                                if (isToday) {
                                    // Enable buttons only for today's sessions
                                    actionButtons = `
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-success mr-2" title="Mark Present" onclick="markAttendance(${session.session_id}, ${session.access_id}, 'present', this)">
                                                <i class="fas fa-check"></i> Present
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" title="Mark Absent" onclick="markAttendance(${session.session_id}, ${session.access_id}, 'absent', this)">
                                                <i class="fas fa-times"></i> Absent
                                            </button>
                                        </div>
                                    `;
                                } else {
                                    // Disable buttons for past (auto-marked as absent) or future dates
                                    const tooltipText = sessionDate && sessionDate < today 
                                        ? 'Past sessions are automatically marked as absent' 
                                        : 'Cannot mark attendance for future sessions';
                                    actionButtons = `
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-success mr-2" disabled title="${tooltipText}">
                                                <i class="fas fa-check"></i> Present
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" disabled title="${tooltipText}">
                                                <i class="fas fa-times"></i> Absent
                                            </button>
                                        </div>
                                    `;
                                }
                            } else if (session.attendance_status === 'present') {
                                actionButtons = `
                                    <button class="btn btn-sm btn-success" disabled title="Present">
                                        <i class="fas fa-check-circle"></i> Present
                                    </button>
                                `;
                            } else if (session.attendance_status === 'absent') {
                                actionButtons = `
                                    <button class="btn btn-sm btn-outline-warning" title="Request Reschedule" onclick="rescheduleSession(${session.session_id}, ${session.access_id}, '${session.date_only}')">
                                        <i class="fas fa-calendar-alt"></i> Reschedule
                                    </button>
                                `;
                            } else {
                                actionButtons = '<span class="text-muted">No actions</span>';
                            }
                            
                            // Determine badge color for session type
                            let sessionTypeBadgeClass = 'badge-info'; // Default: blue for Orientation only
                            if (session.session_type.indexOf('Orientation + Counseling') !== -1 || 
                                (session.session_type.indexOf('Counseling') !== -1 && session.session_type.indexOf('Orientation') !== -1)) {
                                // Orientation + Counseling -> warning (yellow/orange)
                                sessionTypeBadgeClass = 'badge-warning';
                            } else if (session.session_type.indexOf('Counseling') !== -1 && session.session_type.indexOf('Orientation') === -1) {
                                // Counseling only -> warning (yellow/orange)
                                sessionTypeBadgeClass = 'badge-warning';
                            }
                            
                            html += `
                                <tr>
                                    <td>${session.couple_name}</td>
                                    <td>${session.date_only}</td>
                                    <td><span class="badge ${sessionTypeBadgeClass}">${session.session_type}</span></td>
                                    <td><span class="badge badge-${session.status === 'confirmed' ? 'success' : 'warning'}">${session.status}</span></td>
                                    <td>${attendanceBadge}</td>
                                    <td class="text-center">
                                        ${actionButtons}
                                    </td>
                                </tr>
                            `;
                        });
                        
                        // Destroy existing DataTable instance if it exists BEFORE updating HTML
                        if ($.fn.DataTable && $.fn.dataTable.isDataTable('#upcomingSessionsTable')) {
                            try {
                                $('#upcomingSessionsTable').DataTable().destroy();
                            } catch (e) {
                                console.warn('Error destroying DataTable:', e);
                            }
                        }
                        
                        // Update table body
                        $('#upcomingSessionsTable tbody').html(html);
                        
                        // Initialize DataTable only if there are actual data rows (not the "no data" message)
                        if (upcomingSessionsCache.length > 0 && $.fn.DataTable) {
                            try {
                                $('#upcomingSessionsTable').DataTable({ 
                                    responsive: true, 
                                    autoWidth: false,
                                    pageLength: 10,
                                    order: [[1, 'asc']], // Sort by date column
                                    destroy: true // Allow re-initialization
                                });
                            } catch (e) {
                                console.error('DataTables initialization error:', e);
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading upcoming sessions:', error);
                    console.error('Response:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load upcoming sessions. Please refresh the page.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        }

        async function markAllPresent(){
            const $btn = $('#markAllPresentBtn');
            try {
                if (!Array.isArray(upcomingSessionsCache) || upcomingSessionsCache.length === 0) {
                    Swal.fire({ icon:'info', title:'No Sessions', text:'There are no sessions to mark.' });
                    return;
                }
                
                // Get today's date in YYYY-MM-DD format
                const today = new Date();
                const todayStr = today.getFullYear() + '-' + 
                               String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                               String(today.getDate()).padStart(2, '0');
                
                // Filter: only pending sessions for today's date
                const pending = upcomingSessionsCache.filter(s => {
                    if (s.attendance_status !== 'pending') return false;
                    // Check if session date is today
                    const sessionDate = s.session_date_raw || s.session_date;
                    if (!sessionDate) return false;
                    // Extract date part (YYYY-MM-DD) from session_date_raw
                    const sessionDateStr = sessionDate.split(' ')[0]; // Get date part before time
                    return sessionDateStr === todayStr;
                });
                
                if (pending.length === 0) {
                    Swal.fire({ 
                        icon:'info', 
                        title:'Nothing To Do', 
                        text:'No pending sessions for today to mark as present.' 
                    });
                    return;
                }
                
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Marking...');
                let successCount = 0, failCount = 0;
                for (const s of pending) {
                    try {
                        await $.ajax({
                            url: 'attendance_mark.php',
                            method: 'POST',
                            dataType: 'json',
                            data: { schedule_id: s.session_id, access_id: s.access_id, status: 'present' }
                        });
                        successCount++;
                    } catch (e) {
                        failCount++;
                    }
                }
                loadUpcomingSessions(currentFilter);
                Swal.fire({ 
                    icon:'success', 
                    title:'Done', 
                    text:`Marked ${successCount} session${successCount !== 1 ? 's' : ''} as present for today${failCount ? `, ${failCount} failed` : ''}.` 
                });
            } finally {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Mark All Present');
            }
        }

        // recent activity removed

        function markAttendance(scheduleId, accessId, status, buttonElement){
            // Disable the button immediately to prevent double-clicks
            if (buttonElement) {
                $(buttonElement).prop('disabled', true);
            }
            
            $.ajax({
                url: 'attendance_mark.php',
                method: 'POST',
                data: {
                    schedule_id: scheduleId,
                    access_id: accessId,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: `Attendance marked as ${status}`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Update the row dynamically instead of reloading
                        if (buttonElement && status === 'present') {
                            var row = $(buttonElement).closest('tr');
                            var actionsCell = row.find('td').eq(5);
                            var attendanceCell = row.find('td').eq(4);
                            
                            // Update attendance badge
                            attendanceCell.html('<span class="badge badge-success">Present</span>');
                            
                            // Replace action buttons with disabled Present button
                            actionsCell.html(
                                '<button class="btn btn-sm btn-success" disabled title="Present">' +
                                '<i class="fas fa-check-circle"></i> Present' +
                                '</button>'
                            );
                        } else if (buttonElement && status === 'absent') {
                            // For absent, reload to show reschedule button
                            loadUpcomingSessions(currentFilter);
                        } else {
                            loadUpcomingSessions(currentFilter);
                        }
                    } else {
                        // Re-enable button on error
                        if (buttonElement) {
                            $(buttonElement).prop('disabled', false);
                        }
                        Swal.fire({
                            title: 'Error!',
                            text: response.message || 'Failed to mark attendance',
                            icon: 'error'
                        });
                    }
                },
                error: function() {
                    // Re-enable button on error
                    if (buttonElement) {
                        $(buttonElement).prop('disabled', false);
                    }
                    Swal.fire({
                        title: 'Error!',
                        text: 'Network error occurred',
                        icon: 'error'
                    });
                }
            });
        }
        
        function rescheduleSession(scheduleId, accessId, dateStr){
            Swal.fire({
                title: 'Reschedule Session',
                text: 'This will notify the admin to reschedule this session.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reschedule',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('../includes/get_notifications.php', { action: 'create_reschedule_notice', schedule_id: scheduleId, access_id: accessId, date: dateStr }, function(resp){
                        Swal.fire({
                            title: 'Reschedule Requested',
                            text: 'Admin has been notified to reschedule this session.',
                            icon: 'info',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }).fail(function(){
                        Swal.fire({ icon:'error', title:'Error', text:'Failed to notify admin. Please try again.' });
                    });
                }
            });
        }
    </script>
</body>

</html>
