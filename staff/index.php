<?php
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$low = low_stock_rows($db);
[$from, $to] = report_dates();
$summary = sales_summary($db, $from, $to);
$pendingCount = (int) (db_one($db, 'SELECT COUNT(*) AS n FROM orders WHERE status = ?', 's', ['pending_approval'])['n'] ?? 0);

$title = 'Staff counter';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>Staff counter</h1>
    <p class="muted">Record walk-in sales, review prescriptions, and update branch quantities.</p>
    <div class="cards">
      <div class="card stat"><span class="muted">Sales (last 60 days)</span><strong><?= e(money((float) $summary['revenue'])) ?></strong></div>
      <div class="card stat"><span class="muted">Transactions</span><strong><?= (int) $summary['transactions'] ?></strong></div>
      <div class="card stat"><span class="muted">Pending Rx orders</span><strong><?= $pendingCount ?></strong></div>
      <div class="card stat"><span class="muted">Low-stock lines</span><strong><?= count($low) ?></strong></div>
    </div>
    <p style="margin-top:20px">
      <a class="btn" href="<?= e(url('staff/pos.php')) ?>">Start in-store sale</a>
      <a class="btn btn-secondary" href="<?= e(url('staff/approvals.php')) ?>">Rx approvals<?= $pendingCount ? ' (' . $pendingCount . ')' : '' ?></a>
      <a class="btn btn-secondary" href="<?= e(url('staff/stock.php')) ?>">Update stock</a>
    </p>
    <h2>Low stock</h2>
    <?php if (!$low): ?>
      <p class="empty">No lines at or below reorder level.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>Product</th><th>Qty</th><th>Reorder</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($low, 0, 12) as $row): ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= (int) $row['reorder_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
