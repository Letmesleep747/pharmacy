<?php
/**
 * Shared review of one pending Rx order — used by staff and admin portals.
 */
$db = db();
$id = get_int('id');
$error = '';

$order = $id ? db_one(
    $db,
    'SELECT o.*, b.name AS branch_name, b.address, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?',
    'i',
    [$id]
) : null;

if (!$order || $order['status'] !== 'pending_approval') {
    flash_set('error', 'That order is not waiting for approval.');
    redirect(ops_path('approvals.php'));
}

require_ops_order_access($order, 'approvals.php');

$items = db_all(
    $db,
    'SELECT oi.*, p.name, p.requires_prescription,
            COALESCE(s.quantity, 0) AS stock_qty,
            COALESCE(s.reserved_qty, 0) AS reserved_qty,
            GREATEST(0, CAST(COALESCE(s.quantity, 0) AS SIGNED) - CAST(COALESCE(s.reserved_qty, 0) AS SIGNED)) AS available_qty
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN branch_stock s ON s.product_id = p.id AND s.branch_id = ?
     WHERE oi.order_id = ?',
    'ii',
    [(int) $order['branch_id'], $id]
);

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    $reviewerId = (int) current_user()['id'];
    try {
        if ($action === 'approve') {
            approve_prescription_order($db, $id, $reviewerId, $note);
            flash_set('success', 'Order #' . $id . ' approved. Reservation kept — customer can collect before the pickup deadline.');
            redirect(ops_path('approvals.php'));
        }
        if ($action === 'reject') {
            reject_prescription_order($db, $id, $reviewerId, $note);
            flash_set('info', 'Order #' . $id . ' rejected. Reserved stock released.');
            redirect(ops_path('approvals.php'));
        }
        $error = 'Unknown action.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$title = 'Review order #' . $id;
require dirname(__DIR__) . '/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/' . ops_nav_file() . '.php'; ?>
  <div>
    <h1>Review order #<?= (int) $order['id'] ?></h1>
    <p class="muted"><?= e($order['customer_name'] ?: 'Customer') ?> · <?= e((string) $order['customer_email']) ?></p>
    <p>Pickup: <strong><?= e($order['branch_name']) ?></strong> — <?= e($order['address']) ?></p>
    <p class="muted">Submitted <?= e($order['created_at']) ?> · Total <?= e(money((float) $order['total'])) ?>
      <?php if (!empty($order['pickup_by'])): ?> · Pickup by <?= e($order['pickup_by']) ?><?php endif; ?>
    </p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>

    <?php if (!empty($order['prescription_path'])): ?>
      <p><a class="btn btn-secondary" href="<?= e(url(ops_path('prescription.php?id=' . $id))) ?>" target="_blank" rel="noopener">View prescription file</a></p>
    <?php else: ?>
      <p class="flash flash-error">No prescription file was attached.</p>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Available</th><th>Reserved</th><th>Line</th></tr></thead>
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
              <td><?= (int) $item['available_qty'] ?></td>
              <td><?= (int) $item['reserved_qty'] ?></td>
              <td><?= e(money((float) $item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form class="form" method="post" style="margin-top:16px">
      <?= csrf_field() ?>
      <label>Note (required to reject)
        <input type="text" name="review_note" maxlength="255" placeholder="e.g. Prescription unclear / expired">
      </label>
      <div class="filters">
        <button class="btn" type="submit" name="action" value="approve">Approve (keep reservation)</button>
        <button class="btn btn-danger" type="submit" name="action" value="reject">Reject</button>
        <a class="btn btn-secondary" href="<?= e(url(ops_path('approvals.php'))) ?>">Back</a>
      </div>
    </form>
  </div>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
