<?php
require_once 'config/header.php';

// Get statistics
$uid = (int) $_SESSION['user_id'];
$stats = [];
$stats['total_doctors'] = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'")->fetch_assoc()['count'];
$stats['total_donors'] = $conn->query("SELECT COUNT(*) as count FROM blood_donors WHERE available = 1")->fetch_assoc()['count'];
$stats['total_medicines'] = $conn->query("SELECT COUNT(*) as count FROM medicines WHERE status = 'available'")->fetch_assoc()['count'];
$stats['total_appointments'] = db_query("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?", 'i', [$uid])->fetch_assoc()['count'];

// Get recent appointments
$recent_appointments = db_query("
    SELECT a.*, d.name as doctor_name, d.specialization 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    WHERE a.user_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
", 'i', [$uid]);

// Get recent orders
$recent_orders = db_query("
    SELECT * FROM medicine_orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", 'i', [$uid]);

// Next upcoming appointment
$stmt = $conn->prepare("
    SELECT a.appointment_date, a.appointment_time, a.status, d.name AS doctor_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.user_id = ? AND a.status IN ('pending', 'confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$next_appointment = $stmt->get_result()->fetch_assoc();

// Latest order
$stmt = $conn->prepare("SELECT order_number, status, total_amount, created_at FROM medicine_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$latest_order = $stmt->get_result()->fetch_assoc();

$first_name = explode(' ', trim($_SESSION['full_name']))[0];
?>

<h1><?php te('Dashboard'); ?></h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> <?php te('Home'); ?></span>
    <span>/</span>
    <span><?php te('Dashboard'); ?></span>
</div>
</div>

</div>

<div class="content-wrapper">
    <div class="greeting">
        <h2><?php te('Hello'); ?>, <?php echo e($first_name); ?></h2>
        <p><?php te('Here is what needs your attention today.'); ?></p>
    </div>

    <!-- Up next -->
    <div class="up-next">
        <div class="card">
            <h3><?php te('Next appointment'); ?></h3>
            <?php if ($next_appointment): ?>
                <p class="headline"><?php echo htmlspecialchars($next_appointment['doctor_name']); ?></p>
                <p class="sub"><?php echo htmlspecialchars($next_appointment['specialization']); ?></p>
                <div class="row">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('D, d M Y', strtotime($next_appointment['appointment_date'])); ?>, <?php echo date('g:i A', strtotime($next_appointment['appointment_time'])); ?></span>
                    <span class="badge badge-<?php echo $next_appointment['status'] === 'confirmed' ? 'success' : 'warning'; ?>"><?php te(ucfirst($next_appointment['status'])); ?></span>
                </div>
            <?php else: ?>
                <p class="empty"><?php te('You have no upcoming appointments.'); ?></p>
                <a href="modules/consult.php" class="btn btn-primary"><?php te('Book a doctor'); ?></a>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><?php te('Latest order'); ?></h3>
            <?php if ($latest_order): ?>
                <p class="headline">#<?php echo htmlspecialchars($latest_order['order_number']); ?></p>
                <p class="sub"><?php echo date('d M Y', strtotime($latest_order['created_at'])); ?> &middot; &#2547;<?php echo number_format((float) $latest_order['total_amount'], 2); ?></p>
                <div class="row">
                    <span class="badge badge-<?php echo $latest_order['status'] === 'delivered' ? 'success' : ($latest_order['status'] === 'cancelled' ? 'danger' : 'info'); ?>"><?php te(ucfirst($latest_order['status'])); ?></span>
                    <a href="my_orders.php"><?php te('View orders'); ?></a>
                </div>
            <?php else: ?>
                <p class="empty"><?php te('You have not ordered any medicines yet.'); ?></p>
                <a href="modules/pharmacy.php" class="btn btn-primary"><?php te('Browse medicines'); ?></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="quick-actions">
        <a href="modules/consult.php" class="quick-action">
            <span class="quick-action-icon"><i class="fas fa-user-md"></i></span>
            <span><strong><?php te('Book doctor'); ?></strong><small><?php te('Specialists near you'); ?></small></span>
        </a>
        <a href="modules/blood.php" class="quick-action">
            <span class="quick-action-icon"><i class="fas fa-tint"></i></span>
            <span><strong><?php te('Find blood'); ?></strong><small><?php te('Search by blood type'); ?></small></span>
        </a>
        <a href="modules/pharmacy.php" class="quick-action">
            <span class="quick-action-icon"><i class="fas fa-pills"></i></span>
            <span><strong><?php te('Order medicine'); ?></strong><small><?php te('Home delivery'); ?></small></span>
        </a>
        <a href="modules/ambulance.php" class="quick-action emergency">
            <span class="quick-action-icon"><i class="fas fa-ambulance"></i></span>
            <span><strong><?php te('Call ambulance'); ?></strong><small><?php te('24/7 emergency'); ?></small></span>
        </a>
    </div>

    <!-- Platform stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo (int) $stats['total_doctors']; ?></div>
                    <div class="stat-label"><?php te('Available doctors'); ?></div>
                </div>
                <div class="stat-icon blue"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo (int) $stats['total_donors']; ?></div>
                    <div class="stat-label"><?php te('Blood donors'); ?></div>
                </div>
                <div class="stat-icon green"><i class="fas fa-tint"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo (int) $stats['total_medicines']; ?></div>
                    <div class="stat-label"><?php te('Medicines available'); ?></div>
                </div>
                <div class="stat-icon orange"><i class="fas fa-pills"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo (int) $stats['total_appointments']; ?></div>
                    <div class="stat-label"><?php te('Your appointments'); ?></div>
                </div>
                <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
    </div>

    <div class="panel-grid">
        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php te('Recent Appointments'); ?></h3>
                <a href="modules/consult.php" style="color: var(--primary-light); font-size: 0.9rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php te('Doctor'); ?></th>
                            <th><?php te('Date'); ?></th>
                            <th><?php te('Status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_appointments->num_rows > 0): ?>
                            <?php while ($apt = $recent_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($apt['specialization']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'pending' => 'badge-warning',
                                            'confirmed' => 'badge-success',
                                            'completed' => 'badge-info',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $badge_class[$apt['status']]; ?>">
                                            <?php te(ucfirst($apt['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--gray);">
                                    No appointments yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php te('Recent Orders'); ?></h3>
                <a href="modules/pharmacy.php" style="color: var(--primary-light); font-size: 0.9rem;">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php te('Order #'); ?></th>
                            <th><?php te('Amount'); ?></th>
                            <th><?php te('Status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders->num_rows > 0): ?>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                        <small style="color: var(--gray);"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    <td><strong>৳<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'pending' => 'badge-warning',
                                            'processing' => 'badge-info',
                                            'shipped' => 'badge-primary',
                                            'delivered' => 'badge-success',
                                            'cancelled' => 'badge-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $badge_class[$order['status']]; ?>">
                                            <?php te(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--gray);">
                                    No orders yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>