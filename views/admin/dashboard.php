<?php
ob_start();
?>
<h1 class="text-2xl mb-4">Dashboard</h1>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="bg-white p-4 rounded shadow"><div class="text-sm text-gray-500">Apps</div><div class="text-2xl"><?php echo $stats['apps']; ?></div></div>
    <div class="bg-white p-4 rounded shadow"><div class="text-sm text-gray-500">Departments</div><div class="text-2xl"><?php echo $stats['depts']; ?></div></div>
</div>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
