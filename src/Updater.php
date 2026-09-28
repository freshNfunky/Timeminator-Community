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
            $asset = null;
            foreach (($data['assets'] ?? []) as $a) {
                if (isset($a['browser_download_url']) && str_ends_with((string) $a['name'], '.zip')) {
                    $asset = (string) $a['browser_download_url'];
                    break;
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
     * Apply pending migrations for the active driver.
     *
     * Files live in schema/migrations/<driver>/*.sql. A portable
     * schema/migrations/*.sql layer is still honoured for legacy files.
     * Applied migrations are recorded by basename in Settings.
     */
    private static function runMigrations(): void
    {
        $applied = (array) Settings::get('applied_migrations', []);
        $dirs = [
            APP_ROOT . '/schema/migrations',
            APP_ROOT . '/schema/migrations/' . DB::driver(),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue;
                }
                DB::applySqlFile($file);
                $applied[] = $name;
                Settings::set('applied_migrations', $applied);
            }
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
