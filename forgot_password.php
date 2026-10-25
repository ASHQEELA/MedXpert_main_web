<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/auth_layout.php';

if (isLoggedIn()) {
    redirect('/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(clean($_POST['email'] ?? '', 100));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Enter a valid email address.');
    } elseif (rate_limited('reset', $email, 3, 10, 60)) {
        http_response_code(429);
        flash_set('error', 'Too many requests. Please wait an hour and try again.');
    } else {
        rate_limit_hit('reset', $email);
        try {
            request_password_reset($conn, $email);
        } catch (Throwable $e) {
            error_log('Password reset request failed: ' . $e->getMessage());
        }
        // The same reply for every address, so the form does not reveal who has an account
        flash_set('success', 'If that email belongs to an account, we sent a reset link. It expires in 60 minutes.');
    }
    redirect('/forgot_password.php');
}

auth_page_start('Forgot your password');
auth_message(flash_take());
?>
            <form method="POST" class="auth-form active">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" class="form-input" name="email" placeholder="Enter your email" autocomplete="email" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-envelope"></i> Send reset link</button>
            </form>
<?php auth_page_end(); ?>
