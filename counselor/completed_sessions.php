<?php
require_once '../includes/session.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Completed Sessions | Counselor</title>
    <?php include '../includes/header.php'; ?>
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
                        <i class="fas fa-check-circle text-success"></i>
                        <h4 class="mb-0">Completed Sessions</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">View all sessions where couples have been marked as present</p>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>

                    <!-- Completed Sessions Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Completed Sessions</h3>
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
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-hover" id="completedSessionsTable">
                                            <thead>
                                                <tr>
                                                    <th>Couple</th>
                                                    <th>Date</th>
                                                    <th>Session Type</th>
                                                    <th>Status</th>
                                                    <th>Attendance</th>
                                                    <th>Completed At</th>
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
            loadCompletedSessions('week'); // Load with default filter
            
            // Filter button handlers
            $('button[data-filter]').on('click', function(e) {
                e.preventDefault();
                
                // Update active button
                $('button[data-filter]').removeClass('btn-primary active').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
                
                var filter = $(this).data('filter');
                loadCompletedSessions(filter);
            });
        });

        let currentFilter = 'week'; // Default filter

        function loadCompletedSessions(filter = 'week') {
            currentFilter = filter;
            $.ajax({
                url: 'get_completed_sessions.php',
                method: 'GET',
                data: { filter: filter },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let html = '';
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(session) {
                                // Map session type to badge color
                                let sessionTypeBadge = '';
                                if (session.session_type.indexOf('Orientation + Counseling') !== -1 || 
                                    (session.session_type.indexOf('Counseling') !== -1 && session.session_type.indexOf('Orientation') !== -1)) {
                                    // Orientation + Counseling -> warning (yellow/orange)
                                    sessionTypeBadge = '<span class="badge badge-warning">' + session.session_type + '</span>';
                                } else if (session.session_type.indexOf('Counseling') !== -1 && session.session_type.indexOf('Orientation') === -1) {
                                    // Counseling only -> warning (yellow/orange)
                                    sessionTypeBadge = '<span class="badge badge-warning">' + session.session_type + '</span>';
                                } else {
                                    // Orientation only -> info (blue)
                                    sessionTypeBadge = '<span class="badge badge-info">' + session.session_type + '</span>';
                                }

                                html += `
                                    <tr>
                                        <td>${session.couple_name}</td>
                                        <td>${session.date_only}</td>
                                        <td>${sessionTypeBadge}</td>
                                        <td><span class="badge badge-success">${session.status}</span></td>
                                        <td><span class="badge badge-success">Present</span></td>
                                        <td>${session.completed_at || 'N/A'}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            html = '<tr><td colspan="6" class="text-center text-muted">No completed sessions found</td></tr>';
                        }
                        // Destroy existing DataTable if it exists BEFORE updating HTML
                        if ($.fn.DataTable && $.fn.dataTable.isDataTable('#completedSessionsTable')) {
                            try {
                                $('#completedSessionsTable').DataTable().destroy();
                            } catch (e) {
                                console.warn('Error destroying DataTable:', e);
                            }
                        }
                        
                        // Update table body
                        $('#completedSessionsTable tbody').html(html);
                        
                        // Initialize DataTable only if there are actual sessions
                        if (response.data && response.data.length > 0 && $.fn.DataTable) {
                            try {
                                $('#completedSessionsTable').DataTable({ 
                                    responsive: true, 
                                    autoWidth: false,
                                    order: [[1, "desc"]], // Sort by date descending
                                    destroy: true // Allow re-initialization
                                });
                            } catch (e) {
                                console.error('DataTables initialization error:', e);
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading completed sessions:', error);
                    console.error('Response:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load completed sessions. Please refresh the page.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    $('#completedSessionsTable tbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading completed sessions</td></tr>');
                }
            });
        }
    </script>
</body>

</html>

