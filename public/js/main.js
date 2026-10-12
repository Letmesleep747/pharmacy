/**
 * Client-side JS for responsive navigation.
 * Toggles the mobile menu and updates aria-expanded for accessibility.
 */
document.querySelectorAll('.nav-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var nav = document.getElementById('site-nav');
    if (!nav) return;
    var open = nav.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});
