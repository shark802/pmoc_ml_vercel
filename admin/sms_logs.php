<?php
require_once '../includes/session.php';
require_once '../includes/conn.php';

// Only allow admin or superadmin
if (!in_array($_SESSION['position'] ?? '', ['admin','superadmin'])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email and SMS Logs</title>
    <?php include '../includes/header.php'; ?>
  <style>
    .quick-action-btn {
      padding: 10px 15px;
      text-align: center;
      border-radius: 5px;
      transition: all 0.2s ease;
      font-weight: 500;
      border: none;
      margin-bottom: 0;
    }
    .quick-action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }
    .quick-action-btn i {
      margin-right: 6px;
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
          <div class="col-sm-6">
            <h1>Email and SMS Logs</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="admin_dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Email and SMS Logs</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <!-- Quick Actions -->
        <div class="row mb-3">
          <div class="col-md-4">
            <button type="button" class="btn btn-success btn-block quick-action-btn" onclick="sendReminder(1)" title="Send SMS reminder for tomorrow's sessions">
              <i class="fas fa-sms"></i> Send SMS Tomorrow
            </button>
          </div>
          <div class="col-md-4">
            <button type="button" class="btn btn-warning btn-block quick-action-btn" onclick="sendReminder(0)" title="Send SMS reminder for today's sessions">
              <i class="fas fa-sms"></i> Send SMS Today
            </button>
          </div>
          <div class="col-md-4">
            <button type="button" class="btn btn-primary btn-block quick-action-btn" onclick="showCustomReminderModal()" title="Send SMS reminder for custom date">
              <i class="fas fa-sms"></i> Send SMS Custom
            </button>
          </div>
        </div>

        <!-- Tabbed Interface -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Communication Logs</h3>
            <div class="card-tools" style="display: flex; gap: 10px; align-items: center;">
              <button type="button" class="btn btn-sm btn-primary active" data-tab="emails-tab">
                <i class="fas fa-envelope"></i> Email Logs
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="sms-tab">
                <i class="fas fa-sms"></i> SMS Logs
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tab="all-tab">
                <i class="fas fa-list"></i> All Logs
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="tab-content">
              <!-- Email Logs Tab -->
              <div class="tab-pane fade show active" id="emails-tab">
                <table id="emailLogsTable" class="table table-bordered table-striped table-hover">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Recipients</th>
                      <th>Subject</th>
                      <th>Couple Name</th>
                      <th>Status</th>
                      <th>Preview</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                    // Query email notifications from the notifications table
                    // Identify emails by checking if recipients contains @ (email address) - this is the primary indicator
                    // Also check content for email-related keywords as fallback
                    $emailSql = "
                      SELECT 
                        n.notification_id as id,
                        n.created_at,
                        n.recipients,
                        n.content as subject,
                        n.notification_status as status,
                        n.access_id,
                        GROUP_CONCAT(DISTINCT CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name
                      FROM notifications n
                      LEFT JOIN couple_profile cp ON cp.access_id = n.access_id
                      WHERE (n.recipients LIKE '%@%' 
                             OR n.recipients REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'
                             OR n.content LIKE '%BCPDO: Schedule%' 
                             OR n.content LIKE '%Email%' 
                             OR n.content LIKE '%Failed to send email%'
                             OR n.content LIKE '%CITY POPULATION%'
                             OR n.content LIKE '%PRE-MARRIAGE%'
                             OR n.content LIKE '%DEVELOPMENT OFFICE%')
                      GROUP BY n.notification_id
                      ORDER BY n.created_at DESC
                      LIMIT 100
                    ";
                    $emailRes = $conn->query($emailSql);
                    while ($emailRow = $emailRes->fetch_assoc()):
                      $statusBadge = '';
                      switch($emailRow['status']) {
                        case 'sent':
                          $statusBadge = '<span class="badge badge-success">Sent</span>';
                          break;
                        case 'failed':
                          $statusBadge = '<span class="badge badge-danger">Failed</span>';
                          break;
                        case 'confirmed':
                          $statusBadge = '<span class="badge badge-info">Confirmed</span>';
                          break;
                        default:
                          $statusBadge = '<span class="badge badge-secondary">' . ucfirst($emailRow['status']) . '</span>';
                      }
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($emailRow['created_at']) ?></td>
                      <td><?= htmlspecialchars($emailRow['recipients'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($emailRow['subject'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($emailRow['couple_name'] ?: '-') ?></td>
                      <td><?= $statusBadge ?></td>
                      <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewEmailLog(<?= (int)$emailRow['id'] ?>)">
                          <i class="fas fa-eye"></i> View
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
              </div>

              <!-- SMS Logs Tab -->
              <div class="tab-pane fade" id="sms-tab">
                <table id="smsLogsTable" class="table table-bordered table-striped table-hover">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Mobile</th>
                      <th>Session Type</th>
                      <th>Couple Name</th>
                      <th>Reminder</th>
                      <th>Status</th>
                      <th>Preview</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                    $sql = "
                      SELECT 
                        MIN(l.id) AS id,
                        MIN(l.created_at) AS created_at,
                        NULL AS schedule_id,
                        l.access_id,
                        l.run_label,
                        CASE WHEN SUM(CASE WHEN l.success = 1 THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END AS success,
                        MAX(COALESCE(l.session_type, s.session_type, s2.session_type, '')) AS session_type,
                        GROUP_CONCAT(DISTINCT TRIM(cp.contact_number) ORDER BY cp.sex DESC SEPARATOR ' / ') AS contact_numbers,
                        GROUP_CONCAT(DISTINCT CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name,
                        LEFT(MAX(l.message), 120) AS msg_preview
                      FROM sms_logs l
                      LEFT JOIN scheduling s ON s.schedule_id = l.schedule_id
                      /* Fallback: derive by access_id and log date if schedule_id not linked */
                      LEFT JOIN scheduling s2 ON s2.access_id = l.access_id AND DATE(s2.session_date) = DATE(l.created_at)
                      LEFT JOIN couple_profile cp ON cp.access_id = l.access_id
                      /* One row per couple per day per run (Morning/Afternoon) */
                      GROUP BY l.access_id, DATE(l.created_at), l.run_label
                      ORDER BY created_at DESC
                      LIMIT 1000
                    ";
                    $res = $conn->query($sql);
                    while ($row = $res->fetch_assoc()):
                      $statusBadge = $row['success'] ? '<span class="badge badge-success">Sent</span>' : '<span class="badge badge-danger">Failed</span>';
                      $runBadge = '<span class="badge badge-info">'.htmlspecialchars($row['run_label'] ?: 'Reminder').'</span>';
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($row['created_at']) ?></td>
                      <td><?= htmlspecialchars($row['contact_numbers'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($row['session_type'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($row['couple_name'] ?: '-') ?></td>
                      <td><?= $runBadge ?></td>
                      <td><?= $statusBadge ?></td>
                      <td title="Full message shown on click">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewLog(<?= (int)$row['id'] ?>)"><i class="fas fa-eye"></i> View</button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
              </div>

              <!-- All Logs Tab -->
              <div class="tab-pane fade" id="all-tab">
                <table id="allLogsTable" class="table table-bordered table-striped table-hover">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Type</th>
                      <th>Recipients</th>
                      <th>Content</th>
                      <th>Couple Name</th>
                      <th>Status</th>
                      <th>Preview</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                    // Combined query for all logs
                    $allLogsSql = "
                      (SELECT 
                        n.notification_id as id,
                        n.created_at,
                        'Email' as type,
                        n.recipients,
                        n.content as content_preview,
                        n.notification_status as status,
                        n.access_id,
                        GROUP_CONCAT(DISTINCT CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name
                      FROM notifications n
                      LEFT JOIN couple_profile cp ON cp.access_id = n.access_id
                      WHERE (n.recipients LIKE '%@%' 
                             OR n.recipients REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'
                             OR n.content LIKE '%BCPDO: Schedule%' 
                             OR n.content LIKE '%Email%' 
                             OR n.content LIKE '%Failed to send email%'
                             OR n.content LIKE '%CITY POPULATION%'
                             OR n.content LIKE '%PRE-MARRIAGE%'
                             OR n.content LIKE '%DEVELOPMENT OFFICE%')
                      GROUP BY n.notification_id)
                      UNION ALL
                      (SELECT 
                        MIN(l.id) AS id,
                        MIN(l.created_at) AS created_at,
                        'SMS' as type,
                        GROUP_CONCAT(DISTINCT TRIM(cp.contact_number) ORDER BY cp.sex DESC SEPARATOR ' / ') AS recipients,
                        LEFT(MAX(l.message), 120) AS content_preview,
                        CASE WHEN SUM(CASE WHEN l.success = 1 THEN 1 ELSE 0 END) > 0 THEN 'sent' ELSE 'failed' END AS status,
                        l.access_id,
                        GROUP_CONCAT(DISTINCT CONCAT(cp.first_name,' ',cp.last_name) ORDER BY cp.sex DESC SEPARATOR ' & ') AS couple_name
                      FROM sms_logs l
                      LEFT JOIN couple_profile cp ON cp.access_id = l.access_id
                      GROUP BY l.access_id, DATE(l.created_at), l.run_label)
                      ORDER BY created_at DESC
                      LIMIT 200
                    ";
                    $allRes = $conn->query($allLogsSql);
                    while ($allRow = $allRes->fetch_assoc()):
                      $statusBadge = '';
                      switch($allRow['status']) {
                        case 'sent':
                          $statusBadge = '<span class="badge badge-success">Sent</span>';
                          break;
                        case 'failed':
                          $statusBadge = '<span class="badge badge-danger">Failed</span>';
                          break;
                        case 'confirmed':
                          $statusBadge = '<span class="badge badge-info">Confirmed</span>';
                          break;
                        default:
                          $statusBadge = '<span class="badge badge-secondary">' . ucfirst($allRow['status']) . '</span>';
                      }
                      $typeBadge = $allRow['type'] === 'Email' ? '<span class="badge badge-primary">Email</span>' : '<span class="badge badge-warning">SMS</span>';
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($allRow['created_at']) ?></td>
                      <td><?= $typeBadge ?></td>
                      <td><?= htmlspecialchars($allRow['recipients'] ?: '-') ?></td>
                      <td><?= htmlspecialchars(substr($allRow['content_preview'] ?: '-', 0, 50)) . (strlen($allRow['content_preview']) > 50 ? '...' : '') ?></td>
                      <td><?= htmlspecialchars($allRow['couple_name'] ?: '-') ?></td>
                      <td><?= $statusBadge ?></td>
                      <td>
                        <?php if ($allRow['type'] === 'Email'): ?>
                          <button class="btn btn-sm btn-outline-primary" onclick="viewEmailLog(<?= (int)$allRow['id'] ?>)">
                            <i class="fas fa-eye"></i> View
                          </button>
                        <?php else: ?>
                          <button class="btn btn-sm btn-outline-primary" onclick="viewLog(<?= (int)$allRow['id'] ?>)">
                            <i class="fas fa-eye"></i> View
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
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
$(function(){
  // Initialize DataTables for all tabs
  $('#emailLogsTable').DataTable({ responsive:true, autoWidth:false, order:[[0,'desc']] });
  $('#smsLogsTable').DataTable({ responsive:true, autoWidth:false, order:[[0,'desc']] });
  $('#allLogsTable').DataTable({ responsive:true, autoWidth:false, order:[[0,'desc']] });
  
  // Handle tab switching with buttons - reinitialize DataTables when tabs are shown
  $('button[data-tab]').on('click', function(e) {
    e.preventDefault();
    
    // Update active button
    $('button[data-tab]').removeClass('btn-primary active').addClass('btn-outline-secondary');
    $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
    
    // Hide all tab panes
    $('.tab-pane').removeClass('show active');
    
    // Show selected tab pane
    const targetTab = $(this).data('tab');
    $('#' + targetTab).addClass('show active');
    
    // Recalculate column widths when tab is shown
    setTimeout(function() {
      $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    }, 100);
  });
});

function viewLog(id){
  $.get('sms_logs_view.php', { id: id }, function(html){
    const modal = $(html);
    $('body').append(modal);
    modal.modal('show');
    modal.on('hidden.bs.modal', function(){ modal.remove(); });
  });
}

function viewEmailLog(id){
  // Remove any existing email log modal first
  const existingModal = $('#emailLogModal');
  if (existingModal.length) {
    existingModal.modal('hide');
    existingModal.on('hidden.bs.modal', function() {
      $(this).remove();
    });
  }
  
  $.get('email_logs_view.php', { id: id }, function(html){
    try {
      // Parse HTML and extract the modal element
      const $html = $(html.trim());
      let $modal = $html;
      
      // If HTML contains multiple elements, find the modal
      if ($html.length > 1 || !$html.hasClass('modal')) {
        $modal = $html.find('.modal').first();
        if ($modal.length === 0) {
          $modal = $html.filter('.modal').first();
        }
      }
      
      // Ensure we have a valid modal
      if ($modal.length === 0 || !$modal.hasClass('modal')) {
        console.error('Invalid modal HTML:', html);
        throw new Error('No valid modal element found in response');
      }
      
      // Change ID to be unique to avoid conflicts
      const uniqueId = 'emailLogModal_' + id + '_' + Date.now();
      $modal.attr('id', uniqueId);
      
      // Append to body
      $('body').append($modal);
      
      // Use setTimeout to ensure DOM is ready
      setTimeout(function() {
        // Initialize and show modal
        $modal.modal('show');
        
        // Clean up when modal is hidden
        $modal.on('hidden.bs.modal', function(){ 
          $(this).remove(); 
        });
      }, 100);
      
    } catch (error) {
      console.error('Error displaying email log modal:', error);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Failed to display email log: ' + (error.message || error),
        timer: 3000,
        showConfirmButton: false
      });
    }
  }).fail(function(xhr, status, error) {
    console.error('Failed to load email log:', error, xhr);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load email details. Please try again.',
      timer: 3000,
      showConfirmButton: false
    });
  });
}

function sendReminder(daysAhead) {
  const dateText = daysAhead === 0 ? 'today' : daysAhead === 1 ? 'tomorrow' : `${daysAhead} days ahead`;
  
  Swal.fire({
    title: 'Send SMS Reminder',
    text: `Send SMS reminders for sessions ${dateText}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Send SMS',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      return fetch('../includes/sms_reminder.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `days_ahead=${daysAhead}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.status !== 'ok') {
          throw new Error(data.message || 'Failed to send SMS');
        }
        return data;
      })
      .catch(error => {
        Swal.showValidationMessage(`Request failed: ${error.message}`);
      });
    },
    allowOutsideClick: () => !Swal.isLoading()
  }).then((result) => {
    if (result.isConfirmed) {
      const data = result.value;
      Swal.fire({
        title: 'SMS Sent Successfully!',
        html: `
          <div class="text-left">
            <p><strong>Run:</strong> ${data.run}</p>
            <p><strong>Sent:</strong> ${data.sent} messages</p>
            <p><strong>Failed:</strong> ${data.failed} messages</p>
            <p><strong>Days Ahead:</strong> ${data.days_ahead}</p>
          </div>
        `,
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        // Refresh the page to show new logs
        location.reload();
      });
    }
  });
}

function showCustomReminderModal() {
  Swal.fire({
    title: 'Custom SMS Reminder',
    html: `
      <div class="form-group">
        <label for="customDate">Select Date:</label>
        <input type="date" id="customDate" class="form-control" min="${new Date().toISOString().split('T')[0]}">
      </div>
      <small class="text-muted">Select the session date to send reminders for</small>
    `,
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Send SMS',
    preConfirm: () => {
      const selectedDate = document.getElementById('customDate').value;
      if (!selectedDate) {
        Swal.showValidationMessage('Please select a date');
        return false;
      }
      
      const today = new Date();
      const targetDate = new Date(selectedDate);
      const diffTime = targetDate - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      return { selectedDate, daysAhead: diffDays };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const { selectedDate, daysAhead } = result.value;
      
      Swal.fire({
        title: 'Confirm Custom SMS',
        text: `Send SMS reminders for sessions on ${selectedDate}? (${daysAhead} days from today)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Send SMS',
        showLoaderOnConfirm: true,
        preConfirm: () => {
          return fetch('../includes/sms_reminder.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `days_ahead=${daysAhead}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.status !== 'ok') {
              throw new Error(data.message || 'Failed to send SMS');
            }
            return data;
          })
          .catch(error => {
            Swal.showValidationMessage(`Request failed: ${error.message}`);
          });
        },
        allowOutsideClick: () => !Swal.isLoading()
      }).then((result) => {
        if (result.isConfirmed) {
          const data = result.value;
          Swal.fire({
            title: 'SMS Sent Successfully!',
            html: `
              <div class="text-left">
                <p><strong>Target Date:</strong> ${selectedDate}</p>
                <p><strong>Run:</strong> ${data.run}</p>
                <p><strong>Sent:</strong> ${data.sent} messages</p>
                <p><strong>Failed:</strong> ${data.failed} messages</p>
                <p><strong>Days Ahead:</strong> ${data.days_ahead}</p>
              </div>
            `,
            icon: 'success',
            confirmButtonText: 'OK'
          }).then(() => {
            // Refresh the page to show new logs
            location.reload();
          });
        }
      });
    }
  });
}
</script>
</body>
</html>
