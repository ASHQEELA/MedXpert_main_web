<?php
/**
 * MedXpert bootstrap.
 * Loads settings, hardens the session, connects to the database, and provides
 * the helpers every page uses: escaping, CSRF, auth guards, flash messages,
 * validation, rate limiting, and safe image uploads.
 *
 * Every page and API file loads this file (directly or through db.php).
 */

if (defined('MX_BOOTSTRAPPED')) {
    return;
}
define('MX_BOOTSTRAPPED', true);

/* ------------------------------------------------------------------
 * 1. Errors: log them, never show them to visitors
 * ------------------------------------------------------------------ */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
// Write errors to logs/php-error.log when that folder can be written
$mx_log_dir = dirname(__DIR__) . '/logs';
if (is_dir($mx_log_dir) && is_writable($mx_log_dir)) {
    ini_set('error_log', $mx_log_dir . '/php-error.log');
}
error_reporting(E_ALL);

/* ------------------------------------------------------------------
 * 2. Settings: config.local.php, then environment variables
 * ------------------------------------------------------------------ */
$GLOBALS['mx_config'] = [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'medxpert',
    'db_port' => 3306,
];

$mx_local_config = __DIR__ . '/config.local.php';
if (is_file($mx_local_config)) {
    $mx_loaded = require $mx_local_config;
    if (is_array($mx_loaded)) {
        $GLOBALS['mx_config'] = array_merge($GLOBALS['mx_config'], $mx_loaded);
    }
}

foreach (['db_host', 'db_user', 'db_pass', 'db_name', 'db_port', 'mail_mode', 'mail_from', 'app_url', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure'] as $mx_key) {
    $mx_env = getenv('MX_' . strtoupper($mx_key));
    if ($mx_env !== false && $mx_env !== '') {
        $GLOBALS['mx_config'][$mx_key] = $mx_env;
    }
}

function mx_config(string $key, $default = null)
{
    return $GLOBALS['mx_config'][$key] ?? $default;
}

/* ------------------------------------------------------------------
 * 3. Failure responses (JSON for API calls, a small page otherwise)
 * ------------------------------------------------------------------ */
function mx_is_api_request(): bool
{
    return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') === 0;
}

function mx_fail(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    if (mx_is_api_request()) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>MedXpert</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:480px;margin:15vh auto;padding:0 1rem;color:#0f1f45">'
            . '<h1 style="font-size:1.4rem">Something went wrong</h1>'
            . '<p>' . $safe . '</p><p><a href="/index.php">Back to MedXpert</a></p></body></html>';
    }
    exit;
}

// A last safety net: log anything nobody caught and show a plain message
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    mx_fail(500, 'Something went wrong on our side. Please try again in a moment.');
});

/* ------------------------------------------------------------------
 * 4. Session hardening and security headers
 * ------------------------------------------------------------------ */
$mx_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (!defined('MX_NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $mx_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// End sessions that sat idle for more than 2 hours
if (!defined('MX_NO_SESSION')) {
    if (!empty($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Everything loads from this site. Inline scripts stay allowed because pages use onclick handlers.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'self'");
    if ($mx_https) {
        header('Strict-Transport-Security: max-age=15552000'); // 180 days
    }
}

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/assets.php';
require_once __DIR__ . '/../src/i18n.php';
require_once __DIR__ . '/../src/mail.php';
require_once __DIR__ . '/../src/audit.php';
require_once __DIR__ . '/../src/privacy.php';
require_once __DIR__ . '/../src/notifications.php';
require_once __DIR__ . '/../src/accounts.php';
require_once __DIR__ . '/../src/orders.php';
require_once __DIR__ . '/../src/charts.php';
require_once __DIR__ . '/../src/schedule.php';
require_once __DIR__ . '/../src/appointments.php';

/* ------------------------------------------------------------------
 * 5. Database
 * ------------------------------------------------------------------ */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        (string)mx_config('db_host'),
        (string)mx_config('db_user'),
        (string)mx_config('db_pass'),
        (string)mx_config('db_name'),
        (int)mx_config('db_port')
    );
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('MedXpert database connection failed: ' . $e->getMessage());
    mx_fail(503, 'The service is temporarily unavailable. Please try again in a few minutes.');
}

/* ------------------------------------------------------------------
 * 7. CSRF protection (checked automatically for every POST)
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Turn a php.ini size such as 8M into bytes. */
function mx_ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (int)$value;
    switch (strtolower(substr($value, -1))) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
    }
    return $number;
}

// When a form is bigger than post_max_size, PHP drops every field, so it looks like a missing CSRF token.
// Say what really happened.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$_POST && !$_FILES) {
    $mx_limit = mx_ini_bytes((string)ini_get('post_max_size'));
    if ($mx_limit > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $mx_limit) {
        $mx_msg = 'That upload is too large for this server. Choose a smaller file.';
        if (mx_is_api_request()) {
            mx_fail(413, $mx_msg);
        }
        flash_set('error', $mx_msg);
        $mx_back = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        redirect($mx_back !== '' ? $mx_back : '/dashboard.php');
    }
}

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $mx_sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($mx_sent) || $mx_sent === '' || !hash_equals(csrf_token(), $mx_sent)) {
        mx_fail(419, 'Your session expired. Reload the page and try again.');
    }
}

/* ------------------------------------------------------------------
 * 8. Authentication helpers and guards
 * ------------------------------------------------------------------ */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/** Redirect to a local path only. */
function redirect(string $url): void
{
    if ($url === '' || $url[0] !== '/' || strpos($url, '//') === 0 || strpos($url, '\\') !== false || preg_match('/[\x00-\x1F]/', $url)) {
        $url = '/index.php';
    }
    header('Location: ' . $url);
    exit();
}

