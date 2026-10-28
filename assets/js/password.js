// Password fields: a show/hide eye on every one, and a live rule checklist on new-password fields.
(function () {
    var RULES = [
        ['len', 'At least 8 characters', function (v) { return v.length >= 8; }],
        ['low', 'A lowercase letter (a to z)', function (v) { return /[a-z]/.test(v); }],
        ['up', 'An uppercase letter (A to Z)', function (v) { return /[A-Z]/.test(v); }],
        ['num', 'A number (0 to 9)', function (v) { return /[0-9]/.test(v); }],
        ['sym', 'A symbol such as ! @ # $ %', function (v) { return /[^A-Za-z0-9\s]/.test(v); }]
    ];
    var PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\\s]).{8,72}';

    function addEye(input) {
        var wrap = document.createElement('span');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('aria-pressed', 'false');
        btn.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.firstChild.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
        wrap.appendChild(btn);
        return wrap;
    }

    function addRules(input, wrap) {
        var list = document.createElement('ul');
        list.className = 'pw-rules';
        list.setAttribute('aria-live', 'polite');
        RULES.forEach(function (r) {
            var li = document.createElement('li');
            li.dataset.rule = r[0];
            li.innerHTML = '<i class="fas fa-circle" aria-hidden="true"></i> <span></span>';
            li.lastChild.textContent = r[1];
            list.appendChild(li);
        });
        wrap.parentNode.insertBefore(list, wrap.nextSibling);
        function update() {
            var v = input.value;
            RULES.forEach(function (r) {
                var li = list.querySelector('[data-rule="' + r[0] + '"]');
                var ok = r[2](v);
                li.classList.toggle('ok', ok);
                li.firstChild.className = ok ? 'fas fa-circle-check' : 'fas fa-circle';
            });
        }
        input.addEventListener('input', update);
        update();
    }

    function init() {
        var fields = document.querySelectorAll('input[type="password"]');
        for (var i = 0; i < fields.length; i++) {
            var input = fields[i];
            var wrap = addEye(input);
            var isNew = input.getAttribute('autocomplete') === 'new-password' &&
                input.name !== 'confirm_password';
            if (isNew) {
                input.setAttribute('pattern', PATTERN);
                input.setAttribute('title', 'Use 8 or more characters with a lowercase letter, an uppercase letter, a number and a symbol.');
                addRules(input, wrap);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
