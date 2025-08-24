<?php
ob_start();
?>
<div class="max-w-sm mx-auto mt-24 bg-white p-6 rounded shadow">
    <h1 class="text-xl font-semibold mb-4 text-center">Set New Password</h1>
    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="password" name="password" class="w-full border rounded p-2" placeholder="New Password" required>
        <button class="w-full py-2 rounded text-white bg-[var(--brand)]">Reset Password</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/auth_layout.php';
