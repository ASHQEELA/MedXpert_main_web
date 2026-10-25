<?php
/**
 * Appointment logic. Every function takes the database connection and,
 * where time matters, the current time, so the tests can control both.
 */

const APPOINTMENT_TYPES = ['in-person', 'video', 'phone'];

/**
 * Book an appointment. Returns the new appointment id.
 * Throws RuntimeException with a message for the person when the booking is refused.
 */
function book_appointment(
    mysqli $conn,
    int $userId,
    int $doctorId,
    string $date,
    string $time,
    string $type,
    string $symptoms,
    ?DateTimeImmutable $now = null
): int {
    $now = $now ?? new DateTimeImmutable('now');

    if ($doctorId <= 0 || $date === '' || $time === '') {
        throw new RuntimeException('Please fill in all required fields');
    }
    if (!valid_date($date) || !valid_time($time)) {
        throw new RuntimeException('Enter a valid date and time');
    }
    if (!in_array($type, APPOINTMENT_TYPES, true)) {
        throw new RuntimeException('Choose a consultation type');
    }
    if ($date < $now->format('Y-m-d')) {
        throw new RuntimeException('Appointment date cannot be in the past');
    }
    if ($date > $now->modify('+1 year')->format('Y-m-d')) {
        throw new RuntimeException('Choose a date within the next year');
    }
    if ($date === $now->format('Y-m-d') && $time <= $now->format('H:i')) {
        throw new RuntimeException('Choose a time later than now');
    }

    $conn->begin_transaction();
    try {
        // Lock the doctor row so two bookings for one slot cannot pass the check together
        $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND status = 'active' FOR UPDATE");
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new RuntimeException('Selected doctor is not available');
        }

        // The time must be one of the doctor's slots for that weekday
        $offered = array_column(doctor_slots($conn, $doctorId, $date, $now), 'time');
        if (!in_array($time, $offered, true)) {
            throw new RuntimeException('The doctor does not see patients at that time. Pick a listed time.');
        }

        $stmt = $conn->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed') LIMIT 1");
        $stmt->bind_param("iss", $doctorId, $date, $time);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RuntimeException('That time is already booked. Choose another time.');
        }

        $stmt = $conn->prepare("SELECT 1 FROM appointments WHERE user_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed') LIMIT 1");
        $stmt->bind_param("iss", $userId, $date, $time);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RuntimeException('You already have an appointment at that time.');
        }

        $stmt = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, consultation_type, symptoms, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iissss", $userId, $doctorId, $date, $time, $type, $symptoms);
        $stmt->execute();
        $id = (int)$stmt->insert_id;

        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/** Cancel a user's own future appointment. Returns true when it was cancelled. */
function cancel_appointment(mysqli $conn, int $appointmentId, int $userId, ?DateTimeImmutable $now = null): bool
{
    $now = $now ?? new DateTimeImmutable('now');
    $today = $now->format('Y-m-d');
    $clock = $now->format('H:i:s');

    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled'
        WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
        AND (appointment_date > ? OR (appointment_date = ? AND appointment_time > ?))");
    $stmt->bind_param("iisss", $appointmentId, $userId, $today, $today, $clock);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}
