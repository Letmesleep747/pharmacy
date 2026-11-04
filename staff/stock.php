<?php
require dirname(__DIR__) . '/includes/init.php';
require_staff_portal();

$db = db();
$branchId = staff_branch_id();
$branchName = user_branch_name() ?: ('Branch #' . $branchId);
$q = trim((string) ($_GET['q'] ?? ''));
$lowOnly = (string) ($_GET['low'] ?? '') === '1';

if (is_post()) {
    $quantities = $_POST['quantity'] ?? [];
    $reorders = $_POST['reorder'] ?? [];
    if (is_array($quantities) && is_array($reorders)) {
        $changed = 0;
        foreach ($quantities as $key => $qty) {
            $key = (string) $key;
            if (!str_contains($key, '_')) {
                continue;
            }
            [$b, $p] = array_map('intval', explode('_', $key, 2));
            // Staff may only update their assigned branch.
            if ($b !== $branchId || $p <= 0) {
                continue;
            }
            $qtyVal = max(0, (int) $qty);
            $r = max(0, (int) ($reorders[$key] ?? 0));
            db_exec(
                $db,
                'INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_level = VALUES(reorder_level)',
                'iiii',
                [$b, $p, $qtyVal, $r]
            )->close();
            $changed++;
        }
        log_activity($db, 'stock_updated', 'Updated stock at ' . $branchName, [
            'branch_id' => $branchId,
            'details' => ['rows' => $changed],
        ]);
        flash_set('success', 'Stock levels saved for ' . $branchName . '.');
        redirect('staff/stock.php' . ($lowOnly ? '?low=1' : '') . ($q !== '' ? ($lowOnly ? '&' : '?') . 'q=' . rawurlencode($q) : ''));
    }
}

$rows = inventory_rows($db, $branchId, $q, $lowOnly);

$title = 'Branch stock';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?>
  <div>
    <h1>Branch stock</h1>
    <p class="muted">Update on-hand quantities for <strong><?= e($branchName) ?></strong>. Available = quantity − reserved.</p>
    <form class="filters" method="get">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search product or SKU" aria-label="Search">
      <label class="check"><input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>> Low stock only</label>
      <button class="btn btn-secondary" type="submit">Filter</button>
      <a class="btn btn-secondary" href="<?= e(url('staff/stock.php')) ?>">Reset</a>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Product</th><th>SKU</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Reorder level</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6">No stock rows match this filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row):
                $key = (int) $row['branch_id'] . '_' . (int) $row['product_id'];
                $available = (int) ($row['available'] ?? max(0, (int) $row['quantity'] - (int) ($row['reserved_qty'] ?? 0)));
                $low = $available <= (int) $row['reorder_level'];
                ?>
              <tr>
                <td><?= e($row['product_name']) ?><?php if ($low): ?> <span class="badge badge-warn">Low</span><?php endif; ?></td>
                <td><?= e($row['sku']) ?></td>
                <td><input type="number" min="0" name="quantity[<?= e($key) ?>]" value="<?= (int) $row['quantity'] ?>" style="width:6rem"></td>
                <td><?= (int) ($row['reserved_qty'] ?? 0) ?></td>
                <td><?= $available ?></td>
                <td><input type="number" min="0" name="reorder[<?= e($key) ?>]" value="<?= (int) $row['reorder_level'] ?>" style="width:6rem"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p><button class="btn" type="submit">Save stock</button></p>
    </form>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
