<?php
ob_start();
?>
<h1 class="text-2xl mb-4">Branding</h1>
<form method="post" enctype="multipart/form-data" class="space-y-4">
<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
<div>
    <label class="block mb-1">Brand Name</label>
    <input type="text" name="brand_name" class="w-full border rounded p-2" value="<?php echo e($settings['brand_name'] ?? ''); ?>">
</div>
<div>
    <label class="block mb-1">Primary Color</label>
    <input type="color" name="primary_color" class="w-20 h-10 p-1 border rounded" value="<?php echo e($settings['primary_color'] ?? '#0d6efd'); ?>">
</div>
<div>
    <label class="block mb-1">Logo</label>
    <input type="file" name="logo" class="w-full border rounded p-2">
    <?php if(!empty($settings['logo_path'])): ?>
        <img src="<?php echo e($settings['logo_path']);?>" alt="logo" class="h-16 mt-2">
    <?php endif; ?>
</div>
<button class="px-4 py-2 rounded text-white bg-[var(--brand)]">Save</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
