<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

$lang = pick($_POST['lang'] ?? '', SUPPORTED_LANGUAGES, 'en');
setcookie('mx_lang', $lang, [
    'expires' => time() + 365 * 24 * 3600,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

redirect(clean($_POST['next'] ?? '/dashboard.php', 200));
