<?php
ob_start();
?>
<h1 class="text-2xl mb-4">Departments</h1>
<table class="min-w-full bg-white rounded shadow">
    <thead class="bg-gray-100"><tr><th class="text-left p-2">Name</th><th class="text-left p-2">Slug</th></tr></thead>
    <tbody class="divide-y">
    <?php foreach($depts as $d): ?>
        <tr><td class="p-2"><?php echo e($d['name']);?></td><td class="p-2"><?php echo e($d['slug']);?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
