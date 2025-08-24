<?php
ob_start();
?>
<div class="container mt-5" style="max-width:400px;">
    <h1 class="h4 mb-3">Set New Password</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="New Password" required>
        </div>
        <button class="btn btn-primary w-100">Reset Password</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
