<nav class="admin-side" aria-label="Staff">
  <?php if (user_branch_name() !== ''): ?>
    <p class="muted" style="margin:0 0 10px;padding:0 4px;font-size:0.85rem"><?= e(user_branch_name()) ?></p>
  <?php endif; ?>
  <a class="<?= nav_active('staff/index') ?>" href="<?= e(url('staff/index.php')) ?>">Counter home</a>
  <a class="<?= nav_active('staff/pos') ?>" href="<?= e(url('staff/pos.php')) ?>">In-store POS</a>
  <a class="<?= nav_active('staff/approvals') ?>" href="<?= e(url('staff/approvals.php')) ?>">Rx approvals</a>
  <a class="<?= nav_active('staff/payment-approvals') ?>" href="<?= e(url('staff/payment-approvals.php')) ?>">Payment receipts</a>
  <a class="<?= nav_active('staff/pickups') ?>" href="<?= e(url('staff/pickups.php')) ?>">Pickups</a>
  <a class="<?= nav_active('staff/orders') ?>" href="<?= e(url('staff/orders.php')) ?>">Order history</a>
  <a class="<?= nav_active('staff/stock') ?>" href="<?= e(url('staff/stock.php')) ?>">Branch stock</a>
  <a class="<?= nav_active('staff/profile') ?>" href="<?= e(url('staff/profile.php')) ?>">My account</a>
  <a class="<?= nav_active('staff/notifications') ?>" href="<?= e(url('staff/notifications.php')) ?>">Notifications<?php
    try {
      $n = unread_notification_count(db());
      if ($n > 0) {
          echo ' <span class="nav-badge" data-notif-badge>' . e($n > 99 ? '99+' : (string) $n) . '</span>';
      }
    } catch (Throwable $e) {
    }
  ?></a>
</nav>
