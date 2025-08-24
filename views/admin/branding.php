<?php
ob_start();
?>
<h1 class="mb-4">Branding</h1>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
<div class="mb-3">
    <label class="form-label">Brand Name</label>
    <input type="text" name="brand_name" class="form-control" value="<?php echo e($settings['brand_name'] ?? ''); ?>">
</div>
<div class="mb-3">
    <label class="form-label">Primary Color</label>
    <input type="color" name="primary_color" class="form-control form-control-color" value="<?php echo e($settings['primary_color'] ?? '#0d6efd'); ?>">
</div>
<div class="mb-3">
    <label class="form-label">Logo</label>
    <input type="file" name="logo" class="form-control">
    <?php if(!empty($settings['logo_path'])): ?>
        <img src="<?php echo e($settings['logo_path']);?>" alt="logo" style="height:60px" class="mt-2">
    <?php endif; ?>
</div>
<button class="btn btn-primary">Save</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
