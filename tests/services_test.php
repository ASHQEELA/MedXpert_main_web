<?php
// Database tests for src/orders.php and src/appointments.php.
// They create their own rows and remove them at the end.

function fx_user(mysqli $c, string $tag): int
{
    $email = "test_{$tag}_" . bin2hex(random_bytes(3)) . '@example.test';
    $c->query("INSERT INTO users (full_name, email, password, phone) VALUES ('Test $tag', '$email', '!', '01712345678')");
    return (int)$c->insert_id;
}

function fx_doctor(mysqli $c): int
{
    $c->query("INSERT INTO doctors (name, specialization, experience, qualification, hospital, location, fee, phone, email, status)
        VALUES ('Test Doctor', 'Test', 1, 'MBBS', 'H', 'Dhaka', 100, '01712345678', 'doc@example.test', 'active')");
    $id = (int)$c->insert_id;
    $c->query("INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes)
        SELECT $id, w, '09:00:00', '17:00:00', 30 FROM (SELECT 0 w UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) x");
    return $id;
}

function fx_medicine(mysqli $c, int $stock): int
{
    $c->query("INSERT INTO medicines (name, category, price, stock, status) VALUES ('Test Med', 'Test', 12.50, $stock, 'available')");
    return (int)$c->insert_id;
}

function fx_row(mysqli $c, string $sql): ?array
{
    return $c->query($sql)->fetch_assoc() ?: null;
}

$mx_now = new DateTimeImmutable('2026-10-17 10:00:00');
$mx_future = '2026-10-20';

function assert_refused_invalid(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new AssertionError('Expected InvalidArgumentException');
}

register_shutdown_function(function () use ($conn) {
    $conn->query("DELETE FROM users WHERE email LIKE 'test\\_%@example.test'");
    $conn->query("DELETE FROM audit_log WHERE action = 'doctor.edit' AND target_id = 42");
    $conn->query("DELETE FROM doctors WHERE email = 'doc@example.test'");
    $conn->query("DELETE FROM medicines WHERE name = 'Test Med'");
});

/* ---------------------------- orders ---------------------------- */

test('place_order() uses the database price and lowers stock', function () use ($conn) {
    $u = fx_user($conn, 'o1');
    $m = fx_medicine($conn, 10);
    $r = place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 3]], 'Dhaka', '01712345678', 'cash');
    $order = fx_row($conn, "SELECT total_amount, status FROM medicine_orders WHERE id = {$r['order_id']}");
    assert_same('37.50', $order['total_amount']);
    assert_same('pending', $order['status']);
    assert_same('7', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m")['stock']);
});

test('place_order() refuses bad quantities', function () use ($conn) {
    $u = fx_user($conn, 'o2');
    $m = fx_medicine($conn, 10);
    foreach ([0, -5, 100] as $q) {
        assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => $q]], 'Dhaka', '01712345678', 'cash'), 'quantity');
    }
    assert_same('10', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m")['stock']);
});

test('place_order() refuses more than the stock and marks sold-out medicine', function () use ($conn) {
    $u = fx_user($conn, 'o3');
    $m = fx_medicine($conn, 2);
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 3]], 'Dhaka', '01712345678', 'cash'), 'out of stock');
    place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 2]], 'Dhaka', '01712345678', 'cash');
    $med = fx_row($conn, "SELECT stock, status FROM medicines WHERE id = $m");
    assert_same('0', $med['stock']);
    assert_same('out_of_stock', $med['status']);
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash'), 'out of stock');
});

test('place_order() refuses a bad payment method, phone or empty address', function () use ($conn) {
    $u = fx_user($conn, 'o4');
    $m = fx_medicine($conn, 5);
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'bitcoin'), 'payment');
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', 'abc', 'cash'), 'phone');
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], '', '01712345678', 'cash'), 'required');
});

