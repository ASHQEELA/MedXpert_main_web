<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    redirect('/notifications.php');
}

$items = db_query("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50", 'i', [$user_id]);
$unread = unread_notifications($conn, $user_id);

require_once __DIR__ . '/config/header.php';
?>

<h1><?php te('Notifications'); ?></h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> <?php te('Home'); ?></span>
    <span>/</span>
    <span><?php te('Notifications'); ?></span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card" style="max-width: 720px; margin: 0 auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title"><?php te('Latest updates'); ?></h3>
            <?php if ($unread > 0): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-secondary btn-sm"><?php te('Mark all as read'); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($items->num_rows === 0): ?>
                <p class="slot-note"><?php te('You have no notifications yet.'); ?></p>
            <?php else: ?>
                <ul class="notice-list">
                    <?php while ($n = $items->fetch_assoc()): ?>
                        <li class="notice <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                            <div>
                                <strong><?php echo e($n['title']); ?></strong>
                                <p><?php echo e($n['body']); ?></p>
                                <small><?php echo e(date('M j, Y g:i A', strtotime($n['created_at']))); ?></small>
                            </div>
                            <?php if ($n['link'] !== ''): ?>
                                <a href="<?php echo e($n['link']); ?>" class="btn btn-secondary btn-sm"><?php te('Open'); ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
