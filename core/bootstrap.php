<?php
// Bootstrap file
$config = require __DIR__.'/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__.'/db.php';
require __DIR__.'/helpers.php';
require __DIR__.'/security.php';
require __DIR__.'/auth.php';
