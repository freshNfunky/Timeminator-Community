<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap. Loads the application source without booting a full
 * request, so the pure-logic tests can require whichever classes and helpers
 * they need. Tests that need a database boot it themselves with
 * `TestSupport::bootInMemoryDb()`.
 */

define('APP_ROOT', dirname(__DIR__));

// Minimal config for `cfg()` and friends. Tests can override in setUp.
$GLOBALS['APP_CONFIG'] = [
    'app_name'          => 'Timeminator (test)',
    'timezone'          => 'Europe/Berlin',
    'day_boundary_hour' => 4,
    'debug'             => true,
    'db_driver'         => 'sqlite',
];
date_default_timezone_set('Europe/Berlin');

require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/Settings.php';
require APP_ROOT . '/src/LoginThrottle.php';
require APP_ROOT . '/src/Stats.php';
require APP_ROOT . '/src/Repo.php';
require APP_ROOT . '/src/Banners.php';

require __DIR__ . '/TestSupport.php';
