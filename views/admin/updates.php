<?php ob_start(); ?>
<h1 class="text-2xl mb-4">Updates</h1>
<?php if (!empty($status)): ?>
<div class="p-2 mb-4 rounded bg-blue-100 text-blue-700"><?php echo e($status); ?></div>
<?php endif; ?>
<form method="post" class="mb-4 space-y-4">
    <?php csrf_field(); ?>
    <div>
        <label class="block mb-1">Repository URL</label>
        <input type="text" name="repo_url" class="w-full border rounded p-2" value="<?php echo e($settings['repo_url'] ?? ''); ?>">
    </div>
    <button name="save_repo" class="px-4 py-2 rounded bg-gray-200">Save</button>
</form>
<?php if (!empty($info['error'])): ?>
<div class="p-2 mb-4 rounded bg-red-100 text-red-700"><?php echo e($info['error']); ?></div>
<?php else: ?>
<p>Current version: <code><?php echo e($info['local']); ?></code></p>
<p>Latest version: <code><?php echo e($info['remote']); ?></code></p>
<?php if ($info['up_to_date']): ?>
<div class="p-2 mb-4 rounded bg-green-100 text-green-700">You are up to date.</div>
<?php else: ?>
<form method="post" class="mb-3">
    <?php csrf_field(); ?>
    <button name="update" class="px-4 py-2 rounded text-white bg-[var(--brand)]">Update to Latest</button>
</form>
<?php endif; ?>
<form method="post">
    <?php csrf_field(); ?>
    <button name="rollback" class="px-4 py-2 rounded bg-yellow-500 text-white">Rollback</button>
</form>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__.'/layout.php'; ?>
