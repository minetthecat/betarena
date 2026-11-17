<?php
function handle_tournament($name, $method, $d) {
    if ($name === 'tournaments') {
        $args = array(); $where = '';
        if (isset($d['status'])) { $where = ' WHERE status = ?'; $args[] = enum_value($d, 'status', array('active','upcoming','finished')); }
        return paged('SELECT * FROM v_tournament' . $where . ' ORDER BY starts_at DESC, tournament_id DESC', $args, $d);
    }
    $id = id_value($d, 'tournament_id');
    if ($name === 'tournament') return found(one('SELECT * FROM v_tournament WHERE tournament_id = ?', array($id)));
    if ($name === 'leaderboard') {
        found(one('SELECT tournament_id FROM tournament WHERE tournament_id = ?', array($id)));
        list($limit) = page_args(array('limit' => isset($d['limit']) ? $d['limit'] : 20));
        return rows('SELECT tournament_id, user_id, score, joined_at FROM tournament_player WHERE tournament_id = ? ORDER BY score DESC, joined_at ASC, user_id ASC LIMIT ' . $limit, array($id));
    }
    $u = current_user(); $uid = $u['user_id'];
    return atomic(function() use ($id, $uid) {
        $t = found(one('SELECT *, (ends_at <= NOW()) AS finished FROM tournament WHERE tournament_id = ? FOR UPDATE', array($id)));
        if ($t['finished']) fail('Turnaj již skončil.', 409);
        if (one('SELECT user_id FROM tournament_player WHERE tournament_id = ? AND user_id = ?', array($id, $uid))) fail('Uživatel již je přihlášen do turnaje.', 409);
        query('INSERT INTO tournament_player (tournament_id, user_id) VALUES (?, ?)', array($id, $uid));
        return one('SELECT * FROM tournament_player WHERE tournament_id = ? AND user_id = ?', array($id, $uid));
    });
}
