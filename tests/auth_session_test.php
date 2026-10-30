<?php
// Database tests for src/auth_session.php (cookie based login sessions).

function fx_count_sessions(mysqli $c, int $userId): int
{
    return (int)$c->query("SELECT COUNT(*) n FROM auth_sessions WHERE user_id = $userId")->fetch_assoc()['n'];
}

test('auth: a login is found by its token and only the hash is stored', function () use ($conn) {
    $u = fx_user($conn, 'a1');
    $token = auth_session_create($conn, $u, false, '203.0.113.5', 'Mozilla/5.0 Chrome/120 Windows');
    assert_true($token !== null && auth_valid_token($token), 'token has 64 hex characters');
    $row = auth_session_lookup($conn, $token);
    assert_same($u, (int)$row['id']);
    $stored = fx_row($conn, "SELECT token_hash FROM auth_sessions WHERE user_id = $u");
    assert_true($stored['token_hash'] !== $token, 'the token itself is not stored');
    assert_same(auth_hash($token), $stored['token_hash']);
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: unknown and badly shaped tokens are refused', function () use ($conn) {
    assert_same(null, auth_session_lookup($conn, str_repeat('a', 64)));
    assert_same(null, auth_session_lookup($conn, 'short'));
    assert_same(null, auth_session_lookup($conn, str_repeat('Z', 64)));
});

test('auth: an expired login is refused', function () use ($conn) {
    $u = fx_user($conn, 'a2');
    $token = auth_session_create($conn, $u, false);
    $conn->query("UPDATE auth_sessions SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id = $u");
    assert_same(null, auth_session_lookup($conn, $token));
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: a normal login ends after 2 hours idle, a remembered login does not', function () use ($conn) {
    $u = fx_user($conn, 'a3');
    $normal = auth_session_create($conn, $u, false);
    $kept = auth_session_create($conn, $u, true);
    $conn->query("UPDATE auth_sessions SET last_seen_at = DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE user_id = $u");
    assert_same(null, auth_session_lookup($conn, $normal), 'idle normal login');
    assert_true(auth_session_lookup($conn, $kept) !== null, 'remembered login survives');
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: a normal login never lasts more than 24 hours', function () use ($conn) {
    $u = fx_user($conn, 'a4');
    $token = auth_session_create($conn, $u, false);
    $conn->query("UPDATE auth_sessions SET created_at = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE user_id = $u");
    assert_same(null, auth_session_lookup($conn, $token));
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: a remembered login lasts about 30 days', function () use ($conn) {
    $u = fx_user($conn, 'a5');
    auth_session_create($conn, $u, true);
    $days = (int)fx_row($conn, "SELECT DATEDIFF(expires_at, NOW()) d FROM auth_sessions WHERE user_id = $u")['d'];
    assert_true($days >= 29 && $days <= 30, "expiry in $days days");
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: touching a normal login moves its expiry forward', function () use ($conn) {
    $u = fx_user($conn, 'a6');
    $token = auth_session_create($conn, $u, false);
    $conn->query("UPDATE auth_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE user_id = $u");
    $row = auth_session_lookup($conn, $token);
    auth_session_touch($conn, (int)$row['session_id'], false);
    $minutes = (int)fx_row($conn, "SELECT TIMESTAMPDIFF(MINUTE, NOW(), expires_at) m FROM auth_sessions WHERE user_id = $u")['m'];
    assert_true($minutes >= 100, "expiry in $minutes minutes");
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: revoke one, revoke others and revoke all', function () use ($conn) {
    $u = fx_user($conn, 'a7');
    $other = fx_user($conn, 'a7b');
    $t1 = auth_session_create($conn, $u, false);
    $t2 = auth_session_create($conn, $u, false);
    $t3 = auth_session_create($conn, $u, true);
    $foreign = auth_session_create($conn, $other, false);
    $id1 = (int)auth_session_lookup($conn, $t1)['session_id'];
    $id2 = (int)auth_session_lookup($conn, $t2)['session_id'];
    $foreignId = (int)auth_session_lookup($conn, $foreign)['session_id'];

    assert_true(!auth_session_revoke($conn, $u, $foreignId), 'cannot revoke another user\'s login');
    assert_true(auth_session_revoke($conn, $u, $id1));
    assert_same(null, auth_session_lookup($conn, $t1));

    assert_same(1, auth_sessions_revoke_others($conn, $u, $id2), 'one other login ended');
    assert_true(auth_session_lookup($conn, $t2) !== null, 'the kept login stays');
    assert_same(null, auth_session_lookup($conn, $t3));

    assert_same(1, auth_sessions_revoke_all($conn, $u));
    assert_same(null, auth_session_lookup($conn, $t2));
    assert_true(auth_session_lookup($conn, $foreign) !== null, 'the other user is untouched');
    $conn->query("DELETE FROM users WHERE id IN ($u, $other)");
});

test('auth: only the newest 10 logins of a user are kept', function () use ($conn) {
    $u = fx_user($conn, 'a8');
    for ($i = 0; $i < 12; $i++) {
        auth_session_create($conn, $u, false);
    }
    assert_same(AUTH_MAX_PER_USER, fx_count_sessions($conn, $u));
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: deleting a user removes their logins', function () use ($conn) {
    $u = fx_user($conn, 'a9');
    auth_session_create($conn, $u, false);
    $conn->query("DELETE FROM users WHERE id = $u");
    assert_same(0, fx_count_sessions($conn, $u));
});

test('auth: a password reset logs the user out everywhere', function () use ($conn) {
    $u = fx_user_with_password($conn, 'a10', 'Old-Passw0rd!');
    $token = auth_session_create($conn, $u, true);
    $reset = create_token($conn, $u, 'reset', 60);
    assert_same(null, reset_password($conn, $reset, 'N3w-Str0ng-Pass'));
    assert_same(null, auth_session_lookup($conn, $token), 'old login ended');
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: the sessions list shows only live logins', function () use ($conn) {
    $u = fx_user($conn, 'a11');
    auth_session_create($conn, $u, false, '198.51.100.7', 'Mozilla/5.0 (iPhone) Safari/605');
    auth_session_create($conn, $u, false);
    $conn->query("UPDATE auth_sessions SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE user_id = $u AND ip = ''");
    $list = auth_sessions_for_user($conn, $u);
    assert_same(1, count($list));
    assert_same('198.51.100.7', $list[0]['ip']);
    $conn->query("DELETE FROM users WHERE id = $u");
});

test('auth: auth_describe_agent() names the browser and system', function () {
    assert_same('Chrome on Windows', auth_describe_agent('Mozilla/5.0 (Windows NT 10.0) AppleWebKit Chrome/120 Safari/537'));
    assert_same('Firefox on Linux', auth_describe_agent('Mozilla/5.0 (X11; Linux x86_64) Gecko Firefox/121.0'));
    assert_same('Safari on iPhone', auth_describe_agent('Mozilla/5.0 (iPhone; CPU iPhone OS 17) Safari/604'));
    assert_same('Unknown device', auth_describe_agent(''));
});
