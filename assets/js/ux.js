// Small usability helpers used on every page.
(function () {
    function __(text) { return (window.MX_T && window.MX_T[text]) || text; }
    function ready(fn) {
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); }
    }

    ready(function () {
        // 1. Right keyboard and autofill hints on common fields (less typing, fewer mistakes)
        document.querySelectorAll('input').forEach(function (input) {
            var name = input.name || '';
            if (name === 'phone' || input.type === 'tel') { input.setAttribute('inputmode', 'tel'); if (!input.autocomplete) { input.setAttribute('autocomplete', 'tel'); } }
            if (name === 'email' && !input.disabled) { input.setAttribute('inputmode', 'email'); input.setAttribute('autocapitalize', 'off'); input.setAttribute('spellcheck', 'false'); if (!input.autocomplete) { input.setAttribute('autocomplete', 'email'); } }
            if (name === 'full_name' && !input.autocomplete) { input.setAttribute('autocomplete', 'name'); }
        });

        // 2. Inline checks: tell people about a mistake next to the field, once they leave it
        function messageFor(input) {
            var v = input.value.trim();
            if (input.required && v === '' && input.type !== 'checkbox' && input.type !== 'file') { return __('This field is required.'); }
            if (v === '') { return ''; }
            if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { return __('Enter an email like name@example.com.'); }
            if ((input.name === 'phone' || input.type === 'tel') && !/^\+?\(?[0-9][0-9 \-().]{6,18}$/.test(v)) { return __('Enter a phone number such as 01712345678.'); }
            if (input.name === 'confirm_password') {
                var form = input.form, first = form && form.querySelector('[name=password], [name=new_password]');
                if (first && first.value !== input.value) { return __('The passwords do not match.'); }
            }
            return '';
        }
        function show(input, text) {
            var holder = input.closest('.pw-wrap') || input;
            var note = holder.parentNode.querySelector('.field-note[data-for="' + (input.name || '') + '"]');
            if (!text) {
                input.removeAttribute('aria-invalid');
                if (note) { note.remove(); }
                return;
            }
            input.setAttribute('aria-invalid', 'true');
            if (!note) {
                note = document.createElement('p');
                note.className = 'field-note';
                note.setAttribute('data-for', input.name || '');
                note.setAttribute('role', 'alert');
                holder.insertAdjacentElement('afterend', note);
                // keep the rule checklist above the message
                var rules = holder.nextElementSibling && holder.nextElementSibling.classList.contains('pw-rules') ? holder.nextElementSibling : null;
                if (rules) { rules.insertAdjacentElement('afterend', note); }
            }
            note.textContent = text;
        }
        document.querySelectorAll('form input, form textarea').forEach(function (input) {
            if (input.type === 'hidden' || input.type === 'checkbox' || input.type === 'file' || input.disabled) { return; }
            input.addEventListener('blur', function () { show(input, messageFor(input)); });
            input.addEventListener('input', function () { if (input.getAttribute('aria-invalid') === 'true') { show(input, messageFor(input)); } });
        });
        // password and confirmation: check as the second one is typed
        document.querySelectorAll('input[name=confirm_password]').forEach(function (confirmField) {
            var first = confirmField.form && confirmField.form.querySelector('[name=password], [name=new_password]');
            if (first) { first.addEventListener('input', function () { if (confirmField.value) { show(confirmField, messageFor(confirmField)); } }); }
        });

        // 3. Put the cursor in the first field of the login screen (fewer clicks)
        var loginEmail = document.querySelector('#loginForm [name=email]');
        if (loginEmail && !location.hash && window.innerWidth > 700) { loginEmail.focus(); }

        // 4. "/" jumps to the search box, Escape clears it
        var search = document.querySelector('.search-input, input[type=search]');
        if (search) {
            document.addEventListener('keydown', function (e) {
                var tag = (document.activeElement && document.activeElement.tagName) || '';
                if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag) && !e.ctrlKey && !e.metaKey) { e.preventDefault(); search.focus(); }
                if (e.key === 'Escape' && document.activeElement === search && search.value) { search.value = ''; search.dispatchEvent(new Event('input', { bubbles: true })); }
            });
            if (!search.placeholder.includes('/')) { search.title = __('Press / to search'); }
        }

        // 5. Warn before leaving a form with unsaved changes (error prevention)
        var profileForm = document.querySelector('form input[name=form][value=profile]');
        profileForm = profileForm && profileForm.form;
        if (profileForm) {
            var dirty = false;
            profileForm.addEventListener('input', function () { dirty = true; });
            profileForm.addEventListener('submit', function () { dirty = false; });
            window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
        }

        // 6. Sidebar sections fold away, and the app remembers which ones (fewer choices at once)
        var saved = {};
        try { saved = JSON.parse(localStorage.getItem('menuFolded') || '{}'); } catch (e) { saved = {}; }
        document.querySelectorAll('.sidebar-menu .menu-section').forEach(function (section, i) {
            var title = section.querySelector('.menu-title');
            if (!title) { return; }
            var key = 's' + i;
            var hasActive = !!section.querySelector('.menu-item.active');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'menu-title menu-title-btn';
            btn.innerHTML = '<span></span><i class="fas fa-chevron-down" aria-hidden="true"></i>';
            btn.firstChild.textContent = title.textContent;
            title.replaceWith(btn);
            function set(folded) {
                section.classList.toggle('folded', folded);
                btn.setAttribute('aria-expanded', folded ? 'false' : 'true');
            }
            set(!hasActive && saved[key] === true);
            btn.addEventListener('click', function () {
                var folded = !section.classList.contains('folded');
                set(folded);
                saved[key] = folded;
                try { localStorage.setItem('menuFolded', JSON.stringify(saved)); } catch (e) { /* ignore */ }
            });
        });
    });
})();
