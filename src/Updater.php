<?php
declare(strict_types=1);

/**
 * Update check + guided apply (Issue #2b).
 *
 * The check queries a configurable release manifest (GitHub Releases API by
 * default). Applying an update downloads the release archive, keeps config.php
 * and data/ untouched, replaces application files and runs pending migrations.
 * All of this needs outbound HTTPS from the server; on a server without it,
 * update manually (see README).
 */
final class Updater
{
    /** Paths never overwritten by an update. */
    private const PROTECTED = ['config.php', 'data', '.git', '.gitignore'];

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
            curl_close($ch);
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

    /** Query the release channel; cache the result in Settings. */
    public static function check(): array
    {
        $url = (string) cfg('update_manifest_url', '');
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
            $asset = null;
            $assetName = null;
            $checksums = null;
            foreach (($data['assets'] ?? []) as $a) {
                $name = (string) ($a['name'] ?? '');
                $url  = (string) ($a['browser_download_url'] ?? '');
                if ($url === '') {
                    continue;
                }
                if ($asset === null && str_ends_with($name, '.zip')) {
                    $asset = $url;
                    $assetName = $name;
                } elseif ($checksums === null && preg_match('/^sha256sums(\.txt)?$/i', $name)) {
                    $checksums = $url;
                }
            }
            $result = [
                'ok'          => true,
                'current'     => self::current(),
                'latest'      => $latest,
                'newer'       => version_compare($latest, self::normalize(self::current()), '>'),
                'url'         => (string) ($data['html_url'] ?? ''),
                'notes'       => (string) ($data['body'] ?? ''),
                'download'    => $asset ?: (string) ($data['zipball_url'] ?? ''),
                'asset_name'  => $assetName,
                'checksums'   => $checksums,
                'checked_at'  => now(),
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

        try {
            self::httpGet($download, [], $zipPath);
            self::verifyChecksum($zipPath, $info);
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

            self::copyTree($src, APP_ROOT);
            self::runMigrations();

            // best-effort cleanup
            self::rrmdir($tmpDir);

            return [true, 'Aktualisiert auf Version ' . self::current() . '.'];
        } catch (Throwable $e) {
            return [false, 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /**
     * Verify the downloaded archive against a SHA256SUMS asset published with
     * the release (issue #2). When no SHA256SUMS asset is present, the update
     * is refused unless `update_allow_unverified` is set to true in config —
     * kept true by default so existing 0.x releases (which predate SHA256SUMS
     * publishing) still update, but every operator SHOULD set it to false.
     *
     * @param array{download?:string,asset_name?:?string,checksums?:?string} $info
     */
    private static function verifyChecksum(string $zipPath, array $info): void
    {
        $checksumsUrl = (string) ($info['checksums'] ?? '');
        $assetName    = (string) ($info['asset_name'] ?? '');

        if ($checksumsUrl === '') {
            if (!(bool) cfg('update_allow_unverified', true)) {
                throw new RuntimeException(
                    'Release stellt kein SHA256SUMS bereit; Update abgelehnt '
                    . '(update_allow_unverified=false). Bitte manuell aktualisieren.'
                );
            }
            error_log('[updater] no SHA256SUMS asset — verification skipped (update_allow_unverified=true)');
            return;
        }

        [$status, $body] = self::httpGet($checksumsUrl);
        if ($status < 200 || $status >= 300 || $body === '') {
            throw new RuntimeException(
                'SHA256SUMS konnte nicht geladen werden (HTTP ' . $status . ').'
            );
        }
        $expected = self::findExpectedHash($body, $assetName);
        if ($expected === null) {
            throw new RuntimeException(
                'Kein passender Hash fuer ' . ($assetName ?: 'das Release-ZIP') . ' in SHA256SUMS.'
            );
        }
        $actual = hash_file('sha256', $zipPath);
        if ($actual === false) {
            throw new RuntimeException('Konnte den SHA256 des Release-Archivs nicht berechnen.');
        }
        if (!hash_equals(strtolower($expected), strtolower($actual))) {
            throw new RuntimeException(
                'SHA256 stimmt nicht ueberein — Release-Archiv abgelehnt. '
                . 'Erwartet: ' . $expected . ', erhalten: ' . $actual
            );
        }
    }

    /** Parse "hash  filename" lines (BSD or GNU coreutils format) and pick ours. */
    private static function findExpectedHash(string $body, string $assetName): ?string
    {
        $fallback = null;
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // "<hash> <space>[* ]<name>" — GNU (two spaces) or BSD ("SHA256 (name) = hash")
            if (preg_match('/^([0-9a-fA-F]{64})\s+\*?(.+)$/', $line, $m)) {
                $name = trim($m[2]);
                if ($assetName !== '' && basename($name) === $assetName) {
                    return $m[1];
                }
                if ($fallback === null) {
                    $fallback = $m[1];
                }
            } elseif (preg_match('/^SHA256\s*\((.+)\)\s*=\s*([0-9a-fA-F]{64})$/', $line, $m)) {
                $name = trim($m[1]);
                if ($assetName !== '' && basename($name) === $assetName) {
                    return $m[2];
                }
                if ($fallback === null) {
                    $fallback = $m[2];
                }
            }
        }
        // If we know the asset name we insist on an exact match; otherwise take
        // the first hash line we saw.
        return $assetName === '' ? $fallback : null;
    }

    private static function copyTree(string $src, string $dst): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $srcLen = strlen(rtrim($src, '/')) + 1;
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), $srcLen);
            $top = explode('/', str_replace('\\', '/', $rel))[0];
            if (in_array($top, self::PROTECTED, true)) {
                continue;
            }
            $target = $dst . '/' . $rel;
            if ($item->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0775, true);
            } else {
                @mkdir(dirname($target), 0775, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Apply pending migrations. Driver-specific files live under
     * schema/migrations/<driver>/*.sql; a legacy schema/migrations/*.sql
     * folder (driver-agnostic) is still supported. Tracking is namespaced by
     * driver so switching drivers replays migrations against the new one.
     */
    private static function runMigrations(): void
    {
        $driver = DB::driver();
        $dir = APP_ROOT . '/schema/migrations/' . $driver;
        if (!is_dir($dir)) {
            $dir = APP_ROOT . '/schema/migrations';
            if (!is_dir($dir) || is_dir(APP_ROOT . '/schema/migrations/sqlite')
                || is_dir(APP_ROOT . '/schema/migrations/mysql')) {
                // Either no migrations dir, or the driver-specific layout is in
                // use but no folder exists for the current driver — nothing to do.
                return;
            }
        }
        $applied = (array) Settings::get('applied_migrations', []);
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $key = $driver . '/' . basename($file);
            // Legacy entries from before driver namespacing are also honored.
            if (in_array($key, $applied, true) || in_array(basename($file), $applied, true)) {
                continue;
            }
            DB::applySqlFile($file);
            $applied[] = $key;
            Settings::set('applied_migrations', $applied);
        }
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
