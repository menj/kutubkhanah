/* Kutubkhanah OPAC theme helpers. GNU GPL v3 or later. */
(function () {
  'use strict';

  // Reading progress bars grow in on load (skipped when the visitor prefers less motion).
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  Array.prototype.forEach.call(document.querySelectorAll('.kk-progress__bar'), function (bar) {
    if (reduce) { return; }
    var target = bar.style.width;
    bar.style.width = '0%';
    requestAnimationFrame(function () {
      bar.style.transition = 'width 1s ease-out';
      bar.style.width = target;
    });
  });

  // An empty hero search opens the full catalogue instead of an empty result page.
  var hero = document.querySelector('.kk-hero__search');
  if (hero) {
    hero.addEventListener('submit', function (e) {
      var input = hero.querySelector('input[type="search"]');
      if (input && !input.value.trim()) {
        e.preventDefault();
        window.location.href = 'index.php?p=shelf';
      }
    });
  }

  // Prevent double submission of the contact and access forms.
  Array.prototype.forEach.call(document.querySelectorAll('form.kk-form'), function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.disabled = true; }
    });
  });
})();

/* Journal: reply to a comment */
(function () {
  var form = document.getElementById('kk-comment-form');
  if (!form) { return; }
  var parent = form.querySelector('input[name="parent"]');
  var note = form.querySelector('.kk-comment-form__replying');
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-reply]') : null;
    if (a) {
      e.preventDefault();
      parent.value = a.getAttribute('data-reply');
      note.querySelector('strong').textContent = a.getAttribute('data-name');
      note.hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var ta = form.querySelector('textarea'); if (ta) { ta.focus({ preventScroll: true }); }
      return;
    }
    if (e.target.hasAttribute && e.target.hasAttribute('data-cancel-reply')) { parent.value = '0'; note.hidden = true; }
  });
}());

/* Hero search: the "search by" menu decides which field the box searches. */
(function () {
  var by = document.getElementById('kk-hero-by'), q = document.getElementById('kk-hero-q');
  if (by && q) by.addEventListener('change', function () { q.name = by.value; });
})();

/* Saved books: the list lives in this browser (localStorage); the server only draws cards for the ids it is sent. */
(function () {
  var KEY = 'kk_saved';
  function read() { try { var v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.filter(function (n) { return n > 0; }).slice(0, 30) : []; } catch (e) { return []; } }
  function write(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
  var base = (document.querySelector('base') || {}).href || '';

  function paint() {
    var ids = read();
    document.querySelectorAll('[data-kk-save]').forEach(function (b) {
      var on = ids.indexOf(parseInt(b.getAttribute('data-kk-save'), 10)) !== -1;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('is-on', on);
      var l = b.querySelector('[data-save-label]'); if (l) l.textContent = on ? l.getAttribute('data-on') || 'Saved' : l.getAttribute('data-off') || 'Save';
    });
    document.querySelectorAll('[data-kk-saved-count]').forEach(function (n) { n.textContent = ids.length; n.hidden = !ids.length; });
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-kk-save]') : null;
    if (!b) return;
    e.preventDefault();
    var id = parseInt(b.getAttribute('data-kk-save'), 10), ids = read(), i = ids.indexOf(id);
    if (i === -1) ids.push(id); else ids.splice(i, 1);
    write(ids); paint();
  });

  document.querySelectorAll('[data-save-label]').forEach(function (l) { l.setAttribute('data-off', l.textContent); });
  paint();

  var box = document.getElementById('kk-mylist');
  if (box) {
    var grid = document.getElementById('kk-mylist-grid'), empty = document.getElementById('kk-mylist-empty'), actions = document.getElementById('kk-mylist-actions');
    var borrow = document.getElementById('kk-mylist-borrow'), clear = document.getElementById('kk-mylist-clear');
    function draw() {
      var ids = read();
      empty.hidden = ids.length > 0; actions.hidden = ids.length === 0;
      if (borrow) borrow.href = 'index.php?p=borrow&ids=' + ids.join(',');
      if (!ids.length) { grid.innerHTML = ''; return; }
      fetch('index.php?p=my-list&fragment=1&ids=' + ids.join(','), { credentials: 'same-origin' })
        .then(function (r) { return r.text(); }).then(function (h) { grid.innerHTML = h; paint(); });
    }
    box.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('[data-kk-save]')) setTimeout(draw, 0);
    });
    if (clear) clear.addEventListener('click', function () { write([]); paint(); draw(); });
    draw();
  }
})();
