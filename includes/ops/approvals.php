<?php
/**
 * Shared Rx approvals queue — used by staff/approvals.php and admin/approvals.php.
 */
$db = db();
$pending = db_all(
    $db,
    'SELECT o.id, o.created_at, o.total, o.prescription_path,
            b.name AS branch_name, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     INNER JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.status = ?
     ORDER BY o.created_at ASC',
    's',
    ['pending_approval']
);

$title = 'Prescription approvals';
require dirname(__DIR__) . '/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/' . ops_nav_file() . '.php'; ?>
  <div>
    <h1>Prescription approvals</h1>
    <p class="muted">Review uploaded prescriptions. Approving keeps the reservation and marks the order ready for pickup. Rejecting releases reserved stock.</p>
    <?php if (!$pending): ?>
      <p class="empty">No orders waiting for approval.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Customer</th><th>Branch</th><th>Total</th><th>Submitted</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($pending as $row): ?>
              <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td><?= e($row['customer_name'] ?: 'Customer') ?><div class="muted"><?= e((string) $row['customer_email']) ?></div></td>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e(money((float) $row['total'])) ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td><a class="btn btn-small" href="<?= e(url(ops_path('approval.php?id=' . (int) $row['id']))) ?>">Review</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
