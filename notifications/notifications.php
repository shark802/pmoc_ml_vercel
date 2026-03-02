<?php
require_once '../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications</title>
  <?php include '../includes/header.php'; ?>
  <style>

    .content-wrapper .content {
      padding-top: 20px;
    }
    
    /* Add spacing above the table */
    .table-responsive {
      margin-top: 15px;
    }
    
    .badge-status{border-radius:12px;padding:4px 8px}
    .content-cell {
      max-width: 300px;
      word-wrap: break-word;
      white-space: normal;
    }
    .type-cell {
      font-weight: 500;
      text-transform: capitalize;
      font-family: monospace;
      font-size: 0.9em;
    }
    .created-cell {
      font-size: 0.9em;
      color: #666;
    }
    .access-code-cell {
      font-weight: 500;
      color: #333;
    }
    .couple-name-cell {
      font-weight: 500;
      color: #333;
    }
    .actions-cell {
      text-align: center;
      white-space: nowrap;
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
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0"><i class="fas fa-bell mr-2"></i>Notifications</h3>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped" id="notificationsTable">
                <thead>
                  <tr>
                    <th>Content</th>
                    <th>Recipients</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $res = $conn->query("
                    SELECT 
                        n.notification_id, 
                        n.recipients, 
                        n.content, 
                        n.notification_status, 
                        n.created_at
                    FROM notifications n
                    WHERE n.notification_status != 'deleted'
                    ORDER BY n.created_at DESC
                ");
                while ($row = $res->fetch_assoc()):
                    // Determine what to show in the type column
                    $recipientsDisplay = $row['recipients'];
                    // If content embeds contacts like "... -> email1, email2", extract them for Type
                    if (in_array($row['recipients'], ['email','sms']) && strpos($row['content'], ' -> ') !== false) {
                        $partsForType = explode(' -> ', $row['content'], 2);
                        if (isset($partsForType[1]) && trim($partsForType[1]) !== '') {
                            $recipientsDisplay = $partsForType[1];
                        }
                    }
                    
                    // Clean up the content by removing email addresses after "->"
                    $cleanContent = $row['content'];
                    if (strpos($cleanContent, ' -> ') !== false) {
                        $parts = explode(' -> ', $cleanContent);
                        $cleanContent = $parts[0]; // Keep only the part before "->"
                    }
                ?>
                  <tr>
                    <td class="content-cell"><?= htmlspecialchars($cleanContent) ?></td>
                    <td class="type-cell"><?= htmlspecialchars($recipientsDisplay) ?></td>
                    <td>
                      <?php 
                        $status = $row['notification_status'] ?? 'pending';
                        $cls = '';
                        $statusText = '';
                        
                        switch($status) {
                            case 'email_sent':
                                $cls = 'badge-info';
                                $statusText = 'Email Sent';
                                break;
                            case 'pending_response':
                                $cls = 'badge-warning';
                                $statusText = 'Pending Response';
                                break;
                            case 'accepted':
                                $cls = 'badge-success';
                                $statusText = 'Accepted';
                                break;
                            case 'rejected':
                                $cls = 'badge-danger';
                                $statusText = 'Rejected';
                                break;
                            case 'rescheduled':
                                $cls = 'badge-warning';
                                $statusText = 'Rescheduled';
                                break;
                            case 'reschedule_requested':
                                $cls = 'badge-warning';
                                $statusText = 'Reschedule Requested';
                                break;
                            case 'confirmed':
                                $cls = 'badge-success';
                                $statusText = 'Confirmed';
                                break;
                            case 'created':
                                $cls = 'badge-secondary';
                                $statusText = 'Created';
                                break;
                            case 'deleted':
                                $cls = 'badge-dark';
                                $statusText = 'Deleted';
                                break;
                            case 'sent':
                                $cls = 'badge-info';
                                $statusText = 'Sent';
                                break;
                            case 'failed':
                                $cls = 'badge-danger';
                                $statusText = 'Failed';
                                break;
                            default:
                                $cls = 'badge-secondary';
                                $statusText = ucfirst($status);
                        }
                      ?>
                      <span class="badge badge-status <?= $cls ?>"><?= htmlspecialchars($statusText) ?></span>
                    </td>
                    <td class="created-cell" data-order="<?= strtotime($row['created_at']) ?>"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at']))) ?></td>
                    <td class="actions-cell">
                      <button type="button" class="btn btn-sm btn-outline-primary view-notification mr-2" 
                              data-content="<?= htmlspecialchars($row['content']) ?>"
                              data-recipients="<?= htmlspecialchars($row['recipients']) ?>"
                              data-status="<?= htmlspecialchars($row['notification_status']) ?>"
                              data-created="<?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at']))) ?>"
                              title="View Details">
                        <i class="fas fa-eye"></i> View
                      </button>
                      <?php if ($row['notification_status'] === 'failed'): ?>
                      <button type="button" class="btn btn-sm btn-outline-warning resend-email mr-2" 
                              data-notification-id="<?= $row['notification_id'] ?>"
                              title="Resend Email">
                        <i class="fas fa-envelope"></i> Resend
                      </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-outline-danger delete-notification" 
                              data-notification-id="<?= $row['notification_id'] ?>"
                              title="Delete Notification">
                        <i class="fas fa-trash"></i> Delete
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php include '../includes/footer.php'; ?>
</div>

<!-- Notification Details Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationModalLabel" tabindex="0">
          <i class="fas fa-bell mr-2"></i>
          Notification Details
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="notification-detail-card">
          <div class="detail-section">
            <div class="detail-header">
              <i class="fas fa-bell text-primary"></i>
              <h6 class="mb-0">Notification Content</h6>
              <button class="copy-btn" onclick="copyToClipboard('modal-content')" title="Copy content">
                <i class="fas fa-copy"></i>
              </button>
            </div>
            <div class="detail-content" id="modal-content"></div>
          </div>
          
          <div class="detail-section">
            <div class="detail-header">
              <i class="fas fa-users text-info"></i>
              <h6 class="mb-0">Recipients</h6>
              <button class="copy-btn" onclick="copyToClipboard('modal-recipients')" title="Copy recipients">
                <i class="fas fa-copy"></i>
              </button>
            </div>
            <div class="detail-content" id="modal-recipients"></div>
          </div>
          
          <div class="detail-section">
            <div class="detail-header">
              <i class="fas fa-clock text-secondary"></i>
              <h6 class="mb-0">Created</h6>
            </div>
            <div class="detail-content" id="modal-created"></div>
          </div>
          
          <div class="detail-section">
            <div class="detail-header">
              <i class="fas fa-info-circle text-warning"></i>
              <h6 class="mb-0">Status</h6>
            </div>
            <div class="detail-content">
              <span id="modal-status" class="badge"></span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
    </div>
</div>



<?php include '../includes/scripts.php'; ?>
  
  <style>
    .notification-detail-card {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
    }
    
    .detail-section {
      margin-bottom: 20px;
      background: white;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      border-left: 4px solid #007bff;
    }
    
    .detail-section:last-child {
      margin-bottom: 0;
    }
    
    .detail-header {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e9ecef;
    }
    
    .detail-header i {
      margin-right: 10px;
      font-size: 16px;
    }
    
    .detail-header h6 {
      color: #495057;
      font-weight: 600;
      margin: 0;
    }
    
    .detail-content {
      color: #212529;
      font-size: 14px;
      line-height: 1.5;
      word-wrap: break-word;
    }
    
    .detail-content .badge {
      font-size: 12px;
      padding: 6px 12px;
    }
    
    /* Status-specific colors */
    .detail-section.status-created { border-left-color: #6c757d; }
    .detail-section.status-sent { border-left-color: #17a2b8; }
    .detail-section.status-failed { border-left-color: #dc3545; }
    .detail-section.status-accepted { border-left-color: #28a745; }
    .detail-section.status-rejected { border-left-color: #dc3545; }
    .detail-section.status-confirmed { border-left-color: #28a745; }
    .detail-section.status-reschedule_requested { border-left-color: #ffc107; }
    
    /* Modal improvements */
    .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .modal-header {
      background: linear-gradient(135deg, #007bff, #0056b3);
      color: white;
      border-radius: 15px 15px 0 0;
      border-bottom: none;
    }
    
    .modal-header .close {
      color: white;
      opacity: 0.8;
    }
    
    .modal-header .close:hover {
      opacity: 1;
    }
    
    .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: 15px 20px;
    }
    
    /* Hover effects */
    .detail-section:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
    }
    
    /* Copy to clipboard button */
    .copy-btn {
      background: #007bff;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 4px 8px;
      font-size: 12px;
      cursor: pointer;
      margin-left: 10px;
    }
    
    .copy-btn:hover {
      background: #0056b3;
    }
    
    /* Responsive improvements */
    @media (max-width: 768px) {
      .notification-detail-card {
        padding: 15px;
      }
      
      .detail-section {
        padding: 12px;
      }
      
      .detail-header h6 {
        font-size: 14px;
      }
      
      .detail-content {
        font-size: 13px;
      }
    }

    /* Modal focus styles */
    #notificationModal.modal-focused {
      outline: 2px solid #007bff;
      outline-offset: 2px;
    }

    #notificationModal .modal-title:focus {
      outline: 2px solid #007bff;
      outline-offset: 2px;
      border-radius: 4px;
    }

    /* Ensure modal is properly centered and visible */
    #notificationModal.show {
      display: block !important;
      background-color: rgba(0, 0, 0, 0.5);
    }

    #notificationModal .modal-content {
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      border: none;
      border-radius: 8px;
    }
    

  </style>
  
<script>
  $(function(){
      <?php // success_message toast now emitted globally in includes/scripts.php ?>
      <?php if (isset($_SESSION['error_message'])): ?>
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: '<?= addslashes($_SESSION['error_message']) ?>',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 4000,
          timerProgressBar: true
        });
        <?php unset($_SESSION['error_message']); ?>
      <?php endif; ?>

    $('#notificationsTable').DataTable({
      "responsive": true,
      "autoWidth": false,
      "order": [[3, 'desc']] // Order by Created column (index 3) in descending order
    });

    // View Notification Details
    $('.view-notification').click(function() {
      var content = $(this).data('content');
      var recipients = $(this).data('recipients');
      var status = $(this).data('status');
      var created = $(this).data('created');

      $('#modal-content').text(content);
      $('#modal-recipients').text(recipients);
      $('#modal-created').text(created);

      // Set status badge with appropriate class
      var statusClass = '';
      var statusText = '';
      switch(status) {
        case 'created':
          statusClass = 'badge-secondary';
          statusText = 'Created';
          break;
        case 'sent':
          statusClass = 'badge-info';
          statusText = 'Sent';
          break;
        case 'failed':
          statusClass = 'badge-danger';
          statusText = 'Failed';
          break;
        case 'accepted':
          statusClass = 'badge-success';
          statusText = 'Accepted';
          break;
        case 'rejected':
          statusClass = 'badge-danger';
          statusText = 'Rejected';
          break;
        case 'confirmed':
          statusClass = 'badge-success';
          statusText = 'Confirmed';
          break;
        case 'reschedule_requested':
          statusClass = 'badge-warning';
          statusText = 'Reschedule Requested';
          break;
        default:
          statusClass = 'badge-secondary';
          statusText = status.charAt(0).toUpperCase() + status.slice(1);
      }

      $('#modal-status').removeClass().addClass('badge ' + statusClass).text(statusText);
      
      // Add dynamic styling based on status
      $('.detail-section').removeClass('status-created status-sent status-failed status-accepted status-rejected status-confirmed status-reschedule_requested');
      $('.detail-section').addClass('status-' + status);
      
      $('#notificationModal').modal('show');
    });

    // Add focus management for notification modal
    $('#notificationModal').on('shown.bs.modal', function() {
      const modal = this;
      
      // Focus on the modal title for accessibility
      const modalTitle = $(modal).find('.modal-title');
      if (modalTitle.length) {
        modalTitle.focus();
      }
      
      // Set up focus trap within the modal
      const focusableElements = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      
      if (focusableElements.length > 0) {
        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];
        
        // Handle tab key to trap focus
        $(modal).on('keydown', function(e) {
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
      
      // Add visual focus indicator
      $(modal).addClass('modal-focused');
    });

    // Clean up focus trap when modal is hidden
    $('#notificationModal').on('hidden.bs.modal', function() {
      $(this).off('keydown').removeClass('modal-focused');
    });
    
    // Copy to clipboard function
    window.copyToClipboard = function(elementId) {
      var text = document.getElementById(elementId).textContent;
      navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        var btn = event.target.closest('.copy-btn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.style.background = '#28a745';
        
        setTimeout(function() {
          btn.innerHTML = originalText;
          btn.style.background = '#007bff';
        }, 1500);
      }).catch(function(err) {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
      });
    };
    
    // Show delete confirmation with SweetAlert2 (matching your system style)
    function showDeleteConfirmation(notificationId, content, recipients) {
      // Truncate content if too long
      var truncatedContent = content.length > 100 ? content.substring(0, 100) + '...' : content;
      var truncatedRecipients = recipients.length > 50 ? recipients.substring(0, 50) + '...' : recipients;
      
      Swal.fire({
        title: 'Delete Notification?',
        html: `Are you sure you want to permanently delete this notification?<br><br>
                <div class="alert alert-warning text-left" style="font-size:0.9rem;">
                  <i class="fas fa-exclamation-triangle mr-2"></i>
                  <strong>Warning:</strong> This action cannot be undone.
                </div>
                <div class="text-left mt-3">
                  <p><strong>Content:</strong></p>
                  <p class="text-muted">${truncatedContent}</p>
                  <p><strong>Recipients:</strong></p>
                  <p class="text-muted">${truncatedRecipients}</p>
                </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete Permanently',
        cancelButtonText: 'Cancel',
        focusCancel: true
      }).then((result) => {
        if (result.isConfirmed) {
          const deleteBtn = $('.delete-notification[data-notification-id="' + notificationId + '"]');
          deleteBtn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...');
          
          $.ajax({
            url: 'delete_notification.php',
            type: 'POST',
            data: { notification_id: notificationId },
            success: function(response) {
              if (response.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Deleted!',
                  text: 'Notification deleted successfully',
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 2000
                });
                setTimeout(() => location.reload(), 2000);
              } else {
                deleteBtn.html('<i class="fas fa-trash"></i>');
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: response.message || 'Error deleting notification',
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 3000
                });
              }
            },
            error: function() {
              deleteBtn.html('<i class="fas fa-trash"></i>');
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error deleting notification. Please try again.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
              });
            }
          });
        }
      });
    }

    // Delete Notification
    $('.delete-notification').click(function() {
      var notificationId = $(this).data('notification-id');
      var content = $(this).closest('tr').find('td:first').text().trim();
      var recipients = $(this).closest('tr').find('td:nth-child(2)').text().trim();
      
      // Show custom delete confirmation modal
      showDeleteConfirmation(notificationId, content, recipients);
    });

    // Resend Email
    $('.resend-email').click(function() {
      var notificationId = $(this).data('notification-id');
      
      Swal.fire({
        title: 'Resend Email?',
        text: 'Are you sure you want to resend this email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Resend Email',
        cancelButtonText: 'Cancel',
        focusCancel: true
      }).then((result) => {
        if (result.isConfirmed) {
          const resendBtn = $(this);
          resendBtn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Sending...');
          
          $.ajax({
            url: 'resend_email.php',
            type: 'POST',
            data: { notification_id: notificationId },
            success: function(response) {
              if (response.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Email Sent!',
                  text: 'Email has been resent successfully',
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 2000
                });
                setTimeout(() => location.reload(), 2000);
              } else {
                resendBtn.html('<i class="fas fa-envelope"></i>');
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: response.message || 'Error resending email',
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 3000
                });
              }
            },
            error: function() {
              resendBtn.html('<i class="fas fa-envelope"></i>');
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Error resending email. Please try again.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
              });
            }
          });
        }
      });
    });
  });
</script>
</body>
</html>


