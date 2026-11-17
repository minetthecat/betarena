<?php
function handle_transaction($name, $method, $d) {
    $u = current_user(); $args = array($u['user_id']);
    if ($name === 'transaction') { $args[] = id_value($d, 'transaction_id'); return found(one('SELECT * FROM `transaction` WHERE user_id = ? AND transaction_id = ?', $args)); }
    $where = 'user_id = ?';
    if (isset($d['type'])) { $where .= ' AND type = ?'; $args[] = enum_value($d, 'type', array('deposit','withdraw','bet','win','bonus')); }
    if (isset($d['currency'])) { $where .= ' AND currency = ?'; $args[] = currency($d); }
    if (isset($d['wallet_id'])) { $where .= ' AND wallet_id = ?'; $args[] = id_value($d, 'wallet_id'); }
    foreach (array('from','to') as $key) if (isset($d[$key])) {
        $date = date_value($d[$key], $key);
        $where .= $key === 'from' ? ' AND created_at >= ?' : ' AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $args[] = $date;
    }
    if (isset($d['from'], $d['to']) && $d['from'] > $d['to']) fail('Datum od musí být před datem do.');
    return paged('SELECT * FROM `transaction` WHERE ' . $where . ' ORDER BY transaction_id DESC', $args, $d);
}
