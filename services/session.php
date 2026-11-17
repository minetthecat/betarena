<?php
/* Shared PHP session settings for the same localhost host, PHP 7.0+. */
function betarena_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) throw new RuntimeException('Session musí být spuštěna před HTML výstupem.');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('PHPSESSID');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // No old directory prefix or cookie domain. Available at /api, /account, /casino.
    session_set_cookie_params(0, '/', '', $secure, true);
    if (!session_start()) throw new RuntimeException('Session nelze spustit.');
}
