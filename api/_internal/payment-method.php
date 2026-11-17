<?php
/* Payment methods of the logged-in user. user_id always comes from the session, never from the request. */

// Columns that may leave the server. The card number is never returned in full and the CVV never at all.
function pm_columns() {
    return 'method_id, code, name, can_deposit, can_withdraw, is_active, RIGHT(card_number, 4) AS card_last4, card_expiry, card_holder_name, '
        . 'recipient_account_number, recipient_bank_code, recipient_iban, recipient_bic, recipient_bank_name, recipient_bank_address, recipient_crypto_address';
}

function pm_luhn($digits) {
    $sum = 0; $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int)$digits[$i];
        if ($alt) { $n *= 2; if ($n > 9) $n -= 9; }
        $sum += $n; $alt = !$alt;
    }
    return $sum % 10 === 0;
}

// Validates the request and returns column => value. $create = true requires code and name.
function pm_values($d, $create) {
    $v = array();
    foreach (array('code' => 30, 'name' => 100) as $key => $max) {
        if (!array_key_exists($key, $d)) { if ($create) fail('Chybí parametr: ' . $key); continue; }
        $v[$key] = text_value($d, $key, true, $max);
    }
    if (isset($v['code']) && !preg_match('/^[A-Za-z0-9_-]+$/D', $v['code'])) fail('Neplatný parametr: code');

    $optional = array('card_holder_name' => 100, 'recipient_account_number' => 34, 'recipient_bank_code' => 10, 'recipient_iban' => 34,
        'recipient_bic' => 11, 'recipient_bank_name' => 150, 'recipient_bank_address' => 255, 'recipient_crypto_address' => 100);
    foreach ($optional as $key => $max) {
        if (!array_key_exists($key, $d)) continue;
        $t = text_value($d, $key, false, $max);
        $v[$key] = ($t === null || $t === '') ? null : $t;
    }

    // Only the last 4 digits of the card are stored; the CVV is not accepted at all (a sent card_cvv is ignored).
    if (array_key_exists('card_number', $d)) {
        $t = text_value($d, 'card_number', false, 19);
        $digits = $t === null ? '' : preg_replace('/[ -]/', '', $t);
        if ($digits === '') $v['card_number'] = null;
        elseif (!preg_match('/^[0-9]{12,19}$/D', $digits) || !pm_luhn($digits)) fail('Neplatný parametr: card_number');
        else $v['card_number'] = substr($digits, -4);
    }
    if (array_key_exists('card_expiry', $d)) {
        $t = text_value($d, 'card_expiry', false, 5);
        if ($t === null || $t === '') $v['card_expiry'] = null;
        elseif (!preg_match('#^(0[1-9]|1[0-2])/[0-9]{2}$#D', $t)) fail('Neplatný parametr: card_expiry (očekáváno MM/RR).');
        else $v['card_expiry'] = $t;
    }

    foreach (array('can_deposit', 'can_withdraw', 'is_active') as $key) {
        if (!array_key_exists($key, $d)) continue;
        if (!is_bool($d[$key])) fail($key . ' musí být boolean.');
        $v[$key] = $d[$key] ? 1 : 0;
    }
    return $v;
}

function handle_payment_method($name, $method, $d) {
    $u = current_user(); $uid = $u['user_id'];
    $cols = pm_columns();

    if ($name === 'payment-methods') return paged('SELECT ' . $cols . ' FROM payment_method WHERE user_id = ? ORDER BY method_id DESC', array($uid), $d);

    if ($method === 'POST') {
        $v = pm_values($d, true);
        $v['user_id'] = $uid;
        try {
            query('INSERT INTO payment_method (`' . implode('`, `', array_keys($v)) . '`) VALUES (' . implode(', ', array_fill(0, count($v), '?')) . ')', array_values($v));
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) fail('Platební metoda s tímto code již existuje.', 409);
            throw $e;
        }
        return one('SELECT ' . $cols . ' FROM payment_method WHERE method_id = ? AND user_id = ?', array(db()->lastInsertId(), $uid));
    }

    $id = id_value($d, 'method_id');
    if ($method === 'GET') return found(one('SELECT ' . $cols . ' FROM payment_method WHERE method_id = ? AND user_id = ?', array($id, $uid)));

    if ($method === 'PUT') {
        $v = pm_values($d, false);
        if (!$v) fail('Nebyla zadána žádná data ke změně.');
        try {
            return atomic(function() use ($id, $uid, $v, $cols) {
                found(one('SELECT method_id FROM payment_method WHERE method_id = ? AND user_id = ? FOR UPDATE', array($id, $uid)));
                $set = array(); $args = array();
                foreach ($v as $key => $value) { $set[] = '`' . $key . '` = ?'; $args[] = $value; }
                $args[] = $id; $args[] = $uid;
                query('UPDATE payment_method SET ' . implode(', ', $set) . ' WHERE method_id = ? AND user_id = ?', $args);
                return one('SELECT ' . $cols . ' FROM payment_method WHERE method_id = ? AND user_id = ?', array($id, $uid));
            });
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) fail('Platební metoda s tímto code již existuje.', 409);
            throw $e;
        }
    }

    // DELETE
    try {
        if (query('DELETE FROM payment_method WHERE method_id = ? AND user_id = ?', array($id, $uid))->rowCount() === 0) fail('Záznam nebyl nalezen.', 404);
    } catch (PDOException $e) {
        if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1451) fail('Platební metodu používají transakce; deaktivujte ji (is_active = false).', 409);
        throw $e;
    }
    return null;
}
