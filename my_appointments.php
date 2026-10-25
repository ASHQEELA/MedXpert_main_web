<?php
require_once 'config/header.php';

// Get user's appointments
$appointments = db_query("
    SELECT a.*, d.name as doctor_name, d.specialization, d.hospital, d.location 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    WHERE a.user_id = ? 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", 'i', [(int)$_SESSION['user_id']]);
?>

<h1><?php te('My Appointments'); ?></h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> <?php te('Home'); ?></span>
    <span>/</span>
    <span><?php te('Appointments'); ?></span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php te('Appointment History'); ?></h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php te('Date & Time'); ?></th>
                        <th><?php te('Doctor'); ?></th>
                        <th><?php te('Type'); ?></th>
                        <th><?php te('Status'); ?></th>
                        <th><?php te('Symptoms'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments->num_rows > 0): ?>
                        <?php while ($apt = $appointments->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></strong><br>
                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($apt['specialization']); ?></small><br>
                                    <small style="color: var(--gray);"><?php echo htmlspecialchars($apt['hospital']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php te(ucfirst($apt['consultation_type'])); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'completed' => 'info',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class[$apt['status']]; ?>">
                                        <?php te(ucfirst($apt['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: var(--gray); max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($apt['symptoms']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if (in_array($apt['status'], ['pending', 'confirmed'], true) && strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']) > time()): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="cancelItem('/api/cancel_appointment.php', <?php echo (int)$apt['id']; ?>, 'Cancel this appointment?')"><?php te('Cancel'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--gray);"><?php te('No appointments found.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>
