<?php
function handle_bonus($name, $method, $d) {
    $available = 'is_active = 1 AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to > NOW())';
    if ($name === 'bonuses') return paged('SELECT * FROM bonus WHERE ' . $available . ' ORDER BY bonus_id DESC', array(), $d);
    $id = id_value($d, 'bonus_id');
    if ($name === 'bonus') return found(one('SELECT * FROM bonus WHERE bonus_id = ? AND ' . $available, array($id)));
    $u = current_user(); $uid = $u['user_id'];
    return atomic(function() use ($id, $uid, $available) {
        $b = found(one('SELECT * FROM bonus WHERE bonus_id = ? AND ' . $available . ' FOR UPDATE', array($id)));
        if (one('SELECT user_bonus_id FROM user_bonus WHERE user_id = ? AND bonus_id = ?', array($uid, $id))) fail('Bonus již byl aktivován.', 409);
        query('INSERT INTO user_bonus (user_id, bonus_id, expires_at) VALUES (?, ?, ?)', array($uid, $id, $b['valid_to']));
        $ub = db()->lastInsertId();
        // A positive fixed-amount bonus credits an existing wallet in its currency.
        if ($b['amount'] !== null && (float)$b['amount'] > 0 && $b['currency'] !== null) wallet_move($uid, $b['currency'], $b['amount'], 'bonus', $id);
        return one('SELECT * FROM user_bonus WHERE user_bonus_id = ?', array($ub));
    });
}
