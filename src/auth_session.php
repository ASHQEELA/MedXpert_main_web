<?php
/**
 * Cookie based login sessions.
 *
 * A login creates a random token. The browser keeps the token in an HttpOnly cookie.
 * The database keeps only a SHA-256 hash of it, with the user, the device and the expiry.
 * Every request looks the cookie up, so a logout, a revoke or a password change ends
 * a session at once. The PHP session still holds CSRF tokens and flash messages.
 *
 * Without the auth_sessions table (migration 006) the app falls back to PHP sessions only.
 */

const AUTH_IDLE_SECONDS = 7200;          // a normal login ends after 2 hours without a request
const AUTH_MAX_SECONDS = 86400;          // and never lasts longer than 24 hours
const AUTH_REMEMBER_SECONDS = 2592000;   // "keep me signed in" lasts 30 days
const AUTH_TOUCH_SECONDS = 60;           // last-seen time is saved at most once a minute
const AUTH_MAX_PER_USER = 10;            // the oldest login goes when someone has more

function auth_cookie_name(): string
{
    // The __Host- prefix makes browsers accept the cookie only from this exact site over HTTPS
    return auth_is_https() ? '__Host-mx_auth' : 'mx_auth';
}

function auth_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** True when the auth_sessions table exists. */
function auth_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $conn->query('SELECT 1 FROM auth_sessions LIMIT 0');
            $ready = true;
        } catch (Throwable $e) {
            error_log('auth_sessions table missing, using PHP sessions only. Run migrations/006_auth_sessions.sql');
            $ready = false;
        }
    }
    return $ready;
}

function auth_hash(string $token): string
{
    return hash('sha256', $token);
}

function auth_valid_token(string $token): bool
{
    return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
}

