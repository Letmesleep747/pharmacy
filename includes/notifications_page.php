<?php
require_once dirname(__DIR__) . '/includes/notifications.php';
$portal = current_portal();
if ($portal === 'admin') require_admin_portal();
elseif ($portal === 'staff') require_staff_portal();
else { require_login(); require_storefront(); }
$db = db();
$userId = (int) current_user()['id'];
sync_prescription_notifications($db, current_user());
if (is_post() && ($_POST['action'] ?? '') === 'mark_read') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) db_exec($db, 'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', 'ii', [$notificationId,$userId])->close();
    redirect($portal === 'customer' ? 'pages/notifications.php' : $portal . '/notifications.php');
}
$rows = db_all($db, 'SELECT id, order_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', 'i', [$userId]);
// Count all unread notifications, including older entries beyond the display limit.
$unreadRow = db_one($db, 'SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$userId]);
$unread = (int) $unreadRow['n'];
$title = 'Notifications';
require dirname(__DIR__) . '/header.php';
?>
<div class="container" style="max-width:900px;margin:30px auto;padding:0 16px">
  <h1>Notifications <?= $unread ? '(' . $unread . ' unread)' : '' ?></h1>
  <p class="muted">Prescription reviews and order updates.</p>
  <?php if (!$rows): ?><p class="empty">No notifications yet.</p><?php endif; ?>
  <?php foreach ($rows as $n): ?>
    <section style="border:1px solid #d8dfe5;border-radius:12px;padding:18px;margin:12px 0;background:<?= $n['is_read'] ? '#fff' : '#f0f8ff' ?>">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <strong><?= e($n['title']) ?></strong>
        <small><?= e($n['created_at']) ?></small>
      </div>
      <p><?= e($n['message']) ?></p>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?php if ($portal !== 'customer' && $n['type'] === 'prescription_submitted'): ?>
          <a class="btn btn-small" href="<?= e(url($portal . '/approval.php?id=' . (int)$n['order_id'])) ?>">Review order</a>
        <?php endif; ?>
        <?php if (!$n['is_read']): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
            <button class="btn btn-secondary btn-small" type="submit">Mark as read</button>
          </form>
        <?php else: ?><span class="muted">Read</span><?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
