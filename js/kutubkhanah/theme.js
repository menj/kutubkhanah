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
