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
  <script src="<?= e(url('public/js/main.js')) ?>"></script>
</body>
</html>
