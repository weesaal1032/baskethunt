<?php
ob_start();
?>
<h1 class="text-2xl mb-4"><?php echo $app ? 'Edit' : 'Add'; ?> Application</h1>
<form method="post" enctype="multipart/form-data" class="space-y-4">
<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
<div>
    <label class="block mb-1">Name</label>
    <input type="text" name="name" class="w-full border rounded p-2" value="<?php echo e($app['name'] ?? ''); ?>" required>
</div>
<div>
    <label class="block mb-1">URL</label>
    <input type="url" name="url" class="w-full border rounded p-2" value="<?php echo e($app['url'] ?? ''); ?>" required>
</div>
<div>
    <label class="block mb-1">Logo</label>
    <input type="file" name="logo" class="w-full border rounded p-2">
    <?php if(!empty($app['logo_path'])): ?>
        <img src="<?php echo e($app['logo_path']);?>" alt="logo" class="h-16 mt-2">
    <?php endif; ?>
</div>
<div class="flex items-center">
    <input class="mr-2" type="checkbox" name="is_global" id="is_global" <?php echo !empty($app['is_global'])?'checked':''; ?>>
    <label for="is_global">Global</label>
</div>
<div class="space-x-2">
    <button class="px-4 py-2 rounded text-white bg-[var(--brand)]">Save</button>
    <a href="/admin.php?r=apps.index" class="px-4 py-2 rounded bg-gray-200">Cancel</a>
</div>
</form>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
