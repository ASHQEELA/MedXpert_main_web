<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = strtolower(clean($_POST['to'] ?? '', 100));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = ['error', 'Enter a valid email address.'];
    } elseif (rate_limited('mailtest', (string)$_SESSION['user_id'], 5, 20, 10)) {
        $result = ['error', 'Too many tests. Wait ten minutes.'];
    } else {
        rate_limit_hit('mailtest', (string)$_SESSION['user_id']);
        $sent = send_mail($to, 'MedXpert test email', "This is a test message from MedXpert.\n\nIf you read it, email sending works.");
        audit_log($conn, 'mail.test', 'mail', null, $sent ? 'Test email sent' : 'Test email failed');
        $result = $sent
            ? ['success', mail_mode() === 'log' ? 'Written to logs/mail.log. Nobody receives it in log mode.' : 'The mail server accepted the message. Check the inbox and the Spam folder.']
            : ['error', 'Not sent. ' . mail_last_error()];
    }
}

require_once __DIR__ . '/../config/header.php';
?>

<h1>Email Test</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Email Test</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card" style="max-width: 600px;">
        <div class="card-header"><h3 class="card-title">Send a test message</h3></div>
        <p>Current mode: <strong><?php echo e(mail_mode()); ?></strong>. Sender: <strong><?php echo e((string)mx_config('mail_from', 'not set')); ?></strong>.</p>
        <?php if ($result): ?>
            <div class="alert alert-<?php echo $result[0] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo e($result[1]); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label" for="to">Send to</label>
                <input type="email" id="to" name="to" class="form-input" value="<?php echo e($_SESSION['email'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send test email</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
