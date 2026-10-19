/* Kutubkhanah: public shelf search and reading-status filter. GNU GPL v3 or later. */
(function () {
  'use strict';

  function fold(s) {
    return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  }

  function init(root) {
    if (root.getAttribute('data-kk-ready')) { return; }
    root.setAttribute('data-kk-ready', '1');
    var cards = Array.prototype.slice.call(root.querySelectorAll('.kk-card'));
    var groups = Array.prototype.slice.call(root.querySelectorAll('.kk-shelf__group'));
    var search = root.querySelector('[data-kk-search]');
    var status = root.querySelector('[data-kk-status]');
    var genre = root.querySelector('[data-kk-genre]');
    var format = root.querySelector('[data-kk-format]');
    var lang = root.querySelector('[data-kk-lang]');
    var counter = root.querySelector('[data-kk-count]');
    var none = root.querySelector('[data-kk-none]');
    var timer = null;

    function apply() {
      var q = fold(search ? search.value.trim() : '');
      var st = status ? status.value : '';
      var gn = genre ? genre.value : '';
      var fm = format ? format.value : '';
      var lg = lang ? lang.value : '';
      var shown = 0;
      cards.forEach(function (c) {
        var fmOk = !fm || (fm === 'Digital Archive' ? c.getAttribute('data-digital') === '1' : c.getAttribute('data-format') === fm);
        var ok = (!q || c.getAttribute('data-search').indexOf(q) !== -1) && (!st || c.getAttribute('data-status') === st)
          && (!gn || c.getAttribute('data-genres').indexOf(',' + gn + ',') !== -1) && fmOk
          && (!lg || c.getAttribute('data-lang') === lg);
        c.hidden = !ok;
        if (ok) { shown++; }
      });
      groups.forEach(function (g) { g.hidden = !g.querySelector('.kk-card:not([hidden])'); });
      if (counter) {
        counter.textContent = shown + ' ' + counter.getAttribute(shown === 1 ? 'data-one' : 'data-many');
      }
      if (none) { none.hidden = shown !== 0; }
    }

    if (search) {
      search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(apply, 120); });
    }
    [status, genre, format, lang].forEach(function (el) { if (el) { el.addEventListener('change', apply); } });
    // Filters preselected from the URL (e.g. "Digital Archives" or a genre link) apply on load.
    if ((genre && genre.value) || (format && format.value) || (lang && lang.value)) { apply(); }
  }

  function ready() { Array.prototype.forEach.call(document.querySelectorAll('.kk-shelf'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
})();
