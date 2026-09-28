<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap. Loads Composer's autoloader for PHPUnit itself and then
 * hand-requires the app's plain-PHP source files (there is no runtime
 * autoloader in Timeminator — releases must run without Composer).
 */

require __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT', dirname(__DIR__));

// Minimal in-memory globals so cfg() has something to read.
$GLOBALS['APP_CONFIG'] = [
    'timezone'          => 'Europe/Berlin',
    'day_boundary_hour' => 4,
    'app_name'          => 'Timeminator Test',
    'trusted_proxies'   => [],
];
date_default_timezone_set('Europe/Berlin');

require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/LoginThrottle.php';
require APP_ROOT . '/src/Settings.php';
require APP_ROOT . '/src/Repo.php';
require APP_ROOT . '/src/Stats.php';
require APP_ROOT . '/src/seed.php';

// A session is needed for the CSRF helpers.
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Boot an in-memory SQLite database with the current schema applied.
 * DB is a singleton, so tests share it within a single process — call this
 * once per test that needs a fresh database and it resets by re-applying.
 */
function tm_boot_sqlite(): void
{
    // Reset the DB singleton state via reflection so each test can start
    // clean instead of sharing schema/data with earlier tests.
    $r = new ReflectionClass(DB::class);
    if ($r->hasProperty('pdo')) {
        $p = $r->getProperty('pdo');
        $p->setValue(null, null);
    }
    DB::boot([
        'db_driver' => 'sqlite',
        'sqlite'    => ['path' => ':memory:'],
    ]);
    DB::applySqlFile(APP_ROOT . '/schema/sqlite.sql');
}
