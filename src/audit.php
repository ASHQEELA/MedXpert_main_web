<?php
/**
 * Audit log for admin actions. A failure to write never stops the action.
 */

/** Record what an admin did. Keep $details short and free of private data. */
function audit_log(mysqli $conn, string $action, string $targetType = '', ?int $targetId = null, string $details = '', ?int $adminId = null): void
{
    try {
        $adminId = $adminId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $ip = function_exists('client_ip') ? client_ip() : '';
        $action = mb_substr($action, 0, 60, 'UTF-8');
        $targetType = mb_substr($targetType, 0, 40, 'UTF-8');
        $details = mb_substr($details, 0, 500, 'UTF-8');

        $stmt = $conn->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $adminId, $action, $targetType, $targetId, $details, $ip);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('Audit log skipped: ' . $e->getMessage());
    }
}
