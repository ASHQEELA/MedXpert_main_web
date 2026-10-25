<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mx_fail(405, 'Invalid request method');
}

// The cart arrives as JSON in "items". A single medicine_id and quantity still work.
$items = json_decode((string)($_POST['items'] ?? ''), true);
if (!is_array($items)) {
    $items = isset($_POST['medicine_id'])
        ? [['medicine_id' => $_POST['medicine_id'], 'quantity' => $_POST['quantity'] ?? 1]]
        : [];
}
$items = array_slice(array_filter($items, 'is_array'), 0, 50);

$prescription = null;
try {
    $uploadError = null;
    $prescription = save_image_upload('prescription', 'prescriptions', 'rx', $uploadError, 1600);
    if ($uploadError !== null) {
        throw new RuntimeException($uploadError);
    }

    // The price from the form is ignored. The price always comes from the database.
    $result = place_order(
        $conn,
        (int)$_SESSION['user_id'],
        $items,
        clean($_POST['delivery_address'] ?? '', 500),
        clean($_POST['phone'] ?? '', 20),
        (string)pick($_POST['payment_method'] ?? 'cash', ORDER_PAYMENT_METHODS, ''),
        $prescription
    );
    notify($conn, (int)$_SESSION['user_id'], 'Order placed',
        'Order ' . $result['order_number'] . ' for BDT ' . number_format($result['total'], 2) . ' is waiting for confirmation.', '/my_orders.php');
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_number' => $result['order_number'],
        'order_id' => $result['order_id'],
        'total' => $result['total'],
    ]);
} catch (RuntimeException $e) {
    if ($prescription) {
        @unlink(dirname(__DIR__) . '/uploads/prescriptions/' . $prescription);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($prescription) {
        @unlink(dirname(__DIR__) . '/uploads/prescriptions/' . $prescription);
    }
    error_log('Order failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to place the order. Please try again.']);
}
