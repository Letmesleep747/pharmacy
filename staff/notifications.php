<?php
require dirname(__DIR__) . '/includes/init.php';

// Force Staff Access
require_staff();

// Load the logic and data
require dirname(__DIR__) . '/includes/notifications_page.php';

$title = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container layout-admin">
    <div><?php require dirname(__DIR__) . '/includes/staff_nav.php'; ?></div>
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
                                        <a href="?read=<?= (int)$n['id'] ?>" class="btn btn-small btn-secondary" style="margin-bottom:4px;">Mark read</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($n['order_id']): ?>
                                        <?php if (str_contains($n['type'], 'payment')): ?>
                                            <a href="<?= e(url('staff/payment-review.php?id=' . $n['order_id'])) ?>" class="btn btn-small">Review Payment</a>
                                        <?php elseif (str_contains($n['type'], 'prescription')): ?>
                                            <a href="<?= e(url('staff/approvals.php')) ?>" class="btn btn-small">View Rx Approvals</a>
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