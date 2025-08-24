<?php
ob_start();
?>
<div class="max-w-sm mx-auto mt-24 bg-white p-6 rounded shadow">
    <h1 class="text-xl font-semibold mb-4 text-center">Admin Login</h1>
    <?php if(!empty($error)): ?><div class="mb-4 p-2 rounded bg-red-100 text-red-700"><?php echo e($error);?></div><?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input name="email" type="email" class="w-full border rounded p-2" placeholder="Email" required>
        <input name="password" type="password" class="w-full border rounded p-2" placeholder="Password" required>
        <button class="w-full py-2 rounded text-white bg-[var(--brand)]">Login</button>
    </form>
    <div class="text-center mt-4"><a href="/admin.php?r=auth.forgot" class="text-sm text-blue-600 hover:underline">Forgot password?</a></div>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
