<?php
function handle_wallet($name, $method, $d) {
    $u = current_user(); $uid = $u['user_id'];
    if ($name === 'wallets') return rows('SELECT * FROM wallet WHERE user_id = ? ORDER BY wallet_id', array($uid));
    if ($name === 'deposit' || $name === 'withdraw') {
        $cur = currency($d); $amount = amount_value($d);
        return atomic(function() use ($uid, $cur, $amount, $name) { return wallet_move($uid, $cur, $amount, $name); });
    }
    if ($method === 'POST') {
        $cur = currency($d);
        query('INSERT INTO wallet (user_id, currency) VALUES (?, ?)', array($uid, $cur));
        return one('SELECT * FROM wallet WHERE wallet_id = ?', array(db()->lastInsertId()));
    }
    $id = id_value($d, 'wallet_id');
    if ($method === 'GET') return found(one('SELECT * FROM wallet WHERE wallet_id = ? AND user_id = ?', array($id, $uid)));
    $cur = $method === 'PUT' ? currency($d) : null;
    return atomic(function() use ($method, $id, $uid, $cur) {
        $w = found(one('SELECT * FROM wallet WHERE wallet_id = ? AND user_id = ? FOR UPDATE', array($id, $uid)));
        if ($method === 'PUT' && $cur === $w['currency']) return $w;
        // Avoid losing balances, changing historical currency, or broken FK links.
        if ((float)$w['balance'] !== 0.0 || one('SELECT transaction_id FROM `transaction` WHERE wallet_id = ? LIMIT 1', array($id))) fail('Lze upravit nebo smazat jen prázdnou peněženku bez historie.', 409);
        if ($method === 'DELETE') { query('DELETE FROM wallet WHERE wallet_id = ?', array($id)); return null; }
        query('UPDATE wallet SET currency = ? WHERE wallet_id = ?', array($cur, $id));
        return one('SELECT * FROM wallet WHERE wallet_id = ?', array($id));
    });
}
