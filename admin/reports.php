<?php
/**
 * Admin report centre — HTML tables + CSV download (?format=csv).
 * Report types: sales, bestsellers, inventory, lowstock, branch.
 */
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
[$from, $to] = report_dates();
$branchId = get_int('branch');
$type = (string) ($_GET['type'] ?? 'sales');
$allowed = ['sales', 'bestsellers', 'inventory', 'lowstock', 'branch'];
if (!in_array($type, $allowed, true)) {
    $type = 'sales';
}

$branches = all_branches($db, false);
$format = (string) ($_GET['format'] ?? 'html');

if ($format === 'csv') {
    if ($type === 'sales') {
        $rows = [];
        foreach (sales_rows($db, $from, $to, $branchId) as $row) {
            $rows[] = [$row['id'], $row['created_at'], $row['source'], $row['status'] ?? '', $row['branch_name'], $row['customer_name'] ?: 'Walk-in', $row['discount_total'], $row['total']];
        }
        csv_download('sales-report.csv', ['Order', 'Created', 'Source', 'Status', 'Branch', 'Customer', 'Discount', 'Total'], $rows);
    }
    if ($type === 'bestsellers') {
        $rows = [];
        foreach (best_sellers($db, $from, $to, $branchId, 50) as $row) {
            $rows[] = [$row['sku'], $row['name'], $row['qty_sold'], $row['revenue']];
        }
        csv_download('bestsellers-report.csv', ['SKU', 'Product', 'Qty sold', 'Revenue'], $rows);
    }
    if ($type === 'inventory') {
        $rows = [];
        foreach (inventory_rows($db, $branchId) as $row) {
            $rows[] = [$row['branch_name'], $row['sku'], $row['product_name'], $row['quantity'], $row['reorder_level']];
        }
        csv_download('inventory-report.csv', ['Branch', 'SKU', 'Product', 'Quantity', 'Reorder level'], $rows);
    }
    if ($type === 'lowstock') {
        $rows = [];
        foreach (low_stock_rows($db, $branchId) as $row) {
            $rows[] = [$row['branch_name'], $row['sku'], $row['product_name'], $row['quantity'], $row['reorder_level']];
        }
        csv_download('low-stock-report.csv', ['Branch', 'SKU', 'Product', 'Quantity', 'Reorder level'], $rows);
    }
    if ($type === 'branch') {
        $rows = [];
        foreach (sales_by_branch($db, $from, $to) as $row) {
            $rows[] = [$row['name'], $row['transactions'], $row['revenue']];
        }
        csv_download('branch-performance.csv', ['Branch', 'Transactions', 'Revenue'], $rows);
    }
}

$summary = sales_summary($db, $from, $to, $branchId);
$labels = [
    'sales' => 'Sales report',
    'bestsellers' => 'Best-selling medications',
    'inventory' => 'Inventory report',
    'lowstock' => 'Low-stock report',
    'branch' => 'Branch performance',
];

$title = 'Reports';
require dirname(__DIR__) . '/includes/header.php';
$query = http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'branch' => $branchId]);
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Reports</h1>
    <p class="muted">Generated from orders, order items and branch_stock. Use Print for PDF (browser print dialog).</p>
    <form class="filters" method="get">
      <select name="type">
        <?php foreach ($labels as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
      <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
      <select name="branch">
        <option value="0">All branches</option>
        <?php foreach ($branches as $branch): ?>
          <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Run</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/reports.php?' . $query . '&format=csv')) ?>">Download CSV</a>
      <button class="btn btn-secondary" type="button" onclick="window.print()">Print / PDF</button>
    </form>

    <p><strong><?= e($labels[$type]) ?></strong>
      <?php if ($type !== 'inventory' && $type !== 'lowstock'): ?>
        · Period <?= e($from) ?> to <?= e($to) ?> · Sales <?= e(money((float) $summary['revenue'])) ?> · <?= (int) $summary['transactions'] ?> transactions
      <?php endif; ?>
    </p>

    <?php if ($type === 'sales'):
        $rows = sales_rows($db, $from, $to, $branchId); ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Date</th><th>Source</th><th>Status</th><th>Branch</th><th>Customer</th><th>Discount</th><th>Total</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="8">No sales in this period.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td><?= e($row['source']) ?></td>
                <td><?= e((string) ($row['status'] ?? '')) ?></td>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['customer_name'] ?: 'Walk-in') ?></td>
                <td><?= e(money((float) $row['discount_total'])) ?></td>
                <td><?= e(money((float) $row['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($type === 'bestsellers'):
        $rows = best_sellers($db, $from, $to, $branchId, 50); ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>SKU</th><th>Product</th><th>Qty sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="4">No item sales in this period.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e($row['sku']) ?></td>
                <td><?= e($row['name']) ?></td>
                <td><?= (int) $row['qty_sold'] ?></td>
                <td><?= e(money((float) $row['revenue'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($type === 'inventory'):
        $rows = inventory_rows($db, $branchId); ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>SKU</th><th>Product</th><th>Qty</th><th>Reorder</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['sku']) ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= (int) $row['reorder_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($type === 'lowstock'):
        $rows = low_stock_rows($db, $branchId); ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>SKU</th><th>Product</th><th>Qty</th><th>Reorder</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?><tr><td colspan="5">No low-stock lines.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['sku']) ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= (int) $row['reorder_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else:
        $rows = sales_by_branch($db, $from, $to); ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>Transactions</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e($row['name']) ?></td>
                <td><?= (int) $row['transactions'] ?></td>
                <td><?= e(money((float) $row['revenue'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
