<?php
/**
 * BetArena API – společný základ, načítá ho každý endpoint:
 *     require __DIR__ . '/../bootstrap.php';
 *
 * Konvence
 *  - Odpověď je vždy JSON: { "success": true|false, "message": "...", ...data }
 *    Chyby validace navíc nesou "errors": { "pole": "hláška" }.
 *  - Vstup: GET = query string, ostatní metody = JSON tělo (u souborů multipart).
 *  - Přihlášení = $_SESSION['user_id'].
 *  - Cílové prostředí: PHP 7.0 + MariaDB 10.1 (bez ?typů, void, [$a, $b] = ...).
 */

/* ------------------------------------------------------------------
 * Konfigurace
 * ------------------------------------------------------------------ */

/** config('db.host', 'default') – čte pole vrácené z config.php. */
function config($key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    $value = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/* ------------------------------------------------------------------
 * Odpovědi
 * ------------------------------------------------------------------ */

function respond($status, array $body)
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Úspěšná odpověď: ok('Hotovo.', ['wallet' => $w], 201); */
function ok($message, array $data = [], $status = 200)
{
    respond($status, array_merge(['success' => true, 'message' => $message], $data));
}

/** Chybová odpověď: fail('Neplatná data.', 422, ['email' => 'Chybí e-mail.']); */
function fail($message, $status = 400, array $errors = [])
{
    $body = ['success' => false, 'message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    respond($status, $body);
}

/* ------------------------------------------------------------------
 * Vstup
 * ------------------------------------------------------------------ */

function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        fail('Metoda ' . $_SERVER['REQUEST_METHOD'] . ' není u tohoto endpointu povolena.', 405);
    }
}

/** Všechna vstupní data požadavku jako pole. */
function requestData()
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = $_GET;
        return $data;
    }
    $type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($type, 'application/json') !== false) {
        $raw  = file_get_contents('php://input');
        $json = ($raw === '' || $raw === false) ? [] : json_decode($raw, true);
        if (!is_array($json)) {
            fail('Tělo požadavku neobsahuje platný JSON.', 400);
        }
        $data = array_merge($_GET, $json);
    } else {
        $data = array_merge($_GET, $_POST);
    }
    return $data;
}

/** Textový parametr bez okolních mezer (prázdný řetězec, pokud chybí). */
function param($key)
{
    return trim(rawParam($key));
}

/** Textový parametr beze změny (hesla se neořezávají). */
function rawParam($key)
{
    $data = requestData();
    return (isset($data[$key]) && is_scalar($data[$key])) ? (string) $data[$key] : '';
}

/* ------------------------------------------------------------------
 * Databáze
 * ------------------------------------------------------------------ */

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            config('db.host', '127.0.0.1'),
            (int) config('db.port', 3306),
            config('db.name'),
            config('db.charset', 'utf8mb4')
        );
        try {
            $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Nelze se připojit k databázi (' . $e->getMessage() . ').');
        }
    }
    return $pdo;
}

/**
 * Spustí funkci v DB transakci (commit / při výjimce rollback a výjimka jde dál).
 * Z funkce se nevolá fail(): vrať hodnotu a chybu vyhoď až po transakci.
 */
function withTransaction($callback)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Porušení UNIQUE klíče (MySQL/MariaDB chyba 1062). */
function isDuplicateKey(PDOException $e)
{
    return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
}

/* ------------------------------------------------------------------
 * Přihlášení (session)
 * ------------------------------------------------------------------ */

function currentUserId()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

/** Vyžaduje přihlášeného uživatele, vrací jeho user_id. */
function requireLogin()
{
    $id = currentUserId();
    if ($id === 0) {
        fail('Pro tuto akci se musíte přihlásit.', 401);
    }
    return $id;
}

/** Povolí jen nepřihlášené (login, registrace). */
function requireGuest()
{
    if (currentUserId() !== 0) {
        fail('Již jste přihlášeni.', 403);
    }
}

/** Vyžaduje roli admin (role se čte z DB), vrací user_id. */
function requireAdmin()
{
    $id   = requireLogin();
    $stmt = db()->prepare('SELECT role FROM `user` WHERE user_id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') {
        fail('K této akci nemáte oprávnění.', 403);
    }
    return $id;
}

function loginUser($userId)
{
    session_regenerate_id(true); // ochrana proti session fixation
    $_SESSION['user_id'] = (int) $userId;
}

