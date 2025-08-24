<?php
// Bootstrap file
$config = require __DIR__.'/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to installer when application hasn't been set up
if (empty($config['installed']) || !file_exists(__DIR__.'/../installed.lock')) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/install') !== 0) {
        header('Location: /install/');
        exit;
    }
}

require __DIR__.'/db.php';
require __DIR__.'/helpers.php';
require __DIR__.'/security.php';
require __DIR__.'/auth.php';
require __DIR__.'/mailer.php';
