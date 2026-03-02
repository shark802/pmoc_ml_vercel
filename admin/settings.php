<?php
require_once '../includes/session.php';

// Load admin for display-only fields
$stmt = $conn->prepare("SELECT admin_name, username, email_address, position, image FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BCPDO Admin | Settings</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body { background: #f4f6f9; }
        .content-wrapper { padding-top: 20px; padding-bottom: 40px; }
        .setting-item { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f1f2f4; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title mb-0"><i class="fas fa-cog mr-2"></i>Account Settings</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <div>
                                        <div class="font-weight-bold">Change Password</div>
                                        <div class="text-muted small">Update your account password</div>
                                    </div>
                                    <button class="btn btn-outline-primary" data-toggle="modal" data-target="#passwordModal">Update</button>
                                </div>
                                <div class="setting-item">
                                    <div>
                                        <div class="font-weight-bold">Profile Visibility</div>
                                        <div class="text-muted small">Your name and position are shown in the navbar</div>
                                    </div>
                                    <span class="badge badge-info"><?= htmlspecialchars($admin['admin_name']) ?> · <?= htmlspecialchars($admin['position']) ?></span>
                                </div>
                                <div class="setting-item">
                                    <div>
                                        <div class="font-weight-bold">Profile Image</div>
                                        <div class="text-muted small">Manage your avatar from the Profile page</div>
                                    </div>
                                    <a href="profile.php" class="btn btn-outline-secondary">Open Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title mb-0">Your Info</h3></div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars($admin['image'] ?: '../images/profiles/default.jpg') ?>" onerror="this.onerror=null;this.src='../images/profiles/default.jpg';" class="img-thumbnail mr-3" style="width:80px;height:80px;object-fit:cover;">
                                    <div>
                                        <div class="font-weight-bold"><?= htmlspecialchars($admin['admin_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($admin['email_address']) ?></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="text-muted small">Username</div>
                                    <div><?= htmlspecialchars($admin['username']) ?></div>
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

<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="passwordForm">
        <div class="modal-body">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control" minlength="8" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/scripts.php'; ?>
<script>
$(function(){
  $('#passwordForm').on('submit', function(e){
    e.preventDefault();
    const pass = this.password.value.trim();
    const conf = this.password_confirm.value.trim();
    if(pass !== conf){
      Swal.fire({ icon:'error', title:'Mismatch', text:'Passwords do not match.' });
      return;
    }
    const fd = new FormData();
    fd.append('admin_id', <?= (int)$_SESSION['admin_id'] ?>);
    fd.append('admin_name', <?= json_encode($_SESSION['admin_name'] ?? $admin['admin_name']) ?>);
    fd.append('username', <?= json_encode($admin['username']) ?>);
    fd.append('email_address', <?= json_encode($admin['email_address']) ?>);
    fd.append('position', <?= json_encode($admin['position']) ?>);
    fd.append('password', pass);
    $.ajax({
      url: 'admin_edit.php',
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(resp){
        if(resp.status === 'success'){
          $('#passwordModal').modal('hide');
          Swal.fire({ icon:'success', title:'Updated', text:'Password changed successfully', timer:1500, showConfirmButton:false });
        } else {
          Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to update password' });
        }
      },
      error: function(){
        Swal.fire({ icon:'error', title:'Error', text:'Request failed. Please try again.' });
      }
    });
  });

  // Add focus management for password modal
  $('#passwordModal').on('shown.bs.modal', function() {
    // Focus on the first input field for better UX
    $(this).find('input[name="password"]').focus();
    
    // Set up focus trap within the modal
    const modal = this;
    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
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
  });

  // Clean up focus trap when modal is hidden
  $('#passwordModal').on('hidden.bs.modal', function() {
    $(this).off('keydown');
  });
});
</script>
</body>
</html>


