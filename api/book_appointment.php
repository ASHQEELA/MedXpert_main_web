<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

try {
    $id = book_appointment(
        $conn,
        (int)$_SESSION['user_id'],
        (int)($_POST['doctor_id'] ?? 0),
        clean($_POST['appointment_date'] ?? '', 10),
        clean($_POST['appointment_time'] ?? '', 5),
        (string)pick($_POST['consultation_type'] ?? 'in-person', APPOINTMENT_TYPES, ''),
        clean($_POST['symptoms'] ?? '', 1000)
    );
    $row = db_query("SELECT a.appointment_date, a.appointment_time, d.name FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.id = ?", 'i', [$id])->fetch_assoc();
    if ($row) {
        notify($conn, (int)$_SESSION['user_id'], 'Appointment booked',
            'Dr. ' . preg_replace('/^Dr\.?\s+/i', '', $row['name']) . ' on ' . date('D, M j', strtotime($row['appointment_date'])) . ' at ' . date('g:i A', strtotime($row['appointment_time'])) . '. We will confirm it soon.',
            '/my_appointments.php');
    }
    echo json_encode(['success' => true, 'message' => 'Appointment booked successfully', 'appointment_id' => $id]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Booking failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment. Please try again.']);
}
