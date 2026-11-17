<?php
function handle_admin($name, $method, $d) {
    $u = current_user();
    if ($u['role'] !== 'admin') fail('Vyžadována role administrátora.', 403);
    return one('SELECT * FROM v_admin_dashboard');
}
