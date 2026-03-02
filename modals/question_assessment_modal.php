<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="question_assessment_add.php" method="POST">
        <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <div class="modal-header bg-primary">
          <h4 class="modal-title">Add Question</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control" required>
              <option value="" disabled selected>Select Category</option> <!-- Placeholder option -->
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>">
                  <?= htmlspecialchars($cat['category_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>


          <div class="form-group">
            <label>Statement</label>
            <textarea name="question_text" class="form-control" rows="3" required></textarea>
          </div>

          <div class="form-group">
            <label>Sub-Questions</label>
            <div class="sub-questions-container mb-2"></div>
            <button type="button" class="btn btn-sm btn-secondary add-sub">
              <i class="fas fa-plus"></i> Add Sub-Question
            </button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" role="dialog" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="editQuestionForm" action="question_assessment_edit.php" method="POST">
      <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <div class="modal-content">
        <div class="modal-header bg-primary">
          <h5 class="modal-title text-white" id="editQuestionModalLabel">Edit Question</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="editQuestionId" name="question_id">

          <div class="form-group">
            <label for="editCategoryId">Category</label>
            <select class="form-control" id="editCategoryId" name="category_id" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="editQuestionText">Statement</label>
            <textarea class="form-control" id="editQuestionText" name="question_text" rows="3" required></textarea>
          </div>

          <div class="form-group">
            <label>Sub-Questions</label>
            <div class="edit-sub-questions-container mb-2">
              <!-- Sub-questions will be filled dynamically -->
            </div>
            <button type="button" class="btn btn-sm btn-secondary edit-add-sub">
              <i class="fas fa-plus"></i> Add Sub-Question
            </button>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>



<!-- Delete Question Modal -->
<div class="modal fade" id="deleteQuestionModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="question_assessment_delete.php" method="POST">
        <?php require_once __DIR__ . '/../includes/csrf_helper.php'; ?>
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <input type="hidden" name="question_id" id="deleteQuestionId">
        <div class="modal-header bg-danger">
          <h4 class="modal-title text-white">Delete Question</h4>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this question?</p>
          <h5 id="deleteQuestionText" class="text-center font-weight-bold"></h5>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>