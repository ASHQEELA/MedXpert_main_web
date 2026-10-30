<?php
/**
 * Password reset and email verification.
 * Tokens are random, stored only as a SHA-256 hash, work once, and expire.
 */

const TOKEN_TTL_RESET = 60;      // minutes
const TOKEN_TTL_VERIFY = 60 * 48; // minutes

/** Create a token and return the raw value to put in the link. Older unused tokens of that kind stop working. */
function create_token(mysqli $conn, int $userId, string $kind, int $ttlMinutes): string
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    $stmt = $conn->prepare("UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND kind = ? AND used_at IS NULL");
    $stmt->bind_param("is", $userId, $kind);
    $stmt->execute();

    $expires = (new DateTimeImmutable('now'))->modify("+$ttlMinutes minutes")->format('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, kind, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $kind, $hash, $expires);
    $stmt->execute();
    return $raw;
}

/** Use a token once. Returns the user id, or null when the token is wrong, used or expired. */
function consume_token(mysqli $conn, string $raw, string $kind): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }
    $hash = hash('sha256', $raw);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE user_tokens SET used_at = ? WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?");
    $stmt->bind_param("ssss", $now, $hash, $kind, $now);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        return null;
    }

    $stmt = $conn->prepare("SELECT user_id FROM user_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['user_id'] : null;
}

/** Check a token without using it. Used to decide whether to show the reset form. */
function token_is_valid(mysqli $conn, string $raw, string $kind): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return false;
    }
    $hash = hash('sha256', $raw);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT 1 FROM user_tokens WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?");
    $stmt->bind_param("sss", $hash, $kind, $now);
    $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

/** Email a reset link when the address belongs to an account. Says nothing about whether it does. */
function request_password_reset(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        return;
    }
    $token = create_token($conn, (int)$user['id'], 'reset', TOKEN_TTL_RESET);
    $link = app_url() . '/reset_password.php?token=' . $token;
    send_mail($email, 'Reset your MedXpert password',
        "Hello {$user['full_name']},\n\nUse this link to choose a new password. It works once and expires in " . TOKEN_TTL_RESET . " minutes:\n\n$link\n\nIf you did not ask for this, ignore this email. Your password stays the same.\n\nMedXpert");
}

/** Set a new password with a reset token. Returns null on success, or a message for the person. */
function reset_password(mysqli $conn, string $token, string $newPassword): ?string
{
    if (!token_is_valid($conn, $token, 'reset')) {
        return 'This reset link is not valid or has expired. Ask for a new one.';
    }
    $problem = password_error($newPassword);
    if ($problem !== null) {
        return $problem;
    }
    $userId = consume_token($conn, $token, 'reset');
    if ($userId === null) {
        return 'This reset link is not valid or has expired. Ask for a new one.';
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $userId);
    $stmt->execute();
    auth_sessions_revoke_all($conn, $userId); // anyone holding an old login is signed out
    notify($conn, $userId, 'Your password was changed', 'If you did not do this, reset your password again and contact us.', '/profile.php');
    return null;
}

/** Email a verification link. */
function send_verification(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT email, full_name, email_verified_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || $user['email_verified_at'] !== null) {
        return false;
    }
    $token = create_token($conn, $userId, 'verify', TOKEN_TTL_VERIFY);
    return send_mail($user['email'], 'Confirm your MedXpert email',
        "Hello {$user['full_name']},\n\nConfirm your email address with this link:\n\n" . app_url() . "/verify_email.php?token=$token\n\nThe link works once and expires in 48 hours.\n\nMedXpert");
}

/** Confirm an email address with a token. Returns true on success. */
/**
 * Send a fresh verification link to an address, if it belongs to an unverified account.
 * Returns nothing, so the caller shows the same reply for every address.
 */
function resend_verification(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        send_verification($conn, (int)$user['id']);
    }
}

function verify_email(mysqli $conn, string $token): bool
{
    $userId = consume_token($conn, $token, 'verify');
    if ($userId === null) {
        return false;
    }
    $stmt = $conn->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return true;
}
