<?php
function handle_account($name, $method, $d) {
    $u = current_user(); $uid = $u['user_id'];
    if ($name === 'account') return public_user($u);
    if ($name === 'profile') {
        $fields = array('first_name' => 100, 'last_name' => 100, 'birth_date' => 10, 'phone' => 30, 'address' => 255);
        if ($method === 'PUT') {
            $set = array(); $args = array();
            foreach ($fields as $key => $max) {
                if (!array_key_exists($key, $d)) continue;
                $v = text_value($d, $key, false, $max);
                if ($key === 'birth_date' && $v !== null) $v = date_value($v, $key);
                $set[] = '`' . $key . '` = ?'; $args[] = $v;
            }
            if (!$set) fail('Nebylo zadáno žádné pole profilu.');
            $args[] = $uid; query('UPDATE `user` SET ' . implode(', ', $set) . ' WHERE user_id = ?', $args);
        }
        return one('SELECT user_id, first_name, last_name, birth_date, phone, address FROM `user` WHERE user_id = ?', array($uid));
    }
    if ($name === 'settings') {
        if ($method === 'PUT') {
            $set = array(); $args = array();
            if (array_key_exists('language', $d)) { $set[] = 'language = ?'; $args[] = text_value($d, 'language', true, 5); }
            if (array_key_exists('notifications', $d)) {
                if (!is_bool($d['notifications'])) fail('notifications musí být boolean.');
                $set[] = 'notifications = ?'; $args[] = $d['notifications'] ? 1 : 0;
            }
            if (!$set) fail('Nebylo zadáno žádné nastavení.');
            $args[] = $uid; query('UPDATE `user` SET ' . implode(', ', $set) . ' WHERE user_id = ?', $args);
        }
        return one('SELECT user_id, language, notifications FROM `user` WHERE user_id = ?', array($uid));
    }
    if ($name === 'password') {
        $new = password_value($d);
        if (!isset($d['current_password']) || !is_string($d['current_password']) || !password_verify($d['current_password'], $u['password_hash'])) fail('Nesprávné aktuální heslo.', 403);
        atomic(function() use ($new, $uid, $u) {
            $fresh = one('SELECT password_hash FROM `user` WHERE user_id = ? FOR UPDATE', array($uid));
            if ($fresh['password_hash'] !== $u['password_hash']) fail('Heslo se mezitím změnilo.', 409);
            query('UPDATE `user` SET password_hash = ? WHERE user_id = ?', array(password_hash($new, PASSWORD_DEFAULT), $uid));
            query('UPDATE auth_token SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL', array($uid, 'reset_password'));
        });
        session_regenerate_id(true); return null;
    }
    if ($name === 'kyc') {
        if ($method === 'GET') return array('kyc_status' => $u['kyc_status'], 'documents' => rows('SELECT * FROM kyc_document WHERE user_id = ? ORDER BY kyc_id DESC', array($uid)));
        $type = text_value($d, 'document_type', true, 50);
        if (!isset($_FILES['file']) || !is_array($_FILES['file']) || is_array($_FILES['file']['error'])) fail('Chybí soubor.');
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) fail('Nahrání souboru selhalo (kód ' . (int)$file['error'] . ').');
        if (!is_uploaded_file($file['tmp_name'])) fail('Neplatný upload.');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $allowed = array('application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png');
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) fail('Povolené doklady: PDF, JPEG, PNG.', 415);
        $dir = dirname(dirname(__DIR__)) . '/uploads';
        if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Adresář /uploads/ není zapisovatelný.');
        $relative = '/uploads/' . bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $path = dirname(dirname(__DIR__)) . $relative;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Doklad nelze uložit.');
        try {
            return atomic(function() use ($uid, $type, $relative) {
                query('SELECT user_id FROM `user` WHERE user_id = ? FOR UPDATE', array($uid));
                query('INSERT INTO kyc_document (user_id, document_type, file_path) VALUES (?, ?, ?)', array($uid, $type, $relative));
                $id = db()->lastInsertId();
                query('UPDATE `user` SET kyc_status = ? WHERE user_id = ?', array('pending', $uid));
                return one('SELECT * FROM kyc_document WHERE kyc_id = ?', array($id));
            });
        } catch (Throwable $e) { unlink($path); throw $e; }
    }
    if ($name === 'tournaments') return paged('SELECT t.*, tp.score, tp.joined_at FROM v_tournament t JOIN tournament_player tp ON tp.tournament_id = t.tournament_id WHERE tp.user_id = ? ORDER BY t.starts_at DESC, t.tournament_id DESC', array($uid), $d);
    $args = array($uid); $where = '';
    if (isset($d['status'])) { $where = ' AND ub.status = ?'; $args[] = enum_value($d, 'status', array('active','completed','expired')); }
    return paged('SELECT b.*, ub.user_bonus_id, ub.user_id, ub.status, ub.claimed_at, ub.expires_at FROM user_bonus ub JOIN bonus b ON b.bonus_id = ub.bonus_id WHERE ub.user_id = ?' . $where . ' ORDER BY ub.user_bonus_id DESC', $args, $d);
}
