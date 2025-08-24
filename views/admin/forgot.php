<?php
ob_start();
?>
<div class="max-w-sm mx-auto mt-24 bg-white p-6 rounded shadow">
    <h1 class="text-xl font-semibold mb-4 text-center">Forgot Password</h1>
    <?php if(!empty($message)): ?><div class="mb-4 p-2 rounded bg-blue-100 text-blue-700"><?php echo e($message);?></div><?php endif; ?>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="email" name="email" class="w-full border rounded p-2" placeholder="Email" required>
        <button class="w-full py-2 rounded text-white bg-[var(--brand)]">Send Reset Link</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
