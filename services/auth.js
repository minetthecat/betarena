/* BetArena auth pages. All API requests go through /services/api.js. */
(function () {
    'use strict';

    if (window.BetArenaAuth && window.BetArenaAuth.version === '2') {
        window.BetArenaAuth.init();
        return;
    }

    const busyForms = new WeakSet();
    const logoutCards = new WeakMap();
    const requiredApi = ['login', 'register', 'logout', 'getAccount', 'requestPasswordReset', 'resetPassword', 'verifyEmail'];
    let loadingApi = null;
    let scanScheduled = false;

    function hasApi() {
        return requiredApi.every(name => typeof window[name] === 'function');
    }

    function ensureApi() {
        if (hasApi()) return Promise.resolve();
        if (loadingApi) return loadingApi;
        loadingApi = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/services/api.js?v=20261023-2';
            script.onload = () => {
                if (hasApi()) resolve();
                else reject(new Error('Soubor /services/api.js neobsahuje potřebné funkce nového API.'));
            };
            script.onerror = () => reject(new Error('Nepodařilo se načíst /services/api.js.'));
            document.head.appendChild(script);
        }).catch(error => {
            loadingApi = null;
            throw error;
        });
        return loadingApi;
    }

    function dataOf(response) {
        if (!response || response.success !== true || !Object.prototype.hasOwnProperty.call(response, 'data')) {
            throw new Error('Server vrátil neočekávaný formát odpovědi.');
        }
        return response.data;
    }

    function changed(authenticated, user) {
        document.dispatchEvent(new CustomEvent('auth:changed', {
            detail: { authenticated: authenticated, user: user || null }
        }));
    }

    function redirect(element) {
        if (!element.isConnected) return;
        const path = element.getAttribute('data-auth-redirect');
        if (!path) return;
        const url = new URL(path, window.location.origin);
        if (url.origin !== window.location.origin) return;
        // Full navigation also refreshes headers/components which read auth state.
        window.location.assign(url.pathname + url.search + url.hash);
    }

    function field(form, name) {
        return form.elements.namedItem(name);
    }

    function clearMessages(form) {
        form.querySelectorAll('[data-error-for]').forEach(node => { node.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach(node => { node.removeAttribute('aria-invalid'); });
        const box = form.querySelector('.auth-msg');
        if (box) {
            box.textContent = '';
            box.hidden = true;
            box.removeAttribute('data-state');
            box.className = 'auth-msg';
        }
    }

    function fieldError(form, name, message) {
        form.querySelectorAll('[data-error-for]').forEach(node => {
            if (node.getAttribute('data-error-for') === name) node.textContent = message;
        });
        const input = field(form, name);
        if (input) input.setAttribute('aria-invalid', 'true');
    }

    function message(form, text, state) {
        const box = form.querySelector('.auth-msg');
        if (box) {
            box.textContent = text;
            box.hidden = false;
            box.setAttribute('data-state', state);
            box.className = 'auth-msg is-' + (state === 'success' ? 'ok' : 'error');
        }
    }

    function validate(form, mode) {
        let valid = true;
        const email = field(form, 'email');
        const password = field(form, 'password');
        if (mode !== 'reset' && (!email || !email.value.trim() || !email.validity.valid)) {
            fieldError(form, 'email', 'Zadej platný e-mail.');
            valid = false;
        }
        if (mode !== 'forgot' && (!password || !password.value)) {
            fieldError(form, 'password', 'Zadej heslo.');
            valid = false;
        }
        if (mode === 'register' || mode === 'reset') {
            const confirm = field(form, 'password_confirm');
            const terms = field(form, 'terms');
            if (!confirm || !confirm.value || confirm.value !== password.value) {
                fieldError(form, 'password_confirm', 'Hesla se neshodují.');
                valid = false;
            }
            if (mode === 'register' && (!terms || !terms.checked)) {
                fieldError(form, 'terms', 'Potvrď věk a souhlas s podmínkami.');
                valid = false;
            }
        }
        if (!valid) {
            const invalid = form.querySelector('[aria-invalid="true"]');
            if (invalid) invalid.focus();
        }
        return valid;
    }

    async function submit(form, mode) {
        if (busyForms.has(form)) return;
        clearMessages(form);
        if (!validate(form, mode)) return;
        busyForms.add(form);
        form.setAttribute('aria-busy', 'true');
        const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
        const originalButtons = buttons.map(button => ({ button: button, disabled: button.disabled, text: button.textContent }));
        buttons.forEach(button => { button.disabled = true; button.textContent = 'Zpracovávám…'; });
        const data = {};
        if (field(form, 'email')) data.email = field(form, 'email').value.trim();
        if (field(form, 'password')) data.password = field(form, 'password').value;

        try {
            await ensureApi();
            if (mode === 'login') {
                dataOf(await window.login(data));
                // This second HTTP request proves the browser kept and sent the session cookie.
                let user;
                try {
                    user = dataOf(await window.getAccount());
                    if (!user || !user.user_id) throw new Error('Chybí uživatel v odpovědi.');
                } catch (error) {
                    throw new Error('Přihlášení API přijalo, ale ověření session selhalo: ' + error.message);
                }
                changed(true, user);
                field(form, 'password').value = '';
                message(form, 'Přihlášení proběhlo úspěšně.', 'success');
                redirect(form);
            } else if (mode === 'register') {
                data.password_confirm = field(form, 'password_confirm').value;
                dataOf(await window.register(data));
                // Registration does not create an authenticated PHP session.
                field(form, 'password').value = '';
                field(form, 'password_confirm').value = '';
                message(form, 'Účet byl vytvořen. Nyní se můžeš přihlásit.', 'success');
            } else if (mode === 'forgot') {
                dataOf(await window.requestPasswordReset(data));
                message(form, 'Pokud účet existuje, byl připraven odkaz pro obnovení hesla.', 'success');
            } else if (mode === 'reset') {
                data.token = tokenFromUrl('reset-password');
                if (!data.token) throw new Error('V adrese chybí token pro obnovení hesla.');
                data.password_confirm = field(form, 'password_confirm').value;
                dataOf(await window.resetPassword(data));
                field(form, 'password').value = '';
                field(form, 'password_confirm').value = '';
                message(form, 'Heslo bylo změněno. Nyní se můžeš přihlásit.', 'success');
                const link = form.closest('.auth-card').querySelector('[data-role=link]');
                if (link) link.hidden = false;
            }
        } catch (error) {
            if (error.errors && typeof error.errors === 'object') {
                Object.keys(error.errors).forEach(name => {
                    const detail = error.errors[name];
                    fieldError(form, name, Array.isArray(detail) ? detail.join(' ') : String(detail));
                });
            }
            message(form, error.message || 'Požadavek se nepodařilo dokončit.', 'error');
        } finally {
            busyForms.delete(form);
            form.removeAttribute('aria-busy');
            originalButtons.forEach(state => { state.button.disabled = state.disabled; state.button.textContent = state.text; });
        }
    }

    async function signOut(card) {
        const previous = logoutCards.get(card);
        if (previous === 'running' || previous === 'done') return;
        logoutCards.set(card, 'running');
        const text = card.querySelector('[data-role="text"]');
        const link = card.querySelector('[data-role="link"]');
        const retry = card.querySelector('[data-auth-retry]');
        card.setAttribute('aria-busy', 'true');
        if (text) text.textContent = 'Odhlašuji…';
        if (link) link.hidden = true;
        if (retry) retry.hidden = true;
        try {
            await ensureApi();
            try {
                dataOf(await window.logout());
            } catch (error) {
                // Already logged out is harmless, but still verify the server session.
                if (Number(error.status) !== 401) throw error;
            }
            let anonymous = false;
            try {
                await window.getAccount();
            } catch (error) {
                if (Number(error.status) === 401) anonymous = true;
                else throw error;
            }
            if (!anonymous) throw new Error('Server po odhlášení stále vrací přihlášený účet.');
            logoutCards.set(card, 'done');
            changed(false, null);
            if (text) text.textContent = 'Odhlášení proběhlo úspěšně.';
            if (link) link.hidden = false;
            redirect(card);
        } catch (error) {
            logoutCards.set(card, 'failed');
            if (text) text.textContent = 'Odhlášení se nepodařilo: ' + (error.message || 'Neznámá chyba.');
            if (retry) retry.hidden = false;
        } finally {
            card.removeAttribute('aria-busy');
        }
    }

    function tokenFromUrl(action) {
        const url = new URL(window.location.href);
        const query = url.searchParams.get('token');
        if (query) return query;
        const prefix = '/account/' + action + '/';
        if (!url.pathname.startsWith(prefix)) return '';
        const rest = url.pathname.slice(prefix.length).replace(/\/$/, '');
        if (!rest || rest.includes('/')) return '';
        try { return decodeURIComponent(rest); } catch (error) { return ''; }
    }

    const verifyCards = new WeakSet();
    async function verify(card) {
        if (verifyCards.has(card)) return;
        verifyCards.add(card);
        const title = card.querySelector('[data-role=title]');
        const text = card.querySelector('[data-role=text]');
        const icon = card.querySelector('[data-role=icon]');
        const link = card.querySelector('[data-role=link]');
        try {
            const token = tokenFromUrl('verify');
            if (!token) throw new Error('V adrese chybí token pro ověření e-mailu.');
            await ensureApi();
            dataOf(await window.verifyEmail({ token: token }));
            if (title) title.textContent = 'E-mail ověřen';
            if (text) text.textContent = 'E-mail byl úspěšně ověřen. Nyní se můžeš přihlásit.';
            if (icon) icon.hidden = false;
        } catch (error) {
            if (title) title.textContent = 'Ověření se nezdařilo';
            if (text) text.textContent = error.message;
            if (icon) icon.hidden = true;
        } finally { if (link) link.hidden = false; }
    }

    function init() {
        document.querySelectorAll('[data-auth-page=verify], [data-auth-load=verify]').forEach(verify);
        document.querySelectorAll('[data-auth-page="logout"], [data-auth-load="logout"]').forEach(card => {
            if (!logoutCards.has(card)) signOut(card);
        });
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form || !form.matches('form[data-auth]')) return;
        const mode = form.getAttribute('data-auth');
        if (!['login', 'register', 'forgot', 'reset'].includes(mode)) return;
        event.preventDefault();
        // Prevent a cached legacy bubble handler from submitting a second request.
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        submit(form, mode);
    }, true);

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-auth-retry]');
        if (!button) return;
        const card = button.closest('[data-auth-page="logout"], [data-auth-load="logout"]');
        if (card) { event.preventDefault(); signOut(card); }
    });

    document.addEventListener('input', event => {
        const input = event.target;
        if (!input.matches || !input.matches('[data-strength]') || !input.form) return;
        const p = input.value;
        let strength = 0;
        if (p.length >= 8) strength++;
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) strength++;
        if (/\d/.test(p)) strength++;
        if (/[^A-Za-z0-9]/.test(p)) strength++;
        if (!p) strength = 0;
        const colors = ['#26352D', '#FF6B6B', '#FFB020', '#C8F135', '#C8F135'];
        input.form.querySelectorAll('.auth-bars span').forEach((bar, i) => { bar.style.background = i < strength ? colors[strength] : colors[0]; });
        const label = input.form.querySelector('[data-role=strength]');
        if (label) label.textContent = ['–', 'Slabé', 'Střední', 'Dobré', 'Silné'][strength];
    });

    // Delegated forms + observer support fragments inserted by a SPA router.
    const observer = new MutationObserver(() => {
        if (scanScheduled) return;
        scanScheduled = true;
        Promise.resolve().then(() => { scanScheduled = false; init(); });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
    window.BetArenaAuth = { version: '2', init: init, ready: ensureApi };
    window.initAuthPage = init;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
