<?php
require dirname(__DIR__) . '/includes/init.php';
require_admin_portal();

$db = db();
$promos = db_all(
    $db,
    'SELECT p.*,
            GROUP_CONCAT(pr.name ORDER BY pr.name SEPARATOR ", ") AS product_names
     FROM promotions p
     LEFT JOIN promotion_products pp ON pp.promotion_id = p.id
     LEFT JOIN products pr ON pr.id = pp.product_id
     GROUP BY p.id
     ORDER BY p.start_date DESC'
);

$title = 'Promotions';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
  <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>
  <div>
    <div class="section-head">
      <h1>Promotions</h1>
      <a class="btn" href="<?= e(url('admin/promotion-form.php')) ?>">New promotion</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Discount</th>
            <th>Period</th>
            <th>Status</th>
            <th>Products</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($promos as $promo):
              $current = promotion_is_current($promo);
              ?>
            <tr>
              <td><?= e($promo['name']) ?></td>
              <td>
                <?= $promo['discount_type'] === 'percent'
                    ? e((float) $promo['discount_value'] . '%')
                    : e(money((float) $promo['discount_value'])) ?>
              </td>
              <td><?= e($promo['start_date']) ?> → <?= e($promo['end_date']) ?></td>
              <td>
                <?php if ($current): ?>
                  <span class="badge badge-ok">Active now</span>
                <?php elseif ((int) $promo['is_active'] !== 1): ?>
                  <span class="badge">Inactive</span>
                <?php else: ?>
                  <span class="badge badge-warn">Scheduled / expired</span>
                <?php endif; ?>
              </td>
              <td><?= e($promo['product_names'] ?: '—') ?></td>
              <td><a href="<?= e(url('admin/promotion-form.php?id=' . (int) $promo['id'])) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
