<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/auth_layout.php';
require_login();

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['consent'])) {
        record_consent($conn, $user_id);
        $_SESSION['consent_version'] = PRIVACY_VERSION;
        redirect('/dashboard.php');
    }
    flash_set('error', 'Tick the box to continue, or log out.');
    redirect('/consent.php');
}

if (has_current_consent($conn, $user_id)) {
    $_SESSION['consent_version'] = PRIVACY_VERSION;
    redirect('/dashboard.php');
}

auth_page_start('Before you continue');
auth_message(flash_take());
?>
            <form method="POST" class="auth-form active">
                <?php echo csrf_field(); ?>
                <p>MedXpert stores health information such as your symptoms, orders and prescription photos. Please read how we use it.</p>
                <p><a href="/privacy.php" target="_blank" rel="noopener">Read the Privacy Policy</a></p>
                <label class="check-row">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>I have read the Privacy Policy and agree that MedXpert may process my health information to provide these services.</span>
                </label>
                <button type="submit" class="btn btn-primary">Accept and continue</button>
            </form>
            <form method="post" action="/api/logout.php" class="auth-footer-link">
                <?php echo csrf_field(); ?>
                <button type="submit" class="link-button">Log out instead</button>
            </form>
<?php auth_page_end(false); ?>
