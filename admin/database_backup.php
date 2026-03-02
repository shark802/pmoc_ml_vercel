<?php
// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

require_once '../includes/session.php';

// Only allow superadmin to access backup
if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') {
    header('Location: admin_dashboard.php?error=unauthorized');
    exit();
}

// Backup directory
$backupDir = '../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Get list of existing backups
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filePath = $backupDir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filePath),
                'date' => filemtime($filePath),
                'path' => $filePath
            ];
        }
    }
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get backup settings from database or use defaults
$backupSettings = [
    'auto_backup_enabled' => false,
    'backup_frequency' => 'daily', // daily, weekly, monthly
    'retention_days' => 30,
    'last_backup' => null
];

// Try to get settings from database
try {
    $settingsQuery = "SELECT setting_key, setting_value FROM backup_settings";
    $settingsResult = $conn->query($settingsQuery);
    if ($settingsResult) {
        while ($row = $settingsResult->fetch_assoc()) {
            $backupSettings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Table might not exist, use defaults
}

// Format file size
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Backup | Admin Panel</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .backup-card {
            transition: transform 0.2s;
        }
        .backup-card:hover {
            transform: translateY(-2px);
        }
        .backup-file-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 10px;
        }
        .backup-file-item:hover {
            background-color: #f8f9fa;
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
                    <i class="fas fa-database text-primary"></i>
                    <h4 class="mb-0">Database Backup</h4>
                </div>
                <p class="text-muted" style="margin-top:-6px;">Manage automatic and manual database backups</p>

                <div class="row">
                    <!-- Manual Backup Card -->
                    <div class="col-lg-6 col-md-12 mb-4">
                        <div class="card backup-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-download mr-2"></i>Manual Backup
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Create an immediate backup of the database.</p>
                                <div class="alert alert-info" style="font-size: 0.875rem; padding: 0.5rem 0.75rem;">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Note:</strong> Old backups are automatically deleted based on the retention period when you create a new backup.
                                </div>
                                <button type="button" class="btn btn-primary btn-lg btn-block" id="createBackupBtn">
                                    <i class="fas fa-database mr-2"></i>Create Backup Now
                                </button>
                                <div id="backupProgress" style="display:none;" class="mt-3">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <p class="text-center mt-2 mb-0">Creating backup...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Automatic Backup Settings Card -->
                    <div class="col-lg-6 col-md-12 mb-4">
                        <div class="card backup-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-cog mr-2"></i>Automatic Backup Settings
                                </h3>
                            </div>
                            <div class="card-body">
                                <form id="backupSettingsForm">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="autoBackupEnabled" 
                                                   <?php echo $backupSettings['auto_backup_enabled'] ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="autoBackupEnabled">
                                                Enable Automatic Backups
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Backup Frequency</label>
                                        <select class="form-control" id="backupFrequency" <?php echo !$backupSettings['auto_backup_enabled'] ? 'disabled' : ''; ?>>
                                            <option value="daily" <?php echo $backupSettings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                            <option value="weekly" <?php echo $backupSettings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                            <option value="monthly" <?php echo $backupSettings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Retention Period (Days)</label>
                                        <input type="number" class="form-control" id="retentionDays" 
                                               value="<?php echo htmlspecialchars($backupSettings['retention_days']); ?>" 
                                               min="1" max="365" <?php echo !$backupSettings['auto_backup_enabled'] ? 'disabled' : ''; ?>>
                                        <small class="form-text text-muted">
                                            Backups older than this will be automatically deleted (applies to both manual and automatic backups)
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-save mr-2"></i>Save Settings
                                    </button>
                                </form>
                                <?php if ($backupSettings['last_backup']): ?>
                                <div class="mt-3">
                                    <small class="text-muted">Last automatic backup: <?php echo date('Y-m-d H:i:s', strtotime($backupSettings['last_backup'])); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup History -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fas fa-history mr-2"></i>Backup History
                                </h3>
                                <div class="card-tools">
                                    <span class="badge badge-info"><?php echo count($backups); ?> backups</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($backups)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i>No backups found. Create your first backup now!
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="backupHistoryTable" class="table table-bordered table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Filename</th>
                                                    <th>Size</th>
                                                    <th>Date Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backups as $backup): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-file-archive mr-2"></i>
                                                        <?php echo htmlspecialchars($backup['filename']); ?>
                                                    </td>
                                                    <td><?php echo formatBytes($backup['size']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i:s', $backup['date']); ?></td>
                                                    <td style="white-space: nowrap;">
                                                        <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-sm btn-outline-primary" style="margin-right: 5px;" title="Download">
                                                            <i class="fas fa-download mr-1"></i> Download
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-warning restore-backup" 
                                                                data-filename="<?php echo htmlspecialchars($backup['filename']); ?>" 
                                                                data-date="<?php echo date('Y-m-d H:i:s', $backup['date']); ?>"
                                                                title="Restore">
                                                            <i class="fas fa-undo mr-1"></i> Restore
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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
<script>
$(document).ready(function() {
    // Initialize DataTable for backup history
    <?php if (!empty($backups)): ?>
    $('#backupHistoryTable').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[2, 'desc']], // Sort by Date Created (newest first)
        columnDefs: [
            { "width": "200px", "targets": 3 } // Actions column - for Download and Restore buttons
        ]
    });
    <?php endif; ?>

    // Toggle form fields based on auto backup switch
    $('#autoBackupEnabled').on('change', function() {
        const enabled = $(this).is(':checked');
        $('#backupFrequency, #retentionDays').prop('disabled', !enabled);
    });

    // Create manual backup
    $('#createBackupBtn').on('click', function() {
        const btn = $(this);
        const progress = $('#backupProgress');
        
        btn.prop('disabled', true);
        progress.show();
        
        $.ajax({
            url: 'backup_database.php',
            method: 'POST',
            data: { action: 'create_backup' },
            dataType: 'json',
            success: function(response) {
                progress.hide();
                btn.prop('disabled', false);
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup Created',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Backup Failed',
                        text: response.message || 'Failed to create backup'
                    });
                }
            },
            error: function(xhr) {
                progress.hide();
                btn.prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to create backup. Please try again.'
                });
            }
        });
    });

    // Save backup settings
    $('#backupSettingsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            auto_backup_enabled: $('#autoBackupEnabled').is(':checked') ? 1 : 0,
            backup_frequency: $('#backupFrequency').val(),
            retention_days: $('#retentionDays').val()
        };
        
        $.ajax({
            url: 'backup_database.php',
            method: 'POST',
            data: {
                action: 'save_settings',
                ...formData
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Saved',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to save settings'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save settings. Please try again.'
                });
            }
        });
    });

    // Restore backup
    $(document).on('click', '.restore-backup', function() {
        const filename = $(this).data('filename');
        const backupDate = $(this).data('date');
        
        // First confirmation - warning about data loss
        Swal.fire({
            title: '⚠️ Restore Database?',
            html: `
                <div class="text-left">
                    <p><strong>Warning: This action will replace ALL current database data!</strong></p>
                    <ul class="text-left" style="padding-left: 20px;">
                        <li>All current data will be <strong>permanently deleted</strong></li>
                        <li>Database will be restored to: <strong>${backupDate}</strong></li>
                        <li>Any data created after this backup will be <strong>lost</strong></li>
                        <li>A backup of current database will be created automatically</li>
                        <li>All users will need to log in again</li>
                    </ul>
                    <p class="mt-3"><strong>Backup file:</strong> ${filename}</p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'I understand, continue',
            cancelButtonText: 'Cancel',
            input: 'text',
            inputPlaceholder: 'Type RESTORE to confirm',
            inputValidator: (value) => {
                if (value !== 'RESTORE') {
                    return 'You must type RESTORE to confirm';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Second confirmation - final check
                Swal.fire({
                    title: 'Final Confirmation',
                    html: `
                        <p>Are you absolutely sure you want to restore from:</p>
                        <p><strong>${filename}</strong></p>
                        <p class="text-danger"><strong>This cannot be undone!</strong></p>
                    `,
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, restore now',
                    cancelButtonText: 'Cancel',
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            const response = await $.ajax({
                                url: 'backup_database.php',
                                method: 'POST',
                                data: {
                                    action: 'restore_backup',
                                    filename: filename
                                },
                                dataType: 'json',
                                timeout: 1800000, // 30 minutes timeout for large databases
                                beforeSend: function() {
                                    // Show progress message
                                    Swal.showLoading();
                                }
                            });
                            
                            // Check if response is valid JSON
                            if (!response) {
                                throw new Error('No response from server');
                            }
                            
                            if (!response.success) {
                                Swal.hideLoading();
                                Swal.showValidationMessage(response.message || 'Restore failed');
                                return false; // Prevent closing the modal
                            }
                            
                            Swal.hideLoading();
                            return response;
                        } catch (error) {
                            Swal.hideLoading();
                            
                            // Handle different error types
                            let errorMessage = 'Failed to restore database';
                            
                            // Check for timeout
                            if (error.status === 'timeout' || error.statusText === 'timeout' || 
                                error.message?.includes('timeout') || error.status === 0) {
                                errorMessage = 'Restore operation timed out or connection was lost. The database may be too large. Please try restoring via phpMyAdmin or contact your administrator.';
                            } 
                            // Check for parse error (invalid JSON)
                            else if (error.status === 200 && error.responseText) {
                                // Server returned something but not valid JSON
                                try {
                                    const parsed = JSON.parse(error.responseText);
                                    errorMessage = parsed.message || 'Restore failed';
                                } catch (e) {
                                    errorMessage = 'Server returned invalid response. Please check server logs.';
                                }
                            }
                            // Check for JSON error response
                            else if (error.responseJSON && error.responseJSON.message) {
                                errorMessage = error.responseJSON.message;
                            } 
                            // Check for other error messages
                            else if (error.message) {
                                errorMessage = error.message;
                            } else if (error.statusText) {
                                errorMessage = error.statusText;
                            } else if (error.status === 500) {
                                errorMessage = 'Server error occurred during restore. Please check server logs or try again.';
                            }
                            
                            Swal.showValidationMessage(errorMessage);
                            return false; // Prevent closing the modal
                        }
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    // Check if confirmed and value exists (restore was successful)
                    if (result.isConfirmed && result.value && result.value.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Database Restored!',
                            html: `
                                <p>Database has been successfully restored from:</p>
                                <p><strong>${filename}</strong></p>
                                ${result.value.safety_backup ? `<p class="mt-2"><small>Safety backup created: <strong>${result.value.safety_backup}</strong></small></p>` : ''}
                                <p class="mt-3">You will be redirected to login page...</p>
                            `,
                            timer: 3000,
                            showConfirmButton: false
                        }).then(() => {
                            // Redirect to logout/login page
                            window.location.href = '../logout.php?restored=1';
                        });
                    } else if (result.isConfirmed && (!result.value || !result.value.success)) {
                        // Restore failed but modal was confirmed (shouldn't happen, but handle it)
                        Swal.fire({
                            icon: 'error',
                            title: 'Restore Failed',
                            text: result.value?.message || 'Failed to restore database. Please try again.',
                            confirmButtonText: 'OK'
                        });
                    }
                    // If cancelled, do nothing (modal just closes)
                });
            }
        });
    });

});
</script>
</body>
</html>

