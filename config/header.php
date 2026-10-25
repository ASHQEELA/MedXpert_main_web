<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site.php';

// Admin pages are protected before any HTML is sent
if (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false) {
    require_admin();
} else {
    require_login();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Accounts that have not accepted the current privacy policy accept it first
if (($_SESSION['consent_version'] ?? null) !== PRIVACY_VERSION) {
    if (has_current_consent($conn, (int)$_SESSION['user_id'])) {
        $_SESSION['consent_version'] = PRIVACY_VERSION;
    } else {
        redirect('/consent.php');
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo e(lang_code()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>MedXpert - Modern Medical Platform</title>
    <?php head_assets(); ?>
</head>
<body>
    <script>try{if(localStorage.getItem('sidebarCollapsed')==='1'&&window.innerWidth>1024){document.body.classList.add('sidebar-collapsed');}}catch(e){}</script>
    <a class="skip-link" href="#main">Skip to main content</a>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar" aria-label="Main navigation">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <h2><i class="fas fa-heartbeat"></i> <span class="logo-text">MedXpert</span></h2>
                    <p><?php te('Your Health, Our Priority'); ?></p>
                </div>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-title"><?php te('Main'); ?></div>
                    <a href="/dashboard.php" class="menu-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        <span><?php te('Dashboard'); ?></span>
                    </a>
                    <a href="/profile.php" class="menu-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span><?php te('My Profile'); ?></span>
                    </a>
                    <a href="/my_appointments.php" class="menu-item <?php echo $current_page === 'my_appointments' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span><?php te('My Appointments'); ?></span>
                    </a>
                    <a href="/notifications.php" class="menu-item bell-link <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span><?php te('Notifications'); ?></span>
                        <?php $mx_unread = unread_notifications($conn, (int)$_SESSION['user_id']); if ($mx_unread > 0): ?>
                            <span class="bell-badge" aria-label="<?php echo $mx_unread; ?> unread"><?php echo $mx_unread > 99 ? '99+' : $mx_unread; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/my_orders.php" class="menu-item <?php echo $current_page === 'my_orders' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-bag"></i>
                        <span><?php te('My Orders'); ?></span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title"><?php te('Medical Services'); ?></div>
                    <a href="/modules/consult.php" class="menu-item <?php echo $current_page === 'consult' ? 'active' : ''; ?>">
                        <i class="fas fa-user-md"></i>
                        <span><?php te('Doctor Consultation'); ?></span>
                    </a>
                    <a href="/modules/blood.php" class="menu-item <?php echo $current_page === 'blood' ? 'active' : ''; ?>">
                        <i class="fas fa-tint"></i>
                        <span><?php te('Blood Bank'); ?></span>
                    </a>
                    <a href="/modules/pharmacy.php" class="menu-item <?php echo $current_page === 'pharmacy' ? 'active' : ''; ?>">
                        <i class="fas fa-pills"></i>
                        <span><?php te('Online Pharmacy'); ?></span>
                    </a>
                    <a href="/modules/ambulance.php" class="menu-item <?php echo $current_page === 'ambulance' ? 'active' : ''; ?>">
                        <i class="fas fa-ambulance"></i>
                        <span><?php te('Ambulance Service'); ?></span>
                    </a>
                </div>
                
                <?php if (isAdmin()): ?>
                <div class="menu-section">
                    <div class="menu-title"><?php te('Administration'); ?></div>
                    <a href="/admin/index.php" class="menu-item <?php echo $current_page === 'index' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Admin Dashboard</span>
                    </a>
                    <a href="/admin/doctors.php" class="menu-item">
                        <i class="fas fa-stethoscope"></i>
                        <span>Manage Doctors</span>
                    </a>
                    <a href="/admin/donors.php" class="menu-item">
                        <i class="fas fa-hand-holding-medical"></i>
                        <span>Manage Donors</span>
                    </a>
                    <a href="/admin/medicines.php" class="menu-item">
                        <i class="fas fa-capsules"></i>
                        <span>Manage Medicines</span>
                    </a>
                    <a href="/admin/orders.php" class="menu-item">
                        <i class="fas fa-shopping-bag"></i>
                        <span><?php te('Orders'); ?></span>
                    </a>
                    <a href="/admin/audit.php" class="menu-item <?php echo $current_page === 'audit' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Audit Log</span>
                    </a>
                    <a href="/admin/mail_test.php" class="menu-item <?php echo $current_page === 'mail_test' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i>
                        <span>Email Test</span>
                    </a>
                    <a href="/admin/appointments.php" class="menu-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php te('Appointments'); ?></span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="menu-section">
                    <div class="menu-title"><?php te('Account'); ?></div>
                    <form method="post" action="/api/logout.php" class="menu-form">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="menu-item menu-item-button">
                            <i class="fas fa-sign-out-alt"></i>
                            <span><?php te('Logout'); ?></span>
                        </button>
                    </form>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <form method="post" action="/api/set_language.php" class="lang-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="next" value="<?php echo e($_SERVER['REQUEST_URI'] ?? '/dashboard.php'); ?>">
                    <input type="hidden" name="lang" value="<?php echo lang_code() === 'bn' ? 'en' : 'bn'; ?>">
                    <button type="submit" class="lang-btn" lang="<?php echo lang_code() === 'bn' ? 'en' : 'bn'; ?>"><i class="fas fa-language"></i> <?php echo lang_code() === 'bn' ? 'English' : 'বাংলা'; ?></button>
                </form>
	                <div class="user-profile">
	                    <div class="user-avatar">
	                        <?php 
	                        $user_id = $_SESSION['user_id'];
	                        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
	                        $stmt->bind_param("i", $user_id);
	                        $stmt->execute();
	                        $avatar_res = $stmt->get_result()->fetch_assoc();
	                        $avatar = $avatar_res['avatar'] ?? 'default-avatar.png';
	                        
	                        if ($avatar && $avatar !== 'default-avatar.png'): ?>
	                            <img src="/uploads/profiles/<?php echo e(safe_image_name($avatar)); ?>" alt="Your profile photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
	                        <?php else: ?>
	                            <?php echo e(mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
	                        <?php endif; ?>
	                    </div>
	                    <div class="user-info">
	                        <h4><?php echo e($_SESSION['full_name']); ?></h4>
	                        <p><?php echo e(ucfirst($_SESSION['role'])); ?></p>
	                    </div>
	                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="main" tabindex="-1">
            <div class="top-bar">
                <button type="button" class="menu-toggle" id="sidebarToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
                <div class="page-title">