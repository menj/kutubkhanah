/* Kutubkhanah: admin helpers (settings tabs, retry lookup, import, cover fetch). GNU GPL v3 or later. */
(function () {
  'use strict';

  var cfg = window.kkAdmin || {};
  var t = cfg.i18n || {};

  function api(action, body, isForm) {
    var opts = { method: 'POST', credentials: 'same-origin', headers: { 'X-KK-Token': cfg.token } };
    if (isForm) {
      opts.body = body;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body || {});
    }
    return fetch(cfg.api + '?action=' + action, opts).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    });
  }

  function fmt(str) {
    var args = Array.prototype.slice.call(arguments, 1), i = 0;
    return String(str).replace(/%(\d+\$)?d/g, function (m, pos) { return pos ? args[parseInt(pos, 10) - 1] : args[i++]; });
  }

  function each(sel, fn) { Array.prototype.forEach.call(document.querySelectorAll(sel), fn); }

  /* Reload an admin page inside SLiMS's content pane (or the whole window outside it). */
  function reloadPane(url) {
    if (!url) { return; }
    if (window.jQuery && window.jQuery('#mainContent').length && window.jQuery.fn.simbioAJAX) {
      window.jQuery('#mainContent').simbioAJAX(url);
    } else {
      window.location.href = url;
    }
  }

  /* Generic action buttons: data-kk-action, data-id, optional data-confirm, data-values (JSON), data-remove, data-reload. */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-kk-action]') : null;
    if (!btn) { return; }
    e.preventDefault();
    if (btn.getAttribute('data-confirm') && !window.confirm(btn.getAttribute('data-confirm'))) { return; }
    var body = {};
    try { body = JSON.parse(btn.getAttribute('data-values') || '{}'); } catch (err) { body = {}; }
    body.id = parseInt(btn.getAttribute('data-id') || '0', 10);
    btn.disabled = true;
    api(btn.getAttribute('data-kk-action'), body).then(function (res) {
      btn.disabled = false;
      if (!res.ok) { window.alert((res.j && res.j.message) || t.error); return; }
      if (btn.hasAttribute('data-remove')) {
        var row = btn.closest('li, tr, .kk-panel');
        if (row) { row.parentNode.removeChild(row); }
      }
      if (btn.getAttribute('data-reload')) { reloadPane(btn.getAttribute('data-reload')); }
    }).catch(function () { btn.disabled = false; window.alert(t.error); });
  });

  /* Settings tabs */
  each('.kk-tabs [role="tab"]', function (tab) {
    tab.addEventListener('click', function () {
      each('.kk-tabs [role="tab"]', function (x) {
        var on = x === tab;
        x.classList.toggle('is-active', on);
        x.setAttribute('aria-selected', on ? 'true' : 'false');
        var pane = document.getElementById(x.getAttribute('aria-controls'));
        if (pane) { pane.hidden = !on; }
      });
    });
    tab.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
      var tabs = Array.prototype.slice.call(document.querySelectorAll('.kk-tabs [role="tab"]'));
      var next = tabs[(tabs.indexOf(tab) + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      next.focus(); next.click();
    });
  });

  /* Settings save */
  each('form.kk-settings', function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var values = {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name) { return; }
        values[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
      });
      var out = form.querySelector('.kk-saved');
      out.textContent = t.working;
      api('settings', { tab: form.getAttribute('data-tab'), values: values }).then(function (res) {
        out.textContent = res.ok ? t.saved : ((res.j && res.j.message) || t.error);
      }).catch(function () { out.textContent = t.error; });
    });
  });

  each('.kk-reset', function (btn) {
    btn.addEventListener('click', function () {
      each('input[type="color"][name^="' + btn.getAttribute('data-mode') + '_"]', function (input) {
        input.value = input.getAttribute('data-default');
      });
    });
  });

  /* Generic save forms: Book Details and Authors */
  each('form.kk-save', function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var values = {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name) { return; }
        values[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
      });
      var out = form.querySelector('.kk-saved');
      out.textContent = t.working;
      api(form.getAttribute('data-action'), { id: parseInt(form.getAttribute('data-id'), 10), values: values }).then(function (res) {
        out.textContent = res.ok ? t.saved : ((res.j && res.j.message) || t.error);
        if (res.ok && form.getAttribute('data-reload')) { reloadPane(form.getAttribute('data-reload')); }
      }).catch(function () { out.textContent = t.error; });
    });
  });

  /* Inbox: change status, delete */
  each('.kk-msg-status', function (sel) {
    sel.addEventListener('change', function () {
      sel.disabled = true;
      api('message_status', { id: parseInt(sel.getAttribute('data-id'), 10), status: sel.value })
        .catch(function () { window.alert(t.error); })
        .then(function () { sel.disabled = false; });
    });
  });
  each('.kk-msg-delete', function (btn) {
    btn.addEventListener('click', function () {
      if (!window.confirm(btn.getAttribute('data-confirm'))) { return; }
      api('message_delete', { id: parseInt(btn.getAttribute('data-id'), 10) }).then(function (res) {
        if (res.ok) {
          var card = btn.closest('.kk-msg');
          if (card) { card.parentNode.removeChild(card); }
        } else { window.alert(t.error); }
      }).catch(function () { window.alert(t.error); });
    });
  });

  /* Retry lookup on the Review page */
  each('.kk-retry', function (btn) {
    btn.addEventListener('click', function () {
      var out = btn.parentNode.querySelector('.kk-retry-status');
      btn.disabled = true;
      out.textContent = t.working;
      api('retry', { id: parseInt(btn.getAttribute('data-id'), 10) }).then(function (res) {
        if (!res.ok) { out.textContent = (res.j && res.j.message) || t.error; return; }
        out.textContent = res.j.status === 'not_found' ? t.notfound : t.updated;
      }).catch(function () { out.textContent = t.error; }).then(function () { btn.disabled = false; });
    });
  });

  /* Libib import */
  var importForm = document.getElementById('kk-import');
  if (importForm) {
    importForm.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var out = document.getElementById('kk-import-status');
      var btn = importForm.querySelector('button');
      btn.disabled = true;
      out.textContent = t.working;
      api('import', new FormData(importForm), true).then(function (res) {
        out.textContent = res.ok ? fmt(t.imported, res.j.created, res.j.skipped) : ((res.j && res.j.message) || t.error);
      }).catch(function () { out.textContent = t.error; }).then(function () { btn.disabled = false; });
    });
  }

  /* Bulk cover fetch, a few books per request so shared hosting never times out */
  var go = document.getElementById('kk-covers');
  if (go) {
    var bar = document.getElementById('kk-covers-progress');
    var fill = bar.querySelector('.kk-progress__bar');
    var status = document.getElementById('kk-covers-status');
    var reset = document.getElementById('kk-covers-reset');

    go.addEventListener('click', function () {
      var total = 0, found = 0, processed = 0, first = true;
      go.disabled = true; bar.hidden = false; fill.style.width = '0%';

      function step() {
        api('covers', { reset: first && reset.checked, limit: 4 }).then(function (res) {
          if (!res.ok) { throw new Error(); }
          var r = res.j;
          first = false;
          processed += r.processed; found += r.found;
          if (!total) { total = processed + r.remaining; }
          fill.style.width = (total ? Math.round(processed / total * 100) : 100) + '%';
          if (r.remaining > 0 && r.processed > 0) {
            status.textContent = fmt(t.progress, processed, found, r.remaining);
            step();
          } else {
            fill.style.width = '100%';
            status.textContent = fmt(t.done, found);
            go.disabled = false;
          }
        }).catch(function () { status.textContent = t.error; go.disabled = false; });
      }
      step();
    });
  }
})();
