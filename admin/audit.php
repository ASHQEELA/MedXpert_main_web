<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$filter = clean($_GET['action'] ?? '', 60);

$where = '';
$types = '';
$params = [];
if ($filter !== '') {
    $where = 'WHERE l.action LIKE ?';
    $types = 's';
    $params[] = addcslashes($filter, '%_\\') . '%';
}

$total = (int)db_query("SELECT COUNT(*) AS c FROM audit_log l $where", $types, $params)->fetch_assoc()['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = db_query(
    "SELECT l.*, u.full_name AS admin_name FROM audit_log l LEFT JOIN users u ON u.id = l.admin_id $where ORDER BY l.id DESC LIMIT $perPage OFFSET $offset",
    $types,
    $params
);

require_once __DIR__ . '/../config/header.php';
?>

<h1>Audit Log</h1>
<div class="breadcrumb">
    <span><i class="fas fa-home"></i> Home</span>
    <span>/</span>
    <span>Admin</span>
    <span>/</span>
    <span>Audit Log</span>
</div>
</div>
</div>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <h3 class="card-title"><?php echo number_format($total); ?> recorded actions</h3>
            <form method="GET" style="display: flex; gap: 0.5rem;">
                <label class="visually-hidden" for="actionFilter">Filter by action</label>
                <select name="action" id="actionFilter" class="form-input" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    <?php foreach (['doctor', 'donor', 'medicine', 'order', 'appointment', 'prescription', 'user'] as $group): ?>
                        <option value="<?php echo e($group); ?>." <?php echo $filter === $group . '.' ? 'selected' : ''; ?>><?php echo e(ucfirst($group)); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows->num_rows === 0): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--gray);">Nothing recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <tr>
                            <td><small><?php echo e(date('M j, Y g:i A', strtotime($row['created_at']))); ?></small></td>
                            <td><?php echo e($row['admin_name'] ?? 'Removed account'); ?></td>
                            <td><code><?php echo e($row['action']); ?></code></td>
                            <td><?php echo e($row['target_type']); ?><?php echo $row['target_id'] !== null ? ' #' . (int)$row['target_id'] : ''; ?></td>
                            <td><small><?php echo e($row['details']); ?></small></td>
                            <td><small><?php echo e($row['ip']); ?></small></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pages">
                <?php
                $query = function (int $p) use ($filter) {
                    return '?' . http_build_query(array_filter(['page' => $p, 'action' => $filter]));
                };
                ?>
                <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="<?php echo e($query($page - 1)); ?>">Newer</a><?php endif; ?>
                <span>Page <?php echo $page; ?> of <?php echo $pages; ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="<?php echo e($query($page + 1)); ?>">Older</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
