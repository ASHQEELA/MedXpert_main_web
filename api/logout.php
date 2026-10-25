<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Logging out needs a POST with a valid token, so another site cannot sign users out
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(isLoggedIn() ? '/dashboard.php' : '/index.php');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();
redirect('/index.php');
