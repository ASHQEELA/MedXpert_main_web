<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

// Get statistics
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$stats['total_doctors'] = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$stats['total_appointments'] = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$stats['total_orders'] = $conn->query("SELECT COUNT(*) as count FROM medicine_orders")->fetch_assoc()['count'];
$stats['total_revenue'] = $conn->query("SELECT SUM(total_amount) as sum FROM medicine_orders WHERE status = 'delivered'")->fetch_assoc()['sum'] ?? 0;
$stats['pending_ambulance'] = $conn->query("SELECT COUNT(*) as count FROM ambulance_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get recent appointments
$recent_appointments = $conn->query("
    SELECT a.*, d.name as doctor_name, u.full_name as user_name 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
");

// Get recent orders
$recent_orders = $conn->query("
    SELECT o.*, u.full_name as user_name 
    FROM medicine_orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
[$orderLabels, $orderValues] = daily_counts($conn, 'medicine_orders', 'created_at', 14);
[$aptLabels, $aptValues] = daily_counts($conn, 'appointments', 'created_at', 14);

require_once __DIR__ . '/../config/header.php';
?>

<h1>Admin Dashboard</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin Dashboard</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['total_doctors']; ?></div>
                    <div class="stat-label">Total Doctors</div>
                </div>
                <div class="stat-icon green">
                    <i class="fas fa-user-md"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['total_appointments']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-icon purple">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">৳<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-icon green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $stats['pending_ambulance']; ?></div>
                    <div class="stat-label">Pending Ambulance</div>
                </div>
                <div class="stat-icon red">
                    <i class="fas fa-ambulance"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="chart-grid-2">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Orders, last 14 days</h3></div>
            <div class="card-body"><?php echo svg_bar_chart($orderLabels, $orderValues, 'Orders placed per day, last 14 days', 'orders'); ?></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Appointments booked, last 14 days</h3></div>
            <div class="card-body"><?php echo svg_bar_chart($aptLabels, $aptValues, 'Appointments booked per day, last 14 days', 'appointments'); ?></div>
        </div>
    </div>

    <div class="panel-grid">
        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Appointments</h3>
                <a href="appointments.php" style="color: var(--primary-light); font-size: 0.9rem;">
                    Manage All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_appointments->num_rows > 0): ?>
                            <?php while ($apt = $recent_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($apt['user_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $apt['status'] === 'confirmed' ? 'success' : ($apt['status'] === 'pending' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--gray);">No appointments yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Orders</h3>
                <a href="orders.php" style="color: var(--primary-light); font-size: 0.9rem;">
                    Manage All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders->num_rows > 0): ?>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                    <td>৳<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo ucfirst($order['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--gray);">No orders yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
