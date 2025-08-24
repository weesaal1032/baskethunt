<?php
require __DIR__."/../core/helpers.php";
if (file_exists(__DIR__.'/../installed.lock')) {
    http_response_code(403);
    exit('Already installed');
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? '';
    $adminPass = $_POST['admin_pass'] ?? '';
    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        // run migrations
        foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
        }
        // create admin user
        $hash = password_hash($adminPass,PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO admin_users (name,email,password_hash,is_super) VALUES (?,?,?,1)');
        $stmt->execute(['Admin',$adminEmail,$hash]);
        // write config
        $config = include __DIR__.'/../config.sample.php';
        $config['db'] = ['host'=>$dbHost,'name'=>$dbName,'user'=>$dbUser,'pass'=>$dbPass,'charset'=>'utf8mb4'];
        $config['app_key'] = bin2hex(random_bytes(16));
        $config['installed'] = true;
        file_put_contents(__DIR__.'/../config.php','<?php return '.var_export($config,true).';');
        touch(__DIR__.'/../installed.lock');
        echo 'Installation complete. <a href="/admin.php">Go to admin</a>';
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Install</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 p-8">
<h1 class="text-2xl mb-4">Basket Hunt Installer</h1>
<?php if(!empty($error)): ?><div class="text-red-600 mb-4"><?php echo e($error);?></div><?php endif; ?>
<form method="post" class="space-y-2 max-w-md">
<input name="db_host" class="border p-2 w-full" placeholder="DB Host" required>
<input name="db_name" class="border p-2 w-full" placeholder="DB Name" required>
<input name="db_user" class="border p-2 w-full" placeholder="DB User" required>
<input name="db_pass" class="border p-2 w-full" placeholder="DB Password" type="password">
<input name="admin_email" class="border p-2 w-full" placeholder="Admin Email" required>
<input name="admin_pass" class="border p-2 w-full" placeholder="Admin Password" type="password" required>
<button class="bg-blue-600 text-white px-4 py-2 rounded">Install</button>
</form>
</body></html>
