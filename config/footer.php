<footer class="site-footer">
                <div class="footer-grid">
                    <div>
                        <h4>In an emergency</h4>
                        <p>Call <a href="tel:<?php echo SITE_EMERGENCY_NUMBER; ?>"><strong><?php echo SITE_EMERGENCY_NUMBER; ?></strong></a>, the national emergency line, open 24 hours.</p>
                    </div>
                    <?php if (SITE_SUPPORT_EMAIL !== '' || SITE_SUPPORT_PHONE !== ''): ?>
                    <div>
                        <h4>Contact us</h4>
                        <?php if (SITE_SUPPORT_PHONE !== ''): ?>
                            <p><i class="fas fa-phone"></i> <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^\d+]/', '', SITE_SUPPORT_PHONE)); ?>"><?php echo htmlspecialchars(SITE_SUPPORT_PHONE); ?></a></p>
                        <?php endif; ?>
                        <?php if (SITE_SUPPORT_EMAIL !== ''): ?>
                            <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars(SITE_SUPPORT_EMAIL); ?>"><?php echo htmlspecialchars(SITE_SUPPORT_EMAIL); ?></a></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h4>Your privacy</h4>
                        <p>Your details are used only to process your appointments, orders, and requests in MedXpert. <a href="/privacy.php">Privacy Policy</a></p>
                    </div>
                </div>
                <p class="footer-note">MedXpert does not replace professional medical advice, diagnosis, or treatment.</p>
                <p class="footer-note">&copy; <?php echo date('Y'); ?> MedXpert</p>
            </footer>
        </main>
    </div>
    
    <?php if (!empty($_SESSION['user_id']) && ($current_page ?? '') !== 'ambulance'): ?>
    <a href="/modules/ambulance.php" class="fab-emergency" aria-label="Request an ambulance">
        <i class="fas fa-ambulance"></i> <span>Ambulance</span>
    </a>
    <?php endif; ?>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Scripts -->
    <?php if (!empty($_SESSION['user_id'])): ?>
    <nav class="tabbar" aria-label="Quick navigation">
        <a href="/dashboard.php" class="<?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span><?php te('Home'); ?></span></a>
        <a href="/modules/consult.php" class="<?php echo ($current_page ?? '') === 'consult' ? 'active' : ''; ?>"><i class="fas fa-user-md"></i><span><?php te('Doctors'); ?></span></a>
        <a href="/modules/pharmacy.php" class="<?php echo ($current_page ?? '') === 'pharmacy' ? 'active' : ''; ?>"><i class="fas fa-pills"></i><span><?php te('Pharmacy'); ?></span></a>
        <a href="/modules/blood.php" class="<?php echo ($current_page ?? '') === 'blood' ? 'active' : ''; ?>"><i class="fas fa-tint"></i><span><?php te('Blood'); ?></span></a>
        <button type="button" id="tabbarMenu" aria-label="<?php te('Toggle menu'); ?>"><i class="fas fa-bars"></i><span><?php te('Menu'); ?></span></button>
    </nav>
    <script>
        (function () {
            var menu = document.getElementById('tabbarMenu');
            var toggle = document.getElementById('sidebarToggle');
            if (menu && toggle) menu.addEventListener('click', function () { toggle.click(); });
        })();
    </script>
    <?php endif; ?>
    <script src="<?php echo e(asset_url('/assets/js/app.js')); ?>"></script>
    <?php if ($flash = flash_take()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showToast(<?php echo json_encode($flash['type'] === 'success' ? 'success' : 'error'); ?>, <?php echo json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>