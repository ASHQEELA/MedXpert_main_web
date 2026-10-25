<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

try {
    $done = cancel_appointment($conn, (int)($_POST['id'] ?? 0), (int)$_SESSION['user_id']);
    if ($done) {
        notify($conn, (int)$_SESSION['user_id'], 'Appointment cancelled', 'Your appointment was cancelled.', '/my_appointments.php', false);
    }
    echo json_encode($done
        ? ['success' => true, 'message' => 'Appointment cancelled']
        : ['success' => false, 'message' => 'This appointment cannot be cancelled']);
} catch (Throwable $e) {
    error_log('Cancel appointment failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not cancel the appointment. Please try again.']);
}