/** Call at the top of a page, before any output. */
function require_login(): void
{
    if (!isLoggedIn()) {
        if (mx_is_api_request()) {
            mx_fail(401, 'Please log in to continue.');
        }
        redirect('/index.php');
    }
}

/** Call at the top of an admin page or API, before any output. */
function require_admin(): void
{
    require_login();
    if (!isAdmin()) {
        if (mx_is_api_request()) {
            mx_fail(403, 'You do not have permission to do that.');
        }
        redirect('/dashboard.php');
    }
}

/** Sign a user in and rotate the session ID. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['csrf']      = bin2hex(random_bytes(32));
    $_SESSION['last_seen'] = time();
}

/* ------------------------------------------------------------------
 * 9. Flash messages (shown as a toast on the next page)
 * ------------------------------------------------------------------ */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ------------------------------------------------------------------
 * 10. Rate limiting (needs the login_attempts table, skipped if missing)
 * ------------------------------------------------------------------ */
function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** True when this client made too many recent failed attempts. */
function rate_limited(string $kind, string $email, int $maxPerEmail = 5, int $maxPerIp = 20, int $minutes = 15): bool
{
    global $conn;
    try {
        $ip = client_ip();
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE kind = ? AND ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->bind_param('ssi', $kind, $ip, $minutes);
        $stmt->execute();
        if ((int)$stmt->get_result()->fetch_assoc()['c'] >= $maxPerIp) {
            return true;
        }
        if ($email !== '') {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE kind = ? AND ip = ? AND email = ? AND created_at > (NOW() - INTERVAL ? MINUTE)");
            $stmt->bind_param('sssi', $kind, $ip, $email, $minutes);
            $stmt->execute();
            return (int)$stmt->get_result()->fetch_assoc()['c'] >= $maxPerEmail;
        }
    } catch (Throwable $e) {
        error_log('Rate limit check skipped: ' . $e->getMessage());
    }
    return false;
}

function rate_limit_hit(string $kind, string $email = ''): void
{
    global $conn;
    try {
        $ip = client_ip();
        $email = mb_substr($email, 0, 100, 'UTF-8');
        $stmt = $conn->prepare("INSERT INTO login_attempts (kind, ip, email) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $kind, $ip, $email);
        $stmt->execute();
        if (random_int(1, 50) === 1) {
            $conn->query("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        }
    } catch (Throwable $e) {
        error_log('Rate limit record skipped: ' . $e->getMessage());
    }
}

function rate_limit_clear(string $kind, string $email): void
{
    global $conn;
    try {
        $ip = client_ip();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE kind = ? AND ip = ? AND email = ?");
        $stmt->bind_param('sss', $kind, $ip, $email);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('Rate limit clear skipped: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
 * 11. Image uploads
 * ------------------------------------------------------------------ */

/**
 * Save an uploaded image after checking its real content.
 * Returns the stored file name, or null when nothing was uploaded.
 * Sets $error when an upload was sent but rejected.
 * Only jpg, png, and webp images up to 2 MB are accepted. The extension comes
 * from the detected image type and never from the visitor's file name.
 */
function save_image_upload(string $field, string $folder, string $prefix, ?string &$error = null, int $maxSide = 1000): ?string
{
    $error = null;
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || is_array($_FILES[$field]['error'])) {
        return null;
    }
    $file = $_FILES[$field];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'This photo is too large for the server. Choose a smaller one, under 2 MB.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The image could not be uploaded. Try again.';
        return null;
    }
    $canResize = function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    // Phone photos are often 3 to 8 MB. With GD the server shrinks them, so allow more.
    if ($file['size'] > ($canResize ? 10 : 2) * 1024 * 1024) {
        $error = 'Images must be ' . ($canResize ? '10' : '2') . ' MB or smaller.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'The image could not be uploaded.';
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($types[$info[2]]) || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 40000000) {
        $error = 'Upload a JPG, PNG, or WebP image.';
        return null;
    }

    $dir = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $error = 'The image could not be saved.';
        return null;
    }

    $ext = $types[$info[2]];
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $name;

    $saved = false;
    if ($canResize && $maxSide > 0) {
        $saved = resize_image($file['tmp_name'], $target, $info[2], $maxSide);
    }
    if (!$saved) {
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = 'This photo is too large and could not be shrunk. Choose one under 2 MB.';
            return null;
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $error = 'The image could not be saved. The uploads folder may not be writable.';
            error_log('Upload failed: cannot write to ' . $dir);
            return null;
        }
    }
    @chmod($target, 0644);
    return $name;
}

/** Shrink an image to fit $maxSide pixels, fix its rotation and drop its metadata. */
function resize_image(string $source, string $target, int $type, int $maxSide): bool
{
    try {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$img) {
            return false;
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            $turn = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($turn !== 0) {
                $rotated = imagerotate($img, $turn, 0);
                if ($rotated) {
                    $img = $rotated;
                }
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($out, $target, 85),
            IMAGETYPE_PNG => imagepng($out, $target, 6),
            IMAGETYPE_WEBP => imagewebp($out, $target, 85),
            default => false,
        };
        return (bool)$ok;
    } catch (Throwable $e) {
        error_log('Image resize failed: ' . $e->getMessage());
        return false;
    }
}

/* ------------------------------------------------------------------
 * 12. Query helper
 * ------------------------------------------------------------------ */

/** Run a prepared SELECT and return the result set. */
function db_query(string $sql, string $types = '', array $params = []): mysqli_result
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}
