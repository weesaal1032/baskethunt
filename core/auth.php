<?php
function auth_user() {
    return $_SESSION['admin'] ?? null;
}
function require_admin() {
    if (!auth_user()) {
        redirect('/admin.php?r=auth.login');
    }
}
