<?php
/**
 * Customer checkout.
 * Online orders reserve available stock (quantity − reserved_qty) for 48 hours.
 * Rx carts also require a prescription upload and stay pending until staff approve.
 */
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
$needsRx = lines_require_prescription($db, cart());
$error = '';

if (is_post()) {
    $branchId = post_int('branch_id');
    $branch = db_one($db, 'SELECT id FROM branches WHERE id = ? AND is_active = 1', 'i', [$branchId]);
    if (!$branch) {
        $error = 'Please choose a pickup branch.';
    } else {
        try {
            $prescriptionPath = null;
            $status = 'ready_for_pickup';

            if ($needsRx) {
                $prescriptionPath = save_prescription_upload($_FILES['prescription'] ?? []);
                $status = 'pending_approval';
            }

            $orderId = complete_sale(
                $db,
                cart(),
                $branchId,
                'online',
                (int) current_user()['id'],
                false,
                $status,
                $prescriptionPath
            );
            cart_clear();
            if ($status === 'pending_approval') {
                flash_set('success', 'Order #' . $orderId . ' submitted. Stock is reserved while a pharmacist reviews your prescription.');
            } else {
                flash_set('success', 'Order #' . $orderId . ' placed. Stock is reserved for 48 hours — collect before the pickup deadline.');
            }
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
    <p class="muted">Choose the branch you will collect from. Available stock at that branch is reserved for 48 hours. Pay in store on collection.</p>
    <?php if ($needsRx): ?>
      <p class="flash flash-info">Your cart includes prescription medication. Upload a doctor’s prescription (PDF/JPG/PNG). Staff must approve before you can collect.</p>
    <?php endif; ?>
    <?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
    <form class="form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label>Pickup branch
        <select name="branch_id" required>
          <option value="">Select a branch</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?= (int) $branch['id'] ?>"><?= e($branch['name']) ?> — <?= e($branch['address']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($needsRx): ?>
        <label>Prescription file
          <input type="file" name="prescription" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
        </label>
        <p class="muted">Max 5 MB. Staff will review this before approving your order.</p>
      <?php endif; ?>
      <button class="btn" type="submit"><?= $needsRx ? 'Submit for approval' : 'Place pickup order' ?></button>
    </form>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
