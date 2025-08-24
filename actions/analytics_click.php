<?php
require __DIR__.'/../core/bootstrap.php';
verify_csrf();
$pdo = db();
$appId = (int)($_POST['app_id'] ?? 0);
$dept = $_POST['dept'] ?? '';
$stmt = $pdo->prepare('INSERT INTO app_clicks (application_id, department_slug, user_agent) VALUES (?,?,?)');
$stmt->execute([$appId,$dept,$_SERVER['HTTP_USER_AGENT'] ?? '']);
echo 'ok';
