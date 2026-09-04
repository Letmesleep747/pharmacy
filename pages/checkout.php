<?php
require dirname(__DIR__) . '/includes/init.php';
require_storefront();
require_login();

if (user_role() !== 'customer') {
    flash_set('error', 'Only customer accounts can place online pickup orders.');
    redirect('index.php');
}

if (cart() === []) {
    flash_set('error', 'Your cart is empty.');
    redirect('pages/cart.php');
}

$db = db();
$branches = all_branches($db);
$error = '';

if (is_post()) {
    $branchId = post_int('branch_id');
    $branch = db_one($db, 'SELECT id FROM branches WHERE id = ? AND is_active = 1', 'i', [$branchId]);
    if (!$branch) {
        $error = 'Please choose a pickup branch.';
    } else {
        try {
            $orderId = complete_sale($db, cart(), $branchId, 'online', (int) current_user()['id']);
            cart_clear();
            flash_set('success', 'Order #' . $orderId . ' placed. Collect it from your chosen branch.');
            redirect('pages/order.php?id=' . $orderId);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$title = 'Checkout';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Pickup checkout</h1>
    <p class="muted">Choose the branch you will collect from. Stock is reserved only at that branch. No online card payment — pay in store on collection.</p>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <form class="form" method="post">
      <?= csrf_field() ?>
      <label>Pickup branch
        <select name="branch_id" required>
          <option value="">Select a branch</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int) $branch['id'] ?>"><?= e($branch['name']) ?> — <?= e($branch['address']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Place pickup order</button>
    </form>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
