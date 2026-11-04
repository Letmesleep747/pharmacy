<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
if (is_post() && ($_POST['action'] ?? '') === 'toggle') {
    $id = post_int('id');
    $before = db_one($db, 'SELECT name, is_active FROM products WHERE id = ?', 'i', [$id]);
    db_exec($db, 'UPDATE products SET is_active = 1 - is_active WHERE id = ?', 'i', [$id])->close();
    if ($before) {
        $nowActive = (int) $before['is_active'] === 1 ? 0 : 1;
        log_activity($db, 'product_toggled', ($nowActive ? 'Showed' : 'Hid') . ' product ' . $before['name'], [
            'entity_type' => 'product',
            'entity_id' => $id,
            'details' => ['is_active' => $nowActive],
        ]);
    }
    flash_set('success', 'Product status updated.');
    redirect('admin/products.php');
}

$products = all_products($db);
$title = 'Products';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <div class="section-head">
      <h1>Products</h1>
      <a class="btn" href="<?= e(url('admin/product-form.php')) ?>">Add product</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Type</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <tr>
              <td><?= e($product['name']) ?></td>
              <td><?= e($product['sku']) ?></td>
              <td><?= e($product['category_name']) ?></td>
              <td><?= e(money((float) $product['unit_price'])) ?></td>
              <td><?= (int) ($product['requires_prescription'] ?? 0) ? 'Rx' : 'OTC' ?></td>
              <td><?= (int) $product['is_active'] ? 'Active' : 'Hidden' ?></td>
              <td>
                <a href="<?= e(url('admin/product-form.php?id=' . (int) $product['id'])) ?>">Edit</a>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                  <button class="btn btn-small btn-secondary" type="submit">Toggle</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
