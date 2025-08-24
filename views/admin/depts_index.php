<?php
ob_start();
?>
<h1 class="mb-4">Departments</h1>
<table class="table table-striped">
    <thead><tr><th>Name</th><th>Slug</th></tr></thead>
    <tbody>
    <?php foreach($depts as $d): ?>
        <tr><td><?php echo e($d['name']);?></td><td><?php echo e($d['slug']);?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__.'/layout.php';
