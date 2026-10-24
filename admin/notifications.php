<?php
require dirname(__DIR__) . '/includes/init.php';

// Force Admin Access
require_admin();

// Load the logic and data
require dirname(__DIR__) . '/includes/notifications_page.php';

$title = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
    <div><?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?></div>
    <div>
        <h1>Notifications</h1>
        
        <?php if (!$notifications): ?>
            <p class="empty">No notifications yet.</p>
        <?php else: ?>
            <form method="post" style="margin-bottom: 15px;">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" name="action" value="mark_all_read">Mark all as read</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $n): ?>
                            <tr style="<?= $n['is_read'] ? 'opacity: 0.6;' : 'font-weight: 600; background: #f8fafc;' ?>">
                                <td style="white-space:nowrap;"><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></td>
                                <td><?= e($n['title']) ?></td>
                                <td><?= e($n['message']) ?></td>
                                <td style="white-space:nowrap;">
                                    <?php if (!$n['is_read']): ?>
                                        <form method="post" style="display:inline">
                                          <?= csrf_field() ?>
                                          <input type="hidden" name="action" value="mark_read">
                                          <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>">
                                          <button class="btn btn-small btn-secondary" type="submit" style="margin-bottom:4px;">Mark read</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($n['order_id']): ?>
                                        <?php if (str_contains($n['type'], 'payment')): ?>
                                            <a href="<?= e(url('admin/payment-review.php?id=' . $n['order_id'])) ?>" class="btn btn-small">Review Payment</a>
                                        <?php elseif (str_contains($n['type'], 'prescription')): ?>
                                            <a href="<?= e(url('admin/approvals.php')) ?>" class="btn btn-small">View Rx Approvals</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>