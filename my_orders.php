<?php
require_once 'config/header.php';

// Get user's orders
$orders = db_query("
    SELECT o.*, 
    (SELECT GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, ')') SEPARATOR ', ') 
     FROM order_items oi 
     JOIN medicines m ON oi.medicine_id = m.id 
     WHERE oi.order_id = o.id) as items
    FROM medicine_orders o 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
", 'i', [(int)$_SESSION['user_id']]);

$history = [];
$historyRows = db_query("SELECT h.order_id, h.status, h.created_at FROM order_status_history h JOIN medicine_orders o ON o.id = h.order_id WHERE o.user_id = ? ORDER BY h.id ASC", 'i', [(int)$_SESSION['user_id']]);
while ($h = $historyRows->fetch_assoc()) {
    $history[(int)$h['order_id']][] = $h;
}
?>

<h1>My Medicine Orders</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> <?php te('Home'); ?></span>
    <span>/</span>
    <span><?php te('Orders'); ?></span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php te('Order History'); ?></h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php te('Order # & Date'); ?></th>
                        <th><?php te('Medicines'); ?></th>
                        <th><?php te('Amount'); ?></th>
                        <th><?php te('Status'); ?></th>
                        <th><?php te('Delivery Info'); ?></th>
                        <th></th>
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
                                    <small style="color: var(--gray); display: block; max-width: 300px;"><?php echo htmlspecialchars($order['items']); ?></small>
                                </td>
                                <td><strong>৳<?php echo number_format($order['total_amount'], 2); ?></strong></td>
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
                                        <?php te(ucfirst($order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: var(--gray); display: block; max-width: 200px;"><?php echo htmlspecialchars($order['delivery_address']); ?></small>
                                    <small style="color: var(--gray);"><?php echo htmlspecialchars($order['phone']); ?></small>
                                </td>
                                <td>
                                    <?php if (in_array($order['status'], ['pending', 'processing'], true)): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="cancelItem('/api/cancel_order.php', <?php echo (int)$order['id']; ?>, 'Cancel this order?')"><?php te('Cancel'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="track-row">
                                <td colspan="6">
                                    <ol class="tracker" aria-label="<?php te('Order tracking'); ?>">
                                        <?php foreach (order_steps($order['status'], $history[(int)$order['id']] ?? []) as $step): ?>
                                            <li class="track-step <?php echo e($step['state']); ?>">
                                                <span class="track-dot" aria-hidden="true"></span>
                                                <span class="track-label"><?php te(ucfirst($step['key'])); ?></span>
                                                <?php if ($step['at']): ?><small><?php echo e(date('M j, g:i A', strtotime($step['at']))); ?></small><?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if ($order['prescription_image']): ?>
                                        <small><a href="/prescription.php?order=<?php echo (int)$order['id']; ?>" target="_blank" rel="noopener"><i class="fas fa-file-medical"></i> Prescription</a></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--gray);"><?php te('No orders found.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>
