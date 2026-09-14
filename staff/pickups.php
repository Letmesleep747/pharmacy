<?php
/**
 * Staff queue: online orders ready for collection.
 * Completing pickup converts reserved_qty into a quantity deduction.
 */
require dirname(__DIR__) . '/includes/init.php';
require_pharmacy_ops();

$db = db();
$error = '';

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');
    $orderId = post_int('order_id');
    try {
        if ($action === 'collect' && $orderId > 0) {
            complete_pickup_order($db, $orderId);
            flash_set('success', 'Order #' . $orderId . ' marked collected. Reserved stock deducted from on-hand quantity.');
            redirect('staff/pickups.php');
        }
        $error = 'Unknown action.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ready = db_all(
    $db,
    'SELECT o.id, o.created_at, o.pickup_by, o.total, o.status,
            b.name AS branch_name, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.status IN (?, ?)
     ORDER BY o.pickup_by ASC, o.created_at ASC',
    'ss',
    ['ready_for_pickup', 'approved']
);

$title = 'Pickup collection';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/' . (is_admin() ? 'admin_nav' : 'staff_nav') . '.php'; ?>
  <div>
    <h1>Pickup collection</h1>
    <p class="muted">Mark orders as collected when the customer arrives. This converts reserved stock into a permanent deduction.</p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$ready): ?>
      <p class="empty">No orders waiting for collection.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Customer</th><th>Branch</th><th>Pickup by</th><th>Total</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($ready as $row): ?>
              <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td><?= e($row['customer_name'] ?: 'Customer') ?><div class="muted"><?= e((string) $row['customer_email']) ?></div></td>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e((string) ($row['pickup_by'] ?? '—')) ?></td>
                <td><?= e(money((float) $row['total'])) ?></td>
                <td>
                  <a href="<?= e(url('staff/order.php?id=' . (int) $row['id'])) ?>">View</a>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="collect">
                    <input type="hidden" name="order_id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-small" type="submit">Mark collected</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
