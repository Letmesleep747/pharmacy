<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();

$branches = all_branches(db());
$title = 'Branches';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Pharmacy branches</h1>
    <div class="grid">
      <?php foreach ($branches as $branch): ?>
        <article class="card">
          <h3><?= e($branch['name']) ?></h3>
          <p><?= e($branch['address']) ?></p>
          <p class="muted"><?= e($branch['phone']) ?></p>
          <p class="muted"><?= e($branch['opening_hours']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
