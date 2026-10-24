  </main>
  <footer class="site-footer">
    <div class="container footer-inner">
      <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(match (current_portal()) {
          'admin' => 'Administrator site',
          'staff' => 'Staff counter site',
          default => 'Customer storefront',
      }) ?></p>
      <p class="muted"><?= e(match (current_portal()) {
          'admin' => 'Manage catalogue, branches, promotions and reports.',
          'staff' => 'Record walk-in sales and keep branch stock up to date.',
          default => 'Search stock by branch. Promotions apply at online checkout.',
      }) ?></p>
    </div>
  </footer>
  <div id="notif-toast-root" class="notif-toast-root" aria-live="polite" aria-relevant="additions"></div>
  <script src="<?= e(url('public/js/main.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/js/main.js') ?>"></script>
  <?php if (current_user()): ?>
    <script src="<?= e(url('public/js/notifications.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/js/notifications.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
