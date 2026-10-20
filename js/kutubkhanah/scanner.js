/* Kutubkhanah: ISBN scanner (camera via html5-qrcode, plus manual entry). GNU GPL v3 or later. */
(function () {
  'use strict';

  var cfg = window.kkAdmin || {};
  var t = cfg.i18n || {};
  var $ = function (s) { return document.querySelector(s); };
  if (!$('#kk-reader')) { return; }

  var elStart = $('#kk-start'), elStop = $('#kk-stop'), elMsg = $('#kk-camera-msg');
  var elResults = $('#kk-results'), elCount = $('#kk-count'), elEmpty = $('#kk-empty');
  var elManual = $('#kk-manual'), elIsbn = $('#kk-isbn');
  var elContinuous = $('#kk-continuous'), elCopy = $('#kk-copy');

  // A previous visit to this page (the base system loads pages by AJAX) may have left a camera running.
  if (window.kkScanner) { try { window.kkScanner.stop(); } catch (e) {} window.kkScanner = null; }

  var scanner = null, running = false, count = 0, last = { code: '', time: 0 }, chain = Promise.resolve();

  function msg(text) { elMsg.textContent = text || ''; }

  function valid13(s) {
    if (!/^\d{13}$/.test(s)) { return false; }
    var sum = 0;
    for (var i = 0; i < 12; i++) { sum += parseInt(s[i], 10) * (i % 2 ? 3 : 1); }
    return (10 - (sum % 10)) % 10 === parseInt(s[12], 10);
  }

  function valid10(s) {
    if (!/^\d{9}[\dX]$/.test(s)) { return false; }
    var sum = 0;
    for (var i = 0; i < 10; i++) { sum += (s[i] === 'X' ? 10 : parseInt(s[i], 10)) * (10 - i); }
    return sum % 11 === 0;
  }

  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) { return; }
      var ctx = new Ctx(), osc = ctx.createOscillator(), gain = ctx.createGain();
      osc.frequency.value = 880; gain.gain.value = 0.05;
      osc.connect(gain); gain.connect(ctx.destination);
      osc.start(); osc.stop(ctx.currentTime + 0.08);
      osc.onended = function () { ctx.close(); };
    } catch (e) { /* sound is optional */ }
  }

  function addItem(isbn) {
    var li = document.createElement('li');
    li.className = 'kk-result is-pending';
    li.innerHTML = '<span class="kk-thumb" aria-hidden="true"></span><span class="kk-result__body">' +
      '<strong class="kk-result__title"></strong><span class="kk-note kk-result__meta"></span></span>';
    li.querySelector('.kk-result__title').textContent = isbn;
    li.querySelector('.kk-result__meta').textContent = t.looking;
    elResults.insertBefore(li, elResults.firstChild);
    elEmpty.hidden = true;
    elCount.textContent = ++count;
    return li;
  }

  function fill(li, state, title, meta, cover, id) {
    li.className = 'kk-result is-' + state;
    li.querySelector('.kk-result__title').textContent = title;
    var m = li.querySelector('.kk-result__meta');
    m.textContent = meta;
    if (id) {
      var a = document.createElement('a');
      a.href = cfg.edit + id;
      a.textContent = ' · Edit';
      m.appendChild(a);
    }
    if (cover) {
      var img = document.createElement('img');
      img.src = cover; img.alt = '';
      li.querySelector('.kk-thumb').appendChild(img);
    }
  }

  function modeValue() {
    var r = document.querySelector('input[name="kk-mode"]:checked');
    return r ? r.value : 'library';
  }

  function send(isbn) {
    var li = addItem(isbn);
    chain = chain.then(function () {
      return fetch(cfg.api + '?action=scan', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-KK-Token': cfg.token },
        body: JSON.stringify({ isbn: isbn, add_copy: !!elCopy.checked, mode: modeValue() })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, j: j }; });
      }).then(function (res) {
        var j = res.j || {};
        if (!res.ok) { fill(li, 'error', isbn, j.message || t.failed); return; }
        if (j.status === 'wish_added') {
          fill(li, 'created', j.title, t.wishadded + (j.authors ? ' · ' + j.authors : ''), '', 0);
          return;
        }
        if (j.status === 'wished') { fill(li, 'duplicate', j.title, t.wished, '', 0); return; }
        if (j.status === 'owned') { fill(li, 'duplicate', j.title, t.owned, '', j.id); return; }
        if (j.status === 'created') {
          fill(li, 'created', j.title, (j.from_wishlist ? t.fromwish : t.created) + (j.authors ? ' · ' + j.authors : '') + (j.sources ? ' · ' + t.from + ' ' + j.sources : ''), j.cover, j.id);
        } else if (j.status === 'incomplete') {
          fill(li, 'incomplete', j.isbn, t.incomplete, j.cover, j.id);
        } else {
          fill(li, 'duplicate', j.title, (j.added_copy ? t.copyadded : t.duplicate) + ' · ' + j.copies + ' ' + (j.copies === 1 ? t.copy : t.copies), j.cover, j.id);
        }
      }).catch(function () { fill(li, 'error', isbn, t.failed); });
    });
  }

  function submit(raw) {
    var s = String(raw || '').replace(/[^0-9Xx]/g, '').toUpperCase();
    if (!(valid13(s) || valid10(s))) { msg(t.invalid); return false; }
    msg('');
    send(s);
    return true;
  }

  function onDecode(text) {
    // ISBN barcodes are EAN-13 starting 978/979; ignore other barcodes.
    if (!/^97[89]\d{10}$/.test(text) || !valid13(text)) { return; }
    var now = Date.now();
    if (text === last.code && now - last.time < 4000) { return; }
    last = { code: text, time: now };
    beep();
    submit(text);
    if (!elContinuous.checked) { stop(); }
  }

  function start() {
    if (!window.isSecureContext || !navigator.mediaDevices || !window.Html5Qrcode) { msg(t.insecure); return; }
    scanner = scanner || new Html5Qrcode('kk-reader', {
      formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13],
      experimentalFeatures: { useBarCodeDetectorIfSupported: true },
      verbose: false
    });
    window.kkScanner = scanner;
    var box = function (w) { var width = Math.min(w - 24, 340); return { width: width, height: Math.round(width * 0.55) }; };
    scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: box }, onDecode, function () {})
      .then(function () { running = true; elStart.hidden = true; elStop.hidden = false; msg(t.scanning); })
      .catch(function (err) { msg(/NotAllowed|Permission/i.test(String(err && (err.name || err))) ? t.denied : t.nocamera); });
  }

  function stop() {
    if (!scanner || !running) { return; }
    scanner.stop().then(function () {
      scanner.clear(); running = false; elStart.hidden = false; elStop.hidden = true; msg('');
    }).catch(function () {});
  }

  elStart.addEventListener('click', start);
  elStop.addEventListener('click', stop);
  elManual.addEventListener('submit', function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (submit(elIsbn.value)) { elIsbn.value = ''; }
    elIsbn.focus();
  });
  window.addEventListener('pagehide', stop);
})();
