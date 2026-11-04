/**
 * Live notification badge + toast popups (polls portal notifications API).
 */
(function () {
  var apiMeta = document.querySelector('meta[name="notif-api"]');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var listMeta = document.querySelector('meta[name="notif-list"]');
  if (!apiMeta || !csrfMeta) {
    return;
  }

  var apiUrl = apiMeta.getAttribute('content');
  var csrf = csrfMeta.getAttribute('content');
  var listUrl = listMeta ? listMeta.getAttribute('content') : '#';
  var storageKey = 'pharmacy_notif_seen_' + (apiUrl || '');
  var lastSeenId = parseInt(localStorage.getItem(storageKey) || '0', 10) || 0;
  var pollMs = 8000;
  var busy = false;
  var shownKey = 'pharmacy_notif_shown_' + (apiUrl || '');
  var freshSeconds = 90; // a notification this new (and never shown) still gets one toast on page load

  function loadShown() {
    try {
      var arr = JSON.parse(localStorage.getItem(shownKey) || '[]');
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  // Unique per notification AND per order event, so duplicate DB rows never toast twice.
  function keyFor(item) {
    return 'id:' + item.id;
  }
  function eventKeyFor(item) {
    return 'ev:' + (item.order_id || 0) + ':' + (item.type || '');
  }

  function alreadyShown(item) {
    var shown = loadShown();
    return shown.indexOf(keyFor(item)) !== -1 || shown.indexOf(eventKeyFor(item)) !== -1;
  }

  function rememberShown(item) {
    var shown = loadShown();
    shown.push(keyFor(item), eventKeyFor(item));
    if (shown.length > 200) shown = shown.slice(shown.length - 200);
    try { localStorage.setItem(shownKey, JSON.stringify(shown)); } catch (e) {}
  }

  function syncLastSeenFromStorage() {
    var stored = parseInt(localStorage.getItem(storageKey) || '0', 10) || 0;
    if (stored > lastSeenId) lastSeenId = stored;
  }

  function bumpLastSeen(id) {
    if (id > lastSeenId) {
      lastSeenId = id;
      try { localStorage.setItem(storageKey, String(lastSeenId)); } catch (e) {}
    }
  }

  function toastRoot() {
    var root = document.getElementById('notif-toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'notif-toast-root';
      root.className = 'notif-toast-root';
      root.setAttribute('aria-live', 'polite');
      document.body.appendChild(root);
    }
    return root;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function updateBadges(count) {
    var nodes = document.querySelectorAll('[data-notif-badge]');
    var label = count > 99 ? '99+' : String(count);
    nodes.forEach(function (el) {
      if (count <= 0) {
        el.hidden = true;
        el.textContent = '';
      } else {
        el.hidden = false;
        el.textContent = label;
      }
    });

    // If badge span was missing (zero on first paint), inject into Notifications links.
    if (count > 0 && nodes.length === 0) {
      document.querySelectorAll('a[href*="notifications.php"]').forEach(function (link) {
        if (link.querySelector('[data-notif-badge]')) return;
        var span = document.createElement('span');
        span.className = 'nav-badge';
        span.setAttribute('data-notif-badge', '');
        span.textContent = label;
        link.appendChild(document.createTextNode(' '));
        link.appendChild(span);
      });
    }
  }

  function formatTime(value) {
    if (!value) return 'now';
    var d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return 'now';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function iconFor(type) {
    var t = String(type || '');
    if (t.indexOf('payment') !== -1) return 'RM';
    if (t.indexOf('prescription') !== -1 || t.indexOf('rx') !== -1) return 'Rx';
    if (t.indexOf('refund') !== -1) return '↻';
    if (t.indexOf('pickup') !== -1 || t.indexOf('ready') !== -1) return '✓';
    return '+';
  }

  function showToast(item) {
    if (alreadyShown(item)) return; // each notification pops up only once, ever
    rememberShown(item);
    var root = toastRoot();
    var el = document.createElement('div');
    el.className = 'notif-toast';
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<div class="notif-toast-icon" aria-hidden="true">' + escapeHtml(iconFor(item.type)) + '</div>' +
      '<div class="notif-toast-body">' +
        '<div class="notif-toast-top">' +
          '<strong>' + escapeHtml(item.title) + '</strong>' +
          '<span class="notif-toast-time">' + escapeHtml(formatTime(item.created_at)) + '</span>' +
        '</div>' +
        '<p>' + escapeHtml(item.message) + '</p>' +
        '<div class="notif-toast-actions">' +
          '<a href="' + escapeHtml(listUrl) + '">Open</a>' +
          '<button type="button" data-mark="' + item.id + '">Mark read</button>' +
        '</div>' +
      '</div>';
    root.insertBefore(el, root.firstChild);

    el.addEventListener('click', function (event) {
      if (event.target.closest('[data-mark]') || event.target.closest('a')) return;
      window.location.href = listUrl;
    });

    var markBtn = el.querySelector('[data-mark]');
    if (markBtn) {
      markBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        markRead(item.id).then(function () {
          el.remove();
        });
      });
    }

    setTimeout(function () {
      if (el.parentNode) el.remove();
    }, 10000);
  }

  function markRead(id) {
    var body = new FormData();
    body.append('_csrf', csrf);
    body.append('action', 'mark_read');
    body.append('notification_id', String(id));
    return fetch(apiUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.ok) {
          updateBadges(data.unread || 0);
        }
        return data;
      })
      .catch(function () { return null; });
  }

  // Page load: show ONE toast for brand-new, never-shown notifications (e.g. right after checkout);
  // everything older is just used as the baseline so old unread items do not pop up.
  function seedFromResponse(data) {
    var items = Array.isArray(data.items) ? data.items.slice() : [];
    items.sort(function (a, b) { return a.id - b.id; });
    items.forEach(function (item) {
      var fresh = (item.age_seconds || 0) <= freshSeconds;
      if (fresh && item.id > lastSeenId && !alreadyShown(item)) {
        showToast(item);
      } else if (!alreadyShown(item)) {
        rememberShown(item); // old unread: treat as already seen
      }
    });
    var maxId = parseInt(data.latest_id || '0', 10) || 0;
    items.forEach(function (item) { if (item.id > maxId) maxId = item.id; });
    bumpLastSeen(maxId);
  }

  function poll() {
    if (busy || document.hidden) return;
    syncLastSeenFromStorage(); // another tab may already have shown newer toasts
    busy = true;
    var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'after_id=' + encodeURIComponent(String(lastSeenId));
    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        updateBadges(data.unread || 0);

        var items = Array.isArray(data.items) ? data.items.slice() : [];
        items.sort(function (a, b) { return a.id - b.id; });
        items.forEach(function (item) {
          if (item.id > lastSeenId) {
            showToast(item); // no-op if this notification/event was already shown
          }
          bumpLastSeen(item.id);
        });
        bumpLastSeen(parseInt(data.latest_id || '0', 10) || 0);
      })
      .catch(function () { /* ignore transient errors */ })
      .finally(function () { busy = false; });
  }

  // First paint: badge + baseline (and one toast for anything brand new and never shown).
  fetch(apiUrl, {
    method: 'GET',
    credentials: 'same-origin',
    headers: { Accept: 'application/json' }
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data || !data.ok) return;
      updateBadges(data.unread || 0);
      seedFromResponse(data);
    })
    .catch(function () {})
    .finally(function () {
      setInterval(poll, pollMs);
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
      });
    });
})();