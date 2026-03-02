<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" role="dialog" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title w-100 text-center" id="addAdminModalLabel">Add New Admin</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php if (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin'): ?>
            <form id="addAdminForm" action="admin_add.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="modal-body">
                    <div id="addAdminAlert" style="display:none;"></div>
                    <div class="form-group">
                        <label for="admin_name" class="required-field"> Admin Name</label>
                        <input type="text" class="form-control" id="admin_name" name="admin_name"
                            pattern="^[a-zA-Z\s]*$"
                            title="name should only contain letters and spaces" autocomplete="off"
                            required>
                        <div id="admin_nameFeedback" class="invalid-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="username" class="required-field">Username</label>
                        <input type="text" class="form-control" id="username" name="username" minlength="8" autocomplete="off" required>
                        <div id="usernameFeedback" class="invalid-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="email_address" class="required-field">Email Address</label>
                        <input type="email" class="form-control" id="email_address" name="email_address" autocomplete="off" required>
                        <div id="emailFeedback" class="invalid-feedback"></div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="required-field">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div id="passwordFeedback" class="invalid-feedback"></div>
                        <div class="password-strength mt-2">
                            <div class="progress">
                                <div id="passwordStrength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="position" class="required-field">Position</label>
                        <select class="form-control" id="position" name="position" required>
                            <option value="admin">Admin</option>
                            <option value="counselor">Counselor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="image">Profile Image</label>
                        <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                        <div class="image-preview-container mt-2" id="imagePreviewContainer" style="display:none;">
                            <p class="preview-label mb-1">Image Preview:</p>
                            <img id="imagePreview" class="img-thumbnail preview-image">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitAddBtn">Save Admin</button>
                </div>
            </form>
            <?php else: ?>
            <div class="p-3">
                <div class="alert alert-warning mb-0">Only Super Admin can add new admins.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" role="dialog" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title w-100 text-center" id="editAdminModalLabel">Edit Admin</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editAdminForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" id="edit_admin_id" name="admin_id">
                <div class="modal-body">
                    <div id="editAdminAlert" style="display:none;"></div>
                    <div class="form-group">
                        <label for="edit_admin_name" class="required-field">Admin Name</label>
                        <input type="text" class="form-control" id="edit_admin_name" name="admin_name"
                            pattern="^[a-zA-Z\s]*$"
                            title="name should only contain letters and spaces" autocomplete="off"
                            required>
                        <div id="edit_admin_nameFeedback" class="invalid-feedback"></div>
                    </div>
                    <div class="form-group">
                        <label for="edit_username" class="required-field">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" minlength="8" autocomplete="off" required>
                        <div id="editUsernameFeedback" class="invalid-feedback"></div>
                    </div>
                    <div class="form-group">
                        <label for="edit_email_address" class="required-field">Email Address</label>
                        <input type="email" class="form-control" id="edit_email_address" name="email_address" autocomplete="off" required>
                        <div id="editEmailFeedback" class="invalid-feedback"></div>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                        <div id="editPasswordFeedback" class="invalid-feedback"></div>
                        <div class="password-strength mt-2">
                            <div class="progress">
                                <div id="editPasswordStrength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="required-field">Position</label>
                        <select class="form-control" id="edit_position" name="position" required>
                            <option value="admin">Admin</option>
                            <option value="counselor">Counselor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_image">Profile Image</label>
                        <input type="file" class="form-control-file" id="edit_image" name="image" accept="image/*">
                        <small class="text-muted">Leave blank to keep current image</small>
                        <div class="current-image-container mt-2" id="currentImageContainer" style="display:none;">
                            <p class="preview-label mb-1">Current Image:</p>
                            <img id="current_image" class="img-thumbnail preview-image">
                        </div>
                        <div class="image-preview-container mt-2" id="editImagePreviewContainer" style="display:none;">
                            <p class="preview-label mb-1">New Image Preview:</p>
                            <img id="editImagePreview" class="img-thumbnail preview-image">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitEditBtn">Update Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete modal removed; using activate/deactivate toggle -->

<script>
    function previewSelectedImage(fileInput, imgId, containerId) {
        const file = fileInput.files && fileInput.files[0];
        const container = document.getElementById(containerId);
        const img = document.getElementById(imgId);
        if (!file) {
            if (container) container.style.display = 'none';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            if (img) img.src = e.target.result;
            if (container) container.style.display = '';
        };
        reader.readAsDataURL(file);
    }
    // Real-time validation functions
    function validateAdminName(input) {
        const admin_name = input.value.trim();
        const feedback = document.getElementById('admin_nameFeedback');
        const regex = /^[a-zA-Z\s]+$/;

        if (admin_name.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return false;
        }

        if (!regex.test(admin_name)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'name should only contain letters and spaces';
            return false;
        }

        if (admin_name.length < 2) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'name must be at least 2 characters';
            return false;
        }

        checkAdminNameAvailability(admin_name, input, feedback);
        return true;
    }

    function checkAdminNameAvailability(admin_name, input, feedback) {
        if (admin_name.length < 2) return;

        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        fetch(`../includes/check_admin_name.php?admin_name=${encodeURIComponent(admin_name)}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This name is already taken';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'name is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validateUsername(input) {
        const username = input.value.trim();
        const feedback = document.getElementById('usernameFeedback');

        if (username.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return false;
        }

        if (username.length < 8) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Username must be at least 8 characters';
            return false;
        }

        checkUsernameAvailability(username, input, feedback);
        return true;
    }

    function checkUsernameAvailability(username, input, feedback) {
        if (username.length < 8) return;

        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        const adminId = $('#edit_admin_id').val();
        fetch(`../includes/check_username.php?username=${encodeURIComponent(username)}&admin_id=${adminId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This username is already taken';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'Username is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validateEmail(input) {
        const email = input.value.trim();
        const feedback = document.getElementById('emailFeedback');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return false;
        }

        if (!regex.test(email)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Please enter a valid email address';
            return false;
        }

        checkEmailAvailability(email, input, feedback);
        return true;
    }

    function checkEmailAvailability(email, input, feedback) {
        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        const adminId = $('#edit_admin_id').val();
        fetch(`../includes/check_email.php?email_address=${encodeURIComponent(email)}&admin_id=${adminId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This email is already registered';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'Email is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validatePassword(input) {
        const password = input.value;
        const feedback = document.getElementById('passwordFeedback');
        const strengthBar = document.getElementById('passwordStrength');

        if (password.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            strengthBar.style.width = '0%';
            feedback.textContent = '';
            return false;
        }

        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const isLengthValid = password.length >= 8;

        let strength = 0;
        if (password.length > 0) strength += 20;
        if (password.length >= 8) strength += 20;
        if (hasUpperCase) strength += 20;
        if (hasLowerCase) strength += 20;
        if (hasNumber) strength += 20;

        strengthBar.style.width = `${strength}%`;

        if (strength < 40) {
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength < 80) {
            strengthBar.className = 'progress-bar bg-warning';
        } else {
            strengthBar.className = 'progress-bar bg-success';
        }

        if (!isLengthValid || !hasUpperCase || !hasLowerCase || !hasNumber) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Password must contain at least 8 characters with 1 uppercase, 1 lowercase, and 1 number';
            return false;
        }

        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
        feedback.textContent = 'Password is strong';
        return true;
    }

    // Edit form validation functions
    function validateEditAdminName(input) {
        const admin_name = input.value.trim();
        const feedback = document.getElementById('edit_admin_nameFeedback');
        const original = $(input).data('original');
        const regex = /^[a-zA-Z\s]+$/;

        if (admin_name === original) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return true;
        }

        if (!regex.test(admin_name)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'name should only contain letters and spaces';
            return false;
        }

        if (admin_name.length < 2) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'name must be at least 2 characters';
            return false;
        }

        const adminId = $('#edit_admin_id').val();
        checkEditAdminNameAvailability(admin_name, adminId, input, feedback);
        return true;
    }

    function checkEditAdminNameAvailability(admin_name, adminId, input, feedback) {
        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        fetch(`../includes/check_admin_name.php?admin_name=${encodeURIComponent(admin_name)}&admin_id=${adminId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This name is already taken';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'name is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validateEditUsername(input) {
        const username = input.value.trim();
        const feedback = document.getElementById('editUsernameFeedback');
        const original = $(input).data('original');

        if (username === original) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return true;
        }

        if (username.length < 8) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Username must be at least 8 characters';
            return false;
        }

        checkEditUsernameAvailability(username, input, feedback);
        return true;
    }

    function checkEditUsernameAvailability(username, input, feedback) {
        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        const adminId = $('#edit_admin_id').val();
        fetch(`../includes/check_username.php?username=${encodeURIComponent(username)}&admin_id=${adminId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This username is already taken';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'Username is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validateEditEmail(input) {
        const email = input.value.trim();
        const feedback = document.getElementById('editEmailFeedback');
        const original = $(input).data('original');
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email === original) {
            input.classList.remove('is-valid', 'is-invalid');
            feedback.textContent = '';
            return true;
        }

        if (!regex.test(email)) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Please enter a valid email address';
            return false;
        }

        checkEditEmailAvailability(email, input, feedback);
        return true;
    }

    function checkEditEmailAvailability(email, input, feedback) {
        feedback.innerHTML = '<span class="loading-spinner"></span> Checking...';

        const adminId = $('#edit_admin_id').val();
        fetch(`../includes/check_email.php?email_address=${encodeURIComponent(email)}&admin_id=${adminId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    feedback.textContent = 'This email is already registered';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');
                    feedback.textContent = 'Email is available';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                feedback.textContent = 'Error checking availability';
            });
    }

    function validateEditPassword(input) {
        const password = input.value;
        const feedback = document.getElementById('editPasswordFeedback');
        const strengthBar = document.getElementById('editPasswordStrength');

        if (password.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            strengthBar.style.width = '0%';
            feedback.textContent = '';
            return true;
        }

        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const isLengthValid = password.length >= 8;

        let strength = 0;
        if (password.length > 0) strength += 20;
        if (password.length >= 8) strength += 20;
        if (hasUpperCase) strength += 20;
        if (hasLowerCase) strength += 20;
        if (hasNumber) strength += 20;

        strengthBar.style.width = `${strength}%`;

        if (strength < 40) {
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength < 80) {
            strengthBar.className = 'progress-bar bg-warning';
        } else {
            strengthBar.className = 'progress-bar bg-success';
        }

        if (!isLengthValid || !hasUpperCase || !hasLowerCase || !hasNumber) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = 'Password must contain at least 8 characters with 1 uppercase, 1 lowercase, and 1 number';
            return false;
        }

        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
        feedback.textContent = 'Password is strong';
        return true;
    }

    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Add form
        document.getElementById('admin_name')?.addEventListener('input', function() {
            validateAdminName(this); // Make sure this matches the function name exactly
        });

        document.getElementById('username')?.addEventListener('input', function() {
            validateUsername(this);
        });

        document.getElementById('email_address')?.addEventListener('input', function() {
            validateEmail(this);
        });

        document.getElementById('password')?.addEventListener('input', function() {
            validatePassword(this);
        });

        // Image preview handlers (Add)
        document.getElementById('image')?.addEventListener('change', function() {
            previewSelectedImage(this, 'imagePreview', 'imagePreviewContainer');
        });

        // Edit form
        document.getElementById('edit_admin_name')?.addEventListener('input', function() {
            validateEditAdminName(this);
        });

        document.getElementById('edit_username')?.addEventListener('input', function() {
            validateEditUsername(this);
        });

        document.getElementById('edit_email_address')?.addEventListener('input', function() {
            validateEditEmail(this);
        });

        document.getElementById('edit_password')?.addEventListener('input', function() {
            validateEditPassword(this);
        });

        // Image preview handler (Edit)
        document.getElementById('edit_image')?.addEventListener('change', function() {
            const hasFile = this.files && this.files[0];
            previewSelectedImage(this, 'editImagePreview', 'editImagePreviewContainer');
            const currentContainer = document.getElementById('currentImageContainer');
            if (currentContainer) {
                currentContainer.style.display = hasFile ? 'none' : '';
            }
        });
    });

    // Form submission handlers
    $('#addAdminForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const formData = new FormData(this);

        if (form[0].checkValidity()) {
            $.ajax({
                url: 'admin_add.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#addAdminModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Admin added successfully',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: xhr.responseJSON?.message || 'Failed to add admin',
                        showConfirmButton: true
                    });
                }
            });
        }
    });

    $('#editAdminForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);

        // Validate all fields
        const isAdminNameValid = validateEditAdminName(document.getElementById('edit_admin_name'));
        const isUsernameValid = validateEditUsername(document.getElementById('edit_username'));
        const isEmailValid = validateEditEmail(document.getElementById('edit_email_address'));
        const isPasswordValid = document.getElementById('edit_password').value.length === 0 ||
            validateEditPassword(document.getElementById('edit_password'));

        if (!isAdminNameValid || !isUsernameValid || !isEmailValid || !isPasswordValid) {
            $('#editAdminAlert').html(`
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Please fix all validation errors before submitting
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `).show();
            return;
        }

        const formData = new FormData(this);

        $.ajax({
            url: '../admin/admin_edit.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#editAdminModal').modal('hide');
                location.reload();
            },
            error: function(xhr) {
                $('#editAdminAlert').html(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    An error occurred while updating the admin
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `).show();
            }
        });
    });

    // Initialize modals
    $('#editAdminModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const adminId = button.data('id');

        $.ajax({
            url: '../admin/admin_row.php',
            method: 'GET',
            data: {
                get_admin: true,
                id: adminId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const admin = response.admin;
                    $('#edit_admin_id').val(admin.admin_id);
                    $('#edit_admin_name').val(admin.admin_name).data('original', admin.admin_name);
                    $('#edit_username').val(admin.username).data('original', admin.username);
                    $('#edit_email_address').val(admin.email_address).data('original', admin.email_address);

                    // Set the position dropdown value
                    $('#edit_position').val(admin.position);

                    // Show current image
                    const currentImg = admin.image && admin.image.length ? admin.image : '../images/profiles/default.jpg';
                    $('#current_image').attr('src', currentImg);
                    $('#currentImageContainer').show();
                    // Reset new preview container
                    $('#editImagePreviewContainer').hide();

                    // Clear any previous validation states
                    $('#editAdminForm').removeClass('was-validated');
                    $('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
                    $('.invalid-feedback').hide();

                    $('#editAdminModal').modal('show');
                }
            }
        });
    });

    // Delete removed in favor of status toggle

    // Add focus management for admin modals
    $('#addAdminModal').on('shown.bs.modal', function() {
        // Focus on the first input field for better UX
        $(this).find('#admin_name').focus();
        
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

    $('#editAdminModal').on('shown.bs.modal', function() {
        // Focus on the first input field for better UX
        $(this).find('#edit_admin_name').focus();
        
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

    // Clean up focus trap when modals are hidden
    $('#addAdminModal, #editAdminModal').on('hidden.bs.modal', function() {
        $(this).off('keydown');
    });
</script>


<style>
    .password-strength {
        margin-top: 5px;
    }

    .progress {
        height: 5px;
        margin-bottom: 5px;
    }

    .invalid-feedback {
        display: none;
        color: #dc3545;
    }

    .is-invalid~.invalid-feedback {
        display: block;
    }

    .is-valid {
        border-color: #28a745;
    }

    .is-invalid {
        border-color: #dc3545;
    }

    .required-field:after {
        content: " *";
        color: #dc3545;
        margin-left: 3px;
    }

    /* .preview-image {
        max-width: 100%;
        height: auto;
        max-height: 200px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 4px;
        background-color: #fff;
    }

    .image-preview-container,
    .current-image-container {
        border: 1px dashed #ced4da;
        border-radius: 4px;
        padding: 10px;
        background-color: #f8f9fa;
        text-align: center;
        margin-top: 10px;
    }

    .preview-label {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
        color: #6c757d;
        font-weight: 500;
    } */

    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: text-bottom;
        border: 0.2em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border .75s linear infinite;
    }

    @keyframes spinner-border {
        to {
            transform: rotate(360deg);
        }
    }

    /* Fixed-size preview styles */
    .image-preview-container, .current-image-container, #editImagePreviewContainer {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .preview-image {
        width: 160px;
        height: 160px;
        object-fit: cover;
        border-radius: 6px;
        background-color: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    }
</style>