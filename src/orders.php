<?php
/**
 * Medicine order logic. Every function takes the database connection, so the
 * tests can call them without a web request.
 */

const ORDER_PAYMENT_METHODS = ['cash', 'card', 'mobile_banking'];

const ORDER_MAX_LINES = 20;

/** Record a status change so the customer can follow the order. */
function record_order_status(mysqli $conn, int $orderId, string $status): void
{
    $stmt = $conn->prepare("INSERT INTO order_status_history (order_id, status) VALUES (?, ?)");
    $stmt->bind_param("is", $orderId, $status);
    $stmt->execute();
}

/**
 * Place an order for one or more medicines. The price always comes from the database.
 * $items is a list of ['medicine_id' => int, 'quantity' => int]. Repeated medicines are merged.
 * A medicine that needs a prescription also needs $prescription, the saved image file name.
 * Returns ['order_id' => int, 'order_number' => string, 'total' => float].
 * Throws RuntimeException with a message for the person when the order is refused.
 */
function place_order(mysqli $conn, int $userId, array $items, string $address, string $phone, string $payment, ?string $prescription = null): array
{
    if ($address === '' || $phone === '') {
        throw new RuntimeException('Please fill in all required fields');
    }
    if (!valid_phone($phone)) {
        throw new RuntimeException('Enter a valid phone number');
    }
    if (!in_array($payment, ORDER_PAYMENT_METHODS, true)) {
        throw new RuntimeException('Choose a payment method');
    }

    // Merge repeated medicines and check each quantity
    $wanted = [];
    foreach ($items as $item) {
        $id = (int)($item['medicine_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Please fill in all required fields');
        }
        if ($qty < 1 || $qty > 99) {
            throw new RuntimeException('Choose a quantity between 1 and 99');
        }
        $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
        if ($wanted[$id] > 99) {
            throw new RuntimeException('Choose a quantity between 1 and 99');
        }
    }
    if (!$wanted) {
        throw new RuntimeException('Your cart is empty');
    }
    if (count($wanted) > ORDER_MAX_LINES) {
        throw new RuntimeException('An order can hold up to ' . ORDER_MAX_LINES . ' different medicines');
    }
    ksort($wanted); // lock rows in the same order every time

    $conn->begin_transaction();
    try {
        $lines = [];
        $total = 0.0;
        $needsPrescription = [];
        $stmt = $conn->prepare("SELECT id, name, price, stock, status, prescription_required FROM medicines WHERE id = ? FOR UPDATE");
        foreach ($wanted as $medicineId => $qty) {
            $stmt->bind_param("i", $medicineId);
            $stmt->execute();
            $medicine = $stmt->get_result()->fetch_assoc();

            if (!$medicine) {
                throw new RuntimeException('Selected medicine not found');
            }
            if ($medicine['status'] !== 'available' || (int)$medicine['stock'] < $qty) {
                throw new RuntimeException($medicine['name'] . ' is out of stock or the quantity is too high');
            }
            if ((int)$medicine['prescription_required'] === 1) {
                $needsPrescription[] = $medicine['name'];
            }
            $price = (float)$medicine['price'];
            $total += $price * $qty;
            $lines[] = ['id' => $medicineId, 'qty' => $qty, 'price' => $price, 'stock' => (int)$medicine['stock']];
        }

        if ($needsPrescription && ($prescription === null || $prescription === '')) {
            throw new RuntimeException('Upload a photo of your prescription for: ' . implode(', ', $needsPrescription));
        }

        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $rx = $needsPrescription ? $prescription : null;

        $stmt = $conn->prepare("INSERT INTO medicine_orders (user_id, order_number, total_amount, delivery_address, phone, payment_method, prescription_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isdssss", $userId, $orderNumber, $total, $address, $phone, $payment, $rx);
        $stmt->execute();
        $orderId = (int)$stmt->insert_id;

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stockStmt = $conn->prepare("UPDATE medicines SET stock = ?, status = ? WHERE id = ?");
        foreach ($lines as $line) {
            $itemStmt->bind_param("iiid", $orderId, $line['id'], $line['qty'], $line['price']);
            $itemStmt->execute();

            $newStock = $line['stock'] - $line['qty'];
            $newStatus = $newStock > 0 ? 'available' : 'out_of_stock';
            $stockStmt->bind_param("isi", $newStock, $newStatus, $line['id']);
            $stockStmt->execute();
        }

        record_order_status($conn, $orderId, 'pending');
        $conn->commit();
        return ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Cancel an order and put its stock back.
 * Pass $userId to limit the cancel to that user's own pending or processing order.
 * Returns null on success, or a message for the person.
 */
function cancel_order(mysqli $conn, int $orderId, ?int $userId = null): ?string
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, user_id, status FROM medicine_orders WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order || ($userId !== null && (int)$order['user_id'] !== $userId)) {
            $conn->rollback();
            return 'Order not found.';
        }
        $allowed = $userId !== null ? ['pending', 'processing'] : ['pending', 'processing', 'shipped'];
        if (!in_array($order['status'], $allowed, true)) {
            $conn->rollback();
            return 'This order can no longer be cancelled.';
        }

        $stmt = $conn->prepare("UPDATE medicines m JOIN order_items oi ON oi.medicine_id = m.id
            SET m.stock = m.stock + oi.quantity, m.status = 'available'
            WHERE oi.order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE medicine_orders SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        record_order_status($conn, $orderId, 'cancelled');

        $conn->commit();
        return null;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Cancel order failed: ' . $e->getMessage());
        return 'Could not cancel the order. Please try again.';
    }
}

/**
 * Steps for the order tracker.
 * $history is a list of ['status' => ..., 'created_at' => ...] rows, oldest first.
 * Returns a list of ['key', 'state' => done|current|todo|cancelled, 'at' => ?string].
 */
function order_steps(string $status, array $history): array
{
    $flow = ['pending', 'processing', 'shipped', 'delivered'];
    $at = [];
    foreach ($history as $row) {
        $at[$row['status']] = $row['created_at']; // the latest time for each status wins
    }

    if ($status === 'cancelled') {
        $steps = [];
        foreach ($flow as $key) {
            if (isset($at[$key])) {
                $steps[] = ['key' => $key, 'state' => 'done', 'at' => $at[$key]];
            }
        }
        $steps[] = ['key' => 'cancelled', 'state' => 'cancelled', 'at' => $at['cancelled'] ?? null];
        return $steps;
    }

    $position = array_search($status, $flow, true);
    $position = $position === false ? 0 : $position;
    $steps = [];
    foreach ($flow as $i => $key) {
        $state = $i < $position ? 'done' : ($i === $position ? 'current' : 'todo');
        if ($state === 'current' && $key === 'delivered') {
            $state = 'done';
        }
        $steps[] = ['key' => $key, 'state' => $state, 'at' => $at[$key] ?? null];
    }
    return $steps;
}
