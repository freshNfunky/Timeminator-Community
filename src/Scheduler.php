<?php
declare(strict_types=1);

/**
 * Lightweight job scheduler (Pro-Sync A.3, #109).
 *
 * Fits the self-hosted Community use-case: operators often sit on shared
 * hosting without proper cron. The HTTP endpoint at `/cron/run?token=...`
 * lets them use a free third-party web-cron service, and the status page
 * tells them whether anything actually fired.
 *
 * Jobs are registered in-process via `bootJobs()`. Each job carries a
 * name, an interval (minutes), and a callable that returns a short status
 * line (or throws). Last-run state lives in `scheduler_runs`, keyed by
 * job name, so a job that disappears between releases just becomes
 * orphaned state (harmless).
 */
final class Scheduler
{
    /** @var array<string, array{interval_min:int, callable:callable, description:string}> */
    private static array $jobs = [];
    private static bool $booted = false;

    public static function register(string $name, int $intervalMinutes, callable $fn, string $description = ''): void
    {
        self::$jobs[$name] = [
            'interval_min' => max(1, $intervalMinutes),
            'callable'     => $fn,
            'description'  => $description,
        ];
    }

    /** Register the built-in Community jobs. Called once per request. */
    public static function bootJobs(): void
    {
        if (self::$booted) { return; }
        self::$booted = true;

        self::register(
            'prune_expired_reset_tokens',
            60,
            static fn(): string => sprintf('deleted %d expired tokens', PasswordReset::prune()),
            'Delete expired password-reset tokens and audit-prune the used ones.'
        );

        self::register(
            'prune_rollback_snapshots',
            24 * 60,
            [self::class, 'pruneRollbackSnapshots'],
            'Delete update-rollback snapshots older than 7 days.'
        );
    }

    /** @return array<string, array{interval_min:int, description:string}> */
    public static function jobs(): array
    {
        $out = [];
        foreach (self::$jobs as $name => $cfg) {
            $out[$name] = [
                'interval_min' => $cfg['interval_min'],
                'description'  => $cfg['description'],
            ];
        }
        return $out;
    }

    public static function has(string $name): bool
    {
        return isset(self::$jobs[$name]);
    }

    /**
     * Combine the registered jobs with their stored run state. Rows with
     * no history yet show up with last_run_at = null.
     *
     * @return array<int,array{name:string,interval_min:int,description:string,last_run_at:?string,last_result:?string,last_duration_ms:?int,last_error:?string}>
     */
    public static function status(): array
    {
        $rows = DB::all('SELECT * FROM scheduler_runs');
        $byName = [];
        foreach ($rows as $r) { $byName[$r['name']] = $r; }

        $out = [];
        foreach (self::$jobs as $name => $cfg) {
            $r = $byName[$name] ?? null;
            $out[] = [
                'name'             => $name,
                'interval_min'     => $cfg['interval_min'],
                'description'      => $cfg['description'],
                'last_run_at'      => $r ? ($r['last_run_at'] ?: null) : null,
                'last_result'      => $r ? ($r['last_result'] ?: null) : null,
                'last_duration_ms' => $r && $r['last_duration_ms'] !== null ? (int) $r['last_duration_ms'] : null,
                'last_error'       => $r ? ($r['last_error'] ?: null) : null,
            ];
        }
        return $out;
    }

    /**
     * Run every job whose last-run timestamp is older than its interval
     * (or has never run). Returns a per-job summary.
     *
     * @return array<int,array{name:string,ran:bool,result:?string,error:?string,duration_ms:?int}>
     */
    public static function runDue(?DateTimeImmutable $now = null): array
    {
        $now = $now ?: new DateTimeImmutable('now');
        $out = [];
        foreach (self::$jobs as $name => $cfg) {
            $due = self::isDue($name, $cfg['interval_min'], $now);
            if (!$due) {
                $out[] = ['name' => $name, 'ran' => false, 'result' => null, 'error' => null, 'duration_ms' => null];
                continue;
            }
            $run = self::runOne($name, $now);
            $out[] = ['name' => $name, 'ran' => true] + $run;
        }
        return $out;
    }

