<?php
ob_start();
?>
<h1 class="mb-4"><?php echo $app ? 'Edit' : 'Add'; ?> Application</h1>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="<?php echo e($app['name'] ?? ''); ?>" required>
</div>
<div class="mb-3">
    <label class="form-label">URL</label>
    <input type="url" name="url" class="form-control" value="<?php echo e($app['url'] ?? ''); ?>" required>
</div>
<div class="mb-3">
    <label class="form-label">Logo</label>
    <input type="file" name="logo" class="form-control">
    <?php if(!empty($app['logo_path'])): ?>
        <img src="<?php echo e($app['logo_path']);?>" alt="logo" style="height:60px" class="mt-2">
    <?php endif; ?>
</div>
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="is_global" id="is_global" <?php echo !empty($app['is_global'])?'checked':''; ?>>
    <label class="form-check-label" for="is_global">Global</label>
</div>
<button class="btn btn-primary">Save</button>
<a href="/admin.php?r=apps.index" class="btn btn-secondary">Cancel</a>
</form>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
