<?php
require __DIR__.'/core/bootstrap.php';
$pdo = db();
$route = $_GET['r'] ?? 'dashboard.index';
$settings = $pdo->query('SELECT * FROM settings WHERE id=1')->fetch() ?: [];
if ($route === 'auth.login') {
    if (auth_user()) { redirect('/admin.php'); }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email=?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password,$user['password_hash'])) {
            $_SESSION['admin'] = ['id'=>$user['id'],'name'=>$user['name']];
            redirect('/admin.php');
        } else {
            $error = 'Invalid credentials';
        }
    }
    require __DIR__.'/views/admin/login.php';
    exit;
}
// secure routes
if (!auth_user()) {
    redirect('/admin.php?r=auth.login');
}
if ($route === 'dashboard.index') {
    $stats = [
        'apps' => (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
        'depts' => (int)$pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
    ];
    require __DIR__.'/views/admin/dashboard.php';
    exit;
}
http_response_code(404);
echo 'Not Found';
