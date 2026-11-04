<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

$db = db();
$id = get_int('id');
$error = '';

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

if (is_post() && ($_POST['action'] ?? '') === 'cancel') {
    try {
        cancel_customer_order($db, $id, (int) current_user()['id']);
        flash_set('info', 'Order #' . $id . ' cancelled. Reserved stock has been released.');
        redirect('pages/order.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $order = db_one(
            $db,
            'SELECT o.*, b.name AS branch_name, b.address
             FROM orders o
             INNER JOIN branches b ON b.id = o.branch_id
             WHERE o.id = ?',
            'i',
            [$id]
        );
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
$canCancel = $order['source'] === 'online'
    && in_array($status, ['pending_approval', 'approved', 'ready_for_pickup'], true);
$messages = order_status_messages($order);

$title = 'Order #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <p>
      <?php if (in_array($status, ['pending_approval', 'expired', 'cancelled'], true)): ?>
        <span class="badge badge-warn"><?= e(order_status_label($status)) ?></span>
      <?php elseif ($status === 'rejected'): ?>
        <span class="badge badge-danger"><?= e(order_status_label($status)) ?></span>
      <?php else: ?>
        <span class="badge badge-ok"><?= e(order_status_label($status)) ?></span>
      <?php endif; ?>
      <?php if (!empty($order['payment_status'])): ?>
        <span class="badge">Payment: <?= e((string) $order['payment_status']) ?></span>
      <?php endif; ?>
    </p>
    <p>Pickup at <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
    <p class="muted"><?= e($order['source']) ?> · <?= e($order['created_at']) ?> · Total <?= e(money((float) $order['total'])) ?></p>
    <?php if (!empty($order['payment_method'])): ?>
      <p class="muted">Payment method: <?= e((string) $order['payment_method']) ?></p>
    <?php endif; ?>
    <?php if (!empty($order['pickup_by']) && in_array($status, ['pending_approval', 'ready_for_pickup', 'approved'], true)): ?>
      <p class="muted">Collect by <strong><?= e($order['pickup_by']) ?></strong> or the reservation expires and stock is released.</p>
    <?php endif; ?>

    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <?php foreach ($messages as $msg): ?>
      <?php
        $class = match ($msg['type']) {
            'error' => 'flash flash-error',
            'ok' => 'flash flash-success',
            default => 'flash flash-info',
        };
      ?>
      <p class="<?= e($class) ?>"><?= e($msg['text']) ?></p>
    <?php endforeach; ?>

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

    <p style="margin-top:16px">
      <a class="btn" href="<?= e(url('pages/receipt.php?id=' . $id)) ?>">View / print receipt</a>
      <a class="btn btn-secondary" href="<?= e(url('pages/orders.php')) ?>">Back to orders</a>
      <?php if ($canCancel): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this order and release reserved stock?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <button class="btn btn-danger" type="submit">Cancel order</button>
        </form>
      <?php endif; ?>
    </p>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
