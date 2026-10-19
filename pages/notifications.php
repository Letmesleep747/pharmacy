<?php
require dirname(__DIR__) . '/includes/init.php';

// Force Customer Access
require_login();
require_storefront();

// Load the logic and data
require dirname(__DIR__) . '/includes/notifications_page.php';

$title = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container" style="max-width:900px;margin:30px auto;padding:0 16px">
  <h1>Notifications <?= $unread ? '(' . $unread . ' unread)' : '' ?></h1>
  <p class="muted">Prescription reviews and order updates.</p>

  <?php if (!$notifications): ?>
    <p class="empty">No notifications yet.</p>
  <?php else: ?>
    <form method="post" style="margin-bottom: 15px;">
      <?= csrf_field() ?>
      <button class="btn btn-secondary" name="action" value="mark_all_read">Mark all as read</button>
    </form>

    <?php foreach ($notifications as $n): ?>
      <section style="border:1px solid #d8dfe5;border-radius:12px;padding:18px;margin:12px 0;background:<?= $n['is_read'] ? '#fff' : '#f0f8ff' ?>">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <strong><?= e($n['title']) ?></strong>
          <small><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></small>
        </div>
        <p><?= e($n['message']) ?></p>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <?php if (!$n['is_read']): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
              <button class="btn btn-secondary btn-small" type="submit">Mark as read</button>
            </form>
          <?php else: ?>
            <span class="muted">Read</span>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>