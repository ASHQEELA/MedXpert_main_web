<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

$email = strtolower(clean($_POST['email'] ?? '', 100));
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
    exit();
}

if (rate_limited('login', $email)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please wait 15 minutes and try again.']);
    exit();
}

$stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Always run one password check so the response time does not reveal whether the email exists
$dummyHash = '$2y$12$CZ4aG93rLVkjzF52JtLvbuYMfMp6jbHy4UijVNdBymHOYHsV8Acz.';
$hash = $user['password'] ?? $dummyHash;
$valid = password_verify($password, $hash) && $user !== null;

if (!$valid) {
    rate_limit_hit('login', $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit();
}

rate_limit_clear('login', $email);

// Upgrade old hashes to the current cost
if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param("si", $newHash, $user['id']);
    $upd->execute();
}

login_user($user);

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'role' => $user['role']
    ]
]);
