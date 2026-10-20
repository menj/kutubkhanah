/* Kutubkhanah installer: show/hide the generated password, and show only the fields the chosen database needs. */
(function () {
    var btn = document.getElementById('kk-show');
    var input = document.getElementById('kk-password');
    if (btn && input) {
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    }

    var type = document.getElementById('kk-dbtype');
    if (type) {
        var sync = function () {
            document.querySelectorAll('[data-for]').forEach(function (box) {
                var on = box.getAttribute('data-for').split(' ').indexOf(type.value) !== -1;
                box.hidden = !on;
                box.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
            });
        };
        type.addEventListener('change', sync);
        sync();
    }

    /* Last step: when the new site answers, ask the installer to delete its own folder. */
    var note = document.getElementById('kk-cleanup');
    if (note && window.fetch) {
        var say = function (t) { note.textContent = t; };
        fetch(note.getAttribute('data-home'), { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) throw new Error('The site returned ' + r.status + '.');
            var body = new URLSearchParams(); body.set('csrf', note.getAttribute('data-csrf'));
            return fetch('index.php?step=cleanup', { method: 'POST', body: body, credentials: 'same-origin' });
        }).then(function (r) { return r.json(); }).then(function (j) {
            say(j.removed ? 'The installer has been removed from your server.' : 'Delete the install folder by hand. ' + (j.message || ''));
        }).catch(function (e) { say('Your site did not answer as expected (' + e.message + '). The installer was kept. Delete the install folder by hand once it works.'); });
    } else if (note) { note.textContent = 'Delete the install folder from your server once you have logged in.'; }
})();
