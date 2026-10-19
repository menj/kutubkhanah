/* Kutubkhanah blog editor: TinyMCE 4 (as in WordPress's classic editor), media library, book cards,
   publish box. GNU GPL v3 or later. */
(function () {
  'use strict';

  var cfg = window.kkPost || {};
  var adm = window.kkAdmin || {};
  var t = cfg.i18n || {};
  var form = document.getElementById('kk-post-form');
  if (!form || !window.tinymce) { return; }

  function api(action, body, isForm) {
    var opts = { method: 'POST', credentials: 'same-origin', headers: { 'X-KK-Token': adm.token } };
    if (isForm) { opts.body = body; } else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body || {}); }
    return fetch(adm.api + '?action=' + action, opts).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function reloadPane(url) {
    if (window.jQuery && window.jQuery('#mainContent').length && window.jQuery.fn.simbioAJAX) { window.jQuery('#mainContent').simbioAJAX(url); }
    else { window.location.href = url; }
  }

  /* ---------- Modal ---------- */
  var modal = document.getElementById('kk-modal');
  var modalBody = document.getElementById('kk-modal-body');
  var lastFocus = null;
  function openModal(title, html) {
    lastFocus = document.activeElement;
    document.getElementById('kk-modal-title').textContent = title;
    modalBody.innerHTML = html;
    modal.hidden = false;
    var f = modalBody.querySelector('input, button, a');
    if (f) { f.focus(); }
  }
  function closeModal() { modal.hidden = true; modalBody.innerHTML = ''; if (lastFocus) { lastFocus.focus(); } }
  modal.addEventListener('click', function (e) { if (e.target === modal || e.target.hasAttribute('data-modal-close')) { closeModal(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { closeModal(); } });

  /* ---------- Media library ---------- */
  function uploadFiles(files) {
    var jobs = Array.prototype.map.call(files, function (file) {
      var fd = new FormData(); fd.append('file', file);
      return api('media_upload', fd, true);
    });
    return Promise.all(jobs);
  }

  function mediaPicker(onPick) {
    openModal(t.media, '<div class="kk-media-upload"><label class="kk-btn kk-btn--primary">' + esc(t.upload)
      + '<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden></label> <span class="kk-note" data-status></span></div>'
      + '<div class="kk-media-grid" data-grid><p class="kk-note">' + esc(t.loading) + '</p></div>');
    var grid = modalBody.querySelector('[data-grid]');
    var status = modalBody.querySelector('[data-status]');
    function load() {
      api('media_list', { page: 1 }).then(function (res) {
        var rows = (res.j && res.j.rows) || [];
        grid.innerHTML = rows.length ? '' : '<p class="kk-note">—</p>';
        rows.forEach(function (m) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'kk-media-item';
          b.innerHTML = '<img src="' + esc(m.thumb) + '" alt="' + esc(m.alt) + '" loading="lazy"><span>' + esc(m.title) + '</span>';
          b.addEventListener('click', function () { closeModal(); onPick(m); });
          grid.appendChild(b);
        });
      });
    }
    modalBody.querySelector('input[type=file]').addEventListener('change', function () {
      if (!this.files.length) { return; }
      status.textContent = t.saving;
      uploadFiles(this.files).then(function (results) {
        var bad = results.filter(function (r) { return !r.ok; });
        status.textContent = bad.length ? ((bad[0].j && bad[0].j.message) || t.error) : '';
        load();
      });
    });
    load();
  }

  /* ---------- Book picker ---------- */
  function bookPicker(onPick) {
    openModal(t.insertBook, '<form class="kk-inline-form" data-find><input class="kk-input" name="q" placeholder="' + esc(t.findBook) + '" autofocus>'
      + '<button class="kk-btn kk-btn--primary" type="submit">' + esc(t.chooseBook) + '</button></form><ul class="kk-list" data-results></ul>');
    var list = modalBody.querySelector('[data-results]');
    modalBody.querySelector('[data-find]').addEventListener('submit', function (e) {
      e.preventDefault();
      var q = this.q.value.trim();
      if (!q) { return; }
      list.innerHTML = '<li class="kk-note">' + esc(t.loading) + '</li>';
      api('book_search', { q: q }).then(function (res) {
        var rows = (res.j && res.j.rows) || [];
        list.innerHTML = rows.length ? '' : '<li class="kk-note">' + esc(t.noBooks) + '</li>';
        rows.forEach(function (b) {
          var li = document.createElement('li'); li.className = 'kk-list__row';
          li.innerHTML = '<span class="kk-thumb">' + (b.thumb ? '<img src="' + esc(b.thumb) + '" alt="">' : '') + '</span>'
            + '<span class="kk-list__body"><strong>' + esc(b.title) + '</strong><span class="kk-note">' + esc(b.authors || '') + (b.year ? ' · ' + esc(b.year) : '') + '</span></span>';
          var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'kk-btn'; btn.textContent = t.chooseBook;
          btn.addEventListener('click', function () { closeModal(); onPick(b); });
          var actions = document.createElement('span'); actions.className = 'kk-list__actions'; actions.appendChild(btn);
          li.appendChild(actions); list.appendChild(li);
        });
      });
    });
  }

  /* ---------- Editor ---------- */
  var dirty = false;
  window.tinymce.baseURL = cfg.tinymce;
  window.tinymce.suffix = '.min';
  window.tinymce.remove('#kk-post-content');
  window.tinymce.init({
    selector: '#kk-post-content',
    plugins: 'lists link image paste hr charmap directionality fullscreen textcolor colorpicker media tabfocus wptextpattern',
    menubar: false,
    toolbar1: 'formatselect | bold italic | bullist numlist blockquote | alignleft aligncenter alignright | link | kkimage kkbook | ltr rtl | fullscreen',
    toolbar2: 'strikethrough hr forecolor | pastetext removeformat charmap | outdent indent | media | undo redo',
    block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Preformatted=pre',
    content_css: cfg.contentCss,
    body_class: 'kk-editor-content',
    min_height: 460,
    height: 520,
    branding: false,
    relative_urls: false,
    convert_urls: false,
    entity_encoding: 'raw',
    browser_spellcheck: true,
    paste_data_images: true,
    image_caption: true,
    image_advtab: false,
    media_alt_source: false,
    media_poster: false,
    link_title: false,
    target_list: [{ text: 'Same window', value: '' }, { text: 'New window', value: '_blank' }],
    images_upload_handler: function (blobInfo, success, failure) {
      var fd = new FormData(); fd.append('file', blobInfo.blob(), blobInfo.filename());
      api('media_upload', fd, true).then(function (res) {
        if (res.ok && res.j.medium) { success(res.j.medium); } else { failure((res.j && res.j.message) || t.error); }
      }).catch(function () { failure(t.error); });
    },
    setup: function (ed) {
      ed.addButton('kkbook', {
        icon: 'books', tooltip: t.insertBook, text: '📚',
        onclick: function () {
          bookPicker(function (b) { ed.focus(); ed.insertContent('<p>[book id="' + parseInt(b.id, 10) + '"]</p>'); });
        }
      });
      ed.addButton('kkimage', {
        icon: 'image', tooltip: t.insertImage,
        onclick: function () {
          mediaPicker(function (m) {
            ed.focus();
            var img = '<img src="' + esc(m.medium) + '" alt="' + esc(m.alt) + '" width="' + Math.min(900, m.width) + '" height="'
              + Math.round(m.height * Math.min(900, m.width) / Math.max(1, m.width)) + '" />';
            ed.insertContent(m.caption ? '<figure class="image">' + img + '<figcaption>' + esc(m.caption) + '</figcaption></figure><p></p>' : '<p>' + img + '</p>');
          });
        }
      });
      ed.on('change keyup', function () { dirty = true; });
    }
  });

  /* ---------- Featured image ---------- */
  var featId = document.getElementById('kk-featured-id');
  var featBox = document.getElementById('kk-featured');
  var featRemove = document.getElementById('kk-featured-remove');
  document.getElementById('kk-featured-set').addEventListener('click', function () {
    mediaPicker(function (m) {
      featId.value = m.id; featBox.innerHTML = '<img src="' + esc(m.thumb) + '" alt="">'; featRemove.hidden = false; dirty = true;
    });
  });
  featRemove.addEventListener('click', function () { featId.value = 0; featBox.innerHTML = ''; featRemove.hidden = true; dirty = true; });

  /* ---------- Categories ---------- */
  var addCat = document.getElementById('kk-addcat');
  if (addCat) {
    addCat.addEventListener('click', function () {
      var input = document.getElementById('kk-newcat');
      var name = input.value.trim();
      if (!name) { return; }
      api('term_save', { id: 0, taxonomy: 'category', values: { name: name } }).then(function (res) {
        if (!res.ok) { window.alert((res.j && res.j.message) || t.error); return; }
        var l = document.createElement('label'); l.className = 'kk-check';
        l.innerHTML = '<input type="checkbox" name="categories[]" value="' + parseInt(res.j.id, 10) + '" checked> ' + esc(name);
        document.getElementById('kk-catlist').appendChild(l); input.value = ''; dirty = true;
      });
    });
  }

  /* ---------- Save ---------- */
  var out = form.querySelector('.kk-saved');
  function values(intent) {
    var v = { categories: [] };
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'button') { return; }
      if (el.name === 'categories[]') { if (el.checked) { v.categories.push(parseInt(el.value, 10)); } return; }
      v[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
    });
    var ed = window.tinymce.get('kk-post-content');
    v.content = ed ? ed.getContent() : v.content;
    if (intent === 'draft') { v.status = 'draft'; }
    else if (v.status === 'draft') { v.status = 'publish'; }
    return v;
  }
  Array.prototype.forEach.call(form.querySelectorAll('[data-save]'), function (btn) {
    btn.addEventListener('click', function () {
      var intent = btn.getAttribute('data-save');
      out.textContent = t.saving;
      btn.disabled = true;
      api('post_save', { id: cfg.id, type: cfg.type, values: values(intent) }).then(function (res) {
        btn.disabled = false;
        if (!res.ok) { out.textContent = (res.j && res.j.message) || t.error; return; }
        dirty = false;
        var j = res.j;
        out.textContent = j.status === 'publish' ? t.published : (j.status === 'future' ? t.scheduled : t.saved);
        if (!cfg.id || intent === 'publish') { reloadPane(adm.api.replace(/api\.php$/, 'post.php') + '?id=' + j.id + '&saved=1'); return; }
        var lab = document.getElementById('kk-post-status-label'); if (lab) { lab.textContent = j.status_label; }
        var pv = document.getElementById('kk-post-preview'); if (pv) { pv.href = j.view; pv.hidden = false; }
        form.querySelector('[name=slug]').value = j.slug;
      }).catch(function () { btn.disabled = false; out.textContent = t.error; });
    });
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); });
  form.addEventListener('input', function () { dirty = true; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = t.unsaved; } });
}());
