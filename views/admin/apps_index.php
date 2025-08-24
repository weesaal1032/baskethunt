<?php
ob_start();
?>
<h1 class="text-2xl mb-4 flex justify-between items-center">Applications <a href="/admin.php?r=apps.create" class="bg-[var(--brand)] text-white px-3 py-1 rounded text-sm">Add</a></h1>
<table class="min-w-full bg-white rounded shadow">
    <thead class="bg-gray-100"><tr><th class="text-left p-2">Name</th><th class="text-left p-2">URL</th><th class="p-2"></th></tr></thead>
    <tbody class="divide-y">
    <?php foreach($apps as $app): ?>
        <tr>
            <td class="p-2"><?php echo e($app['name']);?></td>
            <td class="p-2"><?php echo e($app['url']);?></td>
            <td class="p-2 text-right"><a href="/admin.php?r=apps.edit&id=<?php echo $app['id']; ?>" class="text-sm text-blue-600 hover:underline">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
