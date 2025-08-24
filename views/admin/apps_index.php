<?php
ob_start();
?>
<h1 class="mb-4">Applications</h1>
<table class="table table-striped">
    <thead><tr><th>Name</th><th>URL</th></tr></thead>
    <tbody>
    <?php foreach($apps as $app): ?>
        <tr><td><?php echo e($app['name']);?></td><td><?php echo e($app['url']);?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
