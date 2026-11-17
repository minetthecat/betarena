/* Compatibility entry for an old layout. All handling lives in /services/auth.js. */
(function () {
    if (window.BetArenaAuth && window.BetArenaAuth.version === '2') { window.BetArenaAuth.init(); return; }
    if (document.querySelector('script[data-betarena-auth-loader]')) return;
    var script = document.createElement('script');
    script.src = '/services/auth.js?v=20261023-2';
    script.setAttribute('data-betarena-auth-loader', '1');
    document.head.appendChild(script);
})();
