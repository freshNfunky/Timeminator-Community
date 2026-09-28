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
require __DIR__ . '/LoginThrottle.php';

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

    send_security_headers($config);

    // Session hardening, scoped to this app's folder so it does not collide
    // with the other projects on the same domain.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($config['session_name'] ?? 'timeminator_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (base_path() ?: '/'),
            'httponly' => true,
            'samesite' => $config['session_samesite'] ?? 'Lax',
            'secure'   => (bool) ($config['secure_cookies'] ?? false),
        ]);
        session_start();
    }

    DB::boot($config);
    Auth::boot();
}

/**
 * Send security response headers on every response. Login and installer views
 * inline no JavaScript, so a strict Content-Security-Policy is realistic.
 * See issue #8.
 */
function send_security_headers(array $config): void
{
    if (headers_sent()) {
        return;
    }
    $csp = $config['csp']
        ?? "default-src 'self'; img-src 'self' data:; style-src 'self'; "
        .  "script-src 'self'; object-src 'none'; base-uri 'self'; "
        .  "frame-ancestors 'none'; form-action 'self'";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: interest-cohort=(), browsing-topics=()');
    header('X-Frame-Options: DENY'); // legacy header, complements frame-ancestors

    if (is_https()) {
        // Default: 6 months, no subdomains, no preload. Opt in to a longer max-age
        // and preload once you are certain every host under the domain is HTTPS.
        $hsts = $config['hsts'] ?? 'max-age=15552000';
        header('Strict-Transport-Security: ' . $hsts);
    }
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) === '443') {
        return true;
    }
    // Behind a reverse proxy — trust only if the proxy is configured to forward
    // this header. Operators terminating TLS at Nginx / Apache should set it.
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}
