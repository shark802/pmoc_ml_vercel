<?php
// messages.php - For displaying alerts
// Success messages are now handled via global SweetAlert2 toast in includes/scripts.php
?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?= $_SESSION['error_message'];
        unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>