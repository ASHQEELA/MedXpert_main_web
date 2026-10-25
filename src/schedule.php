<?php
/**
 * Doctor schedules and time slots.
 * A schedule row says: on this weekday the doctor sees patients from start to end,
 * in slots of N minutes. Weekday 0 is Sunday and 6 is Saturday.
 */

const SCHEDULE_SLOT_CHOICES = [10, 15, 20, 30, 45, 60];

/** Build the slot times for one schedule row. Returns a list like ['09:00', '09:30']. */
function slot_times(string $start, string $end, int $minutes): array
{
    if ($minutes < 5 || !valid_time(substr($start, 0, 5)) || !valid_time(substr($end, 0, 5))) {
        return [];
    }
    $from = (int)substr($start, 0, 2) * 60 + (int)substr($start, 3, 2);
    $to = (int)substr($end, 0, 2) * 60 + (int)substr($end, 3, 2);
    $out = [];
    for ($t = $from; $t + $minutes <= $to; $t += $minutes) {
        $out[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }
    return $out;
}

/**
 * Slots a doctor offers on a date, each marked taken or past.
 * Returns a list of ['time' => '09:00', 'available' => bool].
 */
function doctor_slots(mysqli $conn, int $doctorId, string $date, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now');
    if (!valid_date($date)) {
        return [];
    }
    $weekday = (int)(new DateTimeImmutable($date))->format('w');

    $stmt = $conn->prepare("SELECT start_time, end_time, slot_minutes FROM doctor_schedules WHERE doctor_id = ? AND weekday = ?");
    $stmt->bind_param("ii", $doctorId, $weekday);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return [];
    }

    $stmt = $conn->prepare("SELECT TIME_FORMAT(appointment_time, '%H:%i') AS t FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed')");
    $stmt->bind_param("is", $doctorId, $date);
    $stmt->execute();
    $taken = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 't');

    $today = $now->format('Y-m-d');
    $clock = $now->format('H:i');
    $slots = [];
    foreach (slot_times($row['start_time'], $row['end_time'], (int)$row['slot_minutes']) as $time) {
        $past = $date < $today || ($date === $today && $time <= $clock);
        $slots[] = ['time' => $time, 'available' => !$past && !in_array($time, $taken, true)];
    }
    return $slots;
}

/**
 * Check schedule input. Returns the cleaned list of weekday numbers.
 * Throws RuntimeException with a message for the person when the input is wrong.
 */
function check_schedule(array $days, string $start, string $end, int $minutes): array
{
    $days = array_values(array_unique(array_filter(array_map('intval', $days), fn($d) => $d >= 0 && $d <= 6)));
    sort($days);
    if (!$days) {
        throw new RuntimeException('Choose at least one working day.');
    }
    if (!valid_time($start) || !valid_time($end) || $start >= $end) {
        throw new RuntimeException('Enter a start time that is before the end time.');
    }
    if (!in_array($minutes, SCHEDULE_SLOT_CHOICES, true) || !slot_times($start, $end, $minutes)) {
        throw new RuntimeException('The slot length does not fit in the working hours.');
    }
    return $days;
}

/** Replace a doctor's schedule. $days is a list of weekday numbers. */
function save_doctor_schedule(mysqli $conn, int $doctorId, array $days, string $start, string $end, int $minutes): void
{
    $days = check_schedule($days, $start, $end, $minutes);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?");
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes) VALUES (?, ?, ?, ?, ?)");
        foreach ($days as $day) {
            $stmt->bind_param("iissi", $doctorId, $day, $start, $end, $minutes);
            $stmt->execute();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/** Readable text for the doctor cards, such as ['Sun, Mon, Tue', '9:00 AM - 5:00 PM']. */
function schedule_summary(array $days, string $start, string $end): array
{
    sort($days);
    $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $label = implode(', ', array_map(fn($d) => $names[$d], $days));
    $fmt = fn($t) => date('g:i A', strtotime($t));
    return [$label, $fmt($start) . ' - ' . $fmt($end)];
}
