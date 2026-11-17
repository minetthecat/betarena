<?php
// ---------- Resolve URL => file in /pages (automatic) ----------
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/');   // "/casino/" => "casino"

$candidates = [];
if ($path === '') {
    $candidates = ['index.html'];
} elseif (
    // only safe segments (no dots, no "..", no backslashes) and no direct "/xxx/index" duplicate
    preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $path)
    && basename($path) !== 'index'
) {
    $candidates = ["{$path}.html", "{$path}/index.html"];
}

$file = null;
foreach ($candidates as $candidate) {
    $full = dirname(__DIR__) . '/pages/' . $candidate;
    if (is_file($full)) {
        $file = $full;
        break;
    }
}

$status = 200;
if ($file === null || $path === '404') {
    $status = 404;
    $file   = dirname(__DIR__) . '/pages/404.html';
}

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('Vary: X-Requested-With');   // same URL = fragment or full page

// Safe output (if 404.html is missing too)
$render = static function ($f) {
    if (is_file($f)) {
        readfile($f);
    } else {
        echo '<h1>404</h1>';
    }
};

// ---------- AJAX: only the page fragment ----------
if ($isAjax) {
    $render($file);
    exit;
}