/* Kutubkhanah installer: show/hide the generated password. */
(function () {
    var btn = document.getElementById('kk-show');
    var input = document.getElementById('kk-password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
})();
