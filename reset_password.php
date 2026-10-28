<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/auth_layout.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : (is_string($_POST['token'] ?? null) ? $_POST['token'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if ($new !== $confirm) {
        flash_set('error', 'The passwords do not match.');
        redirect('/reset_password.php?token=' . rawurlencode($token));
    }
    $error = reset_password($conn, $token, $new);
    if ($error === null) {
        flash_set('success', 'Your password was changed. Log in with the new password.');
        redirect('/login.php');
    }
    flash_set('error', $error);
    redirect('/reset_password.php?token=' . rawurlencode($token));
}

auth_page_start('Choose a new password');
auth_message(flash_take());

if (!token_is_valid($conn, $token, 'reset')) {
    echo '<div class="alert alert-error">This reset link is not valid or has expired.</div>';
    echo '<p class="auth-footer-link"><a href="/forgot_password.php">Ask for a new link</a></p>';
} else {
    ?>
            <form method="POST" class="auth-form active">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <div class="form-group">
                    <label class="form-label" for="password">New password</label>
                    <input type="password" id="password" class="form-input" name="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Create a strong password" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm">Confirm new password</label>
                    <input type="password" id="confirm" class="form-input" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change password</button>
            </form>
<?php
}
auth_page_end();
