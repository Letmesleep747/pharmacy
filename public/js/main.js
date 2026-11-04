/**
 * Client-side JS for responsive navigation, live search, cart, and toasts.
 */
document.querySelectorAll('.nav-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var nav = document.getElementById('site-nav');
    if (!nav) return;
    var open = nav.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});

/** Live search / filters: typing or changing a filter resubmits the form. */
(function () {
  function bindLiveFilters(form) {
    if (!form || form.dataset.liveBound === '1') return;
    form.dataset.liveBound = '1';
    var timer = null;

    function submitSoon(delay) {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }, delay);
    }

    form.querySelectorAll('input[type="search"][name="q"]').forEach(function (input) {
      input.addEventListener('input', function () {
        submitSoon(350);
      });
    });

    form.querySelectorAll('select, input[type="checkbox"], input[type="date"]').forEach(function (el) {
      el.addEventListener('change', function () {
        submitSoon(0);
      });
    });
  }

  document.querySelectorAll('form.filters, form.search-bar').forEach(bindLiveFilters);
})();

/** Cart page: quantity changes save immediately without an Update button. */
(function () {
  var form = document.getElementById('cart-form');
  if (!form) return;

  var api = form.getAttribute('data-cart-api');
  var saving = document.getElementById('cart-saving');
  var timer = null;
  var csrfInput = form.querySelector('input[name="_csrf"]');

  function setCartCount(count) {
    document.querySelectorAll('[data-cart-count]').forEach(function (el) {
      el.textContent = String(count);
    });
  }

  function syncFromResponse(data) {
    if (!data || !data.ok) return;
    setCartCount(data.count || 0);

    if (data.empty) {
      window.location.reload();
      return;
    }

    var byId = {};
    (data.items || []).forEach(function (item) {
      byId[String(item.product_id)] = item;
    });

    form.querySelectorAll('#cart-body tr[data-product-id]').forEach(function (row) {
      var id = row.getAttribute('data-product-id');
      var item = byId[id];
      if (!item) {
        row.remove();
        return;
      }
      var qtyInput = row.querySelector('[data-cart-qty]');
      if (qtyInput && String(qtyInput.value) !== String(item.quantity)) {
        qtyInput.value = item.quantity;
      }
      var discountCell = row.querySelector('[data-discount]');
      var lineCell = row.querySelector('[data-line]');
      if (discountCell) discountCell.textContent = item.discount;
      if (lineCell) lineCell.textContent = item.line_total;
      var promo = row.querySelector('[data-promo]');
      if (item.promo) {
        if (!promo) {
          promo = document.createElement('div');
          promo.className = 'badge badge-ok';
          promo.setAttribute('data-promo', '');
          row.querySelector('td').appendChild(promo);
        }
        promo.textContent = item.promo;
      } else if (promo) {
        promo.remove();
      }
    });

    var subtotal = form.querySelector('[data-subtotal]');
    var discountTotal = form.querySelector('[data-discount-total]');
    var total = form.querySelector('[data-total]');
    if (subtotal) subtotal.textContent = data.subtotal;
    if (discountTotal) discountTotal.textContent = data.discount;
    if (total) total.textContent = data.total;
  }

  function saveCart() {
    if (!api || !csrfInput) return;
    if (saving) saving.hidden = false;

    var body = new FormData(form);
    body.set('action', 'update');

    fetch(api, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        syncFromResponse(data);
      })
      .catch(function () {
        /* Fall back to a normal form post if the API is unavailable. */
      })
      .finally(function () {
        if (saving) saving.hidden = true;
      });
  }

  form.querySelectorAll('[data-cart-qty]').forEach(function (input) {
    input.addEventListener('change', function () {
      clearTimeout(timer);
      saveCart();
    });
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(saveCart, 400);
    });
  });
})();

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
