<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';
// Fetch all categories
$categories = [];
try {
    $stmt = $conn->prepare("SELECT * FROM question_category ORDER BY category_id ASC"); // Ensures that categories are ordered by ID
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching categories: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Manage Question Categories</title>
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
                        <i class="fas fa-tags text-primary"></i>
                        <h4 class="mb-0">Category Management</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Organize questions into categories for better assessment structure</p>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>
                    <div class="card">
                        <div class="card-header">
                        <h3 class="card-title">Category List</h3>
                            <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#addCategoryModal">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="categoriesTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th> <!-- Add sequential number column -->
                                        <th>Category Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; // Start the counter at 1 
                                    ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?= $counter++ ?></td> <!-- Display sequential number -->
                                            <td><?= htmlspecialchars($category['category_name']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-btn"
                                                    data-id="<?= $category['category_id'] ?>"
                                                    data-name="<?= htmlspecialchars($category['category_name']) ?>"
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-btn"
                                                    data-id="<?= $category['category_id'] ?>"
                                                    data-name="<?= htmlspecialchars($category['category_name']) ?>"
                                                    title="Delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php include '../modals/question_category_modal.php'; ?>
        <?php include '../includes/footer.php'; ?>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
        $(document).ready(function() {
            $('#categoriesTable').DataTable({
                "responsive": true,
                "autoWidth": false
            });


            $('.edit-btn').click(function() {
                const id = $(this).data('id');
                $('#editCategoryModal').modal('show');
                $('#editCategoryModal').find('#category_id').val(id);
                $('#editCategoryModal').find('#category_name').val($(this).data('name'));
            });

            // Delete with SweetAlert2 for uniform UX
            $('.delete-btn').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete Category?',
                    text: `This will delete the category "${name}".`,
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = $('<form>', { method: 'POST', action: 'question_category_delete.php' });
                        form.append($('<input>', { type: 'hidden', name: 'category_id', value: id }));
                        $('body').append(form);
                        form.trigger('submit');
                    }
                });
            });
        });
    </script>
</body>

</html>