/*
 * Auth stránky (/pages/auth/*.html) – napojení na API přes funkce z api.js
 * (login, register, logout, requestPasswordReset, resetPassword, verifyEmail).
 * api.js musí být načtený před tímto souborem.
 * Pokud router vkládá stránky přes innerHTML (skripty se nespustí), zavolej po vložení window.initAuthPage().
 */
(function () {
    'use strict';
    if (window.initAuthPage) { window.initAuthPage(); return; } // soubor načten podruhé = jen znovu init

    var PAGE = '/www'; // prefix stránek front-endu (viz SITEMAP.md)

    function val(f, name) { return f.elements[name] ? f.elements[name].value : ''; }

    function msg(root, type, text) {
        var el = root.querySelector('.auth-msg');
        if (!el) return;
        el.className = 'auth-msg is-' + type;
        el.textContent = text;
        el.hidden = false;
    }

    function fieldError(f, name, text) {
        var el = f.querySelector('[data-error-for="' + name + '"]');
        if (el) el.textContent = text;
        if (f.elements[name]) f.elements[name].setAttribute('aria-invalid', 'true');
    }

    function clearErrors(f) {
        f.querySelector('.auth-msg').hidden = true;
        [].forEach.call(f.querySelectorAll('[data-error-for]'), function (e) { e.textContent = ''; });
        [].forEach.call(f.elements, function (i) { i.removeAttribute('aria-invalid'); });
    }

    // Chyba z api.js má status jen tehdy, když odpověděl server (jinak síť / neplatná odpověď)
    function apiError(err) {
        return err.status ? err.message : 'Server není dostupný. Zkuste to prosím později.';
    }

    // Token je poslední část adresy (/auth/verify/<token>) nebo ?token=
    function tokenFromUrl() {
        var q = new URLSearchParams(location.search).get('token');
        if (q) return q;
        var parts = location.pathname.split('/').filter(Boolean);
        return parts[parts.length - 1] || '';
    }

    /* ---------- formuláře ---------- */
    var actions = {
        login: async function (f) {
            await login({ email: val(f, 'email').trim(), password: val(f, 'password') });
            location.href = PAGE + '/account/';
        },
        register: async function (f) {
            if (!f.elements.terms.checked) {
                fieldError(f, 'terms', 'Pro registraci je nutný souhlas s podmínkami.');
                return;
            }
            var r = await register({
                email: val(f, 'email').trim(),
                password: val(f, 'password'),
                password_confirm: val(f, 'password_confirm')
            });
            f.reset();
            msg(f, 'ok', r.message);
        },
        forgot: async function (f) {
            var r = await requestPasswordReset({ email: val(f, 'email').trim() });
            msg(f, 'ok', r.message);
        },
        reset: async function (f) {
            var r = await resetPassword({
                token: tokenFromUrl(),
                password: val(f, 'password'),
                password_confirm: val(f, 'password_confirm')
            });
            msg(f, 'ok', r.message);
            setTimeout(function () { location.href = PAGE + '/auth/login'; }, 1500);
        }
    };

    async function onSubmit(e) {
        var f = e.target.closest ? e.target.closest('form[data-auth]') : null;
        if (!f || !actions[f.dataset.auth]) return;
        e.preventDefault();
        var btn = f.querySelector('button[type=submit]');
        clearErrors(f);
        btn.disabled = true;
        try {
            await actions[f.dataset.auth](f);
        } catch (err) {
            msg(f, 'error', apiError(err));
            Object.keys(err.errors || {}).forEach(function (k) {
                if (err.errors[k]) fieldError(f, k, err.errors[k]);
            });
        }
        btn.disabled = false;
    }

    /* ---------- stránky, které volají API hned po načtení ---------- */
    function done(root, success, title, text) {
        root.querySelector('[data-role=title]').textContent = title;
        root.querySelector('[data-role=text]').textContent = text;
        var icon = root.querySelector('[data-role=icon]');
        if (icon) icon.hidden = !success;
        root.querySelector('[data-role=link]').hidden = false;
    }

    var loaders = {
        verify: async function (root) {
            try {
                var r = await verifyEmail({ token: tokenFromUrl() });
                done(root, true, 'E-mail ověřen', r.message);
            } catch (err) {
                done(root, false, 'Ověření se nezdařilo', apiError(err));
            }
        },
        logout: async function (root) {
            try {
                var r = await logout();
                done(root, true, 'Odhlášení', r.message);
            } catch (err) {
                done(root, false, 'Odhlášení', err.status === 401 ? 'Nejste přihlášeni.' : apiError(err));
            }
        }
    };

    /* ---------- síla hesla (jen vizuální nápověda) ---------- */
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.matches || !el.matches('[data-strength]')) return;
        var p = el.value, n = 0;
        if (p.length >= 8) n++;
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) n++;
        if (/\d/.test(p)) n++;
        if (/[^A-Za-z0-9]/.test(p)) n++;
        if (!p) n = 0;
        var colors = ['#26352D', '#FF6B6B', '#FFB020', '#C8F135', '#C8F135'];
        var labels = ['–', 'Slabé', 'Střední', 'Dobré', 'Silné'];
        [].forEach.call(el.form.querySelectorAll('.auth-bars span'), function (b, i) {
            b.style.background = i < n ? colors[n] : colors[0];
        });
        el.form.querySelector('[data-role=strength]').textContent = labels[n];
    });

    function init() {
        var el = document.querySelector('[data-auth-load]');
        if (el && !el.dataset.done) {
            el.dataset.done = '1';
            loaders[el.dataset.authLoad](el);
        }
    }

    document.addEventListener('submit', onSubmit);
    window.initAuthPage = init;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
