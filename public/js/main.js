/**
 * Client-side JS for responsive navigation and action toasts (admin/staff).
 */
document.querySelectorAll('.nav-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var nav = document.getElementById('site-nav');
    if (!nav) return;
    var open = nav.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});

(function () {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function ensureToastRoot() {
    var root = document.getElementById('notif-toast-root');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'notif-toast-root';
    root.className = 'notif-toast-root';
    root.setAttribute('aria-live', 'polite');
    document.body.appendChild(root);
    return root;
  }

  function iconForType(type) {
    if (type === 'success') return '✓';
    if (type === 'error') return '!';
    return 'i';
  }

  window.showActionToast = function (payload) {
    if (!payload || !payload.message) return;
    var type = payload.type || 'info';
    var title = payload.title || (type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Update');
    var root = ensureToastRoot();
    var el = document.createElement('div');
    el.className = 'notif-toast is-' + type;
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<div class="notif-toast-icon" aria-hidden="true">' + escapeHtml(iconForType(type)) + '</div>' +
      '<div class="notif-toast-body">' +
        '<div class="notif-toast-top">' +
          '<strong>' + escapeHtml(title) + '</strong>' +
          '<button type="button" class="notif-toast-dismiss" aria-label="Dismiss">&times;</button>' +
        '</div>' +
        '<p>' + escapeHtml(payload.message) + '</p>' +
      '</div>';
    root.insertBefore(el, root.firstChild);

    var dismiss = el.querySelector('.notif-toast-dismiss');
    if (dismiss) {
      dismiss.addEventListener('click', function (event) {
        event.stopPropagation();
        el.remove();
      });
    }

    setTimeout(function () {
      if (el.parentNode) el.remove();
    }, 7000);
  };

  var dataEl = document.getElementById('action-toast-data');
  if (dataEl) {
    try {
      var data = JSON.parse(dataEl.textContent || '{}');
      if (data && data.message) {
        window.showActionToast(data);
      }
    } catch (e) {
      /* ignore bad JSON */
    }
  }
})();
