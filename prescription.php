<?php
// Shows a prescription photo to the customer who uploaded it and to admins only.
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$orderId = (int)($_GET['order'] ?? 0);
$row = db_query("SELECT user_id, prescription_image FROM medicine_orders WHERE id = ?", 'i', [$orderId])->fetch_assoc();

$allowed = $row && $row['prescription_image'] && (isAdmin() || (int)$row['user_id'] === (int)$_SESSION['user_id']);
$name = $allowed ? safe_image_name($row['prescription_image']) : '';
$path = dirname(__FILE__) . '/uploads/prescriptions/' . $name;

if ($name === '' || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (isAdmin()) {
    audit_log($conn, 'prescription.view', 'order', $orderId);
}
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
