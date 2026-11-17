<?php
/* Shared runtime; compatible with PHP 7.0. */
function config($key, $default = null) {
    static $settings;
    if ($settings === null) $settings = require dirname(__DIR__) . '/config.php';
    $value = $settings;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
function respond($data = null, $message = 'OK', $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode(array('success' => $status < 400, 'message' => $message, 'data' => $data), JSON_UNESCAPED_UNICODE);
    if ($json === false) { http_response_code(500); $json = '{"success":false,"message":"JSON encoding failed.","data":null}'; }
    echo $json;
    exit;
}
function fail($message, $status = 400) { respond(null, $message, $status); }
function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . config('db.host') . ';port=' . config('db.port', 3306) . ';dbname=' . config('db.name') . ';charset=' . config('db.charset', 'utf8mb4'), config('db.user'), config('db.pass'), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false));
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
function query($sql, $args = array()) { $s = db()->prepare($sql); $s->execute($args); return $s; }
function one($sql, $args = array()) { $r = query($sql, $args)->fetch(); return $r === false ? null : $r; }
function rows($sql, $args = array()) { return query($sql, $args)->fetchAll(); }
function found($row) { if ($row === null) fail('Záznam nebyl nalezen.', 404); return $row; }
function input() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') return $_GET;
    $type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($type, 'multipart/form-data') === 0) return $_POST;
    if (stripos($type, 'application/json') !== 0) fail('Použijte Content-Type: application/json.', 415);
    $raw = file_get_contents('php://input');
    $value = json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($value)) fail('Tělo musí být JSON objekt.');
    return json_decode($raw, true);
}
function text_value($d, $key, $required = true, $max = 255) {
    if (!array_key_exists($key, $d) || $d[$key] === null) {
        if ($required) fail('Chybí parametr: ' . $key);
        return null;
    }
    if (!is_string($d[$key])) fail('Neplatný parametr: ' . $key);
    $v = trim($d[$key]);
    if (($required && $v === '') || mb_strlen($v, 'UTF-8') > $max) fail('Neplatný parametr: ' . $key);
    return $v;
}
function id_value($d, $key) {
    if (!isset($d[$key]) || is_bool($d[$key]) || !is_scalar($d[$key]) || !preg_match('/^[1-9][0-9]*$/D', (string)$d[$key])) fail('Neplatné ID: ' . $key);
    // Keep BIGINT transaction identifiers as strings.
    return (string)$d[$key];
}
function enum_value($d, $key, $values) {
    $v = text_value($d, $key);
    if (!in_array($v, $values, true)) fail('Neplatný parametr: ' . $key);
    return $v;
}
function currency($d) { return enum_value($d, 'currency', array('CZK','EUR','BSC')); }
function date_value($v, $key) {
    if (!is_string($v)) fail('Neplatné datum: ' . $key);
    $dt = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$dt || $dt->format('Y-m-d') !== $v) fail('Neplatné datum: ' . $key);
    return $v;
}
function page_args($d) {
    $p = isset($d['page']) ? id_value($d, 'page') : '1';
    $l = isset($d['limit']) ? id_value($d, 'limit') : '20';
    if ((float)$p > 1000000 || (float)$l > 100) fail('page musí být 1–1000000 a limit 1–100.');
    return array((int)$l, ((int)$p - 1) * (int)$l);
}
function paged($sql, $args, $d) {
    list($limit, $offset) = page_args($d);
    return rows($sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset, $args);
}
function current_user($required = true) {
    static $user = false;
    if ($user === false) {
        $user = isset($_SESSION['user_id']) ? one('SELECT * FROM `user` WHERE user_id = ?', array($_SESSION['user_id'])) : null;
    }
    if (!$user && $required) fail('Vyžadováno přihlášení.', 401);
    return $user;
}
function public_user($user) { unset($user['password_hash']); return $user; }
function guest_only() { if (current_user(false)) fail('Operace je určena nepřihlášeným uživatelům.', 403); }
function password_value($d, $confirm = true) {
    if (!isset($d['password']) || !is_string($d['password']) || strlen($d['password']) < config('auth.password_min_length', 8) || strlen($d['password']) > 72) fail('Heslo nesplňuje délku ' . config('auth.password_min_length', 8) . '–72 bajtů.');
    if ($confirm && (!isset($d['password_confirm']) || $d['password'] !== $d['password_confirm'])) fail('Hesla se neshodují.');
    return $d['password'];
}
function email_value($d) {
    $e = text_value($d, 'email', true, 190);
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) fail('Neplatný e-mail.');
    return $e;
}
function atomic($callback) {
    db()->beginTransaction();
    try { $result = $callback(); db()->commit(); return $result; }
    catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
}
// No floats in balance arithmetic. DECIMAL calculations stay in MySQL.
function amount_value($d) {
    if (!isset($d['amount']) || is_bool($d['amount']) || !is_scalar($d['amount'])) fail('Neplatná částka.');
    $s = (string)$d['amount'];
    if (!preg_match('/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/D', $s) || !preg_match('/[1-9]/', $s)) fail('Částka musí být kladná, maximálně se dvěma desetinnými místy.');
    return $s;
}
function token_issue($uid, $type, $ttl) {
    $token = bin2hex(random_bytes(32));
    query('INSERT INTO auth_token (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))', array($uid, $type, hash('sha256', $token), (int)$ttl));
    return $token;
}
function token_mail($email, $token, $kind) {
    $url = rtrim(config('app.url'), '/') . ($kind === 'verify_email' ? '/account/verify' : '/account/reset-password') . '?token=' . rawurlencode($token);
    if (config('mail.driver', 'log') === 'mail') {
        $from = str_replace(array("\r", "\n"), '', config('mail.from'));
        if (!mail($email, 'BetArena: ' . $kind, $url, 'From: ' . $from)) throw new RuntimeException('Odeslání e-mailu selhalo.');
    } else { error_log('BetArena ' . $kind . ' ' . $email . ' ' . $url); }
}
function wallet_move($uid, $cur, $amount, $type, $bonusId = null) {
    // Caller owns transaction, serializes a single wallet with FOR UPDATE.
    $w = found(one('SELECT * FROM wallet WHERE user_id = ? AND currency = ? FOR UPDATE', array($uid, $cur)));
    $operator = $type === 'withdraw' ? '-' : '+';
    if ($type === 'withdraw' && !one('SELECT wallet_id FROM wallet WHERE wallet_id = ? AND balance >= CAST(? AS DECIMAL(18,2))', array($w['wallet_id'], $amount))) fail('Nedostatečný zůstatek.', 409);
    if ($operator === '+' && !one('SELECT wallet_id FROM wallet WHERE wallet_id = ? AND balance <= 9999999999999999.99 - CAST(? AS DECIMAL(18,2))', array($w['wallet_id'], $amount))) fail('Překročen maximální zůstatek.', 409);
    query('UPDATE wallet SET balance = balance ' . $operator . ' CAST(? AS DECIMAL(18,2)) WHERE wallet_id = ?', array($amount, $w['wallet_id']));
    $w = one('SELECT * FROM wallet WHERE wallet_id = ?', array($w['wallet_id']));
    query('INSERT INTO `transaction` (user_id, wallet_id, type, currency, amount, balance_after, bonus_id) VALUES (?, ?, ?, ?, ?, ?, ?)', array($uid, $w['wallet_id'], $type, $cur, $amount, $w['balance'], $bonusId));
    return one('SELECT * FROM `transaction` WHERE transaction_id = ?', array(db()->lastInsertId()));
}
function run_endpoint($group, $name, $methods) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    set_exception_handler(function($e) {
        error_log((string)$e);
        if ($e instanceof PDOException && $e->getCode() === '23000') respond(null, 'Operace koliduje s existujícím záznamem nebo vazbou.', 409);
        respond(null, config('app.debug', false) ? $e->getMessage() : 'Interní chyba serveru.', 500);
    });
    date_default_timezone_set(config('app.timezone', 'Europe/Prague'));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
    if ($origin && in_array($origin, config('cors.allowed_origins', array()), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    header('Allow: ' . implode(', ', array_merge($methods, array('OPTIONS'))));
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        http_response_code(204); exit;
    }
    if (!in_array($method, $methods, true)) fail('Nepovolená HTTP metoda.', 405);
    // Same-origin browser requests from api.js carry this header. Cross-origin JSON
    // requests require an explicitly permitted CORS origin.
    if ($method !== 'GET' && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) fail('Chybí hlavička X-Requested-With.', 403);
    require_once dirname(dirname(__DIR__)) . '/services/session.php';
    betarena_start_session();
    $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($ct, 'multipart/form-data') === 0 && !($group === 'account' && $name === 'kyc' && $method === 'POST')) fail('Tento endpoint přijímá JSON.', 415);
    if ($group === 'account' && $name === 'kyc' && $method === 'POST' && stripos($ct, 'multipart/form-data') !== 0) fail('Upload vyžaduje multipart/form-data.', 415);
    $data = input();
    require __DIR__ . '/' . $group . '.php';
    $handler = 'handle_' . str_replace('-', '_', $group);
    $result = $handler($name, $method, $data);
    respond($result);
}
