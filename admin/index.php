<?php
/**
 * Admin analytics dashboard.
 * Charts and cards are filled from real orders / stock queries (see includes/reports.php).
 */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
[$from, $to] = report_dates();
$branchId = get_int('branch');
$branches = all_branches($db, false);

$summary = sales_summary($db, $from, $to, $branchId);
$byDay = sales_by_day($db, $from, $to, $branchId);
$byBranch = $branchId > 0
    ? sales_by_branch($db, $from, $to) // still show comparison; highlight filter in copy
    : sales_by_branch($db, $from, $to);
$best = best_sellers($db, $from, $to, $branchId, 8);
$low = low_stock_rows($db, $branchId);

$branchLabel = 'All branches';
if ($branchId > 0) {
    foreach ($branches as $b) {
        if ((int) $b['id'] === $branchId) {
            $branchLabel = (string) $b['name'];
            break;
        }
    }
}

$title = 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Analytics dashboard</h1>
    <p class="muted">Sales include completed, ready-for-pickup, approved, and pending-approval orders (not rejected/expired). Filter by branch to compare counters.</p>
    <form class="filters" method="get">
      <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
      <label>Branch
        <select name="branch">
          <option value="0">All branches</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>>
              <?= e($branch['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/index.php')) ?>">Reset</a>
    </form>
    <p class="muted" style="margin-top:0">Showing: <strong><?= e($branchLabel) ?></strong> · <?= e($from) ?> → <?= e($to) ?></p>
    <div class="cards">
      <div class="card stat"><span class="muted">Total sales</span><strong><?= e(money((float) $summary['revenue'])) ?></strong></div>
      <div class="card stat"><span class="muted">Transactions</span><strong><?= (int) $summary['transactions'] ?></strong></div>
      <div class="card stat"><span class="muted">Discounts given</span><strong><?= e(money((float) $summary['discounts'])) ?></strong></div>
      <div class="card stat"><span class="muted">Low-stock lines</span><strong><?= count($low) ?></strong></div>
    </div>
    <div class="grid-2" style="margin-top:20px">
      <div class="chart-box">
        <h2>Sales trend<?= $branchId > 0 ? ' (' . e($branchLabel) . ')' : '' ?></h2>
        <canvas id="salesChart" height="160"></canvas>
      </div>
      <div class="chart-box">
        <h2>Branch performance</h2>
        <canvas id="branchChart" height="160"></canvas>
      </div>
    </div>
    <div class="grid-2" style="margin-top:20px">
      <div>
        <h2>Best-selling medications</h2>
        <?php if (!$best): ?>
          <p class="empty">No sales in this period.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($best as $row): ?>
                  <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= (int) $row['qty_sold'] ?></td>
                    <td><?= e(money((float) $row['revenue'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <h2>Low-stock medications</h2>
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
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const salesLabels = <?= json_encode(array_column($byDay, 'd'), JSON_UNESCAPED_UNICODE) ?>;
const salesValues = <?= json_encode(array_map('floatval', array_column($byDay, 'revenue'))) ?>;
const branchLabels = <?= json_encode(array_column($byBranch, 'name'), JSON_UNESCAPED_UNICODE) ?>;
const branchValues = <?= json_encode(array_map('floatval', array_column($byBranch, 'revenue'))) ?>;
if (window.Chart) {
  new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: { labels: salesLabels, datasets: [{ label: 'Revenue (RM)', data: salesValues, borderColor: '#0f6b5c', tension: 0.25, fill: false }] },
    options: { plugins: { legend: { display: false } } }
  });
  new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: { labels: branchLabels, datasets: [{ label: 'Revenue (RM)', data: branchValues, backgroundColor: '#1a8f7a' }] },
    options: { plugins: { legend: { display: false } } }
  });
}
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
