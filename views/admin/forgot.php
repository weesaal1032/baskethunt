<?php
ob_start();
?>
<div class="container mt-5" style="max-width:400px;">
    <h1 class="h4 mb-3">Forgot Password</h1>
    <?php if(!empty($message)): ?><div class="alert alert-info"><?php echo e($message);?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email" required>
        </div>
        <button class="btn btn-primary w-100">Send Reset Link</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
