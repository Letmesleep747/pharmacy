<?php
/**
 * Shared printable receipt — included by pages/receipt.php, staff/receipt.php, admin/receipt.php.
 */
$db = db();
$id = get_int('id');

$order = $id ? db_one(
    $db,
    'SELECT o.*, b.name AS branch_name, b.address, b.phone, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?',
    'i',
    [$id]
) : null;

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect(current_portal() === 'customer' ? 'pages/orders.php' : ops_path('orders.php'));
}

$portal = current_portal();
if ($portal === 'customer') {
    require_login();
    if ((int) ($order['user_id'] ?? 0) !== (int) current_user()['id']) {
        flash_set('error', 'You cannot view that receipt.');
        redirect('pages/orders.php');
    }
} else {
    require_ops_order_access($order, 'orders.php');
}

$items = db_all(
    $db,
    'SELECT oi.*, p.name, p.sku, p.requires_prescription
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id',
    'i',
    [$id]
);

$status = (string) ($order['status'] ?? 'completed');
$backHref = match ($portal) {
    'admin' => url('admin/order.php?id=' . $id),
    'staff' => url('staff/order.php?id=' . $id),
    default => url('pages/order.php?id=' . $id),
};

$title = 'Receipt #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($portal === 'customer'): ?>
<section class="section">
  <div class="container receipt-page">
<?php else: ?>
<div class="container layout-admin receipt-page">
  <?php require dirname(__DIR__) . '/includes/' . ops_nav_file() . '.php'; ?>
  <div>
<?php endif; ?>
    <div class="no-print" style="margin-bottom:16px">
      <button class="btn" type="button" onclick="window.print()">Print receipt</button>
      <a class="btn btn-secondary" href="<?= e($backHref) ?>">Back to order</a>
    </div>

    <article class="card receipt-sheet">
      <header class="receipt-head">
        <div>
          <h1 style="margin:0"><?= e(APP_NAME) ?></h1>
          <p class="muted" style="margin:4px 0 0">Pickup receipt</p>
        </div>
        <div class="receipt-meta">
          <strong>Order #<?= (int) $order['id'] ?></strong><br>
          <?= e($order['created_at']) ?><br>
          <?= e(order_status_label($status)) ?>
        </div>
      </header>

      <div class="grid-2" style="margin:18px 0;gap:12px">
        <div>
          <h2 style="margin:0 0 6px;font-size:1rem">Pickup branch</h2>
          <p style="margin:0"><?= e($order['branch_name']) ?></p>
          <p class="muted" style="margin:4px 0 0"><?= e($order['address']) ?></p>
          <?php if (!empty($order['phone'])): ?>
            <p class="muted" style="margin:4px 0 0"><?= e($order['phone']) ?></p>
          <?php endif; ?>
        </div>
        <div>
          <h2 style="margin:0 0 6px;font-size:1rem">Customer</h2>
          <p style="margin:0"><?= e($order['customer_name'] ?: 'Walk-in') ?></p>
          <?php if (!empty($order['customer_email'])): ?>
            <p class="muted" style="margin:4px 0 0"><?= e((string) $order['customer_email']) ?></p>
          <?php endif; ?>
          <p class="muted" style="margin:4px 0 0">Source: <?= e((string) $order['source']) ?></p>
          <?php if (!empty($order['payment_method'])): ?>
            <p class="muted" style="margin:4px 0 0">Payment: <?= e((string) $order['payment_method']) ?></p>
          <?php endif; ?>
          <?php if (!empty($order['pickup_by'])): ?>
            <p class="muted" style="margin:4px 0 0">Pickup by: <?= e($order['pickup_by']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>SKU</th>
              <th>Qty</th>
              <th>Unit</th>
              <th>Discount</th>
              <th>Line</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <?= e($item['name']) ?>
                  <?php if ((int) ($item['requires_prescription'] ?? 0) === 1): ?>
                    <span class="badge badge-warn">Rx</span>
                  <?php endif; ?>
                </td>
                <td><?= e((string) $item['sku']) ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= e(money((float) $item['unit_price'])) ?></td>
                <td><?= e(money((float) $item['discount_amount'])) ?></td>
                <td><?= e(money((float) $item['line_total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p style="text-align:right;margin-top:14px">
        Subtotal <?= e(money((float) $order['subtotal'])) ?><br>
        Discount −<?= e(money((float) $order['discount_total'])) ?><br>
        <strong>Total <?= e(money((float) $order['total'])) ?></strong>
      </p>
      <p class="muted" style="margin-top:18px;font-size:13px">
        Demo receipt for assessment use. Show this order number at the counter when collecting.
      </p>
    </article>
<?php if ($portal === 'customer'): ?>
  </div>
</section>
<?php else: ?>
  </div>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
