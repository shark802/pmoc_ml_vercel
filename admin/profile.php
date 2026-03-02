<?php
require_once '../includes/session.php';
require_once '../includes/csrf_helper.php';

// Fetch latest admin data
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
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
    <title>BCPDO Admin | My Profile</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body { background: #f4f6f9; }
        .content-wrapper { padding-top: 20px; padding-bottom: 40px; }
        .profile-card .card-body { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }
        .profile-avatar { width: 200px; height: 200px; object-fit: cover; border-radius: 8px; border: 3px solid #dee2e6; }
        .form-label { font-weight: 600; }
        .preview-image { width: 160px; height: 160px; object-fit: cover; border-radius: 6px; background-color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .image-preview-row { display: flex; align-items: center; gap: 16px; margin-top: 8px; }
        .preview-label { margin: 0; font-size: 0.9rem; color: #6c757d; }
        
        /* Dark Mode Styles */
        body.dark-mode { background: #1a1d29 !important; }
        
        body.dark-mode .profile-avatar {
            border-color: rgba(255,255,255,0.2) !important;
        }
        
        body.dark-mode .form-label {
            color: #e2e8f0 !important;
        }
        
        body.dark-mode .preview-image {
            background-color: #2f3640 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3) !important;
        }
        
        body.dark-mode .preview-label {
            color: #adb5bd !important;
        }
        
        /* Readonly field in dark mode */
        body.dark-mode .readonly-white[readonly],
        body.dark-mode input[readonly].readonly-white {
            background-color: #2f3640 !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.12) !important;
        }
        
        /* Form controls in dark mode */
        body.dark-mode .form-control:not(.readonly-white) {
            background-color: #2f3640 !important;
            color: #e2e8f0 !important;
            border-color: rgba(255,255,255,0.12) !important;
        }
        
        body.dark-mode .form-control:not(.readonly-white):focus {
            background-color: #3b4147 !important;
            color: #ffffff !important;
            border-color: #6c757d !important;
            box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25) !important;
        }
        
        body.dark-mode .form-control::placeholder {
            color: #adb5bd !important;
        }
        
        /* File input in dark mode */
        body.dark-mode .form-control-file {
            color: #e2e8f0 !important;
        }
        
        /* Text muted in dark mode */
        body.dark-mode .text-muted {
            color: #adb5bd !important;
        }
        
        /* Card styling in dark mode */
        body.dark-mode .profile-card .card {
            background-color: #2a2e33 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        
        body.dark-mode .profile-card .card-header {
            background-color: #343a40 !important;
            border-bottom-color: rgba(255,255,255,0.1) !important;
            color: #e2e8f0 !important;
        }
        
        body.dark-mode .profile-card .card-body {
            background-color: #2a2e33 !important;
            color: #e2e8f0 !important;
        }
    </style>
    <script>
        function previewImage(input){
            const currentBox = document.getElementById('profileCurrentImageContainer');
            const newBox = document.getElementById('profileNewImagePreviewContainer');
            const newImg = document.getElementById('profileNewImagePreview');
            if(input.files && input.files[0]){
                const reader = new FileReader();
                reader.onload = e => {
                    if (newImg) newImg.src = e.target.result;
                    if (currentBox) currentBox.style.display = 'none';
                    if (newBox) newBox.style.display = '';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                if (currentBox) currentBox.style.display = '';
                if (newBox) newBox.style.display = 'none';
            }
        }
    </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card profile-card">
                            <div class="card-header">
                                <h3 class="card-title mb-0"><i class="fas fa-user-circle mr-2"></i>My Profile</h3>
                            </div>
                            <div class="card-body">
                                <div>
                                    <div id="profileCurrentImageContainer">
                                        <img id="avatarPreview" class="profile-avatar" src="<?= htmlspecialchars($admin['image'] ?: '../images/profiles/default.jpg') ?>" onerror="this.onerror=null;this.src='../images/profiles/default.jpg';" alt="Avatar">
                                    </div>
                                    <div id="profileNewImagePreviewContainer" class="image-preview-row" style="display:none;">
                                        <p class="preview-label mb-0">New Image Preview:</p>
                                        <img id="profileNewImagePreview" class="preview-image" alt="New preview">
                                    </div>
                                </div>
                                <div>
                                    <form id="profileForm" method="post" action="admin_edit.php" enctype="multipart/form-data">
                                        <input type="hidden" name="admin_id" value="<?= (int)$_SESSION['admin_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label class="form-label">Admin Name</label>
                                                <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($admin['admin_name']) ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="form-label">Position</label>
                                                <input type="text" class="form-control readonly-white" value="<?= htmlspecialchars($admin['position']) ?>" readonly>
                                                <input type="hidden" name="position" value="<?= htmlspecialchars($admin['position']) ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email_address" class="form-control" value="<?= htmlspecialchars($admin['email_address']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label class="form-label">New Password <small class="text-muted">(optional)</small></label>
                                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="form-label">Profile Image</label>
                                                <input type="file" name="image" accept="image/*" class="form-control-file" onchange="previewImage(this)">
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Changes</button>
                                            <a href="../admin/admin_dashboard.php" class="btn btn-secondary ml-2">Cancel</a>
                                        </div>
                                    </form>
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
<?php include '../includes/scripts.php'; ?>
<script>
$(function(){
  $('#profileForm').on('submit', function(e){
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    const submitBtn = $(form).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    
    // Disable submit button and show loading state
    submitBtn.prop('disabled', true);
    submitBtn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving changes...');
    
    // Show loading alert
    Swal.fire({
      title: 'Saving changes...',
      html: 'Please wait while we update your profile.',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    $.ajax({
      url: 'admin_edit.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(resp){
        // Close loading alert
        Swal.close();
        
        if(resp.status === 'success'){
          Swal.fire({ 
            icon:'success', 
            title:'Saved!', 
            text: resp.message, 
            timer: 1500, 
            showConfirmButton:false 
          }).then(()=>{
            location.reload();
          });
        } else {
          // Re-enable button on error
          submitBtn.prop('disabled', false);
          submitBtn.html(originalBtnText);
          Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to update profile' });
        }
      },
      error: function(xhr, status, error){
        // Close loading alert
        Swal.close();
        
        // Re-enable button on error
        submitBtn.prop('disabled', false);
        submitBtn.html(originalBtnText);
        
        console.error('AJAX Error:', status, error);
        console.error('Response:', xhr.responseText);
        let errorMessage = 'Request failed. Please try again.';
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.message) {
            errorMessage = response.message;
          }
        } catch(e) {
          // If response is not JSON, use default message
        }
        Swal.fire({ icon:'error', title:'Error', text: errorMessage });
      }
    });
  });
});
</script>
</body>
</html>


