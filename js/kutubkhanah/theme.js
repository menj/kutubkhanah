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
