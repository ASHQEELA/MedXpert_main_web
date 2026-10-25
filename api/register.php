<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

$full_name = clean($_POST['full_name'] ?? '', 100);
$email = strtolower(clean($_POST['email'] ?? '', 100));
$phone = clean($_POST['phone'] ?? '', 20);
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if ($full_name === '' || $email === '' || $phone === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

if (!valid_phone($phone)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid phone number']);
    exit();
}

if (empty($_POST['consent'])) {
    echo json_encode(['success' => false, 'message' => 'Please read and accept the Privacy Policy to create an account.']);
    exit();
}

$passwordProblem = password_error($password, $email);
if ($passwordProblem !== null) {
    echo json_encode(['success' => false, 'message' => $passwordProblem]);
    exit();
}

// Limit sign-ups from one address to 10 per hour
if (rate_limited('register', '', 0, 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many sign-ups from this connection. Please try again later.']);
    exit();
}
rate_limit_hit('register');

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

if (!$exists) {
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);
        $stmt->execute();
        $newUserId = (int)$stmt->insert_id;
        record_consent($conn, $newUserId);
        try {
            send_verification($conn, $newUserId);
        } catch (Throwable $mailError) {
            error_log('Verification email skipped: ' . $mailError->getMessage());
        }
    } catch (mysqli_sql_exception $e) {
        // A duplicate email from a simultaneous request ends up here. Treat it like an existing account.
        if ($e->getCode() !== 1062) {
            error_log('Registration failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
            exit();
        }
    }
}

// The reply is identical for new and existing emails so the form does not reveal who has an account
echo json_encode([
    'success' => true,
    'message' => 'Registration complete. Log in with your email and password. If you already had an account, use your existing password.'
]);
