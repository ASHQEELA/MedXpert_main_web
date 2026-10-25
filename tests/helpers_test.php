<?php
// Unit tests for src/helpers.php. They need no database.

test('e() escapes quotes, angle brackets and ampersands once', function () {
    assert_same('Tom &amp; O&#039;Neil', e("Tom & O'Neil"));
    assert_same('&lt;script&gt;', e('<script>'));
});

test('e() does not double-encode when called on raw text only', function () {
    assert_same('a &amp;amp; b', e(e('a & b')));
});

test('clean() trims, strips control characters and caps the length', function () {
    assert_same('hello', clean("  hel\x00lo \n"));
    assert_same('abc', clean('abcdef', 3));
    assert_same('', clean(['array']));
});

test('clean() keeps raw text and counts characters, not bytes', function () {
    assert_same("Tom & O'Neil", clean("Tom & O'Neil"));
    assert_same('বাং', clean('বাংলা', 3));
});

test('pick() accepts only listed values', function () {
    assert_same('a', pick('a', ['a', 'b']));
    assert_same(null, pick('c', ['a', 'b']));
    assert_same('b', pick('c', ['a', 'b'], 'b'));
    assert_same(null, pick(['a'], ['a', 'b']));
});

test('valid_phone() accepts Bangladesh formats and rejects junk', function () {
    foreach (['01712345678', '+8801712345678', '+880 1712-345678'] as $ok) {
        assert_true(valid_phone($ok), "$ok should be valid");
    }
    foreach (['', 'abc', '12', '0171234567890123456789', '01712<script>'] as $bad) {
        assert_true(!valid_phone($bad), "$bad should be invalid");
    }
});

test('valid_date() rejects impossible dates', function () {
    assert_true(valid_date('2026-02-28'));
    assert_true(!valid_date('2026-02-30'));
    assert_true(!valid_date('28/02/2026'));
    assert_true(!valid_date(''));
});

test('valid_time() accepts HH:MM only', function () {
    assert_true(valid_time('09:30'));
    assert_true(valid_time('23:59'));
    assert_true(!valid_time('24:00'));
    assert_true(!valid_time('9:30'));
    assert_true(!valid_time('10:60'));
});

test('password_error() enforces length and blocks common passwords', function () {
    assert_true(password_error('short') !== null);
    assert_true(password_error('password') !== null);
    assert_true(password_error(str_repeat('a', 73)) !== null);
    assert_true(password_error('a@b.com1234', 'A@B.com1234') !== null);
    assert_same(null, password_error('Str0ngPass!9'));
});

test('safe_image_name() allows plain file names only', function () {
    assert_same('doctor_1_abc.jpg', safe_image_name('doctor_1_abc.jpg'));
    assert_same('default.png', safe_image_name('../../etc/passwd', 'default.png'));
    assert_same('', safe_image_name('a b.png'));
    assert_same('', safe_image_name(null));
});

test('json_attr() is safe inside a double-quoted attribute', function () {
    $out = json_attr(['name' => 'A "quoted" <b>name</b> & more']);
    assert_true(strpos($out, '"') === false, 'raw double quote found');
    assert_true(strpos($out, '<') === false, 'raw angle bracket found');
});

test('t() returns the English text and falls back when Bangla has no entry', function () {
    require_once __DIR__ . '/../src/i18n.php';
    unset($_COOKIE['mx_lang']);
    assert_same('Dashboard', t('Dashboard'));
    $_COOKIE['mx_lang'] = 'bn';
    assert_same('ড্যাশবোর্ড', t('Dashboard'));
    assert_same('Text with no entry', t('Text with no entry'));
    $_COOKIE['mx_lang'] = 'xx';
    assert_same('en', lang_code());
    unset($_COOKIE['mx_lang']);
});

test('every Bangla entry has a non-empty text', function () {
    foreach (require __DIR__ . '/../lang/bn.php' as $key => $text) {
        assert_true(is_string($text) && trim($text) !== '', "empty text for $key");
    }
});