function auth_cookie_set(string $token, bool $remember): void
{
    if (headers_sent()) {
        return;
    }
    setcookie(auth_cookie_name(), $token, [
        'expires'  => $remember ? time() + AUTH_REMEMBER_SECONDS : 0,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[auth_cookie_name()] = $token;
}

function auth_cookie_clear(): void
{
    if (!headers_sent()) {
        setcookie(auth_cookie_name(), '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'secure'   => auth_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE[auth_cookie_name()]);
}

/** Create a login for a user. Returns the token to put in the cookie, or null on failure. */
function auth_session_create(mysqli $conn, int $userId, bool $remember, string $ip = '', string $agent = ''): ?string
{
    $token = bin2hex(random_bytes(32));
    $hash = auth_hash($token);
    $ip = substr($ip, 0, 45);
    $agent = substr($agent, 0, 255);
    $seconds = $remember ? AUTH_REMEMBER_SECONDS : AUTH_IDLE_SECONDS;
    $stmt = $conn->prepare(
        'INSERT INTO auth_sessions (user_id, token_hash, remember, ip, user_agent, created_at, last_seen_at, expires_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))'
    );
    $flag = $remember ? 1 : 0;
    $stmt->bind_param('isissi', $userId, $hash, $flag, $ip, $agent, $seconds);
    if (!$stmt->execute()) {
        return null;
    }
    auth_session_trim($conn, $userId);
    return $token;
}

/** Keep only the newest AUTH_MAX_PER_USER logins for a user. */
function auth_session_trim(mysqli $conn, int $userId): void
{
    $limit = AUTH_MAX_PER_USER;
    $stmt = $conn->prepare('SELECT id FROM auth_sessions WHERE user_id = ? ORDER BY last_seen_at DESC, id DESC LIMIT 100 OFFSET ?');
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $old = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    foreach ($old as $id) {
        $del = $conn->prepare('DELETE FROM auth_sessions WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
    }
}

/** The login and its user for a cookie token, or null when the token is unknown, expired or idle. */
function auth_session_lookup(mysqli $conn, string $token): ?array
{
    if (!auth_valid_token($token)) {
        return null;
    }
    $hash = auth_hash($token);
    $idle = AUTH_IDLE_SECONDS;
    $max = AUTH_MAX_SECONDS;
    $stmt = $conn->prepare(
        'SELECT s.id AS session_id, s.remember, s.last_seen_at, u.id, u.full_name, u.email, u.role
           FROM auth_sessions s JOIN users u ON u.id = s.user_id
          WHERE s.token_hash = ? AND s.expires_at > NOW()
            AND (s.remember = 1 OR (s.last_seen_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                                    AND s.created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)))
          LIMIT 1'
    );
    $stmt->bind_param('sii', $hash, $idle, $max);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Record that the login was used. A normal login also moves its expiry forward. */
function auth_session_touch(mysqli $conn, int $sessionId, bool $remember): void
{
    $seconds = AUTH_IDLE_SECONDS;
    if ($remember) {
        $stmt = $conn->prepare('UPDATE auth_sessions SET last_seen_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $sessionId);
    } else {
        $stmt = $conn->prepare('UPDATE auth_sessions SET last_seen_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?');
        $stmt->bind_param('ii', $seconds, $sessionId);
    }
    $stmt->execute();
}

/** Run at the start of every request: turn the cookie into the signed-in user. */
function auth_boot(mysqli $conn): void
{
    if (!auth_table_ready($conn)) {
        return;
    }
    $token = (string)($_COOKIE[auth_cookie_name()] ?? '');
    $row = $token !== '' ? auth_session_lookup($conn, $token) : null;

    if ($row === null) {
        if ($token !== '') {
            auth_cookie_clear();
        }
        foreach (['user_id', 'full_name', 'email', 'role', 'auth_session_id'] as $key) {
            unset($_SESSION[$key]);
        }
        return;
    }

    // The user row is read fresh, so a changed role or name applies at once
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['full_name'] = $row['full_name'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['auth_session_id'] = (int)$row['session_id'];

    if (time() - (int)($_SESSION['auth_touched'] ?? 0) >= AUTH_TOUCH_SECONDS) {
        auth_session_touch($conn, (int)$row['session_id'], (bool)$row['remember']);
        $_SESSION['auth_touched'] = time();
    }
    if (random_int(1, 100) === 1) {
        auth_purge_expired($conn);
    }
}

function auth_purge_expired(mysqli $conn): void
{
    $conn->query('DELETE FROM auth_sessions WHERE expires_at < NOW()');
}

/** End the login this browser holds. */
function auth_session_end_current(mysqli $conn): void
{
    $token = (string)($_COOKIE[auth_cookie_name()] ?? '');
    if ($token !== '' && auth_valid_token($token) && auth_table_ready($conn)) {
        $hash = auth_hash($token);
        $stmt = $conn->prepare('DELETE FROM auth_sessions WHERE token_hash = ?');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
    }
    auth_cookie_clear();
}

/** Every login of a user, newest first. */
function auth_sessions_for_user(mysqli $conn, int $userId): array
{
    if (!auth_table_ready($conn)) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT id, remember, ip, user_agent, created_at, last_seen_at FROM auth_sessions
          WHERE user_id = ? AND expires_at > NOW() ORDER BY last_seen_at DESC, id DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** End one login of a user. Returns true when a login was removed. */
function auth_session_revoke(mysqli $conn, int $userId, int $sessionId): bool
{
    if (!auth_table_ready($conn)) {
        return false;
    }
    $stmt = $conn->prepare('DELETE FROM auth_sessions WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $sessionId, $userId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

/** End every login of a user except one. Returns how many ended. */
function auth_sessions_revoke_others(mysqli $conn, int $userId, int $keepSessionId): int
{
    if (!auth_table_ready($conn)) {
        return 0;
    }
    $stmt = $conn->prepare('DELETE FROM auth_sessions WHERE user_id = ? AND id <> ?');
    $stmt->bind_param('ii', $userId, $keepSessionId);
    $stmt->execute();
    return $stmt->affected_rows;
}

/** End every login of a user. */
function auth_sessions_revoke_all(mysqli $conn, int $userId): int
{
    if (!auth_table_ready($conn)) {
        return 0;
    }
    $stmt = $conn->prepare('DELETE FROM auth_sessions WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->affected_rows;
}

/** "Chrome on Windows" from a user agent text. */
function auth_describe_agent(string $agent): string
{
    if ($agent === '') {
        return 'Unknown device';
    }
    $browser = 'Browser';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
        if (stripos($agent, $needle) !== false) {
            $browser = $name;
            break;
        }
    }
    $system = 'unknown system';
    foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'Mac', 'Linux' => 'Linux'] as $needle => $name) {
        if (stripos($agent, $needle) !== false) {
            $system = $name;
            break;
        }
    }
    return $browser . ' on ' . $system;
}
