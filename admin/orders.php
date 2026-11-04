<?php
/**
 * Admin order history — all branches, with filters for tracking.
 */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
[$from, $to] = report_dates();
$branchId = get_int('branch');
$status = trim((string) ($_GET['status'] ?? 'all'));
$source = trim((string) ($_GET['source'] ?? 'all'));
$q = trim((string) ($_GET['q'] ?? ''));
$allowedStatus = ['all', 'pending_approval', 'ready_for_pickup', 'approved', 'completed', 'rejected', 'cancelled', 'expired'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}
if (!in_array($source, ['all', 'online', 'instore'], true)) {
    $source = 'all';
}

$branches = all_branches($db, false);
$orders = orders_history(
    $db,
    $from,
    $to,
    $branchId,
    0,
    $status === 'all' ? '' : $status,
    $source === 'all' ? '' : $source,
    200,
    $q
);

$title = 'Order history';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Order history</h1>
    <p class="muted">Track online and in-store orders across branches. For sales totals and charts, use the Dashboard or Reports.</p>

    <form class="filters" method="get">
      <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
      <label>Branch
        <select name="branch">
          <option value="0">All branches</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>>
              <?= e($branch['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <?php foreach ($allowedStatus as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>>
              <?= $opt === 'all' ? 'All statuses' : e(order_status_label($opt)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Source
        <select name="source">
          <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>All sources</option>
          <option value="online" <?= $source === 'online' ? 'selected' : '' ?>>Online</option>
          <option value="instore" <?= $source === 'instore' ? 'selected' : '' ?>>In-store</option>
        </select>
      </label>
      <label>Search
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order #, customer, branch">
      </label>
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/orders.php')) ?>">Reset</a>
    </form>

    <?php if (!$orders): ?>
      <p class="empty">No orders match these filters.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Branch</th>
              <th>Source</th>
              <th>Status</th>
              <th>Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $row): ?>
              <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td>
                  <?= e($row['customer_name'] ?: 'Walk-in') ?>
                  <?php if (!empty($row['customer_email'])): ?>
                    <div class="muted"><?= e((string) $row['customer_email']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['source']) ?></td>
                <td><span class="badge"><?= e(order_status_label((string) $row['status'])) ?></span></td>
                <td><?= e(money((float) $row['total'])) ?></td>
                <td><a href="<?= e(url('admin/order.php?id=' . (int) $row['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
