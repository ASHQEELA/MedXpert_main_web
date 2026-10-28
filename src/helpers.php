<?php
/**
 * Pure helpers: escaping, input cleaning and validation.
 * No sessions, no database, no output. The tests load this file on its own.
 */

/** Escape a value for HTML output. Use on every value you print. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON that is safe inside a double-quoted HTML attribute. */
function json_attr($value): string
{
    return e(json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));
}

/**
 * Clean text input: trim, drop control characters, cap the length.
 * The result is raw text. Escape it with e() when you print it.
 */
function clean($value, int $max = 1000): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', trim($value));
    return mb_substr($value, 0, $max, 'UTF-8');
}

/** Pick a value from an allowlist, or return the fallback. */
function pick($value, array $allowed, $fallback = null)
{
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : $fallback;
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $phone);
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time);
}

/** Returns an error message for a weak password, or null when it is acceptable. */
function password_error(string $password, string $email = ''): ?string
{
    if (strlen($password) < 8) {
        return 'Use a password of at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Use a password of 72 characters or fewer.';
    }
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9\s]/', $password)) {
        return 'Use a lowercase letter, an uppercase letter, a number and a symbol in your password.';
    }
    $weak = ['password', '12345678', '123456789', 'qwertyui', 'iloveyou', 'admin123', 'medxpert', 'password1'];
    if (in_array(strtolower($password), $weak, true) || ($email !== '' && strtolower($password) === strtolower($email))) {
        return 'That password is too easy to guess. Choose a different one.';
    }
    return null;
}

/** Return a safe file name for use in an image URL, or the fallback. */
function safe_image_name($name, string $fallback = ''): string
{
    return (is_string($name) && preg_match('/^[A-Za-z0-9._-]{1,120}$/', $name)) ? $name : $fallback;
}


