<?php
declare(strict_types=1);

/**
 * Update check + guided apply.
 *
 * apply() runs a single flow that:
 *   1. downloads the release archive over HTTPS,
 *   2. verifies its SHA-256 against a sibling `.sha256` or a `SHA256SUMS`
 *      asset from the release (hard-fails when the release advertises one
 *      that does not match; warns when the release ships no checksum at
 *      all — pre-v0.3 releases do not have any),
 *   3. snapshots every existing file that will be overwritten or pruned
 *      into `data/backup_<ts>/`,
 *   4. copies the new tree over the app,
 *   5. prunes files that used to exist in tracked directories but are no
 *      longer part of the release,
 *   6. applies pending migrations.
 *
 * Any exception in steps 3–6 triggers a full restore from the snapshot,
 * so a botched update never leaves the site half-written. On success the
 * snapshot is recorded in Settings so `rollbackLast()` can restore the
 * previous version later.
 */
final class Updater
{
    /** Paths never overwritten, backed up or pruned. */
    private const PROTECTED = ['config.php', 'data', '.git', '.gitignore', 'vendor', '.phpunit.cache'];

    /**
     * Directories in which stale files (present locally but not in the new
     * archive) are pruned. Anything outside this list — top-level custom
     * files, `docs/`, whatever a self-hoster dropped in — is left alone.
     */
    private const PRUNE_ROOTS = ['src', 'views', 'assets', 'schema'];

    public static function current(): string
    {
        return app_version();
    }

    private static function normalize(string $v): string
    {
        return ltrim(trim($v), 'vV');
    }

