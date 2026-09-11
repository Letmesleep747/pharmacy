<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

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
    redirect('index.php');
}

$isOwner = (int) ($order['user_id'] ?? 0) === (int) current_user()['id'];
if (!$isOwner) {
    flash_set('error', 'You cannot view that order.');
    redirect('index.php');
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
$title = 'Order #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <p>
      <?php if ($status === 'pending_approval'): ?>
        <span class="badge badge-warn"><?= e(order_status_label($status)) ?></span>
      <?php elseif ($status === 'approved'): ?>
        <span class="badge badge-ok"><?= e(order_status_label($status)) ?></span>
      <?php elseif ($status === 'rejected'): ?>
        <span class="badge badge-danger"><?= e(order_status_label($status)) ?></span>
      <?php else: ?>
        <span class="badge badge-ok"><?= e(order_status_label($status)) ?></span>
      <?php endif; ?>
    </p>
    <p>Pickup at <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
    <p class="muted"><?= e($order['source']) ?> · <?= e($order['created_at']) ?> · Total <?= e(money((float) $order['total'])) ?></p>
    <?php if ($status === 'rejected' && !empty($order['review_note'])): ?>
      <p class="flash flash-error">Reason: <?= e($order['review_note']) ?></p>
    <?php endif; ?>
    <?php if ($status === 'pending_approval'): ?>
      <p class="muted">Your prescription is with the pharmacy team. You will be able to collect after approval.</p>
    <?php endif; ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Line</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <?= e($item['name']) ?>
                <?php if ((int) $item['requires_prescription'] === 1): ?>
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
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
