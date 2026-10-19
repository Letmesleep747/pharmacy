<?php
$db = db();
$branchScope = ops_branch_scope();
$sql = "SELECT o.id, o.created_at, o.total, o.payment_method, o.payment_receipt_path, b.name AS branch_name, u.name AS customer_name, u.email AS customer_email FROM orders o INNER JOIN branches b ON b.id=o.branch_id LEFT JOIN users u ON u.id=o.user_id WHERE o.payment_status='pending_review'";
$types=''; $params=[];
if ($branchScope > 0) { $sql .= ' AND o.branch_id=?'; $types='i'; $params[]=$branchScope; }
$sql .= ' ORDER BY o.created_at ASC';
$pending = db_all($db, $sql, $types, $params);
$title='Payment receipt approvals'; require dirname(__DIR__).'/header.php';
?>
<div class="container layout-admin"><div><?php require dirname(__DIR__).'/'.ops_nav_file().'.php'; ?></div><div><h1>Payment receipt approvals</h1><p class="muted">Verify customer-uploaded payment receipts. Rejecting marks the demo refund as processed; this project does not move real money.</p>
<?php if (!$pending): ?><p class="empty">No payment receipts waiting for review.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Branch</th><th>Method</th><th>Total</th><th>Submitted</th><th></th></tr></thead><tbody><?php foreach($pending as $row): ?><tr><td>#<?= (int)$row['id'] ?></td><td><?= e($row['customer_name'] ?: 'Customer') ?><div class="muted"><?= e((string)$row['customer_email']) ?></div></td><td><?= e($row['branch_name']) ?></td><td><?= e((string)$row['payment_method']) ?></td><td><?= e(money((float)$row['total'])) ?></td><td><?= e($row['created_at']) ?></td><td><a class="btn btn-small" href="<?= e(url(ops_path('payment-review.php?id='.(int)$row['id']))) ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div><?php require dirname(__DIR__).'/footer.php'; ?>
