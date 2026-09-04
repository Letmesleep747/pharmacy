<?php
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$id = get_int('id');
$order = $id ? db_one(
    $db,
    'SELECT o.*, b.name AS branch_name, b.address
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     WHERE o.id = ?',
    'i',
    [$id]
) : null;

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('staff/index.php');
}

$items = db_all(
    $db,
    'SELECT oi.*, p.name
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?',
    'i',
    [$id]
);

$title = 'Sale #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>Sale #<?= (int) $order['id'] ?></h1>
    <p>Branch <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
    <p class="muted"><?= e($order['source']) ?> · <?= e($order['created_at']) ?> · Total <?= e(money((float) $order['total'])) ?></p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Line</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['name']) ?></td>
              <td><?= (int) $item['quantity'] ?></td>
              <td><?= e(money((float) $item['unit_price'])) ?></td>
              <td><?= e(money((float) $item['discount_amount'])) ?></td>
              <td><?= e(money((float) $item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p><a class="btn" href="<?= e(url('staff/pos.php')) ?>">New sale</a></p>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
