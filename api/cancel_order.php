<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

$error = cancel_order($conn, (int)($_POST["id"] ?? 0), (int)$_SESSION["user_id"]);

if ($error === null) {
    echo json_encode(['success' => true, 'message' => 'Order cancelled']);
} else {
    echo json_encode(['success' => false, 'message' => $error]);
}
