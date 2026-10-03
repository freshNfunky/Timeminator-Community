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
    'registration_endpoint' => 'https://license.felixschaller.com/timeminator-registry/register.php',

    // --- Community banner carousel + telemetry (Issue #22) ---
    // The Community edition carries a slim banner column that rotates through
    // FelixSchallerCOM services, tools and news. Creatives are loaded from a
    // dedicated subdomain so they can be rotated without shipping a release; on
    // any network failure the app falls back to the bundled default set in
    // assets/banners/default.json, so the slot is never empty or broken.
    //
    // This is how the free Community edition is funded. In the Pro edition the
    // whole block is removed (or set 'enabled' => false to strip it here).
    // Full detail: docs/BANNERS.md.
    'banners' => [
        // Master switch. true/false forces the feature on/off (Pro ships false);
        // leave it unset to use the Community default (on, with an admin toggle
        // under Admin -> System).
        'enabled' => true,

        // Subdomain that serves the carousel feed as JSON: { "items": [ ... ] }.
        // The app fetches it server-side, so a strict CSP (connect-src 'self')
        // is enough — the browser only ever talks to this installation.
        'endpoint' => 'https://banner.felixschaller.com/feed.json',

        // First-party hosts whose https links/images the feed may reference.
        // Subdomains are always included. Anything else in the feed is stripped,
        // so a compromised feed can never point the slot at a foreign site.
        'allowed_hosts' => ['felixschaller.com', 'xixum.ai', 'af-ax.com'],

        // How long a fetched feed is cached (seconds) before the next refresh.
        // Also bounds how often telemetry is sent (once per refresh, not per view).
        'cache_ttl' => 3600,

        // Anonymized usage signal sent with each feed refresh so the operator can
        // see roughly how many Community instances are live. NEVER any
        // time-tracking data. Each field can be disabled individually; the admin
        // also has a single master opt-out under Admin -> System.
        'telemetry' => [
            'version'    => true, // app version, e.g. "0.6.0"
            'install_id' => true, // random UUID, generated once and stored in data/
            'hashed_id'  => true, // sha256(install_id | host | hash_salt) — stable across IP changes
            'ip'         => true, // request IP, truncated (last octet / IPv6 /48 zeroed)
        ],

        // Salt mixed into the hashed identifier. Set a long random string per
        // installation if you want the hash to be unlinkable to the raw UUID.
        'hash_salt' => '',
    ],
];
