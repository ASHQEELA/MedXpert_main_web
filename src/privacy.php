<?php
/**
 * Consent, data export and account deletion.
 */

/** Change this when the privacy policy changes. People are asked to accept again. */
const PRIVACY_VERSION = '2026-10';

/** Record consent for an account. */
function record_consent(mysqli $conn, int $userId): void
{
    $version = PRIVACY_VERSION;
    $stmt = $conn->prepare("UPDATE users SET consented_at = NOW(), consent_version = ? WHERE id = ?");
    $stmt->bind_param("si", $version, $userId);
    $stmt->execute();
}

/** True when the account accepted the current policy. */
function has_current_consent(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT consented_at, consent_version FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && $row['consented_at'] !== null && $row['consent_version'] === PRIVACY_VERSION;
}

/** Everything the site holds about one account, ready to download. The password hash is left out. */
function export_user_data(mysqli $conn, int $userId): array
{
    $fetch = function (string $sql) use ($conn, $userId): array {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    };

    $profile = $fetch("SELECT full_name, email, phone, address, role, avatar, created_at, email_verified_at, consented_at, consent_version FROM users WHERE id = ?")[0] ?? [];

    return [
        'exported_at' => gmdate('c'),
        'profile' => $profile,
        'appointments' => $fetch("SELECT a.appointment_date, a.appointment_time, a.consultation_type, a.symptoms, a.status, a.created_at, d.name AS doctor
            FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.user_id = ? ORDER BY a.created_at"),
        'orders' => array_map(function (array $order) use ($conn) {
            $stmt = $conn->prepare("SELECT m.name, oi.quantity, oi.price FROM order_items oi JOIN medicines m ON m.id = oi.medicine_id WHERE oi.order_id = ?");
            $stmt->bind_param("i", $order['id']);
            $stmt->execute();
            unset($order['id']);
            $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            return $order;
        }, $fetch("SELECT id, order_number, total_amount, delivery_address, phone, payment_method, status, prescription_image IS NOT NULL AS has_prescription, created_at
            FROM medicine_orders WHERE user_id = ? ORDER BY created_at")),
        'ambulance_requests' => $fetch("SELECT name, phone, pickup_location, destination, emergency_type, patient_condition, request_type, status, created_at
            FROM ambulance_requests WHERE user_id = ? ORDER BY created_at"),
        'notifications' => $fetch("SELECT title, body, created_at FROM notifications WHERE user_id = ? ORDER BY created_at"),
    ];
}

/** True when no other admin account exists besides this one. */
function is_last_admin(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND id <> ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] === 0;
}

/**
 * Delete an account and the data tied to it. The password must be right.
 * Returns null on success, or a message for the person.
 */
function delete_user_account(mysqli $conn, int $userId, string $password): ?string
{
    $stmt = $conn->prepare("SELECT password, role, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !password_verify($password, $user['password'])) {
        return 'The password is wrong.';
    }

    if ($user['role'] === 'admin' && is_last_admin($conn, $userId)) {
        return 'This is the only admin account. Make another admin first.';
    }

    // Collect files first, because the rows vanish with the account
    $stmt = $conn->prepare("SELECT prescription_image FROM medicine_orders WHERE user_id = ? AND prescription_image IS NOT NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $files = array_map(fn($r) => dirname(__DIR__) . '/uploads/prescriptions/' . safe_image_name($r['prescription_image'], '_none_'), $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    if (!empty($user['avatar']) && $user['avatar'] !== 'default-avatar.png') {
        $files[] = dirname(__DIR__) . '/uploads/profiles/' . safe_image_name($user['avatar'], '_none_');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM ambulance_requests WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Account deletion failed: ' . $e->getMessage());
        return 'The account could not be deleted. Please try again.';
    }

    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    return null;
}
