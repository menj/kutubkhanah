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

  /* Theme and plugin upload (.zip) */
  each('form[data-kk-zip]', function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var out = form.querySelector('.kk-saved');
      var fd = new FormData(form);
      fd.append('target', form.getAttribute('data-target'));
      out.textContent = t.working;
      api('install_zip', fd, true).then(function (res) {
        if (!res.ok) { out.textContent = (res.j && res.j.message) || t.error; return; }
        out.textContent = t.saved;
        reloadPane(window.location.pathname);
      }).catch(function () { out.textContent = t.error; });
    });
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
      var body = { id: parseInt(form.getAttribute('data-id'), 10), values: values };
      if (form.getAttribute('data-taxonomy')) { body.taxonomy = form.getAttribute('data-taxonomy'); }
      api(form.getAttribute('data-action'), body).then(function (res) {
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

  /* Backup restore (SQLite and PostgreSQL) */
  var restoreForm = document.getElementById('kk-restore');
  if (restoreForm) {
    restoreForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var out = document.getElementById('kk-restore-status');
      var btn = restoreForm.querySelector('button');
      btn.disabled = true;
      out.textContent = t.working;
      api('restore', new FormData(restoreForm), true).then(function (res) {
        out.textContent = res.ok ? (res.j.rows + ' rows restored in ' + res.j.tables + ' tables. Previous data saved as ' + res.j.safety + '.') : ((res.j && res.j.message) || t.error);
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
  /* Blog: media upload page */
  var mediaForm = document.getElementById('kk-media-upload');
  if (mediaForm) {
    mediaForm.querySelector('input[type=file]').addEventListener('change', function () {
      var files = Array.prototype.slice.call(this.files);
      var out = mediaForm.querySelector('.kk-saved');
      if (!files.length) { return; }
      out.textContent = t.working;
      Promise.all(files.map(function (f) { var fd = new FormData(); fd.append('file', f); return api('media_upload', fd, true); })).then(function (rs) {
        var bad = rs.filter(function (r) { return !r.ok; });
        out.textContent = bad.length ? ((bad[0].j && bad[0].j.message) || t.error) : t.saved;
        if (!bad.length) { reloadPane(mediaForm.getAttribute('data-reload')); }
      }).catch(function () { out.textContent = t.error; });
    });
  }

  /* Blog: WordPress import */
  var wxr = document.getElementById('kk-wxr-form');
  if (wxr) {
    wxr.addEventListener('submit', function (e) {
      e.preventDefault();
      var out = wxr.querySelector('.kk-saved');
      var btn = wxr.querySelector('button[type=submit]');
      out.textContent = t.wxrworking; btn.disabled = true;
      api('wxr_import', new FormData(wxr), true).then(function (res) {
        btn.disabled = false;
        if (!res.ok) { out.textContent = (res.j && res.j.message) || t.error; return; }
        var j = res.j;
        out.textContent = fmt(t.wxrdone, j.posts, j.pages, j.comments, j.images, j.terms, j.skipped) + (j.errors && j.errors.length ? ' ' + j.errors.join(' · ') : '');
      }).catch(function () { btn.disabled = false; out.textContent = t.error; });
    });
  }

  /* Duplicates & editions: merge or link a group */
  each('[data-merge]', function (box) {
    var out = box.querySelector('.kk-saved');
    var ids = box.querySelector('[data-ids]').value.split(',').map(function (x) { return parseInt(x, 10); });
    var root = document.querySelector('.kk[data-reload]');
    function done(res, btn) {
      btn.disabled = false;
      if (!res.ok) { out.textContent = (res.j && res.j.message) || t.error; return; }
      out.textContent = t.saved;
      reloadPane(root && root.getAttribute('data-reload'));
    }
    var go = box.querySelector('[data-merge-go]');
    if (go) {
      go.addEventListener('click', function () {
        var sel = box.querySelector('input[type=radio]:checked');
        if (!sel) { return; }
        var keep = parseInt(sel.value, 10);
        if (!window.confirm(t.mergeconfirm || 'Merge?')) { return; }
        go.disabled = true; out.textContent = t.working;
        api('dup_merge', { keep: keep, drop: ids.filter(function (x) { return x !== keep; }) }).then(function (r) { done(r, go); })
          .catch(function () { go.disabled = false; out.textContent = t.error; });
      });
    }
    var link = box.querySelector('[data-merge-link]');
    if (link) {
      link.addEventListener('click', function () {
        link.disabled = true; out.textContent = t.working;
        api('work_link', { ids: ids }).then(function (r) { done(r, link); })
          .catch(function () { link.disabled = false; out.textContent = t.error; });
      });
    }
  });

  /* Reading lists: reorder, note, remove, add */
  each('ol[data-list]', function (ol) {
    var list = parseInt(ol.getAttribute('data-list'), 10);
    var url = window.location.href;
    ol.addEventListener('click', function (e) {
      var li = e.target.closest('li[data-book]');
      if (!li) { return; }
      var book = parseInt(li.getAttribute('data-book'), 10);
      var mv = e.target.closest('[data-list-move]');
      if (mv) {
        var dir = parseInt(mv.getAttribute('data-list-move'), 10);
        api('list_move', { list: list, book: book, dir: dir }).then(function (r) {
          if (!r.ok) { return; }
          var sib = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
          if (sib) { ol.insertBefore(dir < 0 ? li : sib, dir < 0 ? sib : li); }
        });
      } else if (e.target.closest('[data-list-remove]')) {
        api('list_remove', { list: list, book: book }).then(function (r) { if (r.ok) { li.parentNode.removeChild(li); } });
      }
    });
    ol.addEventListener('change', function (e) {
      var inp = e.target.closest('[data-list-note]');
      var li = e.target.closest('li[data-book]');
      if (inp && li) { api('list_note', { list: list, book: parseInt(li.getAttribute('data-book'), 10), note: inp.value }); }
    });
  });
  each('[data-picker]', function (box) {
    var input = box.querySelector('input');
    var results = box.querySelector('.kk-picker__results');
    var timer = null;
    input.addEventListener('input', function () {
      window.clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { results.hidden = true; return; }
      timer = window.setTimeout(function () {
        api('book_search', { q: q }).then(function (r) {
          results.innerHTML = '';
          (r.j.rows || []).forEach(function (row) {
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'kk-picker__item'; b.setAttribute('dir', 'auto');
            b.textContent = row.title + (row.authors ? ' — ' + row.authors : '') + (row.year ? ' (' + row.year + ')' : '');
            b.addEventListener('click', function () {
              api(box.getAttribute('data-picker'), { list: parseInt(box.getAttribute('data-list'), 10), book: row.id }).then(function (res) {
                if (res.ok) { reloadPane(window.location.pathname + '?tab=' + box.getAttribute('data-list')); } else { window.alert((res.j && res.j.message) || t.error); }
              });
            });
            li.appendChild(b); results.appendChild(li);
          });
          results.hidden = !results.children.length;
        });
      }, 250);
    });
  });

  /* Search inside: index the pending files one at a time */
  each('[data-inside]', function (box) {
    var run = box.querySelector('[data-inside-run]');
    var status = box.querySelector('[data-inside-status]');
    var bar = box.querySelector('[data-inside-bar]');
    if (!run) { return; }
    run.addEventListener('click', function () {
      run.disabled = true; status.textContent = t.working;
      api('doc_pending', {}).then(function (r) {
        var ids = (r.j && r.j.ids) || [];
        if (!ids.length) { status.textContent = t.nothingnew || t.saved; run.disabled = false; return; }
        var wrap = box.querySelector('.kk-progress'); wrap.hidden = false;
        var i = 0, failed = 0;
        (function next() {
          if (i >= ids.length) {
            status.textContent = fmt(t.indexdone || '%d files', ids.length - failed) + (failed ? ' · ' + failed + ' ' + (t.failed || 'failed') : '');
            bar.style.width = '100%'; run.disabled = false;
            window.setTimeout(function () { reloadPane(window.location.pathname); }, 1200);
            return;
          }
          status.textContent = (i + 1) + ' / ' + ids.length;
          api('doc_index', { id: ids[i], no_ocr: box.querySelector('[data-inside-noocr]').checked ? 1 : 0 }).then(function (x) {
            if (!x.ok) { failed++; }
          }).catch(function () { failed++; }).then(function () { i++; bar.style.width = Math.round(i / ids.length * 100) + '%'; next(); });
        })();
      }).catch(function () { status.textContent = t.error; run.disabled = false; });
    });
  });
})();
