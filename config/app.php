<?php

declare(strict_types=1);

use NullAuth\Support\Env;

return [
    'app' => [
        'env' => Env::get('APP_ENV', 'production'),
        'url' => Env::get('APP_URL', 'https://localhost'),
        'key_base64' => Env::get('APP_KEY_BASE64', ''),
        'pepper' => Env::get('APP_PEPPER', ''),
        'hotkey_sequence' => array_values(array_filter(array_map('trim', explode(',', Env::get('APP_HOTKEY_SEQUENCE', 'ArrowUp,ArrowUp,ArrowDown,ArrowDown,ArrowLeft,ArrowRight,ArrowLeft,ArrowRight,b,a') ?? '')))),
        'allowlist_cidrs' => array_values(array_filter(array_map('trim', explode(',', Env::get('APP_ALLOWLIST_CIDRS', '') ?? '')))),
    ],
    'database' => [
        'dsn' => Env::get('DB_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=nullauth'),
        'user' => Env::get('DB_USER', 'nullauth_app'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
    'session' => [
        'name' => Env::get('APP_SESSION_NAME', 'nullauth_session'),
        'idle_seconds' => (int) Env::get('APP_SESSION_IDLE_SECONDS', '900'),
        'absolute_seconds' => (int) Env::get('APP_SESSION_ABSOLUTE_SECONDS', '28800'),
    ],
    'argon2id' => [
        'memory_cost' => 131072,
        'time_cost' => 4,
        'threads' => 2,
    ],
];

