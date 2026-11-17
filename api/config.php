<?php
/**
 * BetArena API – konfigurace.
 * Soubor vrací pole; čte se přes funkci config('db.host') apod.
 * Upravte hodnoty pro své prostředí (a nedávejte soubor do veřejného repozitáře).
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'betarena',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Základní URL front-endu (používá se v odkazech v e-mailech)
        'url'      => 'http://localhost',
        // true = chybové hlášky obsahují detail výjimky (jen pro vývoj!)
        'debug'    => false,
        'timezone' => 'Europe/Prague',
    ],

    'auth' => [
        'password_min_length'    => 8,
        'verify_ttl'             => 86400, // platnost ověřovacího tokenu (s) = 24 h
        'reset_ttl'              => 3600,  // platnost tokenu pro reset hesla (s) = 1 h
        // true = přihlášení povoleno až po ověření e-mailu
        'require_verified_email' => false,
    ],

    'mail' => [
        // 'log'  = e-mail se jen zapíše do error logu PHP (vývoj)
        // 'mail' = odeslání přes PHP mail()
        'driver'    => 'log',
        'from'      => 'no-reply@betarena.local',
        'from_name' => 'BetArena',
    ],

    'cors' => [
        // Povolené origin hodnoty, pokud front-end běží na jiné doméně/portu,
        // např. ['http://localhost:5173']. Při stejné doméně nechte prázdné.
        'allowed_origins' => [],
    ],
];
