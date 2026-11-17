<?php
function handle_casino($name, $method, $d) {
    if ($name === 'providers') return rows('SELECT * FROM provider ORDER BY name, provider_id');
    if ($name === 'game') return found(one('SELECT * FROM game WHERE game_id = ? AND is_active = 1', array(id_value($d, 'game_id'))));
    if ($name === 'favorite') {
        $u = current_user(); $id = id_value($d, 'game_id');
        if ($method === 'DELETE') { query('DELETE FROM favorite_game WHERE user_id = ? AND game_id = ?', array($u['user_id'], $id)); return null; }
        found(one('SELECT game_id FROM game WHERE game_id = ? AND is_active = 1', array($id)));
        query('INSERT INTO favorite_game (user_id, game_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE game_id = VALUES(game_id)', array($u['user_id'], $id));
        return one('SELECT * FROM favorite_game WHERE user_id = ? AND game_id = ?', array($u['user_id'], $id));
    }
    $filter = $name === 'favorites' ? 'favorites' : (isset($d['filter']) ? enum_value($d, 'filter', array('popular','favorites','all','provider')) : 'all');
    $sql = 'SELECT g.* FROM game g'; $args = array(); $where = ' WHERE g.is_active = 1';
    if ($filter === 'favorites') { $u = current_user(); $sql .= ' JOIN favorite_game f ON f.game_id = g.game_id'; $where .= ' AND f.user_id = ?'; $args[] = $u['user_id']; }
    if ($filter === 'provider') { $slug = text_value($d, 'provider', true, 50); $sql .= ' JOIN provider p ON p.provider_id = g.provider_id'; $where .= ' AND p.slug = ?'; $args[] = $slug; }
    $order = $filter === 'popular' ? ' ORDER BY g.popularity DESC, g.game_id DESC' : ' ORDER BY g.game_id DESC';
    return paged($sql . $where . $order, $args, $d);
}
