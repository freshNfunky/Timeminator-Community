<?php
/**
 * Timeminator Community - configuration
 *
 * Copy this file to config.php and adjust. config.php is git-ignored and
 * protected from web access via .htaccess.
 */

return [
    // 'mysql' for production (public.felixschaller.com), 'sqlite' for local dev.
    'db_driver' => 'sqlite',

    // --- MySQL / MariaDB (All-Inkl / kasserver) ---
    'mysql' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'timeminator',
        'username' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],

    // --- SQLite (local dev) ---
    // Lives inside data/ which is denied to the web by data/.htaccess.
    'sqlite' => [
        'path' => __DIR__ . '/data/timeminator.sqlite',
    ],

    // App timezone. All timestamps are stored/displayed in this zone.
    'timezone' => 'Europe/Berlin',

    // Hour that separates work-days for statistics ("Arbeitstag").
    // Activity before this hour counts toward the previous work-day.
    // 0 = calendar day; 4 = the spec's 04:00 night boundary.
    'day_boundary_hour' => 4,

    // Session cookie name (keep distinct so it does not collide with the
    // other projects living on public.felixschaller.com).
    'session_name' => 'timeminator_sid',

    // Set true only when served over HTTPS (production). Locally: false.
    'secure_cookies' => false,

    // Application display name.
    'app_name' => 'Timeminator Community',

    // Show PHP errors in the browser. true for local dev, false in production.
    'debug' => true,

    // Link shown in the nav bar / footer pointing to the Pro edition
    // (Planung/Budget/Report, Angebote, Rechnungen + Buchhaltungs-Konnektoren).
    'pro_url' => 'https://timeminator.felixschaller.com',

    // --- Updates / registration (Issue #2) ---
    // Release channel for the update check. GitHub Releases API by default.
    // Adjust the repo slug to wherever the Community Edition is published.
    'update_manifest_url' => 'https://api.github.com/repos/freshNfunky/Timeminator-Community/releases/latest',
    // Endpoint the OPTIONAL, opt-in installation registration is sent to.
    // This is the lead-generator (Issue #2c): only email/domain/version, off by default.
    'registration_endpoint' => 'https://public.felixschaller.com/timeminator-registry/register.php',
    // Allow the updater to install a release when the release does not publish
    // a SHA256SUMS asset. Kept true so existing 0.x releases still update; SHOULD
    // be set to false once you rely on releases that publish SHA256SUMS.
    'update_allow_unverified' => true,

    // --- Security response headers (Issue #8) ---
    // Custom Content-Security-Policy. Leave commented for the built-in default,
    // which assumes login and installer views inline no JavaScript.
    // 'csp' => "default-src 'self'; ...",
    // Custom Strict-Transport-Security. Sent only when the request is HTTPS.
    // 'hsts' => 'max-age=31536000; includeSubDomains',
    // Session cookie SameSite policy. 'Lax' by default; 'Strict' is safer but
    // logs users out when they follow a link into the app from an external site.
    // 'session_samesite' => 'Strict',
];
