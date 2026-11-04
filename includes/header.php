<?php
$pageTitle = ($title ?? 'Home') . ' · ' . APP_NAME;
$flash = flash_get();
$user = current_user();
// Portal drives which navigation links and brand tag appear (customer / staff / admin).
$portal = current_portal();
$brandHref = match ($portal) {
    'admin' => url('admin/index.php'),
    'staff' => url('staff/index.php'),
    default => url('index.php'),
};
$brandTag = match ($portal) {
    'admin' => 'Administrator site',
    'staff' => 'Staff counter',
    default => 'Online pharmacy',
};
$notifUnread = 0;
$notifHref = match ($portal) {
    'admin' => url('admin/notifications.php'),
    'staff' => url('staff/notifications.php'),
    default => url('pages/notifications.php'),
};
if ($user) {
    try {
        $notifUnread = unread_notification_count(db(), (int) $user['id']);
    } catch (Throwable $e) {
        $notifUnread = 0;
    }
}
$notifBadge = static function (int $count): string {
    if ($count <= 0) {
        return '';
    }
    $label = $count > 99 ? '99+' : (string) $count;
    return ' <span class="nav-badge" data-notif-badge>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <?php if ($user): ?>
    <?php
      $notifApi = match ($portal) {
          'admin' => url('admin/notifications-api.php'),
          'staff' => url('staff/notifications-api.php'),
          default => url('api/notifications.php'),
      };
    ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="notif-api" content="<?= e($notifApi) ?>">
    <meta name="notif-list" content="<?= e($notifHref) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,650&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('public/css/style.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/css/style.css') ?>">
  <link rel="stylesheet" href="<?= e(url('public/css/notifications.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/css/notifications.css') ?>">
</head>
<body class="portal-<?= e($portal) ?>">
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?= e($brandHref) ?>">
        <span class="brand-mark" aria-hidden="true">+</span>
        <span>
          <strong><?= e(APP_NAME) ?></strong>
          <small><?= e($brandTag) ?></small>
        </span>
      </a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
      <nav id="site-nav" class="site-nav">
        <?php if ($portal === 'customer'): ?>
          <a class="<?= nav_active('index.php') ?>" href="<?= e(url('index.php')) ?>">Home</a>
          <a class="<?= nav_active('pages/products') ?>" href="<?= e(url('pages/products.php')) ?>">Medications</a>
          <a class="<?= nav_active('pages/promotions') ?>" href="<?= e(url('pages/promotions.php')) ?>">Promotions</a>
          <a class="<?= nav_active('pages/branches') ?>" href="<?= e(url('pages/branches.php')) ?>">Branches</a>
          <a class="<?= nav_active('pages/cart') ?>" href="<?= e(url('pages/cart.php')) ?>">Cart (<?= (int) cart_count() ?>)</a>
          <?php if ($user): ?>
            <a class="<?= nav_active('pages/orders') ?>" href="<?= e(url('pages/orders.php')) ?>">Orders</a>
            <a class="<?= nav_active('pages/notifications') ?>" href="<?= e($notifHref) ?>">Notifications<?= $notifBadge($notifUnread) ?></a>
            <a class="nav-user <?= nav_active('pages/profile') ?>" href="<?= e(url('pages/profile.php')) ?>"><?= e($user['name']) ?></a>
            <a href="<?= e(url('pages/logout.php')) ?>">Sign out</a>
          <?php else: ?>
            <a class="<?= nav_active('pages/login') ?>" href="<?= e(url('pages/login.php')) ?>">Sign in</a>
            <a class="btn btn-small" href="<?= e(url('pages/register.php')) ?>">Register</a>
          <?php endif; ?>
        <?php elseif ($portal === 'staff'): ?>
          <a class="<?= nav_active('staff/index') ?>" href="<?= e(url('staff/index.php')) ?>">Counter</a>
          <a class="<?= nav_active('staff/pos') ?>" href="<?= e(url('staff/pos.php')) ?>">POS</a>
          <a class="<?= nav_active('staff/approvals') ?>" href="<?= e(url('staff/approvals.php')) ?>">Rx approvals</a>
          <a class="<?= nav_active('staff/pickups') ?>" href="<?= e(url('staff/pickups.php')) ?>">Pickups</a>
          <a class="<?= nav_active('staff/orders') ?>" href="<?= e(url('staff/orders.php')) ?>">Orders</a>
          <a class="<?= nav_active('staff/stock') ?>" href="<?= e(url('staff/stock.php')) ?>">Stock</a>
          <?php if ($user): ?>
            <a class="<?= nav_active('staff/notifications') ?>" href="<?= e($notifHref) ?>">Notifications<?= $notifBadge($notifUnread) ?></a>
            <span class="nav-user"><?= e($user['name']) ?><?php if (!empty($user['branch_name'])): ?> · <?= e((string) $user['branch_name']) ?><?php endif; ?></span>
            <a href="<?= e(url('staff/logout.php')) ?>">Sign out</a>
          <?php else: ?>
            <a class="<?= nav_active('staff/login') ?>" href="<?= e(url('staff/login.php')) ?>">Sign in</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="<?= nav_active('admin/index') ?>" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
          <a class="<?= nav_active('admin/products') ?>" href="<?= e(url('admin/products.php')) ?>">Catalogue</a>
          <a class="<?= nav_active('admin/approvals') ?>" href="<?= e(url('admin/approvals.php')) ?>">Rx approvals</a>
          <a class="<?= nav_active('admin/pickups') ?>" href="<?= e(url('admin/pickups.php')) ?>">Pickups</a>
          <a class="<?= nav_active('admin/orders') ?>" href="<?= e(url('admin/orders.php')) ?>">Orders</a>
          <a class="<?= nav_active('admin/reports') ?>" href="<?= e(url('admin/reports.php')) ?>">Reports</a>
          <?php if ($user): ?>
            <a class="<?= nav_active('admin/notifications') ?>" href="<?= e($notifHref) ?>">Notifications<?= $notifBadge($notifUnread) ?></a>
            <span class="nav-user"><?= e($user['name']) ?></span>
            <a href="<?= e(url('admin/logout.php')) ?>">Sign out</a>
          <?php else: ?>
            <a class="<?= nav_active('admin/login') ?>" href="<?= e(url('admin/login.php')) ?>">Sign in</a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <?php if ($flash): ?>
    <script type="application/json" id="action-toast-data"><?= json_encode([
        'type' => (string) ($flash['type'] ?? 'info'),
        'message' => (string) ($flash['message'] ?? ''),
        'title' => match ((string) ($flash['type'] ?? 'info')) {
            'success' => 'Success',
            'error' => 'Something went wrong',
            'info' => 'Update',
            default => 'Notice',
        },
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
  <?php endif; ?>
  <main id="main">
