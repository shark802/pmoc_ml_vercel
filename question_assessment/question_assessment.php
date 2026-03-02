<?php
require_once '../includes/conn.php';
require_once '../includes/session.php';

// Fetch all questions with sub-questions
$questions = [];
try {
    $stmt = $conn->prepare("
        SELECT q.*, c.category_name, sq.sub_question_id, sq.sub_question_text
        FROM question_assessment q
        LEFT JOIN question_category c ON q.category_id = c.category_id
        LEFT JOIN sub_question_assessment sq ON q.question_id = sq.question_id
        ORDER BY c.category_id ASC, q.question_id ASC, sq.sub_question_id ASC
    ");
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group sub-questions
    $grouped = [];
    foreach ($results as $row) {
        $question_id = $row['question_id'];
        if (!isset($grouped[$question_id])) {
            $grouped[$question_id] = [
                'question_id' => $question_id,
                'question_text' => $row['question_text'],
                'category_name' => $row['category_name'],
                'sub_questions' => []
            ];
        }
        if ($row['sub_question_id']) {
            $grouped[$question_id]['sub_questions'][] = [
                'sub_question_id' => $row['sub_question_id'],
                'sub_question_text' => $row['sub_question_text']
            ];
        }
    }
    $questions = array_values($grouped);
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Error fetching questions in " . __FILE__ . " line " . __LINE__ . ": " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $_SESSION['error_message'] = "Unable to load questions. Please refresh the page.";
}

// Fetch categories with caching (cache for 1 hour)
$categories = [];
try {
    require_once __DIR__ . '/../includes/cache_helper.php';
    
    $categories = getCachedData('question_categories', function() use ($conn) {
        $stmt = $conn->prepare("SELECT * FROM question_category ORDER BY category_id ASC");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }, 3600); // Cache for 1 hour
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Error fetching categories in " . __FILE__ . " line " . __LINE__ . ": " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $_SESSION['error_message'] = "Unable to load question categories. Please refresh the page.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Question Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .sub-question {
            background-color: #f8f9fa;
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
                    <!-- Page Header -->
                    <div class="d-flex align-items-center mb-4" style="gap:10px;">
                        <i class="fas fa-question-circle text-primary"></i>
                        <h4 class="mb-0">Question Management</h4>
                    </div>
                    <p class="text-muted" style="margin-top:-6px;">Create and manage assessment questions and categories</p>
                </div>
            </section>
            <section class="content">
                <div class="container-fluid">
                    <?php include '../includes/messages.php'; ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Question List</h3>
                            <button class="btn btn-success btn-sm float-right" data-toggle="modal" data-target="#addQuestionModal">
                                <i class="fas fa-plus"></i> Add Question
                            </button>
                        </div>
                        <div class="card-body">
                            <?php 
                                // Prepare first four categories for the button grid
                                $firstFour = array_slice($categories, 0, 4);
                            ?>
                            <div class="mb-3">
                                <div class="row">
                                    <?php foreach ($firstFour as $cat): ?>
                                        <?php 
                                            $fullName = $cat['category_name'];
                                            $stripped = preg_replace('/^MARRIAGE EXPECTATIONS AND INVENTORY ON\s+/i', '', $fullName);
                                            if (function_exists('mb_strimwidth')) {
                                                $shortLabel = mb_strimwidth($stripped, 0, 38, '…', 'UTF-8');
                                            } else {
                                                $shortLabel = strlen($stripped) > 38 ? substr($stripped, 0, 35) . '…' : $stripped;
                                            }
                                        ?>
                                        <div class="col-md-6 mb-2">
                                            <button type="button" class="btn btn-outline-secondary btn-block category-pill" data-category="<?= htmlspecialchars($fullName) ?>" title="<?= htmlspecialchars($fullName) ?>">
                                                <i class="fas fa-tag"></i> <?= htmlspecialchars($shortLabel) ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-right">
                                    <a href="#" class="small category-pill" data-category="__ALL__"><i class="fas fa-list"></i> Show All</a>
                                </div>
                            </div>
                            <table id="questionTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Question Number</th>
                                        <th>Main Question</th>
                                        <th>Sub-Questions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $i = 1; // Initialize the counter for the first category
                                    $currentCategory = ''; // Initialize the current category tracker

                                    foreach ($questions as $question):
                                        // Check if the category changes
                                        if ($currentCategory != $question['category_name']) {
                                            $currentCategory = $question['category_name']; // Update the current category
                                            $i = 1; // Reset the counter for the new category
                                        }
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($question['category_name']) ?></td>
                                            <td><?= $i++ ?></td> <!-- Display the counter value and increment it -->
                                            <td><?= htmlspecialchars($question['question_text']) ?></td>
                                            <td>
                                                <?php if (!empty($question['sub_questions'])): ?>
                                                    <ul class="list-unstyled">
                                                        <?php foreach ($question['sub_questions'] as $sub): ?>
                                                            <li>• <?= htmlspecialchars($sub['sub_question_text']) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-btn mr-2" data-id="<?= $question['question_id'] ?>"
                                                title="Edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $question['question_id'] ?>"
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

        <?php include '../includes/footer.php'; ?>
        <?php include '../modals/question_assessment_modal.php'; ?>
        <?php include '../includes/scripts.php'; ?>
    </div>

    
    <script>
        $(document).ready(function() {
            // Guard: prevent opening Add Question modal when no categories exist
            const categories = <?= json_encode($categories ?? []) ?>;
            $(document).on('click', 'button[data-target="#addQuestionModal"]', function(e){
                if (!categories || categories.length === 0){
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Add a category first',
                        html: 'You need at least one category before adding questions.<br><small>Tip: Create “General Counseling” to get started.</small>',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
            });

            const table = $('#questionTable').DataTable({
                "responsive": true,
                "autoWidth": false,
                "stateSave": true,
                // 0 = persist indefinitely in localStorage for this table id
                "stateDuration": 0
            });

            // Category quick filter pills with persistence
            const filterKey = 'qa_category_filter';
            function applyCategoryFilter(value){
                if (value === '__ALL__') {
                    table.column(0).search('');
                } else {
                    table.column(0).search('^' + $.fn.dataTable.util.escapeRegex(value) + '$', true, false);
                }
                table.draw();
            }
            // Restore saved filter
            const savedFilter = localStorage.getItem(filterKey) || '__ALL__';
            applyCategoryFilter(savedFilter);
            $('.category-pill').removeClass('active').filter(`[data-category="${savedFilter}"]`).addClass('active');

            // Handle clicks
            $(document).on('click', '.category-pill', function(e) {
                e.preventDefault();
                $('.category-pill').removeClass('active');
                $(this).addClass('active');
                const value = $(this).data('category');
                localStorage.setItem(filterKey, value);
                applyCategoryFilter(value);
            });

            // Add Sub-Question Field
            $(document).on('click', '.add-sub', function() {
                const container = $(this).closest('.modal').find('.sub-questions-container');
                const newField = `<div class="input-group mb-2">
            <input type="text" name="sub_questions[]" class="form-control" required>
            <div class="input-group-append">
                <button class="btn btn-outline-danger remove-sub" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;
                container.append(newField);
            });

            // Remove Sub-Question Field
            $(document).on('click', '.remove-sub', function() {
                $(this).closest('.input-group').remove();
            });

            // Edit Question Handler (delegated for DataTables pagination/redraw)
            $(document).on('click', '.edit-btn', function() {
                const questionId = $(this).data('id');
                $.get('question_assessment_row.php', {
                    id: questionId
                }, function(response) {
                    const data = JSON.parse(response);
                    const modal = $('#editQuestionModal');

                    modal.find('#editQuestionId').val(data.question.question_id);
                    modal.find('#editCategoryId').val(data.question.category_id);
                    modal.find('#editQuestionText').val(data.question.question_text);

                    const subContainer = modal.find('.edit-sub-questions-container');
                    subContainer.empty(); // <-- Clear old sub-questions

                    data.sub_questions.forEach(sub => {
                        subContainer.append(`
                <div class="input-group mb-2">
                    <input type="hidden" name="existing_sub_ids[]" value="${sub.sub_question_id}">
                    <input type="text" name="sub_questions[]" 
                        value="${sub.sub_question_text}" class="form-control" required>
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-sub" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);
                    });

                    modal.modal('show');
                });
            });

            // Add new sub-question manually inside Edit Modal
            $(document).on('click', '.edit-add-sub', function() {
                const container = $(this).closest('.modal').find('.edit-sub-questions-container');
                const newField = `
        <div class="input-group mb-2">
            <input type="text" name="sub_questions[]" class="form-control" required>
            <div class="input-group-append">
                <button class="btn btn-outline-danger remove-sub" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
                container.append(newField);
            });

            // Delete Handler (delegated for DataTables pagination/redraw)
            $(document).on('click', '.delete-btn', function() {
                const questionId = $(this).data('id');
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete Question?',
                    text: 'This will delete the question and all its sub-questions.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location = `question_assessment_delete.php?id=${questionId}`;
                    }
                });
            });
        });
    </script>
</body>

</html>