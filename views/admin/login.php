<?php
ob_start();
?>
<div class="max-w-sm mx-auto mt-20 bg-white p-6 rounded-2xl shadow">
<h1 class="text-xl mb-4">Admin Login</h1>
<?php if(!empty($error)): ?><div class="text-red-600 mb-2"><?php echo e($error);?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <input name="email" type="email" class="w-full p-2 border mb-2" placeholder="Email" required>
    <input name="password" type="password" class="w-full p-2 border mb-4" placeholder="Password" required>
    <button class="bg-[var(--brand)] text-white px-4 py-2 rounded">Login</button>
</form>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
