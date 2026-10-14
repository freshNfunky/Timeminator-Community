<?php
declare(strict_types=1);

/**
 * Scheduler controllers (Pro-Sync A.3, #109).
 *
 * Two surfaces:
 *   - Admin → Cron page under `admin.system` for status + manual run +
 *     token regeneration.
 *   - Public `cron_web_run` endpoint for a web-cron hitter, gated by a
 *     per-install token so the whole internet can't trigger jobs.
 */

function ctrl_cron_index(): void
{
    require_perm('admin.system');
    Scheduler::bootJobs();
    view('admin/cron', [
        'jobs'      => Scheduler::status(),
        'token'     => Scheduler::token(),
        'web_cron'  => cron_web_cron_url(),
    ], t('cron.title', 'Scheduler'));
}

function ctrl_cron_run_one(): void
{
    require_perm('admin.system');
    csrf_check();
    Scheduler::bootJobs();
    $name = trim((string) post('name'));
    if (!Scheduler::has($name)) {
        flash(t('cron.err_unknown_job', 'Unbekannter Job.'), 'err');
        redirect_route('admin_cron');
    }
    $r = Scheduler::runOne($name);
    if ($r['error'] !== null) {
        flash(sprintf(t('cron.flash_run_failed', 'Job "%s" fehlgeschlagen: %s'), $name, $r['error']), 'err');
    } else {
        flash(sprintf(t('cron.flash_run_ok', 'Job "%s" ausgefuehrt (%d ms): %s'),
            $name, $r['duration_ms'], $r['result'] ?? ''));
    }
    redirect_route('admin_cron');
}

function ctrl_cron_regen_token(): void
{
    require_perm('admin.system');
    csrf_check();
    Scheduler::regenerateToken();
    flash(t('cron.flash_token_regen', 'Neuer Web-Cron-Token erzeugt.'));
    redirect_route('admin_cron');
}

/**
 * Public web-cron entry point. Only `?token=` + the stored token in
 * constant-time compare. Returns JSON so a cron-hitter service can log
 * what fired. We NEVER go through the usual require_login — cron hitters
 * are not logged in.
 */
function ctrl_cron_web_run(): void
{
    $token = (string) get('token', '');
    if (!Scheduler::verifyToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'bad_token']);
        return;
    }
    Scheduler::bootJobs();
    $start = (int) (microtime(true) * 1000);
    $results = Scheduler::runDue();
    $elapsed = (int) ((microtime(true) * 1000) - $start);
    $ran = array_values(array_filter($results, static fn(array $r) => $r['ran']));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'          => true,
        'ran'         => array_map(static fn(array $r) => [
            'name'        => $r['name'],
            'result'      => $r['result'],
            'error'       => $r['error'],
            'duration_ms' => $r['duration_ms'],
        ], $ran),
        'skipped'     => array_values(array_map(static fn(array $r) => $r['name'],
            array_filter($results, static fn(array $r) => !$r['ran']))),
        'duration_ms' => $elapsed,
    ], JSON_UNESCAPED_SLASHES);
}

/**
 * Compose the absolute URL an operator should drop into their cron
 * service. Uses `cfg('app_url')` when configured; otherwise falls back
 * to the active request's host so the admin can at least copy a working
 * URL from inside the app.
 */
function cron_web_cron_url(): string
{
    $configured = trim((string) cfg('app_url', ''));
    if ($configured !== '' && preg_match('#^(https?://[^/]+)#i', $configured, $m)) {
        $origin = $m[1];
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $origin = $scheme . '://' . $host;
    }
    return $origin . route('cron_web_run', ['token' => Scheduler::token()]);
}
