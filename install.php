<?php
declare(strict_types=1);

/**
 * Timeminator web installer (Issue #2a).
 * Runs only while no config.php exists. Checks requirements, tests the database,
 * writes config.php, applies the schema, seeds roles/permissions and creates the
 * first admin. Locks itself once a configuration exists.
 */

define('APP_ROOT', __DIR__);
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/db.php';
require __DIR__ . '/src/seed.php';
require __DIR__ . '/src/Settings.php';
require __DIR__ . '/src/Registration.php';

// Minimal config so cfg()/helpers work during install.
$GLOBALS['APP_CONFIG'] = ['app_name' => 'Timeminator'];
date_default_timezone_set('Europe/Berlin');

send_security_headers();

session_name('timeminator_install');
session_start();

// Already installed -> hand off to the app.
if (is_file(APP_ROOT . '/config.php')) {
    redirect(base_path() . '/index.php');
}

// ---------- Requirements ----------
$checks = [];
$checks[] = ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
$checks[] = ['PDO', extension_loaded('pdo'), ''];
$checks[] = ['pdo_mysql (fuer MySQL)', extension_loaded('pdo_mysql'), ''];
$checks[] = ['pdo_sqlite (fuer lokalen Modus)', extension_loaded('pdo_sqlite'), ''];
$configWritable = is_writable(APP_ROOT) || (is_file(APP_ROOT . '/config.php') && is_writable(APP_ROOT . '/config.php'));
$checks[] = ['config.php schreibbar', $configWritable, APP_ROOT];
if (!is_dir(APP_ROOT . '/data')) { @mkdir(APP_ROOT . '/data', 0775, true); }
$checks[] = ['data/ schreibbar', is_writable(APP_ROOT . '/data'), ''];
$canProceed = $configWritable && extension_loaded('pdo');

$errors = [];
$done = false;

// ---------- Process ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['_icsrf'] ?? '', (string) ($_POST['_csrf'] ?? ''))) {
        $errors[] = 'Sicherheits-Token ungueltig. Bitte Formular neu laden.';
    } else {
        $driver = ($_POST['db_driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $tz = trim((string) ($_POST['timezone'] ?? 'Europe/Berlin')) ?: 'Europe/Berlin';

        $defaultManifest = 'https://api.github.com/repos/freshNfunky/Timeminator-Community/releases/latest';
        $defaultRegistration = 'https://public.felixschaller.com/timeminator-registry/register.php';
        $manifestUrl = trim((string) ($_POST['update_manifest_url'] ?? $defaultManifest)) ?: $defaultManifest;
        $registrationUrl = trim((string) ($_POST['registration_endpoint'] ?? $defaultRegistration)) ?: $defaultRegistration;
        $updateDisabled = !empty($_POST['disable_update_check']);
        $registrationDisabled = !empty($_POST['disable_registration']);
        if ($manifestUrl !== '' && !preg_match('~^https?://~i', $manifestUrl)) {
            $errors[] = 'Update-Manifest-URL muss mit http:// oder https:// beginnen.';
        }
        if ($registrationUrl !== '' && !preg_match('~^https?://~i', $registrationUrl)) {
            $errors[] = 'Registrierungs-Endpoint muss mit http:// oder https:// beginnen.';
        }

        $config = [
            'db_driver' => $driver,
            'mysql' => [
                'host'     => trim((string) ($_POST['mysql_host'] ?? 'localhost')),
                'port'     => (int) ($_POST['mysql_port'] ?? 3306),
                'database' => trim((string) ($_POST['mysql_db'] ?? '')),
                'username' => trim((string) ($_POST['mysql_user'] ?? '')),
                'password' => (string) ($_POST['mysql_pass'] ?? ''),
                'charset'  => 'utf8mb4',
            ],
            'sqlite' => ['path' => APP_ROOT . '/data/timeminator.sqlite'],
            'timezone' => $tz,
            'day_boundary_hour' => 4,
            'session_name' => 'timeminator_sid',
            'secure_cookies' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'app_name' => trim((string) ($_POST['app_name'] ?? 'Timeminator Community')) ?: 'Timeminator Community',
            'debug' => false,
            'pro_url' => 'https://timeminator.felixschaller.com',
            'update_manifest_url' => $manifestUrl,
            'registration_endpoint' => $registrationUrl,
        ];

        // Admin account validation.
        $adminUser = trim((string) ($_POST['admin_user'] ?? ''));
        $adminName = trim((string) ($_POST['admin_name'] ?? ''));
        $adminPass = (string) ($_POST['admin_pass'] ?? '');
        $adminPass2 = (string) ($_POST['admin_pass2'] ?? '');
        if ($adminUser === '') $errors[] = 'Admin-Benutzername fehlt.';
        if (strlen($adminPass) < 8) $errors[] = 'Admin-Passwort muss mindestens 8 Zeichen haben.';
        if ($adminPass !== $adminPass2) $errors[] = 'Passwoerter stimmen nicht ueberein.';
        if ($driver === 'mysql' && $config['mysql']['database'] === '') $errors[] = 'MySQL-Datenbankname fehlt.';

        if (!$errors) {
            try {
                $GLOBALS['APP_CONFIG'] = $config;
                date_default_timezone_set($tz);
                DB::boot($config); // throws on bad credentials

                $schema = $driver === 'mysql' ? APP_ROOT . '/schema/mysql.sql' : APP_ROOT . '/schema/sqlite.sql';
                DB::applySqlFile($schema);
                seed_roles();
                seed_admin_user($adminUser, $adminName ?: $adminUser, $adminPass);

                // Write config.php
                $php = "<?php\n// Generated by the Timeminator installer on " . date('Y-m-d H:i:s') . "\nreturn " . var_export($config, true) . ";\n";
                if (@file_put_contents(APP_ROOT . '/config.php', $php) === false) {
                    throw new RuntimeException('config.php konnte nicht geschrieben werden.');
                }

                // Persist runtime toggles (endpoints can be edited later in Admin -> System).
                if ($updateDisabled) {
                    Settings::set('update_check_disabled', true);
                }
                if ($registrationDisabled) {
                    Settings::set('registration_disabled', true);
                }

                // Optional registration (opt-in). Skipped when administratively disabled.
                if (!$registrationDisabled && !empty($_POST['reg_opt_in']) && !empty($_POST['reg_email'])) {
                    Registration::save(true, trim((string) $_POST['reg_email']));
                }

                $done = true;
            } catch (Throwable $e) {
                $errors[] = 'Installation fehlgeschlagen: ' . $e->getMessage();
            }
        }
    }
}

$_SESSION['_icsrf'] = $_SESSION['_icsrf'] ?? bin2hex(random_bytes(16));
$icsrf = $_SESSION['_icsrf'];

view_bare('install', [
    'checks' => $checks,
    'canProceed' => $canProceed,
    'errors' => $errors,
    'done' => $done,
    'icsrf' => $icsrf,
    'post' => $_POST,
], 'Installation');
