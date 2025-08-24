<?php
ob_start();
?>
<h1 class="mb-4">Applications <a href="/admin.php?r=apps.create" class="btn btn-primary btn-sm float-end">Add</a></h1>
<table class="table table-striped">
    <thead><tr><th>Name</th><th>URL</th><th></th></tr></thead>
    <tbody>
    <?php foreach($apps as $app): ?>
        <tr>
            <td><?php echo e($app['name']);?></td>
            <td><?php echo e($app['url']);?></td>
            <td class="text-end"><a href="/admin.php?r=apps.edit&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-secondary">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
