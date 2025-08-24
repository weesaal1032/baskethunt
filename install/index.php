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
<html>
<head>
    <meta charset="utf-8">
    <title>Basket Hunt Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center p-4">
    <div class="bg-white shadow-xl rounded-lg w-full max-w-lg p-8">
        <h1 class="text-3xl font-semibold text-center mb-6">Basket Hunt Setup</h1>
        <?php if(!empty($error)): ?><div class="bg-red-100 border border-red-300 text-red-700 p-2 rounded mb-4 text-sm"><?php echo e($error);?></div><?php endif; ?>
        <form method="post" class="space-y-6">
            <div>
                <h2 class="text-lg font-medium mb-2">Database</h2>
                <div class="space-y-2">
                    <div>
                        <label class="block text-sm font-medium">DB Host</label>
                        <input name="db_host" class="border rounded w-full p-2" placeholder="localhost" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">DB Name</label>
                        <input name="db_name" class="border rounded w-full p-2" placeholder="baskethunt" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">DB User</label>
                        <input name="db_user" class="border rounded w-full p-2" placeholder="root" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">DB Password</label>
                        <input name="db_pass" type="password" class="border rounded w-full p-2" placeholder="••••••">
                    </div>
                </div>
            </div>
            <div>
                <h2 class="text-lg font-medium mb-2">Admin Account</h2>
                <div class="space-y-2">
                    <div>
                        <label class="block text-sm font-medium">Admin Email</label>
                        <input name="admin_email" class="border rounded w-full p-2" placeholder="admin@example.com" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Admin Password</label>
                        <input name="admin_pass" type="password" class="border rounded w-full p-2" placeholder="••••••" required>
                    </div>
                </div>
            </div>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded">Install</button>
        </form>
    </div>
</body>
</html>
