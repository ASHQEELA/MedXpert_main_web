<?php

require_once __DIR__ . '/config/bootstrap.php';
if (isLoggedIn()) {
    redirect('/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="<?php echo e(lang_code()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>MedXpert - Login</title>
    <?php head_assets(); ?>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-logo">
                    <h1><i class="fas fa-heartbeat"></i> MedXpert</h1>
                    <p><?php te('Your Complete Healthcare Solution'); ?></p>
                </div>
            </div>
            
            <form method="post" action="/api/set_language.php" class="lang-form lang-form-auth">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="next" value="/index.php">
                <input type="hidden" name="lang" value="<?php echo lang_code() === 'bn' ? 'en' : 'bn'; ?>">
                <button type="submit" class="lang-btn" lang="<?php echo lang_code() === 'bn' ? 'en' : 'bn'; ?>"><i class="fas fa-language"></i> <?php echo lang_code() === 'bn' ? 'English' : 'বাংলা'; ?></button>
            </form>

            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="loginForm"><?php te('Login'); ?></button>
                <button class="auth-tab" data-tab="registerForm"><?php te('Register'); ?></button>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" class="auth-form active" onsubmit="handleLogin(event)">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label class="form-label"><?php te('Email Address'); ?></label>
                    <input type="email" class="form-input" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php te('Password'); ?></label>
                    <input type="password" class="form-input" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> <?php te('Login'); ?>
                </button>
                <p class="auth-footer-link"><a href="/forgot_password.php"><?php te('Forgot your password?'); ?></a></p>
                <p class="auth-footer-link"><a href="/resend_verification.php"><?php te('Did not get the confirmation email? Send it again'); ?></a></p>
                
            </form>
            
            <!-- Register Form -->
            <form id="registerForm" class="auth-form" onsubmit="handleRegister(event)">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label class="form-label"><?php te('Full Name'); ?></label>
                    <input type="text" class="form-input" name="full_name" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php te('Email Address'); ?></label>
                    <input type="email" class="form-input" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php te('Phone Number'); ?></label>
                    <input type="tel" class="form-input" name="phone" placeholder="Enter your phone number" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php te('Password'); ?></label>
                    <input type="password" class="form-input" name="password" placeholder="At least 8 characters" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php te('Confirm Password'); ?></label>
                    <input type="password" class="form-input" name="confirm_password" placeholder="Confirm your password" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                
                <label class="check-row">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>I have read the <a href="/privacy.php" target="_blank" rel="noopener">Privacy Policy</a> and agree that MedXpert may process my health information to provide these services.</span>
                </label>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> <?php te('Create Account'); ?>
                </button>
            </form>
        </div>
    </div>
    
    <script src="<?php echo e(asset_url('/assets/js/app.js')); ?>"></script>
    <?php if (isset($_GET['deleted'])): ?>
    <script>document.addEventListener('DOMContentLoaded', function () { showToast('success', 'Your account was deleted.'); });</script>
    <?php endif; ?>
    <script>
        function handleLogin(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            setBusy(form, true, 'Logging in...');
            
            postJson('api/login.php', formData)
            .then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    showToast('error', data.message || 'Login failed. Please check your credentials.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Something went wrong. Please try again.');
            })
            .finally(() => setBusy(form, false));
        }
        
        function handleRegister(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            if (formData.get('password') !== formData.get('confirm_password')) {
                showToast('error', 'Passwords do not match.');
                return;
            }
            
            const form = e.target;
            setBusy(form, true, 'Creating account...');
            postJson('api/register.php', formData)
            .then(data => {
                if (data.success) {
                    showToast('success', data.message || 'Registration complete. Please log in.');
                    document.querySelector('[data-tab="loginForm"]').click();
                    e.target.reset();
                } else {
                    showToast('error', data.message || 'Registration failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Something went wrong. Please try again.');
            })
            .finally(() => setBusy(form, false));
        }
    </script>
</body>
</html>