    /**
     * Run a single job regardless of its schedule. Captures timing + any
     * exception, persists state, returns the summary.
     *
     * @return array{result:?string,error:?string,duration_ms:int}
     */
    public static function runOne(string $name, ?DateTimeImmutable $now = null): array
    {
        if (!isset(self::$jobs[$name])) {
            return ['result' => null, 'error' => 'unknown_job', 'duration_ms' => 0];
        }
        $now = $now ?: new DateTimeImmutable('now');
        $start = (int) (microtime(true) * 1000);
        $result = null;
        $error  = null;
        try {
            $result = (string) (self::$jobs[$name]['callable'])();
        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('[Scheduler] ' . $name . ' failed: ' . $error);
        }
        $duration = (int) ((microtime(true) * 1000) - $start);
        self::recordRun($name, $now->format('Y-m-d H:i:s'), $result, $duration, $error);
        return ['result' => $result, 'error' => $error, 'duration_ms' => $duration];
    }

    private static function isDue(string $name, int $intervalMin, DateTimeImmutable $now): bool
    {
        $row = DB::one('SELECT last_run_at FROM scheduler_runs WHERE name = ?', [$name]);
        if (!$row || empty($row['last_run_at'])) {
            return true;
        }
        $lastTs = strtotime((string) $row['last_run_at']);
        if ($lastTs === false) {
            return true;
        }
        return ($now->getTimestamp() - $lastTs) >= $intervalMin * 60;
    }

    private static function recordRun(string $name, string $when, ?string $result, int $durationMs, ?string $error): void
    {
        $existing = DB::one('SELECT name FROM scheduler_runs WHERE name = ?', [$name]);
        if ($existing) {
            DB::run(
                'UPDATE scheduler_runs
                    SET last_run_at = ?, last_result = ?, last_duration_ms = ?, last_error = ?
                  WHERE name = ?',
                [$when, $result, $durationMs, $error, $name]
            );
        } else {
            DB::run(
                'INSERT INTO scheduler_runs (name, last_run_at, last_result, last_duration_ms, last_error)
                 VALUES (?, ?, ?, ?, ?)',
                [$name, $when, $result, $durationMs, $error]
            );
        }
    }

    /* -------------------- built-in jobs -------------------- */

    /**
     * Delete rollback snapshots the Updater left behind. Keeps a short
     * tail — if an update looks fine for a week, we assume no further
     * rollback is needed and reclaim the disk.
     */
    public static function pruneRollbackSnapshots(int $keepDays = 7): string
    {
        $base = APP_ROOT . '/data';
        if (!is_dir($base)) { return 'no data dir, nothing to prune'; }
        $cutoff = time() - $keepDays * 86400;
        $deleted = 0;
        foreach ((array) glob($base . '/backup_*', GLOB_ONLYDIR) as $dir) {
            if (!is_string($dir)) { continue; }
            $mtime = @filemtime($dir);
            if ($mtime === false || $mtime >= $cutoff) { continue; }
            if (self::rrmdir($dir)) { $deleted++; }
        }
        return sprintf('removed %d snapshot(s) older than %dd', $deleted, $keepDays);
    }

    private static function rrmdir(string $dir): bool
    {
        if (!is_dir($dir)) { return false; }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }

    /* -------------------- web-cron token -------------------- */

    public static function token(): string
    {
        $token = (string) Settings::get('cron_token', '');
        if ($token === '') {
            $token = self::regenerateToken();
        }
        return $token;
    }

    public static function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(24));
        Settings::set('cron_token', $token);
        return $token;
    }

    public static function verifyToken(string $candidate): bool
    {
        $stored = (string) Settings::get('cron_token', '');
        if ($stored === '' || $candidate === '') { return false; }
        if (strlen($stored) !== strlen($candidate)) { return false; }
        return hash_equals($stored, $candidate);
    }

    /** For tests: wipe the in-process registry so each case starts fresh. */
    public static function resetForTests(): void
    {
        self::$jobs = [];
        self::$booted = false;
    }
}
