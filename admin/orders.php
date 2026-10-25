<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $status = pick($_POST['status'] ?? '', ['pending', 'processing', 'shipped', 'delivered', 'cancelled']);
    $current = db_query("SELECT status FROM medicine_orders WHERE id = ?", 'i', [$order_id])->fetch_assoc();
    if ($status === null || !$current) {
        flash_set('error', 'Choose a valid status.');
    } elseif ($current['status'] === $status) {
        flash_set('success', 'Order status unchanged.');
    } elseif ($current['status'] === 'cancelled') {
        flash_set('error', 'A cancelled order cannot be reopened. Ask the customer to order again.');
    } elseif ($status === 'cancelled') {
        $error = cancel_order($conn, $order_id);
        if ($error === null) {
            $owner = db_query("SELECT user_id, order_number FROM medicine_orders WHERE id = ?", 'i', [$order_id])->fetch_assoc();
            if ($owner) {
                notify($conn, (int)$owner['user_id'], 'Order cancelled', 'Order ' . $owner['order_number'] . ' was cancelled.', '/my_orders.php');
            }
        }
        if ($error === null) {
            audit_log($conn, 'order.cancel', 'order', $order_id, 'Cancelled by admin, stock returned');
        }
        flash_set($error === null ? 'success' : 'error', $error ?? 'Order cancelled and stock returned.');
    } else {
        $stmt = $conn->prepare("UPDATE medicine_orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        record_order_status($conn, $order_id, $status);
        audit_log($conn, 'order.status', 'order', $order_id, 'Set to ' . $status);
        $owner = db_query("SELECT user_id, order_number FROM medicine_orders WHERE id = ?", 'i', [$order_id])->fetch_assoc();
        if ($owner) {
            notify($conn, (int)$owner['user_id'], 'Order ' . $status, 'Order ' . $owner['order_number'] . ' is now ' . $status . '.', '/my_orders.php');
        }
        flash_set('success', 'Order status updated.');
    }
    redirect('/admin/orders.php');
}

$orders = $conn->query("
    SELECT o.*, u.full_name as user_name, u.email as user_email,
    (SELECT GROUP_CONCAT(CONCAT(m.name, ' x', oi.quantity) SEPARATOR ', ') FROM order_items oi JOIN medicines m ON m.id = oi.medicine_id WHERE oi.order_id = o.id) AS items
    FROM medicine_orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
");

require_once __DIR__ . '/../config/header.php';
?>

<h1>Manage Orders</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Manage Orders</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders->num_rows > 0): ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                    <small><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($order['phone']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo e($order['items']); ?></small>
                                    <?php if ($order['prescription_image']): ?>
                                        <br><a href="/prescription.php?order=<?php echo (int)$order['id']; ?>" target="_blank" rel="noopener"><i class="fas fa-file-medical"></i> Prescription</a>
                                    <?php endif; ?>
                                    <br><small style="color: var(--gray);"><?php echo e($order['delivery_address']); ?></small>
                                </td>
                                <td>৳<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo strtoupper($order['payment_method']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class[$order['status']]; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 0.5rem;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" class="form-input" style="padding: 0.25rem; font-size: 0.8rem; width: auto;" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--gray);">No orders found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
