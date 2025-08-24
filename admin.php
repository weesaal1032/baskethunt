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

if ($route === 'auth.logout') {
    session_destroy();
    redirect('/admin.php?r=auth.login');
}

if ($route === 'auth.forgot') {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        $email = $_POST['email'] ?? '';
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email=?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time()+3600);
            $pdo->prepare('INSERT INTO password_resets(email, token, expires_at) VALUES (?,?,?)')
                ->execute([$email,$token,$expires]);
            $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].'/admin.php?r=auth.reset&token='.$token;
            send_mail($email, 'Password Reset', "Click <a href='$link'>here</a> to reset your password.");
        }
        $message = 'If that email exists, a reset link has been sent.';
    }
    require __DIR__.'/views/admin/forgot.php';
    exit;
}

if ($route === 'auth.reset') {
    $token = $_GET['token'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token=? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if (!$reset) {
        exit('Invalid token');
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        $password = $_POST['password'] ?? '';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE admin_users SET password_hash=? WHERE email=?')->execute([$hash,$reset['email']]);
        $pdo->prepare('DELETE FROM password_resets WHERE email=?')->execute([$reset['email']]);
        redirect('/admin.php?r=auth.login');
    }
    require __DIR__.'/views/admin/reset.php';
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

if ($route === 'apps.index') {
    $apps = $pdo->query('SELECT * FROM applications ORDER BY sort_order')->fetchAll();
    require __DIR__.'/views/admin/apps_index.php';
    exit;
}

if ($route === 'depts.index') {
    $depts = $pdo->query('SELECT * FROM departments ORDER BY sort_order')->fetchAll();
    require __DIR__.'/views/admin/depts_index.php';
    exit;
}
http_response_code(404);
echo 'Not Found';
