<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/auth_layout.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$ok = false;
try {
    $ok = verify_email($conn, $token);
} catch (Throwable $e) {
    error_log('Email verification failed: ' . $e->getMessage());
}

$loggedIn = !empty($_SESSION['user_id']);
$next = $loggedIn ? '/dashboard.php' : '/login.php';
$nextLabel = $loggedIn ? 'Go to dashboard' : 'Log in to MedXpert';

auth_page_start($ok ? 'Email confirmed' : 'Link not valid');
?>
            <div class="result-card <?php echo $ok ? 'result-ok' : 'result-bad'; ?>" role="status">
                <div class="result-icon"><i class="fas <?php echo $ok ? 'fa-check' : 'fa-xmark'; ?>"></i></div>
                <h2><?php echo $ok ? 'Your email is confirmed' : 'This link did not work'; ?></h2>
                <p>
                    <?php echo $ok
                        ? 'Thank you. Your account is ready. You now receive appointment and order updates by email.'
                        : 'The link is wrong, already used, or expired. Ask for a new link below.'; ?>
                </p>
                <a class="btn btn-primary result-btn" href="<?php echo e($next); ?>"><?php echo e($nextLabel); ?></a>
                <?php if (!$ok): ?>
                    <a class="result-link" href="/resend_verification.php">Send me a new link</a>
                <?php endif; ?>
            </div>
<?php
auth_page_end(false);
