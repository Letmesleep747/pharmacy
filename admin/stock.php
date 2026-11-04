<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$branchId = get_int('branch');
$q = trim((string) ($_GET['q'] ?? ''));
$lowOnly = (string) ($_GET['low'] ?? '') === '1';

if (is_post()) {
    $quantities = $_POST['quantity'] ?? [];
    $reorders = $_POST['reorder'] ?? [];
    if (is_array($quantities) && is_array($reorders)) {
        $changed = 0;
        $touchedBranches = [];
        foreach ($quantities as $key => $qty) {
            $key = (string) $key;
            if (!str_contains($key, '_')) {
                continue;
            }
            [$b, $p] = array_map('intval', explode('_', $key, 2));
            if ($b <= 0 || $p <= 0) {
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
            $touchedBranches[$b] = true;
        }
        log_activity($db, 'stock_updated', 'Updated branch stock levels', [
            'branch_id' => $branchId > 0 ? $branchId : null,
            'details' => [
                'rows' => $changed,
                'branches' => array_map('intval', array_keys($touchedBranches)),
            ],
        ]);
        $redirect = 'admin/stock.php';
        $qs = [];
        if ($branchId > 0) {
            $qs[] = 'branch=' . $branchId;
        }
        if ($lowOnly) {
            $qs[] = 'low=1';
        }
        if ($q !== '') {
            $qs[] = 'q=' . rawurlencode($q);
        }
        flash_set('success', 'Stock levels saved.');
        redirect($redirect . ($qs ? ('?' . implode('&', $qs)) : ''));
    }
}

$branches = all_branches($db, false);
$rows = inventory_rows($db, $branchId, $q, $lowOnly);

$title = 'Branch stock';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Branch stock</h1>
    <p class="muted">On-hand quantity is stored per branch. Available = quantity − reserved (held for pickup orders). Low stock uses available stock.</p>
    <form class="filters" method="get">
      <select name="branch" aria-label="Branch">
        <option value="0">All branches</option>
        <?php foreach ($branches as $branch): ?>
          <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search product, SKU, or branch" aria-label="Search">
      <label class="check"><input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>> Low stock only</label>
      <button class="btn btn-secondary" type="submit">Filter</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/stock.php')) ?>">Reset</a>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>Product</th><th>SKU</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Reorder level</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7">No stock rows match this filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row):
                $key = (int) $row['branch_id'] . '_' . (int) $row['product_id'];
                $available = (int) ($row['available'] ?? max(0, (int) $row['quantity'] - (int) ($row['reserved_qty'] ?? 0)));
                $low = $available <= (int) $row['reorder_level'];
                ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
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