    /** HTTP GET returning [status, body] or throws. */
    private static function httpGet(string $url, array $headers = [], ?string $saveTo = null): array
    {
        $ua = 'Timeminator/' . self::current();
        $headers[] = 'User-Agent: ' . $ua;
        $headers[] = 'Accept: application/vnd.github+json';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $fh = null;
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($saveTo) {
                $fh = fopen($saveTo, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fh);
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            // Since PHP 8.0 the cURL handle is an object that auto-closes
            // when $ch goes out of scope; curl_close() is a no-op and was
            // formally deprecated in PHP 8.5.
            if ($fh) fclose($fh);
            if ($body === false && $err) {
                throw new RuntimeException('HTTP error: ' . $err);
            }
            return [$status, $saveTo ? '' : (string) $body];
        }

        // Fallback: streams
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 60,
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new RuntimeException('Konnte URL nicht laden (kein Netzzugang?).');
        }
        if ($saveTo) {
            file_put_contents($saveTo, $body);
            return [200, ''];
        }
        return [200, $body];
    }

    public static function manifestUrl(): string
    {
        $override = (string) Settings::get('update_manifest_url_override', '');
        return $override !== '' ? $override : (string) cfg('update_manifest_url', '');
    }

    public static function isDisabled(): bool
    {
        return (bool) Settings::get('update_check_disabled', false);
    }

    /** Query the release channel; cache the result in Settings. */
    public static function check(): array
    {
        if (self::isDisabled()) {
            return ['ok' => false, 'error' => 'Update-Pruefung ist administrativ deaktiviert.'];
        }
        $url = self::manifestUrl();
        if ($url === '') {
            return ['ok' => false, 'error' => 'Keine update_manifest_url konfiguriert.'];
        }
        try {
            [$status, $body] = self::httpGet($url);
            if ($status < 200 || $status >= 300) {
                return ['ok' => false, 'error' => 'Release-Server antwortete mit HTTP ' . $status . '.'];
            }
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['tag_name'])) {
                return ['ok' => false, 'error' => 'Unerwartete Antwort vom Release-Server.'];
            }
            $latest = self::normalize((string) $data['tag_name']);
            $assets = (array) ($data['assets'] ?? []);
            $asset = null;
            $assetName = '';
            foreach ($assets as $a) {
                if (isset($a['browser_download_url']) && str_ends_with((string) $a['name'], '.zip')) {
                    $asset = (string) $a['browser_download_url'];
                    $assetName = (string) $a['name'];
                    break;
                }
            }
            $result = [
                'ok'           => true,
                'current'      => self::current(),
                'latest'       => $latest,
                'newer'        => version_compare($latest, self::normalize(self::current()), '>'),
                'url'          => (string) ($data['html_url'] ?? ''),
                'notes'        => (string) ($data['body'] ?? ''),
                'download'     => $asset ?: (string) ($data['zipball_url'] ?? ''),
                'asset_name'   => $assetName,
                'checksum_url' => self::findChecksumUrl($assets, $assetName),
                'checked_at'   => now(),
            ];
            Settings::set('update', $result);
            return $result;
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function cached(): ?array
    {
        return Settings::get('update');
    }

    /**
     * Refresh the update cache at most once every $throttleSeconds. Called on
     * boot from the layout, so the banner has a fresh answer without hitting
     * the release manifest on every request. Any error is swallowed — a page
     * load must never fail because the release manifest is unreachable.
     */
    public static function opportunisticCheck(int $throttleSeconds = 86400): void
    {
        if (self::isDisabled()) {
            return;
        }
        if (self::manifestUrl() === '') {
            return;
        }
        $cached = self::cached();
        $last = 0;
        if (is_array($cached) && !empty($cached['checked_at'])) {
            try {
                $last = (new DateTimeImmutable((string) $cached['checked_at']))->getTimestamp();
            } catch (Throwable) {
                $last = 0;
            }
        }
        if (time() - $last < $throttleSeconds) {
            return;
        }
        try {
            self::check();
        } catch (Throwable) {
            // silent — offline server, blocked outbound, throttled manifest.
        }
    }

    /**
     * Return the cached update info only when it points to a version that
     *  a) is newer than the running one,
     *  b) has not been skipped by an administrator, and
     *  c) has not been dismissed in this session.
     * Otherwise null. The banner uses this — everywhere else keeps calling
     * cached() directly.
     */
    public static function bannerInfo(): ?array
    {
        $info = self::cached();
        if (!is_array($info) || empty($info['ok']) || empty($info['newer'])) {
            return null;
        }
        $latest = (string) ($info['latest'] ?? '');
        if ($latest === '') {
            return null;
        }
        if (self::isVersionSkipped($latest)) {
            return null;
        }
        if (isset($_SESSION['update_banner_dismissed_for'])
            && (string) $_SESSION['update_banner_dismissed_for'] === self::normalize($latest)) {
            return null;
        }
        return $info;
    }

    public static function isVersionSkipped(string $version): bool
    {
        $v = self::normalize($version);
        if ($v === '') {
            return false;
        }
        $skipped = (array) Settings::get('update_skipped_versions', []);
        foreach ($skipped as $s) {
            if (self::normalize((string) $s) === $v) {
                return true;
            }
        }
        return false;
    }

    /** Persist a "skip this version forever" — until a newer one shows up. */
    public static function skipVersion(string $version): void
    {
        $v = self::normalize($version);
        if ($v === '') {
            return;
        }
        $skipped = (array) Settings::get('update_skipped_versions', []);
        $skipped[] = $v;
        Settings::set('update_skipped_versions', array_values(array_unique(
            array_map([self::class, 'normalize'], $skipped)
        )));
    }

    /** Session-scoped dismiss ("show me again next login"). */
    public static function dismissForSession(string $version): void
    {
        $_SESSION['update_banner_dismissed_for'] = self::normalize($version);
    }

    /** Wipe every remembered skip — the operator wants to see all offers again. */
    public static function clearSkippedVersions(): void
    {
        Settings::set('update_skipped_versions', []);
    }

    /** @return array<int,string> the persisted list of skipped versions */
    public static function skippedVersions(): array
    {
        return array_values(array_map(
            [self::class, 'normalize'],
            (array) Settings::get('update_skipped_versions', [])
        ));
    }

    /** Download + apply the latest release. Returns [ok, message]. */
    public static function apply(): array
    {
        if (!class_exists('ZipArchive')) {
            return [false, 'PHP-Erweiterung ZipArchive fehlt; bitte manuell aktualisieren.'];
        }
        $info = self::check();
        if (empty($info['ok'])) {
            return [false, $info['error'] ?? 'Update-Pruefung fehlgeschlagen.'];
        }
        if (empty($info['newer'])) {
            return [false, 'Bereits aktuell (Version ' . self::current() . ').'];
        }
        $download = (string) $info['download'];
        if ($download === '') {
            return [false, 'Kein Download-Link im Release gefunden.'];
        }

        $tmpDir = APP_ROOT . '/data/update_' . date('Ymd_His');
        @mkdir($tmpDir, 0775, true);
        $zipPath = $tmpDir . '/release.zip';
        $backupDir = APP_ROOT . '/data/backup_' . date('Ymd_His');
        $warnings = [];
        $manifest = ['backed_up' => [], 'created' => []];
        $fromVersion = self::current();
        $strict = (bool) cfg('require_release_checksum', false);

        try {
            self::httpGet($download, [], $zipPath);

            $checkResult = self::verifyChecksum($zipPath, $info, $tmpDir);
            if ($checkResult['status'] === 'mismatch') {
                throw new RuntimeException('Checksumme des Downloads passt nicht (' . $checkResult['expected'] . ' erwartet, ' . $checkResult['actual'] . ' erhalten).');
            }
            if ($checkResult['status'] === 'missing') {
                if ($strict) {
                    throw new RuntimeException('Release liefert keine SHA-256 Checksumme und require_release_checksum ist aktiviert.');
                }
                $warnings[] = 'Release liefert keine SHA-256 Checksumme; ' . $checkResult['message'];
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Release-Archiv konnte nicht geoeffnet werden.');
            }
            $extractDir = $tmpDir . '/extract';
            @mkdir($extractDir, 0775, true);
            $zip->extractTo($extractDir);
            $zip->close();

            // Find the source root (release archives wrap everything in one folder).
            $src = $extractDir;
            $entries = array_values(array_filter(scandir($extractDir) ?: [], fn($e) => $e !== '.' && $e !== '..'));
            if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
                $src = $extractDir . '/' . $entries[0];
            }

            // Collect the file set the new archive would install, relative to $src.
            $sourceFiles = self::listRelativeFiles($src);

            @mkdir($backupDir, 0775, true);

            // 1) Snapshot + copy new files.
            self::snapshotAndCopy($src, APP_ROOT, $backupDir, $sourceFiles, $manifest);

            // 2) Prune files present locally but not in the archive, only in tracked dirs.
            self::prune($sourceFiles, APP_ROOT, $backupDir, $manifest);

            // 3) Migrations.
            self::runMigrations();

            // Success: remember the backup so it can be used for manual rollback later.
            Settings::set('update_backup', [
                'dir'          => $backupDir,
                'from_version' => $fromVersion,
                'to_version'   => self::current(),
                'created_at'   => now(),
                'manifest'     => $manifest,
                'warnings'     => $warnings,
            ]);

            // Best-effort tmp cleanup.
            self::rrmdir($tmpDir);

            $msg = 'Aktualisiert auf Version ' . self::current() . '.';
            if ($warnings) {
                $msg .= ' Hinweise: ' . implode(' | ', $warnings);
            }
            return [true, $msg];
        } catch (Throwable $e) {
            self::restoreFromManifest($manifest, $backupDir);
            // Also drop the failed backup dir since we already restored from it.
            self::rrmdir($backupDir);
            self::rrmdir($tmpDir);
            return [false, 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /**
     * Restore the most recent successful backup, if any. Returns [ok, message].
     */
    public static function rollbackLast(): array
    {
        $backup = Settings::get('update_backup');
        if (!is_array($backup) || empty($backup['dir']) || !is_dir($backup['dir'])) {
            return [false, 'Kein Backup einer vorherigen Version gefunden.'];
        }
        $manifest = (array) ($backup['manifest'] ?? ['backed_up' => [], 'created' => []]);
        try {
            self::restoreFromManifest($manifest, $backup['dir']);
            self::rrmdir($backup['dir']);
            Settings::set('update_backup', null);
            return [true, 'Zurueckgesetzt auf Version ' . (string) ($backup['from_version'] ?? '?') . '.'];
        } catch (Throwable $e) {
            return [false, 'Rollback fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @return array<int,array{path:string}> just a set of relative paths */
    private static function listRelativeFiles(string $src): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $srcLen = strlen(rtrim($src, '/')) + 1;
        foreach ($it as $item) {
            if ($item->isDir()) continue;
            $rel = str_replace('\\', '/', substr($item->getPathname(), $srcLen));
            if (self::isProtectedRel($rel)) continue;
            $out[$rel] = true;
        }
        return $out;
    }

    /**
     * For each file in $sourceFiles, back up the current app file (if any)
     * to $backupDir and then copy the new file into place.
     *
     * @param array<string,bool> $sourceFiles
     * @param array{backed_up:array<int,string>,created:array<int,string>} $manifest
     */
    private static function snapshotAndCopy(string $src, string $dst, string $backupDir, array $sourceFiles, array &$manifest): void
    {
        foreach (array_keys($sourceFiles) as $rel) {
            $srcPath = $src . '/' . $rel;
            $dstPath = $dst . '/' . $rel;
            if (is_file($dstPath)) {
                $bakPath = $backupDir . '/' . $rel;
                @mkdir(dirname($bakPath), 0775, true);
                if (!copy($dstPath, $bakPath)) {
                    throw new RuntimeException('Backup fuer ' . $rel . ' fehlgeschlagen.');
                }
                $manifest['backed_up'][] = $rel;
            } else {
                $manifest['created'][] = $rel;
            }
            @mkdir(dirname($dstPath), 0775, true);
            if (!copy($srcPath, $dstPath)) {
                throw new RuntimeException('Konnte ' . $rel . ' nicht schreiben.');
            }
        }
    }

    /**
     * Remove every file under PRUNE_ROOTS that is not in $sourceFiles and
     * not protected. Backs up each removed file first.
     *
     * @param array<string,bool> $sourceFiles
     * @param array{backed_up:array<int,string>,created:array<int,string>} $manifest
     */
    private static function prune(array $sourceFiles, string $dst, string $backupDir, array &$manifest): void
    {
        foreach (self::PRUNE_ROOTS as $root) {
            $abs = $dst . '/' . $root;
            if (!is_dir($abs)) continue;
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                if ($item->isDir()) continue;
                $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($dst) + 1));
                if (self::isProtectedRel($rel)) continue;
                if (isset($sourceFiles[$rel])) continue;
                $bakPath = $backupDir . '/' . $rel;
                @mkdir(dirname($bakPath), 0775, true);
                if (!copy($item->getPathname(), $bakPath)) {
                    throw new RuntimeException('Backup vor Prune fuer ' . $rel . ' fehlgeschlagen.');
                }
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException('Konnte veraltete Datei ' . $rel . ' nicht entfernen.');
                }
                $manifest['pruned'][] = $rel;
            }
        }
    }

    /**
     * Undo whatever was recorded in $manifest: restore backed-up files,
     * delete files that were freshly created, restore files that were
     * pruned. Safe to call more than once — the input state is idempotent.
     *
     * @param array{backed_up?:array<int,string>,created?:array<int,string>,pruned?:array<int,string>} $manifest
     */
    private static function restoreFromManifest(array $manifest, string $backupDir): void
    {
        foreach ((array) ($manifest['created'] ?? []) as $rel) {
            @unlink(APP_ROOT . '/' . $rel);
        }
        foreach ((array) ($manifest['backed_up'] ?? []) as $rel) {
            $bakPath = $backupDir . '/' . $rel;
            $dstPath = APP_ROOT . '/' . $rel;
            if (is_file($bakPath)) {
                @mkdir(dirname($dstPath), 0775, true);
                @copy($bakPath, $dstPath);
            }
        }
        foreach ((array) ($manifest['pruned'] ?? []) as $rel) {
            $bakPath = $backupDir . '/' . $rel;
            $dstPath = APP_ROOT . '/' . $rel;
            if (is_file($bakPath)) {
                @mkdir(dirname($dstPath), 0775, true);
                @copy($bakPath, $dstPath);
            }
        }
    }

    private static function isProtectedRel(string $rel): bool
    {
        $top = explode('/', $rel)[0];
        return in_array($top, self::PROTECTED, true);
    }

    /**
     * Find a SHA-256 URL in the release's asset list. Prefers a sibling
     * `<zip>.sha256`; falls back to a `SHA256SUMS(.txt)` asset. Returns
     * '' when neither is present.
     */
    private static function findChecksumUrl(array $assets, string $assetName): string
    {
        $sibling = $assetName !== '' ? strtolower($assetName) . '.sha256' : '';
        $sumsCandidates = ['sha256sums', 'sha256sums.txt', 'checksums.txt'];
        $sumsUrl = '';
        foreach ($assets as $a) {
            $n = strtolower((string) ($a['name'] ?? ''));
            $u = (string) ($a['browser_download_url'] ?? '');
            if ($u === '') continue;
            if ($sibling !== '' && $n === $sibling) {
                return $u;
            }
            if (in_array($n, $sumsCandidates, true)) {
                $sumsUrl = $u;
            }
        }
        return $sumsUrl;
    }

    /**
     * @return array{status: 'ok'|'missing'|'mismatch', expected?: string, actual?: string, message?: string}
     */
    private static function verifyChecksum(string $zipPath, array $info, string $tmpDir): array
    {
        $expected = '';
        $checksumUrl = (string) ($info['checksum_url'] ?? '');
        if ($checksumUrl === '') {
            return ['status' => 'missing', 'message' => 'Update wurde ohne Pruefsummen-Vergleich installiert.'];
        }

        $sumsPath = $tmpDir . '/checksum.txt';
        try {
            self::httpGet($checksumUrl, [], $sumsPath);
        } catch (Throwable $e) {
            return ['status' => 'missing', 'message' => 'Pruefsummen-Datei nicht abrufbar (' . $e->getMessage() . ').'];
        }
        $text = trim((string) @file_get_contents($sumsPath));
        if ($text === '') {
            return ['status' => 'missing', 'message' => 'Pruefsummen-Datei war leer.'];
        }
        $assetName = strtolower((string) ($info['asset_name'] ?? ''));
        $expected = self::parseChecksumFor($text, $assetName);
        if ($expected === '') {
            return ['status' => 'missing', 'message' => 'Pruefsummen-Datei enthaelt keinen Hash fuer ' . $assetName . '.'];
        }
        $actual = strtolower((string) hash_file('sha256', $zipPath));
        if (!hash_equals($expected, $actual)) {
            return ['status' => 'mismatch', 'expected' => $expected, 'actual' => $actual];
        }
        return ['status' => 'ok', 'expected' => $expected, 'actual' => $actual];
    }

    /**
     * Extract a hash from a `SHA256SUMS`-formatted text: either a single
     * bare hash line, or `<hash>  <name>` / `<hash> *<name>` lines. The
     * hash matching $assetName wins; otherwise the first bare hash is
     * returned. Returns '' when nothing plausible is found.
     */
    public static function parseChecksumFor(string $text, string $assetName): string
    {
        $assetName = strtolower($assetName);
        $fallback = '';
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (preg_match('/^([a-f0-9]{64})(?:\s+[\*\s]?(.+))?$/i', $line, $m)) {
                $hash = strtolower($m[1]);
                $name = strtolower(trim($m[2] ?? ''));
                if ($name === $assetName && $assetName !== '') {
                    return $hash;
                }
                if ($fallback === '' && $name === '') {
                    $fallback = $hash;
                }
            }
        }
        return $fallback;
    }

    /**
     * Apply pending migrations for the active driver.
     *
     * Files live in `schema/migrations/<driver>/YYYY-MM-DD-NN-slug.<ext>`.
     * A portable `schema/migrations/*.sql` layer is still honoured for
     * legacy files. Both `.sql` (DDL / DML) and `.php` (data
     * transformations) files are supported; `.php` files are `require`d
     * in a scope that exposes DB and Settings. Applied migrations are
     * recorded by basename in Settings — see docs/schema-migrations.md.
     */
    public static function runMigrations(): int
    {
        $applied = (array) Settings::get('applied_migrations', []);
        $ran = 0;
        $dirs = [
            APP_ROOT . '/schema/migrations',
            APP_ROOT . '/schema/migrations/' . DB::driver(),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = array_merge(
                glob($dir . '/*.sql') ?: [],
                glob($dir . '/*.php') ?: []
            );
            sort($files);
            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue;
                }
                if (str_ends_with($name, '.php')) {
                    self::runPhpMigration($file);
                } else {
                    DB::applySqlFile($file);
                }
                $applied[] = $name;
                Settings::set('applied_migrations', $applied);
                $ran++;
            }
        }
        return $ran;
    }

    /**
     * `require` a data-migration script in an isolated closure so its
     * top-level variables never leak into caller scope. Any throw
     * propagates — the caller decides whether to record it as applied.
     */
    private static function runPhpMigration(string $file): void
    {
        (static function (string $__file) {
            require $__file;
        })($file);
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
