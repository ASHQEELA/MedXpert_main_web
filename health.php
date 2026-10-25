<?php
// Health check for uptime monitors. It shows no private data and starts no session.
define('MX_NO_SESSION', true);
header('Content-Type: application/json');
header('Cache-Control: no-store');

$ok = true;
$checks = ['app' => true, 'database' => false, 'uploads' => false];

try {
    require_once __DIR__ . '/config/bootstrap.php';
    $checks['database'] = (bool)$conn->query('SELECT 1');
} catch (Throwable $e) {
    error_log('Health check database failure: ' . $e->getMessage());
}
$checks['uploads'] = is_writable(__DIR__ . '/uploads');

foreach ($checks as $passed) {
    $ok = $ok && $passed;
}
http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks, 'time' => gmdate('c')]);
