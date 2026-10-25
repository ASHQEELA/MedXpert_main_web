<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = clean($_GET['date'] ?? '', 10);

if ($doctor_id <= 0 || !valid_date($date)) {
    echo json_encode(['success' => false, 'message' => 'Choose a doctor and a date']);
    exit();
}

try {
    echo json_encode(['success' => true, 'slots' => doctor_slots($conn, $doctor_id, $date)]);
} catch (Throwable $e) {
    error_log('Slots failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not load times. Please try again.']);
}
