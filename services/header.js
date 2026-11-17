  (function () {
    if (window.BetArenaHeaderService) { window.BetArenaHeaderService.init(); return; }
    function mount() {
    'use strict';


    function $(id) { return document.getElementById(id); }

    var navbar = $('navbar');
    if (!navbar || navbar.dataset.authHeaderBound) return;
    navbar.dataset.authHeaderBound = '1';
    var toggle = $('navbar-toggle');
    var account = $('account');
    var accountToggle = $('account-toggle');
    var guestActions = navbar.querySelector('.navbar__actions--guest');
    var userActions = navbar.querySelector('.navbar__actions--user');
    function setAuthView(isUser) {
      guestActions.hidden = isUser;
      userActions.hidden = !isUser;
    }

    /* ---------- Menu (hamburger, dropdown) ---------- */

    function setMenu(open) {
      navbar.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    }
    function setAccount(open) {
      account.classList.toggle('is-open', open);
      accountToggle.setAttribute('aria-expanded', String(open));
    }

    toggle.addEventListener('click', function () { setMenu(!navbar.classList.contains('is-open')); });
    accountToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      setAccount(!account.classList.contains('is-open'));
    });
    document.addEventListener('click', function (e) {
      if (!account.contains(e.target)) { setAccount(false); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setAccount(false); setMenu(false); }
    });
    window.matchMedia('(min-width: 861px)').addEventListener('change', function (e) {
      if (e.matches) { setMenu(false); }
    });

    /* ---------- Přihlášený uživatel: jméno, iniciály, zůstatek ---------- */

    function localPart(email) { return String(email || '').split('@')[0]; }

    function fullName(u) {
      return [u.first_name, u.last_name].filter(Boolean).join(' ');
    }

    // Bez vyplněného jména (registrace ho nevyžaduje) se použije část e-mailu před zavináčem
    function displayName(u) {
      return fullName(u) || localPart(u.email) || 'Můj účet';
    }

    function initials(u) {
      var parts = [u.first_name, u.last_name].filter(Boolean);
      if (parts.length) {
        return parts.map(function (p) { return p.charAt(0); }).join('').toUpperCase();
      }
      return (localPart(u.email).slice(0, 2) || '?').toUpperCase();
    }

    // Zůstatek: peněženka v CZK, jinak první dostupná; bez peněženky se nezobrazí
    function balanceText(wallets) {
      if (!wallets || !wallets.length) { return ''; }
      var w = wallets[0];
      for (var i = 0; i < wallets.length; i++) {
        if (wallets[i].currency === 'CZK') { w = wallets[i]; break; }
      }
      var n = parseFloat(w.balance);
      if (isNaN(n)) { return ''; }
      try {
        return new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: w.currency }).format(n);
      } catch (e) {
        return n.toFixed(2).replace('.', ',') + ' ' + w.currency;
      }
    }

    function setBalance(text) {
      ['balance-bar', 'balance-menu'].forEach(function (id) {
        var el = $(id);
        el.textContent = text;
        el.hidden = !text;
      });
    }

    function showUser(u, wallets) {
      var name = displayName(u);
      $('account-name').textContent = name;
      $('account-avatar').textContent = initials(u);
      $('account-who-name').textContent = name;
      $('account-who-email').textContent = u.email;
      accountToggle.title = u.email;
      setBalance(balanceText(wallets));
      setAuthView(true);
    }

    function showGuest() {
      setBalance('');
      setAccount(false);
      setAuthView(false);
    }

    /* ---------- Zjištění stavu přihlášení z API ---------- */

    var revision = 0;
    async function load() {
      var current = ++revision;
      try {
        await window.BetArenaAuth.ready();
        var response = await window.getAccount();
        var user = response && response.success === true ? response.data : null;
        if (!user || !user.user_id) throw new Error('Neplatná odpověď účtu.');
        if (current !== revision) return;
        showUser(user, []);
        // Wallet failure must not make an authenticated user look logged out.
        try {
          var result = await window.getWallets();
          if (current === revision && result && result.success === true && Array.isArray(result.data)) setBalance(balanceText(result.data));
        } catch (error) { if (current === revision) setBalance(''); }
      } catch (error) {
        if (current === revision) showGuest();
      }
    }
    document.addEventListener('auth:changed', function (event) {
      if (!navbar.isConnected) return;
      ++revision;
      if (event.detail && event.detail.authenticated && event.detail.user) {
        showUser(event.detail.user, []);
        load();
      } else { showGuest(); }
    });

    load();

    // Návrat tlačítkem Zpět (stránka z cache prohlížeče) – stav se znovu ověří
    window.addEventListener('pageshow', function (e) { if (e.persisted) { load(); } });

    window.BetArenaHeader = { refresh: load };
    }
    window.BetArenaHeaderService = { init: start };
    function start() {
      if (!window.BetArenaAuth || !window.BetArenaAuth.ready) {
        if (document.querySelector('script[data-header-auth-loader]')) return;
        var script = document.createElement('script');
        script.src = '/services/auth.js?v=20261023-2';
        script.setAttribute('data-header-auth-loader', '1');
        script.onload = mount;
        script.onerror = function () {
          var root = document.getElementById('navbar');
          if (root) root.querySelector('.navbar__actions--guest').hidden = false;
        };
        document.head.appendChild(script);
      } else mount();
    }
    var observer = new MutationObserver(start);
    observer.observe(document.documentElement, { childList: true, subtree: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
  })();
