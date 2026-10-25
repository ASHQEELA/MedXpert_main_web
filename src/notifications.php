<?php
/**
 * In-app notifications, with an optional email copy.
 * A failure here never stops the action that caused it.
 */

function notify(mysqli $conn, int $userId, string $title, string $body = '', string $link = '', bool $email = true): void
{
    try {
        $title = mb_substr($title, 0, 150, 'UTF-8');
        $body = mb_substr($body, 0, 500, 'UTF-8');
        $link = (strpos($link, '/') === 0 && strpos($link, '//') !== 0) ? mb_substr($link, 0, 200, 'UTF-8') : '';

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $title, $body, $link);
        $stmt->execute();

        if ($email && mail_mode() !== 'off') {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                $text = $title . "\n\n" . $body . ($link !== '' ? "\n\n" . app_url() . $link : '') . "\n\nMedXpert";
                send_mail($user['email'], $title, $text);
            }
        }
    } catch (Throwable $e) {
        error_log('Notification skipped: ' . $e->getMessage());
    }
}

function unread_notifications(mysqli $conn, int $userId): int
{
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['c'];
    } catch (Throwable $e) {
        return 0; // the table may be missing on an old database
    }
}
