<?php
/**
 * Shared order detail / collect — used by staff/order.php and admin/order.php.
 */
$db = db();
$id = get_int('id');
$error = '';

$order = $id ? db_one(
    $db,
    'SELECT o.*, b.name AS branch_name, b.address, u.name AS customer_name
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?',
    'i',
    [$id]
) : null;

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect(ops_path('pickups.php'));
}

require_ops_order_access($order, 'pickups.php');

if (is_post() && ($_POST['action'] ?? '') === 'collect') {
    try {
        complete_pickup_order($db, $id);
        flash_set('success', 'Order #' . $id . ' collected. Reserved stock deducted.');
        redirect(ops_path('pickups.php'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = db_all(
    $db,
    'SELECT oi.*, p.name, p.requires_prescription
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?',
    'i',
    [$id]
);

$status = (string) ($order['status'] ?? 'completed');
$canCollect = in_array($status, ['ready_for_pickup', 'approved'], true);

$title = 'Sale #' . $id;
require dirname(__DIR__) . '/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/' . ops_nav_file() . '.php'; ?>
  <div>
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <p><span class="badge"><?= e(order_status_label($status)) ?></span></p>
    <p>Branch <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
    <p class="muted">
      <?= e($order['source']) ?> · <?= e($order['created_at']) ?> · Total <?= e(money((float) $order['total'])) ?>
      <?php if (!empty($order['customer_name'])): ?> · <?= e($order['customer_name']) ?><?php endif; ?>
    </p>
    <?php if (!empty($order['pickup_by'])): ?>
      <p class="muted">Pickup by <?= e($order['pickup_by']) ?></p>
    <?php endif; ?>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Line</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <?= e($item['name']) ?>
                <?php if ((int) ($item['requires_prescription'] ?? 0) === 1): ?>
                  <span class="badge badge-warn">Rx</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $item['quantity'] ?></td>
              <td><?= e(money((float) $item['unit_price'])) ?></td>
              <td><?= e(money((float) $item['discount_amount'])) ?></td>
              <td><?= e(money((float) $item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p>
      <?php if ($canCollect): ?>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="collect">
          <button class="btn" type="submit">Mark collected</button>
        </form>
      <?php endif; ?>
      <?php if (current_portal() === 'staff'): ?>
        <a class="btn btn-secondary" href="<?= e(url('staff/pos.php')) ?>">New sale</a>
      <?php endif; ?>
      <a class="btn btn-secondary" href="<?= e(url(ops_path('pickups.php'))) ?>">Pickup queue</a>
    </p>
  </div>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
