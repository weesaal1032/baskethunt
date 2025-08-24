<?php
ob_start();
?>
<div class="container mt-5" style="max-width:400px;">
<h1 class="h4 mb-3">Admin Login</h1>
<?php if(!empty($error)): ?><div class="alert alert-danger"><?php echo e($error);?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <div class="mb-3"><input name="email" type="email" class="form-control" placeholder="Email" required></div>
    <div class="mb-3"><input name="password" type="password" class="form-control" placeholder="Password" required></div>
    <div class="d-grid mb-3"><button class="btn btn-primary">Login</button></div>
</form>
<a href="/admin.php?r=auth.forgot">Forgot password?</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
