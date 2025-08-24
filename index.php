<?php
require __DIR__.'/core/bootstrap.php';
$pdo = db();

// Fetch settings and departments; show helpful message if schema is missing
try {
    $settings = $pdo->query('SELECT * FROM settings WHERE id=1')->fetch() ?: [];
    $depts = $pdo->query('SELECT slug,name FROM departments WHERE is_active=1 ORDER BY sort_order')->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database not initialized. Please run the installer.');
}
$deptSlug = $_GET['dept'] ?? ($_COOKIE['dept'] ?? '');
if ($deptSlug) {
    setcookie('dept',$deptSlug,time()+3600*24*30,'/');
}
// Build department selector
ob_start();
?>
<select id="department" class="border rounded p-2">
    <option value="">All Departments</option>
    <?php foreach($depts as $d): ?>
        <option value="<?php echo e($d['slug']);?>" <?php echo $deptSlug===$d['slug']?'selected':'';?>><?php echo e($d['name']);?></option>
    <?php endforeach; ?>
</select>
<?php
$departmentSelector = ob_get_clean();
// Fetch apps
if ($deptSlug) {
    $stmt = $pdo->prepare('SELECT DISTINCT a.* FROM applications a
        LEFT JOIN application_department ad ON a.id=ad.application_id
        LEFT JOIN departments d ON d.id=ad.department_id
        WHERE a.is_enabled=1 AND (a.is_global=1 OR d.slug=?) ORDER BY a.sort_order');
    $stmt->execute([$deptSlug]);
    $apps = $stmt->fetchAll();
} else {
    $apps = $pdo->query('SELECT * FROM applications WHERE is_global=1 AND is_enabled=1 ORDER BY sort_order')->fetchAll();
}
require __DIR__.'/views/public/launcher.php';
