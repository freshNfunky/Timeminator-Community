<?php
declare(strict_types=1);

/**
 * Application bootstrap: config, session, database, auth.
 * Included by index.php and the JSON API. NOT used by install.php (which runs
 * before a configuration exists).
 */

define('APP_ROOT', dirname(__DIR__));

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

function config_exists(): bool
{
    return is_file(APP_ROOT . '/config.php');
}

/** Load config into the global used by cfg(). */
function load_config(): array
{
    global $APP_CONFIG;
    $APP_CONFIG = require APP_ROOT . '/config.php';
    return $APP_CONFIG;
}

function app_boot(): void
{
    $config = load_config();

    // Timezone: everything is stored and shown in this zone.
    date_default_timezone_set($config['timezone'] ?? 'Europe/Berlin');

    // Error handling.
    $debug = (bool) ($config['debug'] ?? false);
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/data/php-error.log');

    // Session hardening, scoped to this app's folder so it does not collide
    // with the other projects on the same domain.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($config['session_name'] ?? 'timeminator_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (base_path() ?: '/'),
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (bool) ($config['secure_cookies'] ?? false),
        ]);
        session_start();
    }

    DB::boot($config);
    Auth::boot();
}
