<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$branchId = get_int('branch');

if (is_post()) {
    $quantities = $_POST['quantity'] ?? [];
    $reorders = $_POST['reorder'] ?? [];
    if (is_array($quantities) && is_array($reorders)) {
        foreach ($quantities as $key => $qty) {
            $key = (string) $key;
            if (!str_contains($key, '_')) {
                continue;
            }
            [$b, $p] = array_map('intval', explode('_', $key, 2));
            if ($b <= 0 || $p <= 0) {
                continue;
            }
            $q = max(0, (int) $qty);
            $r = max(0, (int) ($reorders[$key] ?? 0));
            db_exec(
                $db,
                'INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_level = VALUES(reorder_level)',
                'iiii',
                [$b, $p, $q, $r]
            )->close();
        }
        flash_set('success', 'Stock levels saved.');
        redirect('admin/stock.php' . ($branchId ? ('?branch=' . $branchId) : ''));
    }
}

$branches = all_branches($db, false);
$rows = inventory_rows($db, $branchId);

$title = 'Branch stock';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <h1>Branch stock</h1>
    <p class="muted">Medication quantity is stored per branch. Low stock is quantity at or below the reorder level.</p>
    <form class="filters" method="get">
      <select name="branch">
        <option value="0">All branches</option>
        <?php foreach ($branches as $branch): ?>
          <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <form method="post">
      <?= csrf_field() ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Branch</th><th>Product</th><th>SKU</th><th>Quantity</th><th>Reorder level</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row):
                $key = (int) $row['branch_id'] . '_' . (int) $row['product_id'];
                $low = (int) $row['quantity'] <= (int) $row['reorder_level'];
                ?>
              <tr>
                <td><?= e($row['branch_name']) ?></td>
                <td><?= e($row['product_name']) ?><?php if ($low): ?> <span class="badge badge-warn">Low</span><?php endif; ?></td>
                <td><?= e($row['sku']) ?></td>
                <td><input type="number" min="0" name="quantity[<?= e($key) ?>]" value="<?= (int) $row['quantity'] ?>" style="width:6rem"></td>
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