function logoutUser()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Údaje o uživateli, které smí odejít klientovi (nikdy password_hash). */
function userPublic(array $u)
{
    return [
        'user_id'        => (int) $u['user_id'],
        'email'          => $u['email'],
        'role'           => $u['role'],
        'first_name'     => $u['first_name'],
        'last_name'      => $u['last_name'],
        'kyc_status'     => $u['kyc_status'],
        'email_verified' => $u['email_verified_at'] !== null,
    ];
}

/* ------------------------------------------------------------------
 * Validace
 * ------------------------------------------------------------------ */

function normalizeEmail($email)
{
    return strtolower(trim($email));
}

function isValidEmail($email)
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Vrací pole chyb (prázdné = v pořádku) pro nové heslo + potvrzení. */
function validateNewPassword($password, $confirm)
{
    $errors = [];
    $min    = (int) config('auth.password_min_length', 8);
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);

    if ($password === '') {
        $errors['password'] = 'Heslo je povinné.';
    } elseif ($length < $min) {
        $errors['password'] = 'Heslo musí mít alespoň ' . $min . ' znaků.';
    } elseif (strlen($password) > 72) { // bcrypt zpracuje jen prvních 72 bajtů
        $errors['password'] = 'Heslo je příliš dlouhé (nejvýše 72 bajtů).';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Hesla se neshodují.';
    }
    return $errors;
}

/* ------------------------------------------------------------------
 * Tokeny (ověření e-mailu, reset hesla) – v DB je jen SHA-256 hash
 * ------------------------------------------------------------------ */

/** Vytvoří token a vrátí jeho čitelnou podobu (do e-mailu). Čas expirace počítá DB. */
function createToken($userId, $type, $ttlSeconds)
{
    $plain = bin2hex(random_bytes(32));
    $stmt  = db()->prepare(
        'INSERT INTO auth_token (user_id, type, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    );
    $stmt->execute([$userId, $type, hash('sha256', $plain), (int) $ttlSeconds]);
    return $plain;
}

/** Vrací řádek tokenu (token_id, user_id), nebo null, pokud je neplatný, použitý či prošlý. */
function findToken($plain, $type)
{
    if (!preg_match('/^[a-f0-9]{64}$/', $plain)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT token_id, user_id FROM auth_token
         WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $plain), $type]);
    $row = $stmt->fetch();
    return $row ? $row : null;
}

/** Spotřebuje token; true jen pro první úspěšné použití (ochrana proti souběhu). */
function consumeToken($tokenId)
{
    $stmt = db()->prepare('UPDATE auth_token SET used_at = NOW() WHERE token_id = ? AND used_at IS NULL');
    $stmt->execute([$tokenId]);
    return $stmt->rowCount() === 1;
}

/* ------------------------------------------------------------------
 * E-mail a odkazy
 * ------------------------------------------------------------------ */

/** Odkaz na stránku front-endu: pageUrl('auth/verify/' . $token). */
function pageUrl($path)
{
    return rtrim(config('app.url', ''), '/')
        . rtrim(config('app.page_prefix', '/www'), '/')
        . '/' . ltrim($path, '/');
}

/** Odešle e-mail dle mail.driver ('log' = jen do error logu, 'mail' = PHP mail()). */
function sendMail($to, $subject, $body)
{
    if (config('mail.driver', 'log') !== 'mail') {
        error_log('[BetArena][mail] To: ' . $to . ' | Subject: ' . $subject . "\n" . $body);
        return true;
    }
    $from    = config('mail.from', 'no-reply@localhost');
    $name    = config('mail.from_name', 'BetArena');
    $headers = 'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $from . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . 'Content-Transfer-Encoding: 8bit';
    return (bool) @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/* ------------------------------------------------------------------
 * Inicializace požadavku
 * ------------------------------------------------------------------ */

date_default_timezone_set(config('app.timezone', 'Europe/Prague'));
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function ($e) {
    error_log('[BetArena] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $body = ['success' => false, 'message' => 'Došlo k chybě serveru. Zkuste to prosím později.'];
    if (config('app.debug', false)) {
        $body['debug'] = get_class($e) . ': ' . $e->getMessage();
    }
    respond(500, $body);
});

// CORS: jen pro povolené originy z config.php (při stejné doméně se nic neposílá)
$origin  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed = config('cors.allowed_origins', []);
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Session cookie: HttpOnly + SameSite=Lax (v PHP < 7.3 se SameSite doplňuje přes path)
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(0, '/; SameSite=Lax', '', $https, true);
    session_start();
}