test('cancel_order() returns stock and reopens a sold-out medicine', function () use ($conn) {
    $u = fx_user($conn, 'o5');
    $m = fx_medicine($conn, 2);
    $r = place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 2]], 'Dhaka', '01712345678', 'cash');
    assert_same(null, cancel_order($conn, $r['order_id'], $u));
    $med = fx_row($conn, "SELECT stock, status FROM medicines WHERE id = $m");
    assert_same('2', $med['stock']);
    assert_same('available', $med['status']);
});

test('cancel_order() refuses another user and a second cancel', function () use ($conn) {
    $owner = fx_user($conn, 'o6');
    $other = fx_user($conn, 'o7');
    $m = fx_medicine($conn, 5);
    $r = place_order($conn, $owner, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash');
    assert_same('Order not found.', cancel_order($conn, $r['order_id'], $other));
    assert_same(null, cancel_order($conn, $r['order_id'], $owner));
    assert_same('This order can no longer be cancelled.', cancel_order($conn, $r['order_id'], $owner));
    assert_same('5', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m")['stock'], 'stock restored once');
});

test('cancel_order() lets a user cancel only pending or processing orders', function () use ($conn) {
    $u = fx_user($conn, 'o8');
    $m = fx_medicine($conn, 5);
    $r = place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash');
    $conn->query("UPDATE medicine_orders SET status = 'shipped' WHERE id = {$r['order_id']}");
    assert_same('This order can no longer be cancelled.', cancel_order($conn, $r['order_id'], $u));
    assert_same(null, cancel_order($conn, $r['order_id']), 'admin may cancel a shipped order');
});

test('place_order() takes several medicines and totals them', function () use ($conn) {
    $u = fx_user($conn, 'c1');
    $m1 = fx_medicine($conn, 10);
    $m2 = fx_medicine($conn, 4);
    $r = place_order($conn, $u, [['medicine_id' => $m1, 'quantity' => 2], ['medicine_id' => $m2, 'quantity' => 1], ['medicine_id' => $m1, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash');
    assert_same(50.0, $r['total'], '3 x 12.50 + 1 x 12.50');
    assert_same('7', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m1")['stock'], 'repeated lines merged');
    assert_same('3', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m2")['stock']);
    assert_same('2', fx_row($conn, "SELECT COUNT(*) c FROM order_items WHERE order_id = {$r['order_id']}")['c']);
    assert_same('pending', fx_row($conn, "SELECT status FROM order_status_history WHERE order_id = {$r['order_id']}")['status']);
});

test('place_order() leaves every stock alone when one line fails', function () use ($conn) {
    $u = fx_user($conn, 'c2');
    $m1 = fx_medicine($conn, 10);
    $m2 = fx_medicine($conn, 1);
    assert_refused(fn() => place_order($conn, $u, [['medicine_id' => $m1, 'quantity' => 2], ['medicine_id' => $m2, 'quantity' => 5]], 'Dhaka', '01712345678', 'cash'), 'out of stock');
    assert_same('10', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m1")['stock']);
    assert_same('0', fx_row($conn, "SELECT COUNT(*) c FROM medicine_orders WHERE user_id = $u")['c']);
});

test('place_order() refuses an empty cart and too many lines', function () use ($conn) {
    $u = fx_user($conn, 'c3');
    assert_refused(fn() => place_order($conn, $u, [], 'Dhaka', '01712345678', 'cash'), 'cart is empty');
    $many = [];
    for ($i = 1; $i <= 21; $i++) { $many[] = ['medicine_id' => $i, 'quantity' => 1]; }
    assert_refused(fn() => place_order($conn, $u, $many, 'Dhaka', '01712345678', 'cash'), 'up to 20');
});

test('place_order() needs a prescription photo for prescription medicines', function () use ($conn) {
    $u = fx_user($conn, 'c4');
    $plain = fx_medicine($conn, 5);
    $rx = fx_medicine($conn, 5);
    $conn->query("UPDATE medicines SET prescription_required = 1 WHERE id = $rx");
    $items = [['medicine_id' => $plain, 'quantity' => 1], ['medicine_id' => $rx, 'quantity' => 1]];
    assert_refused(fn() => place_order($conn, $u, $items, 'Dhaka', '01712345678', 'cash'), 'prescription');
    assert_same('5', fx_row($conn, "SELECT stock FROM medicines WHERE id = $plain")['stock']);
    $r = place_order($conn, $u, $items, 'Dhaka', '01712345678', 'cash', 'rx_test.jpg');
    assert_same('rx_test.jpg', fx_row($conn, "SELECT prescription_image p FROM medicine_orders WHERE id = {$r['order_id']}")['p']);
    $r2 = place_order($conn, $u, [['medicine_id' => $plain, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash', 'rx_unneeded.jpg');
    assert_same(null, fx_row($conn, "SELECT prescription_image p FROM medicine_orders WHERE id = {$r2['order_id']}")['p'], 'photo ignored when not needed');
});

test('cancel_order() returns stock for every line', function () use ($conn) {
    $u = fx_user($conn, 'c5');
    $m1 = fx_medicine($conn, 5);
    $m2 = fx_medicine($conn, 5);
    $r = place_order($conn, $u, [['medicine_id' => $m1, 'quantity' => 2], ['medicine_id' => $m2, 'quantity' => 3]], 'Dhaka', '01712345678', 'cash');
    assert_same(null, cancel_order($conn, $r['order_id'], $u));
    assert_same('5', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m1")['stock']);
    assert_same('5', fx_row($conn, "SELECT stock FROM medicines WHERE id = $m2")['stock']);
    assert_same('cancelled', fx_row($conn, "SELECT status FROM order_status_history WHERE order_id = {$r['order_id']} ORDER BY id DESC LIMIT 1")['status']);
});

/* -------------------------- appointments -------------------------- */

test('book_appointment() books a free slot', function () use ($conn, $mx_now, $mx_future) {
    $u = fx_user($conn, 'a1');
    $d = fx_doctor($conn);
    $id = book_appointment($conn, $u, $d, $mx_future, '10:30', 'video', 'cough', $mx_now);
    assert_same('pending', fx_row($conn, "SELECT status FROM appointments WHERE id = $id")['status']);
});

test('book_appointment() refuses a taken slot and a double booking', function () use ($conn, $mx_now, $mx_future) {
    $u1 = fx_user($conn, 'a2');
    $u2 = fx_user($conn, 'a3');
    $d1 = fx_doctor($conn);
    $d2 = fx_doctor($conn);
    book_appointment($conn, $u1, $d1, $mx_future, '11:00', 'video', '', $mx_now);
    assert_refused(fn() => book_appointment($conn, $u2, $d1, $mx_future, '11:00', 'video', '', $mx_now), 'already booked');
    assert_refused(fn() => book_appointment($conn, $u1, $d2, $mx_future, '11:00', 'video', '', $mx_now), 'already have');
});

test('book_appointment() frees the slot after a cancel', function () use ($conn, $mx_now, $mx_future) {
    $u1 = fx_user($conn, 'a4');
    $u2 = fx_user($conn, 'a5');
    $d = fx_doctor($conn);
    $id = book_appointment($conn, $u1, $d, $mx_future, '12:00', 'phone', '', $mx_now);
    assert_true(cancel_appointment($conn, $id, $u1, $mx_now));
    book_appointment($conn, $u2, $d, $mx_future, '12:00', 'phone', '', $mx_now);
});

test('book_appointment() refuses past, far-future and malformed times', function () use ($conn, $mx_now) {
    $u = fx_user($conn, 'a6');
    $d = fx_doctor($conn);
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-10-16', '10:00', 'video', '', $mx_now), 'past');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-10-17', '09:00', 'video', '', $mx_now), 'later than now');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2028-01-01', '10:00', 'video', '', $mx_now), 'next year');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-13-40', '10:00', 'video', '', $mx_now), 'valid date');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-10-20', '25:00', 'video', '', $mx_now), 'valid date');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-10-20', '10:00', 'teleport', '', $mx_now), 'consultation');
});

test('book_appointment() refuses an inactive or missing doctor', function () use ($conn, $mx_now, $mx_future) {
    $u = fx_user($conn, 'a7');
    $d = fx_doctor($conn);
    $conn->query("UPDATE doctors SET status = 'inactive' WHERE id = $d");
    assert_refused(fn() => book_appointment($conn, $u, $d, $mx_future, '10:00', 'video', '', $mx_now), 'not available');
    assert_refused(fn() => book_appointment($conn, $u, 999999, $mx_future, '10:00', 'video', '', $mx_now), 'not available');
});

test('cancel_appointment() refuses another user, past visits and repeats', function () use ($conn, $mx_now, $mx_future) {
    $owner = fx_user($conn, 'a8');
    $other = fx_user($conn, 'a9');
    $d = fx_doctor($conn);
    $id = book_appointment($conn, $owner, $d, $mx_future, '13:00', 'video', '', $mx_now);
    assert_true(!cancel_appointment($conn, $id, $other, $mx_now), 'other user');
    assert_true(!cancel_appointment($conn, $id, $owner, new DateTimeImmutable('2026-10-25 09:00:00')), 'visit already passed');
    assert_true(cancel_appointment($conn, $id, $owner, $mx_now));
    assert_true(!cancel_appointment($conn, $id, $owner, $mx_now), 'second cancel');
});

/* ----------------------------- slots ----------------------------- */

test('slot_times() builds slots that fit the working hours', function () {
    assert_same(['09:00', '09:30', '10:00'], slot_times('09:00', '10:30', 30));
    assert_same(['09:00', '09:45'], slot_times('09:00', '10:30', 45));
    assert_same([], slot_times('09:00', '09:10', 30));
});

test('doctor_slots() marks taken and past slots', function () use ($conn, $mx_now, $mx_future) {
    $u = fx_user($conn, 's1');
    $d = fx_doctor($conn);
    book_appointment($conn, $u, $d, $mx_future, '10:00', 'video', '', $mx_now);
    $byTime = array_column(doctor_slots($conn, $d, $mx_future, $mx_now), 'available', 'time');
    assert_same(false, $byTime['10:00']);
    assert_same(true, $byTime['10:30']);
    assert_same(16, count($byTime));
    $monday = new DateTimeImmutable('2026-10-19 10:00:00');
    $today = array_column(doctor_slots($conn, $d, '2026-10-19', $monday), 'available', 'time');
    assert_same(false, $today['09:30'], 'earlier than now');
    assert_same(true, $today['10:30'], 'later than now');
});

test('doctor_slots() is empty on a day off', function () use ($conn, $mx_now) {
    $d = fx_doctor($conn);
    assert_same([], doctor_slots($conn, $d, '2026-10-24', $mx_now), 'Saturday has no schedule');
});

test('book_appointment() refuses a time outside the schedule', function () use ($conn, $mx_now, $mx_future) {
    $u = fx_user($conn, 's2');
    $d = fx_doctor($conn);
    assert_refused(fn() => book_appointment($conn, $u, $d, $mx_future, '18:00', 'video', '', $mx_now), 'does not see patients');
    assert_refused(fn() => book_appointment($conn, $u, $d, $mx_future, '10:10', 'video', '', $mx_now), 'does not see patients');
    assert_refused(fn() => book_appointment($conn, $u, $d, '2026-10-24', '10:00', 'video', '', $mx_now), 'does not see patients');
});

test('save_doctor_schedule() replaces the schedule and validates input', function () use ($conn, $mx_now) {
    $d = fx_doctor($conn);
    save_doctor_schedule($conn, $d, [6], '10:00', '12:00', 60);
    assert_same(['10:00', '11:00'], array_column(doctor_slots($conn, $d, '2026-10-24', $mx_now), 'time'));
    assert_same([], doctor_slots($conn, $d, '2026-10-20', $mx_now), 'old days removed');
    assert_refused(fn() => save_doctor_schedule($conn, $d, [], '10:00', '12:00', 30), 'working day');
    assert_refused(fn() => save_doctor_schedule($conn, $d, [1], '12:00', '10:00', 30), 'before the end');
    assert_refused(fn() => save_doctor_schedule($conn, $d, [1], '10:00', '12:00', 7), 'slot length');
});


/* ----------------------- tracking and charts ----------------------- */

test('order_steps() shows the progress of a live order', function () {
    $steps = order_steps('shipped', [
        ['status' => 'pending', 'created_at' => '2026-10-17 09:00:00'],
        ['status' => 'processing', 'created_at' => '2026-10-17 10:00:00'],
        ['status' => 'shipped', 'created_at' => '2026-10-17 12:00:00'],
    ]);
    assert_same(['done', 'done', 'current', 'todo'], array_column($steps, 'state'));
    assert_same('2026-10-17 10:00:00', $steps[1]['at']);
    assert_same(null, $steps[3]['at']);
});

test('order_steps() marks a delivered order as finished', function () {
    $steps = order_steps('delivered', []);
    assert_same(['done', 'done', 'done', 'done'], array_column($steps, 'state'));
});

test('order_steps() shows a cancelled order up to the cancel', function () {
    $steps = order_steps('cancelled', [
        ['status' => 'pending', 'created_at' => '2026-10-17 09:00:00'],
        ['status' => 'cancelled', 'created_at' => '2026-10-17 09:30:00'],
    ]);
    assert_same(['pending', 'cancelled'], array_column($steps, 'key'));
    assert_same('cancelled', $steps[1]['state']);
});

test('svg_bar_chart() draws one bar per value with a title and escapes labels', function () {
    $svg = svg_bar_chart(['1 Oct', '<b>2 Oct</b>'], [0, 5], 'Orders & more', 'orders');
    assert_same(2, substr_count($svg, '<rect'));
    assert_true(strpos($svg, '<b>') === false, 'label was not escaped');
    assert_true(strpos($svg, 'Orders &amp; more') !== false);
    assert_same('', svg_bar_chart([], [], 'Empty'));
});

test('daily_counts() returns a value for every day', function () use ($conn) {
    [$labels, $values] = daily_counts($conn, 'appointments', 'created_at', 7);
    assert_same(7, count($labels));
    assert_same(7, count($values));
    assert_refused_invalid(fn() => daily_counts($conn, 'users; DROP TABLE users', 'created_at', 7));
});

/* ----------------------- notifications ----------------------- */

test('notify() stores a notification and unread_notifications() counts it', function () use ($conn) {
    $u = fx_user($conn, 'n1');
    notify($conn, $u, 'Hello', 'Body text', '/my_orders.php', false);
    notify($conn, $u, 'Second', '', 'https://evil.example/x', false);
    assert_same(2, unread_notifications($conn, $u));
    assert_same('/my_orders.php', fx_row($conn, "SELECT link FROM notifications WHERE user_id = $u AND title = 'Hello'")['link']);
    assert_same('', fx_row($conn, "SELECT link FROM notifications WHERE user_id = $u AND title = 'Second'")['link'], 'outside link dropped');
});

/* ----------------------- password reset and verification ----------------------- */

test('a reset token works once and only for the right kind', function () use ($conn) {
    $u = fx_user($conn, 't1');
    $token = create_token($conn, $u, 'reset', 60);
    assert_same(null, consume_token($conn, $token, 'verify'), 'wrong kind');
    assert_same($u, consume_token($conn, $token, 'reset'));
    assert_same(null, consume_token($conn, $token, 'reset'), 'second use');
    assert_same(null, consume_token($conn, 'not-a-token', 'reset'));
});

test('a new token replaces the older one', function () use ($conn) {
    $u = fx_user($conn, 't2');
    $old = create_token($conn, $u, 'reset', 60);
    $new = create_token($conn, $u, 'reset', 60);
    assert_same(null, consume_token($conn, $old, 'reset'));
    assert_same($u, consume_token($conn, $new, 'reset'));
});

test('an expired token is refused', function () use ($conn) {
    $u = fx_user($conn, 't3');
    $token = create_token($conn, $u, 'reset', 60);
    $conn->query("UPDATE user_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = $u");
    assert_true(!token_is_valid($conn, $token, 'reset'));
    assert_same(null, consume_token($conn, $token, 'reset'));
});

test('reset_password() sets the new password and keeps the rules', function () use ($conn) {
    $u = fx_user($conn, 't4');
    $token = create_token($conn, $u, 'reset', 60);
    assert_true(reset_password($conn, $token, 'short') !== null, 'weak password refused');
    assert_same(null, reset_password($conn, $token, 'N3w-Str0ng-Pass'));
    $hash = fx_row($conn, "SELECT password FROM users WHERE id = $u")['password'];
    assert_true(password_verify('N3w-Str0ng-Pass', $hash));
    assert_true(reset_password($conn, $token, 'An0ther-Str0ng-One') !== null, 'link works once');
});

test('verify_email() confirms the address once', function () use ($conn) {
    $u = fx_user($conn, 't5');
    $token = create_token($conn, $u, 'verify', 60);
    assert_true(verify_email($conn, $token));
    assert_true(fx_row($conn, "SELECT email_verified_at v FROM users WHERE id = $u")['v'] !== null);
    assert_true(!verify_email($conn, $token), 'second use');
});

test('request_password_reset() makes no token for an unknown email', function () use ($conn) {
    request_password_reset($conn, 'nobody_' . bin2hex(random_bytes(3)) . '@example.test');
    $u = fx_user($conn, 't6');
    $email = fx_row($conn, "SELECT email FROM users WHERE id = $u")['email'];
    request_password_reset($conn, $email);
    assert_same('1', fx_row($conn, "SELECT COUNT(*) c FROM user_tokens WHERE user_id = $u AND kind = 'reset'")['c']);
});

test('resend_verification() sends one new link to an unverified account only', function () use ($conn) {
    $u = fx_user($conn, 'rv');
    $conn->query("UPDATE users SET email_verified_at = NULL WHERE id = $u");
    $email = fx_row($conn, "SELECT email FROM users WHERE id = $u")['email'];
    resend_verification($conn, $email);
    resend_verification($conn, $email);
    assert_same('1', fx_row($conn, "SELECT COUNT(*) c FROM user_tokens WHERE user_id = $u AND kind = 'verify' AND used_at IS NULL")['c'], 'old link retired');
    $conn->query("UPDATE users SET email_verified_at = NOW() WHERE id = $u");
    $conn->query("DELETE FROM user_tokens WHERE user_id = $u");
    resend_verification($conn, $email);
    resend_verification($conn, 'nobody_' . bin2hex(random_bytes(3)) . '@example.test');
    assert_same('0', fx_row($conn, "SELECT COUNT(*) c FROM user_tokens WHERE user_id = $u")['c'], 'verified account gets nothing');
});

/* ----------------------- consent, export, deletion, audit ----------------------- */

function fx_user_with_password(mysqli $c, string $tag, string $password, string $role = 'user'): int
{
    $email = "test_{$tag}_" . bin2hex(random_bytes(3)) . '@example.test';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    $c->query("INSERT INTO users (full_name, email, password, phone, role) VALUES ('Test $tag', '$email', '$hash', '01712345678', '$role')");
    return (int)$c->insert_id;
}

test('consent is recorded and checked against the current version', function () use ($conn) {
    $u = fx_user($conn, 'p1');
    assert_true(!has_current_consent($conn, $u), 'new account has not consented');
    record_consent($conn, $u);
    assert_true(has_current_consent($conn, $u));
    $conn->query("UPDATE users SET consent_version = '2000-01' WHERE id = $u");
    assert_true(!has_current_consent($conn, $u), 'old version needs a new accept');
});

test('export_user_data() holds only the account owner records and no password hash', function () use ($conn, $mx_now, $mx_future) {
    $a = fx_user($conn, 'p2');
    $b = fx_user($conn, 'p3');
    $d = fx_doctor($conn);
    $m = fx_medicine($conn, 5);
    book_appointment($conn, $a, $d, $mx_future, '10:00', 'video', 'my cough', $mx_now);
    place_order($conn, $a, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash');
    place_order($conn, $b, [['medicine_id' => $m, 'quantity' => 1]], 'Other place', '01712345678', 'cash');
    $data = export_user_data($conn, $a);
    assert_same(1, count($data['appointments']));
    assert_same(1, count($data['orders']));
    assert_same('Dhaka', $data['orders'][0]['delivery_address']);
    assert_same(1, count($data['orders'][0]['items']));
    assert_true(!array_key_exists('password', $data['profile']), 'hash must not be exported');
    assert_true(strpos(json_encode($data), 'Other place') === false, 'other user data leaked');
});

test('delete_user_account() needs the right password and removes the data', function () use ($conn, $mx_now, $mx_future) {
    $u = fx_user_with_password($conn, 'p4', 'Correct-Horse-9');
    $d = fx_doctor($conn);
    $m = fx_medicine($conn, 5);
    book_appointment($conn, $u, $d, $mx_future, '11:00', 'video', '', $mx_now);
    $r = place_order($conn, $u, [['medicine_id' => $m, 'quantity' => 1]], 'Dhaka', '01712345678', 'cash');
    $conn->query("INSERT INTO ambulance_requests (user_id, name, phone, pickup_location, destination) VALUES ($u, 'T', '01712345678', 'A', 'B')");

    assert_same('The password is wrong.', delete_user_account($conn, $u, 'nope'));
    assert_same('1', fx_row($conn, "SELECT COUNT(*) c FROM users WHERE id = $u")['c'], 'still there');

    assert_same(null, delete_user_account($conn, $u, 'Correct-Horse-9'));
    foreach (['users WHERE id', 'appointments WHERE user_id', 'medicine_orders WHERE user_id', 'ambulance_requests WHERE user_id'] as $where) {
        assert_same('0', fx_row($conn, "SELECT COUNT(*) c FROM $where = $u")['c'], $where);
    }
    assert_same('0', fx_row($conn, "SELECT COUNT(*) c FROM order_items WHERE order_id = {$r['order_id']}")['c'], 'items removed with the order');
});

test('is_last_admin() sees whether another admin exists', function () use ($conn) {
    $admin = fx_user_with_password($conn, 'p5', 'Admin-Pass-123', 'admin');
    $others = (int)fx_row($conn, "SELECT COUNT(*) c FROM users WHERE role = 'admin' AND id <> $admin")['c'];
    assert_same($others === 0, is_last_admin($conn, $admin));
    $second = fx_user_with_password($conn, 'p6', 'Admin-Pass-456', 'admin');
    assert_same(false, is_last_admin($conn, $admin), 'a second admin exists');
    assert_same(null, delete_user_account($conn, $admin, 'Admin-Pass-123'), 'not the last admin, so allowed');
    assert_same('1', fx_row($conn, "SELECT COUNT(*) c FROM users WHERE id = $second")['c']);
});

test('audit_log() writes a row and keeps details short', function () use ($conn) {
    $admin = fx_user_with_password($conn, 'p7', 'Admin-Pass-789', 'admin');
    audit_log($conn, 'doctor.edit', 'doctor', 42, str_repeat('x', 900), $admin);
    $row = fx_row($conn, "SELECT action, target_type, target_id, details FROM audit_log WHERE admin_id = $admin ORDER BY id DESC LIMIT 1");
    assert_same('doctor.edit', $row['action']);
    assert_same('42', $row['target_id']);
    assert_same(500, strlen($row['details']));
});

test('asset_url() adds a version for a file that exists', function () {
    assert_true(preg_match('#^/assets/css/style\.css\?v=\d+$#', asset_url('/assets/css/style.css')) === 1);
    assert_same('/assets/missing.css', asset_url('/assets/missing.css'));
});
