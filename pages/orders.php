<?php
/**
 * Customer order history — track online pickup orders.
 */
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

if (user_role() !== 'customer') {
    flash_set('error', 'Only customers can view this order history.');
    redirect('index.php');
}

$db = db();
$userId = (int) current_user()['id'];
[$from, $to] = report_dates();
$status = trim((string) ($_GET['status'] ?? 'all'));
$allowedStatus = ['all', 'pending_approval', 'ready_for_pickup', 'approved', 'completed', 'rejected', 'expired'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

$orders = orders_history($db, $from, $to, 0, $userId, $status === 'all' ? '' : $status);

$title = 'Order history';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Order history</h1>
    <p class="muted">Track your online pickup orders, prescription reviews, and collection status.</p>

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
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="<?= e(url('pages/orders.php')) ?>">Reset</a>
    </form>

    <?php if (!$orders): ?>
      <p class="empty">No orders found for this period.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Branch</th>
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
                <td><?= e($row['branch_name']) ?></td>
                <td><span class="badge"><?= e(order_status_label((string) $row['status'])) ?></span></td>
                <td><?= e(money((float) $row['total'])) ?></td>
                <td><a class="btn btn-small" href="<?= e(url('pages/order.php?id=' . (int) $row['id'])) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
