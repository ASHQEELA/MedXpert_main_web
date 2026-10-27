<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = pick($_POST['form'] ?? 'profile', ['profile', 'password', 'verify', 'export', 'delete'], 'profile');

    if ($form === 'export') {
        $data = export_user_data($conn, $user_id);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="medxpert-my-data-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    if ($form === 'delete') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (rate_limited('delete', (string)$user_id, 5, 10, 60)) {
            flash_set('error', 'Too many attempts. Please wait an hour.');
            redirect('/profile.php');
        }
        $error = delete_user_account($conn, $user_id, $password);
        if ($error !== null) {
            rate_limit_hit('delete', (string)$user_id);
            flash_set('error', $error);
            redirect('/profile.php');
        }
        unset($_SESSION['user_id']);
        audit_log($conn, 'user.self_delete', 'user', null, 'An account was deleted by its owner', null);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        redirect('/login.php?deleted=1');
    }

    if ($form === 'verify') {
        if (rate_limited('verify', (string)$user_id, 3, 10, 60)) {
            flash_set('error', 'Too many requests. Please wait an hour.');
        } else {
            rate_limit_hit('verify', (string)$user_id);
            send_verification($conn, $user_id);
            flash_set('success', 'We sent a confirmation link to your email.');
        }
        redirect('/profile.php');
    }

    if ($form === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = db_query("SELECT password FROM users WHERE id = ?", 'i', [$user_id])->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) {
            flash_set('error', 'Your current password is wrong.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'The new passwords do not match.');
        } elseif (($pwError = password_error($new)) !== null) {
            flash_set('error', $pwError);
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT, ['cost' => 12]);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            $stmt->execute();
            session_regenerate_id(true);
            flash_set('success', 'Password changed.');
        }
        redirect('/profile.php');
    }

    $full_name = clean($_POST['full_name'] ?? '', 100);
    $phone = clean($_POST['phone'] ?? '', 20);
    $address = clean($_POST['address'] ?? '', 500);

    $problem = null;
    if ($full_name === '') {
        $problem = 'Enter your name.';
    } elseif (!valid_phone($phone)) {
        $problem = 'Enter a valid phone number.';
    }

    $uploadError = null;
    $newAvatar = $problem === null ? save_image_upload('avatar', 'profiles', 'user_' . $user_id, $uploadError, 600) : null;
    if ($problem === null && $uploadError !== null) {
        $problem = $uploadError;
    }

    if ($problem !== null) {
        flash_set('error', $problem);
        redirect('/profile.php');
    }

    if ($newAvatar !== null) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, avatar = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $phone, $address, $newAvatar, $user_id);
        $_SESSION['avatar'] = $newAvatar;
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
    }
    $stmt->execute();
    $_SESSION['full_name'] = $full_name;
    flash_set('success', 'Profile updated.');
    redirect('/profile.php');
}

$user = db_query("SELECT * FROM users WHERE id = ?", 'i', [$user_id])->fetch_assoc();

require_once __DIR__ . '/config/header.php';
?>

<h1><?php te('My Profile'); ?></h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> <?php te('Home'); ?></span>
    <span>/</span>
    <span>Profile</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3 class="card-title"><?php te('Edit Personal Information'); ?></h3>
        </div>
        
        <?php if (empty($user['email_verified_at'])): ?>
            <form method="POST" class="verify-banner">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form" value="verify">
                <span><i class="fas fa-envelope"></i> <?php te('Your email is not confirmed.'); ?></span>
                <button type="submit" class="btn btn-secondary btn-sm"><?php te('Send confirmation link'); ?></button>
            </form>
        <?php endif; ?>

        <form method="POST" class="card-body" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="profile">
            <div class="form-group" style="text-align: center; margin-bottom: 2rem;">
                <div class="user-avatar" style="width: 100px; height: 100px; margin: 0 auto 1rem; font-size: 2.5rem;">
                    <?php if ($user['avatar'] && $user['avatar'] !== 'default-avatar.png'): ?>
                        <img src="/uploads/profiles/<?php echo e(safe_image_name($user['avatar'])); ?>" alt="Your profile photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo e(mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
                    <?php endif; ?>
                </div>
                <label class="form-label"><?php te('Profile Picture'); ?></label>
                <input type="file" name="avatar" class="form-input" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="form-group">
                <label class="form-label"><?php te('Full Name'); ?></label>
                <input type="text" name="full_name" class="form-input" value="<?php echo e($user['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address (Cannot be changed)</label>
                <input type="email" class="form-input" value="<?php echo e($user['email']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php te('Phone Number'); ?></label>
                <input type="tel" name="phone" class="form-input" value="<?php echo e($user['phone'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php te('Address'); ?></label>
                <textarea name="address" class="form-input" rows="3"><?php echo e($user['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php te('Member Since'); ?></label>
                <p style="color: var(--gray);"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php te('Save Changes'); ?>
            </button>
        </form>
    </div>

    <div class="card" style="max-width: 600px; margin: 1.5rem auto 0;">
        <div class="card-header">
            <h3 class="card-title"><?php te('Change Password'); ?></h3>
        </div>
        <form method="POST" class="card-body">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="password">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-input" autocomplete="current-password" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password (at least 8 characters)</label>
                <input type="password" name="new_password" class="form-input" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-input" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-key"></i> <?php te('Change Password'); ?>
            </button>
        </form>
    </div>

    <div class="card" style="max-width: 600px; margin: 1.5rem auto 0;">
        <div class="card-header">
            <h3 class="card-title">Your data</h3>
        </div>
        <div class="card-body">
            <p>Download a copy of what MedXpert holds about you, or read the <a href="/privacy.php">Privacy Policy</a>.</p>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form" value="export">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-download"></i> Download my data</button>
            </form>
            <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid var(--border, #d6e0f5);">
            <h4>Delete my account</h4>
            <p>This removes your profile, appointments, orders, prescription photos, ambulance requests and notifications. It cannot be undone.</p>
            <form method="POST" onsubmit="return confirm('Delete your account for good?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="form" value="delete">
                <div class="form-group">
                    <label class="form-label" for="deletePassword">Enter your password to confirm</label>
                    <input type="password" id="deletePassword" name="password" class="form-input" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-danger"><i class="fas fa-user-slash"></i> Delete my account</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>
