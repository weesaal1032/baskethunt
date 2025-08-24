<?php ob_start(); ?>
<h1>Updates</h1>
<?php if (!empty($info['error'])): ?>
<div class="alert alert-danger"><?php echo e($info['error']); ?></div>
<?php else: ?>
<p>Current version: <code><?php echo e($info['local']); ?></code></p>
<p>Latest version: <code><?php echo e($info['remote']); ?></code></p>
<?php if ($info['up_to_date']): ?>
<div class="alert alert-success">You are up to date.</div>
<?php else: ?>
<form method="post" class="mb-3">
    <?php csrf_field(); ?>
    <button name="update" class="btn btn-primary">Update to Latest</button>
</form>
<?php endif; ?>
<form method="post">
    <?php csrf_field(); ?>
    <button name="rollback" class="btn btn-warning">Rollback</button>
</form>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__.'/layout.php'; ?>
