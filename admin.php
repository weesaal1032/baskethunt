<?php
require __DIR__.'/core/bootstrap.php';
$updateFile = __DIR__.'/core/update.php';
if (file_exists($updateFile)) require_once $updateFile;
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


if ($route === 'apps.create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $is_global = isset($_POST['is_global']) ? 1 : 0;
        $logoPath = null;
        if (!empty($_FILES['logo']['tmp_name'])) {
            $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/svg+xml'=>'svg'];
            $type = mime_content_type($_FILES['logo']['tmp_name']);
            if (isset($allowed[$type]) && $_FILES['logo']['size'] <= 1024*1024) {
                $dir = __DIR__.'/public/logos';
                if (!is_dir($dir)) { mkdir($dir,0755,true); }
                $file = uniqid().'.'.$allowed[$type];
                move_uploaded_file($_FILES['logo']['tmp_name'],$dir.'/'.$file);
                $logoPath = '/public/logos/'.$file;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO applications(name,url,logo_path,is_global,is_enabled,sort_order) VALUES (?,?,?,?,1,(SELECT IFNULL(MAX(sort_order),0)+1 FROM applications))');
        $stmt->execute([$name,$url,$logoPath,$is_global]);
        redirect('/admin.php?r=apps.index');
    }
    $app = null;
    require __DIR__.'/views/admin/apps_form.php';
    exit;
}

if ($route === 'apps.edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id=?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) { http_response_code(404); exit('Not found'); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $is_global = isset($_POST['is_global']) ? 1 : 0;
        $logoPath = $app['logo_path'];
        if (!empty($_FILES['logo']['tmp_name'])) {
            $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/svg+xml'=>'svg'];
            $type = mime_content_type($_FILES['logo']['tmp_name']);
            if (isset($allowed[$type]) && $_FILES['logo']['size'] <= 1024*1024) {
                $dir = __DIR__.'/public/logos';
                if (!is_dir($dir)) { mkdir($dir,0755,true); }
                $file = uniqid().'.'.$allowed[$type];
                move_uploaded_file($_FILES['logo']['tmp_name'],$dir.'/'.$file);
                $logoPath = '/public/logos/'.$file;
            }
        }
        $stmt = $pdo->prepare('UPDATE applications SET name=?, url=?, logo_path=?, is_global=? WHERE id=?');
        $stmt->execute([$name,$url,$logoPath,$is_global,$id]);
        redirect('/admin.php?r=apps.index');
    }
    require __DIR__.'/views/admin/apps_form.php';
    exit;
}


if ($route === 'branding.index') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $brand = trim($_POST['brand_name'] ?? '');
        $color = trim($_POST['primary_color'] ?? '#0d6efd');
        $logoPath = $settings['logo_path'] ?? null;
        if (!empty($_FILES['logo']['tmp_name'])) {
            $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/svg+xml'=>'svg'];
            $type = mime_content_type($_FILES['logo']['tmp_name']);
            if (isset($allowed[$type]) && $_FILES['logo']['size'] <= 1024*1024) {
                $dir = __DIR__.'/public/logos';
                if (!is_dir($dir)) { mkdir($dir,0755,true); }
                $file = uniqid().'.'.$allowed[$type];
                move_uploaded_file($_FILES['logo']['tmp_name'],$dir.'/'.$file);
                $logoPath = '/public/logos/'.$file;
            }
        }
        $stmt = $pdo->prepare('UPDATE settings SET brand_name=?, primary_color=?, logo_path=? WHERE id=1');
        $stmt->execute([$brand,$color,$logoPath]);
        redirect('/admin.php?r=branding.index');
    }
    require __DIR__.'/views/admin/branding.php';
    exit;
}
if ($route === 'depts.index') {
    $depts = $pdo->query('SELECT * FROM departments ORDER BY sort_order')->fetchAll();
    require __DIR__.'/views/admin/depts_index.php';
    exit;
}
if ($route === 'updates.index') {
    $info = function_exists('update_check') ? update_check() : ['error' => 'Updater missing'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (isset($_POST['update'])) {
            apply_update();
            $info = update_check();
        } elseif (isset($_POST['rollback'])) {
            rollback_update();
            $info = update_check();
        }
    }
    require __DIR__.'/views/admin/updates.php';
    exit;
}
http_response_code(404);
echo 'Not Found';
