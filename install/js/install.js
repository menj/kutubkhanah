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
})();
