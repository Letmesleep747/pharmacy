<?php
/**
 * Staff order history — home branch only.
 */
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$branchId = staff_branch_id();
$branchName = user_branch_name() ?: ('Branch #' . $branchId);
[$from, $to] = report_dates();
$status = trim((string) ($_GET['status'] ?? 'all'));
$source = trim((string) ($_GET['source'] ?? 'all'));
$allowedStatus = ['all', 'pending_approval', 'ready_for_pickup', 'approved', 'completed', 'rejected', 'expired'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}
if (!in_array($source, ['all', 'online', 'instore'], true)) {
    $source = 'all';
}

$orders = orders_history(
    $db,
    $from,
    $to,
    $branchId,
    0,
    $status === 'all' ? '' : $status,
    $source === 'all' ? '' : $source
);

$title = 'Order history';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>Order history</h1>
    <p class="muted">Tracking for <strong><?= e($branchName) ?></strong> only — online pickups and in-store sales. Sales totals stay on the counter dashboard.</p>

    <form class="filters" method="get">
      <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
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
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="<?= e(url('staff/orders.php')) ?>">Reset</a>
    </form>

    <?php if (!$orders): ?>
      <p class="empty">No orders match these filters for your branch.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Customer</th>
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
                <td><?= e($row['source']) ?></td>
                <td><span class="badge"><?= e(order_status_label((string) $row['status'])) ?></span></td>
                <td><?= e(money((float) $row['total'])) ?></td>
                <td><a href="<?= e(url('staff/order.php?id=' . (int) $row['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
