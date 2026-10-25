<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

function ambulance_error(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$name = clean($_POST['name'] ?? '', 100);
$phone = clean($_POST['phone'] ?? '', 20);
$pickup_location = clean($_POST['pickup_location'] ?? '', 500);
$destination = clean($_POST['destination'] ?? '', 500);
$emergency_type = pick($_POST['emergency_type'] ?? '', ['Heart Emergency', 'Accident', 'Breathing Problem', 'Stroke', 'Pregnancy', 'Other']);
$patient_condition = clean($_POST['patient_condition'] ?? '', 1000);
$request_type = pick($_POST['request_type'] ?? 'emergency', ['emergency', 'scheduled'], 'emergency');

if ($name === '' || $phone === '' || $pickup_location === '' || $destination === '') {
    ambulance_error('Please fill in all required fields');
}

if (!valid_phone($phone)) {
    ambulance_error('Enter a valid phone number');
}

if ($emergency_type === null) {
    ambulance_error('Choose an emergency type');
}

// Allow 10 requests per hour from one connection
if (rate_limited('ambulance', '', 0, 10, 60)) {
    http_response_code(429);
    ambulance_error('Too many requests. For an emergency, call 999 now.');
}

try {
    $stmt = $conn->prepare("INSERT INTO ambulance_requests (user_id, name, phone, pickup_location, destination, emergency_type, patient_condition, request_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isssssss", $user_id, $name, $phone, $pickup_location, $destination, $emergency_type, $patient_condition, $request_type);
    $stmt->execute();
    rate_limit_hit('ambulance');

    notify($conn, $user_id, 'Ambulance request received',
        'We got your ' . strtolower($emergency_type) . ' request. For an emergency, call 999 now.', '/modules/ambulance.php');
    echo json_encode(['success' => true, 'message' => 'Ambulance request submitted successfully', 'request_id' => $stmt->insert_id]);
} catch (Throwable $e) {
    error_log('Ambulance request failed: ' . $e->getMessage());
    ambulance_error('Failed to submit ambulance request. Please try again.');
}
