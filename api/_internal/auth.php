<?php
function handle_auth($name, $method, $d) {
    if ($name === 'register') {
        guest_only(); $email = email_value($d); $password = password_value($d);
        $result = atomic(function() use ($email, $password) {
            query('INSERT INTO `user` (email, password_hash) VALUES (?, ?)', array($email, password_hash($password, PASSWORD_DEFAULT)));
            $id = db()->lastInsertId();
            // Every new account starts with an empty cash wallet and bonus wallet.
            foreach (array('CZK', 'BSC') as $currency) {
                query('INSERT INTO wallet (user_id, currency) VALUES (?, ?)', array($id, $currency));
            }
            $token = token_issue($id, 'verify_email', config('auth.verify_ttl', 86400));
            return array('user_id' => $id, 'token' => $token);
        });
        token_mail($email, $result['token'], 'verify_email');
        return public_user(one('SELECT * FROM `user` WHERE user_id = ?', array($result['user_id'])));
    }
    if ($name === 'login') {
        guest_only(); $email = email_value($d);
        if (!isset($d['password']) || !is_string($d['password'])) fail('Chybí heslo.');
        $u = one('SELECT * FROM `user` WHERE email = ?', array($email));
        if (!$u || !password_verify($d['password'], $u['password_hash'])) fail('Nesprávný e-mail nebo heslo.', 401);
        if (config('auth.require_verified_email', false) && !$u['email_verified_at']) fail('E-mail není ověřen.', 403);
        session_regenerate_id(true); $_SESSION['user_id'] = $u['user_id'];
        return public_user($u);
    }
    if ($name === 'logout') {
        current_user(); $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy(); return null;
    }
    if ($name === 'forgot-password') {
        $email = email_value($d); $u = one('SELECT user_id FROM `user` WHERE email = ?', array($email));
        if ($u) {
            $token = atomic(function() use ($u) {
                query('SELECT user_id FROM `user` WHERE user_id = ? FOR UPDATE', array($u['user_id']));
                query('UPDATE auth_token SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL', array($u['user_id'], 'reset_password'));
                return token_issue($u['user_id'], 'reset_password', config('auth.reset_ttl', 3600));
            });
            token_mail($email, $token, 'reset_password');
        }
        return null;
    }
    $token = text_value($d, 'token', true, 128);
    $password = $name === 'reset-password' ? password_value($d) : null;
    return atomic(function() use ($name, $token, $password) {
        $type = $name === 'verify' ? 'verify_email' : 'reset_password';
        // Lock user before token, same lock order as token issuance.
        $candidate = one('SELECT user_id FROM auth_token WHERE token_hash = ? AND type = ?', array(hash('sha256', $token), $type));
        if (!$candidate) fail('Token je neplatný nebo vypršel.');
        query('SELECT user_id FROM `user` WHERE user_id = ? FOR UPDATE', array($candidate['user_id']));
        $t = one('SELECT * FROM auth_token WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE', array(hash('sha256', $token), $type));
        if (!$t) fail('Token je neplatný nebo vypršel.');
        if ($name === 'verify') query('UPDATE `user` SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE user_id = ?', array($t['user_id']));
        else query('UPDATE `user` SET password_hash = ? WHERE user_id = ?', array(password_hash($password, PASSWORD_DEFAULT), $t['user_id']));
        query('UPDATE auth_token SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL', array($t['user_id'], $type));
        return null;
    });
}
