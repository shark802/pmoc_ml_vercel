<?php
require_once '../includes/session.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Management | Admin Panel</title>
    <?php include '../includes/header.php'; ?>
</head>

<style>
    .admin-avatar {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
    }

    .action-buttons {
        min-width: 120px;
    }
</style>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="d-flex align-items-center mb-4" style="gap:10px;">
                        <i class="fas fa-users-cog text-primary"></i>
                        <h4 class="mb-0">Admin Management</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Manage system administrators and user accounts</p>
                </div>
            </section>

            <?php if (!isset($_SESSION['position']) || $_SESSION['position'] !== 'superadmin') { echo '<div class="content p-3"><div class="alert alert-warning">Only Super Admin can manage admin accounts.</div></div>'; } ?>
            <section class="content" style="display: <?php echo (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin') ? 'block' : 'none'; ?>;">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Admin List</h3>
                                    <?php if (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin'): ?>
                                    <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#addAdminModal">
                                        <i class="fas fa-plus"></i> Add Admin
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <table id="adminsTable" class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Avatar</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Email</th>
                                                <th>Username</th>
                                                <th>Status</th>
                                                <th class="action-buttons">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Ensure is_active column exists
                                            try {
                                                $checkCol = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'is_active'");
                                                $checkCol->execute();
                                                $res = $checkCol->get_result()->fetch_assoc();
                                                if ((int)$res['cnt'] === 0) {
                                                    $conn->query("ALTER TABLE admin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
                                                }
                                                $checkCol->close();
                                            } catch (Exception $e) {}
                                            $sql = "SELECT *, IFNULL(is_active, 1) AS is_active FROM admin";
                                            $result = $conn->query($sql);
                                            if ($result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    $statusBadge = ((int)($row['is_active'] ?? 1) === 1)
                                                        ? "<span class='badge badge-success'>Active</span>"
                                                        : "<span class='badge badge-secondary'>Inactive</span>";
                                                    echo "<tr>
                                                    <td><img src='" . htmlspecialchars($row['image'] ?? '../images/profiles/default.jpg') . "' class='admin-avatar' alt='Admin Avatar'></td>
                                                    <td>" . htmlspecialchars($row['admin_name']) . "</td>
                                                    <td>" . ucfirst(htmlspecialchars($row['position'])) . "</td>
                                                    <td>" . htmlspecialchars($row['email_address']) . "</td>
                                                    <td>" . htmlspecialchars($row['username']) . "</td>
                                                    <td>" . $statusBadge . "</td>
                                                    <td>";
                                                    if (strtolower($row['position']) !== 'superadmin') {
                                                        echo "<button class='btn btn-sm btn-outline-primary edit-btn' data-id='" . $row['admin_id'] . "'>
                                                                <i class='fas fa-edit'></i> Edit
                                                              </button>";
                                                        if (isset($_SESSION['position']) && $_SESSION['position'] === 'superadmin' && (int)$row['admin_id'] !== (int)$_SESSION['admin_id']) {
                                                            $toggleText = ((int)$row['is_active'] === 1) ? 'Deactivate' : 'Activate';
                                                            $toggleIcon = ((int)$row['is_active'] === 1) ? 'fa-user-slash' : 'fa-user-check';
                                                            echo " <button class='btn btn-sm btn-outline-warning toggle-active-btn' data-id='" . $row['admin_id'] . "' data-active='" . (int)$row['is_active'] . "'>
                                                                    <i class='fas " . $toggleIcon . "'></i> " . $toggleText . "
                                                                  </button>";
                                                        }
                                                    }
                                                    echo "</td>
                                                </tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='7' class='text-center'>No admins found</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../modals/admin_modal.php'; ?>
        <?php include '../includes/footer.php'; ?>
        <?php include '../includes/scripts.php'; ?>
    </div>


    <!-- Admin Management Script -->
    <script>
        $(document).ready(function() {
            $('#adminsTable').DataTable({
                "responsive": true,
                "autoWidth": false
            });

            $(document).on('click', '.edit-btn', function() {
                var adminId = $(this).data('id');
                $.ajax({
                    url: 'admin_row.php',
                    method: 'GET',
                    data: {
                        get_admin: true,
                        id: adminId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            var admin = response.admin;
                            $('#edit_admin_id').val(admin.admin_id);
                            $('#edit_admin_name').val(admin.admin_name);
                            $('#edit_username').val(admin.username);
                            $('#edit_email_address').val(admin.email_address);
                            $('#edit_position').val(admin.position);
                            $('#edit_position_display').val(admin.position === 'superadmin' ? 'Super Admin' : 'Admin');
                            var currentImg = (admin.image && admin.image.length) ? admin.image : '../images/profiles/default.jpg';
                            $('#current_image').attr('src', currentImg);
                            $('#currentImageContainer').show();
                            $('#editImagePreviewContainer').hide();
                            $('#editAdminModal').modal('show');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to fetch admin data'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while fetching admin data'
                        });
                    }
                });
            });

            $(document).on('click', '.toggle-active-btn', function() {
                var adminId = $(this).data('id');
                var currentActive = parseInt($(this).data('active'));
                var action = currentActive === 1 ? 'deactivate' : 'activate';
                Swal.fire({
                    title: (action === 'deactivate' ? 'Deactivate Admin?' : 'Activate Admin?'),
                    text: (action === 'deactivate' ? 'This admin will not be able to log in.' : 'This admin will be able to log in.'),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, ' + action + ' it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'admin_toggle_active.php',
                            method: 'POST',
                            data: { admin_id: adminId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Updated!',
                                        text: response.message,
                                        showConfirmButton: true,
                                        timer: 1200
                                    }).then(() => { location.reload(); });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Error!', text: response.message });
                                }
                            },
                            error: function() {
                                Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to update status' });
                            }
                        });
                    }
                });
            });

            $('#editAdminForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: 'admin_edit.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#editAdminModal').modal('hide');
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred'
                        });
                    }
                });
            });

            // Remove legacy delete handlers above and below; now using toggle active
        });
    </script>
</body>

</html>