<?php
/**
 * Administrator audit trail — who did what, and when.
 */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
ensure_activity_logs_table($db);

$actionLabels = activity_action_labels();
$actorRole = trim((string) ($_GET['role'] ?? 'all'));
$action = trim((string) ($_GET['action'] ?? 'all'));
$q = trim((string) ($_GET['q'] ?? ''));
$branchId = get_int('branch');

if (!in_array($actorRole, ['all', 'staff', 'admin'], true)) {
    $actorRole = 'all';
}
if ($action !== 'all' && !isset($actionLabels[$action])) {
    $action = 'all';
}

$sql = 'SELECT a.*, b.name AS branch_name
        FROM activity_logs a
        LEFT JOIN branches b ON b.id = a.branch_id
        WHERE 1 = 1';
$types = '';
$params = [];

if ($actorRole !== 'all') {
    $sql .= ' AND a.actor_role = ?';
    $types .= 's';
    $params[] = $actorRole;
}
if ($action !== 'all') {
    $sql .= ' AND a.action = ?';
    $types .= 's';
    $params[] = $action;
}
if ($branchId > 0) {
    $sql .= ' AND a.branch_id = ?';
    $types .= 'i';
    $params[] = $branchId;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= ' AND (a.summary LIKE ? OR a.actor_name LIKE ? OR a.details LIKE ? OR CAST(a.entity_id AS CHAR) LIKE ?)';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT 250';
$logs = db_all($db, $sql, $types, $params);
$branches = all_branches($db, false);

$title = 'Activity log';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Activity log</h1>
    <p class="muted">Track staff and admin actions (approvals, pickups, POS, stock, catalogue changes) so unexpected changes can be reviewed.</p>

    <form class="filters" method="get">
      <select name="role" aria-label="Role">
        <option value="all" <?= $actorRole === 'all' ? 'selected' : '' ?>>All roles</option>
        <option value="staff" <?= $actorRole === 'staff' ? 'selected' : '' ?>>Staff</option>
        <option value="admin" <?= $actorRole === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
      <select name="action" aria-label="Action">
        <option value="all" <?= $action === 'all' ? 'selected' : '' ?>>All actions</option>
        <?php foreach ($actionLabels as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="branch" aria-label="Branch">
        <option value="0">All branches</option>
        <?php foreach ($branches as $branch): ?>
          <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>>
            <?= e($branch['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, order #, summary…" style="min-width:14rem">
      <button class="btn btn-secondary" type="submit">Filter</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/activity.php')) ?>">Reset</a>
    </form>

    <?php if (!$logs): ?>
      <p class="empty">No activity matches these filters yet. Staff/admin actions will appear here as they happen.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Who</th>
              <th>Action</th>
              <th>Summary</th>
              <th>Branch</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $row):
                $label = $actionLabels[$row['action']] ?? $row['action'];
                $details = trim((string) ($row['details'] ?? ''));
                ?>
              <tr>
                <td><?= e($row['created_at']) ?></td>
                <td>
                  <?= e($row['actor_name']) ?>
                  <div class="muted"><?= e(ucfirst((string) $row['actor_role'])) ?></div>
                </td>
                <td><span class="badge"><?= e($label) ?></span></td>
                <td>
                  <?= e($row['summary']) ?>
                  <?php if ($details !== ''): ?>
                    <div class="muted" style="font-size:12px;word-break:break-word"><?= e($details) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($row['branch_name'] ?: '—') ?></td>
                <td class="muted"><?= e((string) ($row['ip_address'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="muted">Showing the latest <?= count($logs) ?> matching entries.</p>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
