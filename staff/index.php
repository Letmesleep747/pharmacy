<?php
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$branchId = staff_branch_id();
$branchName = user_branch_name() ?: ('Branch #' . $branchId);
[$from, $to] = report_dates();
$summary = sales_summary($db, $from, $to, $branchId);
$byDay = sales_by_day($db, $from, $to, $branchId);
$low = low_stock_rows($db, $branchId);
$pendingCount = (int) (db_one(
    $db,
    'SELECT COUNT(*) AS n FROM orders WHERE status = ? AND branch_id = ?',
    'si',
    ['pending_approval', $branchId]
)['n'] ?? 0);
$pickupCount = (int) (db_one(
    $db,
    'SELECT COUNT(*) AS n FROM orders WHERE status IN (?, ?) AND branch_id = ?',
    'ssi',
    ['ready_for_pickup', 'approved', $branchId]
)['n'] ?? 0);

$title = 'Staff counter';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>Staff counter</h1>
    <p class="muted">Working at <strong><?= e($branchName) ?></strong>. Sales figures are for this branch only.</p>
    <?php if ($low): ?>
      <p class="flash flash-error">
        Low-stock alert: <?= count($low) ?> product line(s) at or below reorder level.
        <a href="<?= e(url('staff/stock.php?low=1')) ?>">Review low stock</a>
      </p>
    <?php endif; ?>

    <form class="filters" method="get">
      <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="<?= e(url('staff/index.php')) ?>">Reset</a>
    </form>

    <div class="cards">
      <div class="card stat"><span class="muted">Sales</span><strong><?= e(money((float) $summary['revenue'])) ?></strong></div>
      <div class="card stat"><span class="muted">Transactions</span><strong><?= (int) $summary['transactions'] ?></strong></div>
      <div class="card stat"><span class="muted">Pending Rx</span><strong><?= $pendingCount ?></strong></div>
      <div class="card stat"><span class="muted">Awaiting pickup</span><strong><?= $pickupCount ?></strong></div>
      <div class="card stat"><span class="muted">Low-stock lines</span><strong><?= count($low) ?></strong></div>
    </div>

    <div class="chart-box" style="margin-top:20px">
      <h2>Sales trend (<?= e($branchName) ?>)</h2>
      <canvas id="salesChart" height="120"></canvas>
    </div>

    <p style="margin-top:20px">
      <a class="btn" href="<?= e(url('staff/pos.php')) ?>">Start in-store sale</a>
      <a class="btn btn-secondary" href="<?= e(url('staff/orders.php')) ?>">Order history</a>
      <a class="btn btn-secondary" href="<?= e(url('staff/approvals.php')) ?>">Rx approvals<?= $pendingCount ? ' (' . $pendingCount . ')' : '' ?></a>
      <a class="btn btn-secondary" href="<?= e(url('staff/pickups.php')) ?>">Pickups<?= $pickupCount ? ' (' . $pickupCount . ')' : '' ?></a>
      <a class="btn btn-secondary" href="<?= e(url('staff/stock.php')) ?>">Update stock</a>
    </p>
    <h2>Low stock</h2>
    <?php if (!$low): ?>
      <p class="empty">No lines at or below reorder level.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>Product</th><th>Available</th><th>Reorder</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($low, 0, 12) as $row): ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td><?= (int) ($row['available'] ?? $row['quantity']) ?></td>
                <td><?= (int) $row['reorder_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const salesLabels = <?= json_encode(array_column($byDay, 'd'), JSON_UNESCAPED_UNICODE) ?>;
const salesValues = <?= json_encode(array_map('floatval', array_column($byDay, 'revenue'))) ?>;
if (window.Chart) {
  new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: { labels: salesLabels, datasets: [{ label: 'Revenue (RM)', data: salesValues, borderColor: '#0f6b5c', tension: 0.25, fill: false }] },
    options: { plugins: { legend: { display: false } } }
  });
}
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
