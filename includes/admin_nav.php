<nav class="admin-side" aria-label="Administrator">
  <a class="<?= nav_active('admin/index') ?>" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
  <a class="<?= nav_active('admin/products') ?>" href="<?= e(url('admin/products.php')) ?>">Products</a>
  <a class="<?= nav_active('admin/product-form') ?>" href="<?= e(url('admin/product-form.php')) ?>">Add product</a>
  <a class="<?= nav_active('admin/branches') ?>" href="<?= e(url('admin/branches.php')) ?>">Branches</a>
  <a class="<?= nav_active('admin/staff') ?>" href="<?= e(url('admin/staff.php')) ?>">Staff accounts</a>
  <a class="<?= nav_active('admin/stock') ?>" href="<?= e(url('admin/stock.php')) ?>">Branch stock</a>
  <a class="<?= nav_active('admin/promotions') ?>" href="<?= e(url('admin/promotions.php')) ?>">Promotions</a>
  <a class="<?= nav_active('admin/approvals') ?>" href="<?= e(url('admin/approvals.php')) ?>">Rx approvals</a>
  <a class="<?= nav_active('admin/pickups') ?>" href="<?= e(url('admin/pickups.php')) ?>">Pickups</a>
  <a class="<?= nav_active('admin/reports') ?>" href="<?= e(url('admin/reports.php')) ?>">Reports</a>
</nav>
