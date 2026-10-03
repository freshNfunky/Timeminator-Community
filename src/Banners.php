<?php
declare(strict_types=1);

/**
 * Community banner carousel (Issue #22).
 *
 * The Community edition carries a slim banner column that rotates through
 * FelixSchallerCOM services, tools and news. Creatives are loaded from a
 * dedicated subdomain (configurable) so they can be rotated without shipping a
 * release. The browser only ever talks to THIS installation: the page fetches a
 * first-party route, and PHP fetches the subdomain server-side. On any network
 * failure the bundled default set in assets/banners/default.json is served, so
 * the slot is never empty or broken.
 *
 * A small, anonymized usage signal rides along with each feed refresh so the
 * operator can see roughly how many Community instances are live. It never
 * contains any time-tracking data, every field can be disabled individually in
 * config.php, and the admin has a single master opt-out under Admin -> System.
 * In the Pro edition the whole feature (and its telemetry) is removed.
 */
final class Banners
{
    private const CACHE_KEY   = 'banner_feed_cache';
    private const INSTALL_KEY = 'install_id';
    private const DEFAULT_TTL = 3600;

    // ---------- Visibility / configuration ----------

    /** Raw 'banners' config block from config.php (always an array). */
    private static function conf(): array
    {
        $c = cfg('banners', []);
        return is_array($c) ? $c : [];
    }

    /**
     * Whether the banner slot is shown at all. The operator's config master
     * switch wins: `['enabled' => false]` strips it (Pro). When the key is
     * absent, the Community default applies and the admin runtime toggle
     * (`banner_visible`, default on) decides.
     */
    public static function isVisible(): bool
    {
        $c = self::conf();
        if (array_key_exists('enabled', $c) && !$c['enabled']) {
            return false;
        }
        return (bool) Settings::get('banner_visible', true);
    }

    /** True when config.php hard-disables the feature (Pro / operator opt-out). */
    public static function isConfigDisabled(): bool
    {
        $c = self::conf();
        return array_key_exists('enabled', $c) && !$c['enabled'];
    }

    /** Config default endpoint, before any admin override. */
    public static function endpointDefault(): string
    {
        return (string) (self::conf()['endpoint'] ?? '');
    }

    /** Feed endpoint: admin override wins over the config default. */
    public static function endpoint(): string
    {
        $override = (string) Settings::get('banner_endpoint_override', '');
        return $override !== '' ? $override : (string) (self::conf()['endpoint'] ?? '');
    }

    public static function cacheTtl(): int
    {
        return max(60, (int) (self::conf()['cache_ttl'] ?? self::DEFAULT_TTL));
    }

    /** Master telemetry opt-out (admin). Default on in Community. */
    public static function telemetryEnabled(): bool
    {
        return (bool) Settings::get('banner_telemetry', true);
    }

    // ---------- Installation identity ----------

    /** Stable random installation id, generated once on first use. */
    public static function installId(): string
    {
        $id = (string) Settings::get(self::INSTALL_KEY, '');
        if ($id === '') {
            $id = self::uuid4();
            Settings::set(self::INSTALL_KEY, $id);
        }
        return $id;
    }

    public static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    // ---------- Feed loading (cache -> remote -> bundled default) ----------

    /**
     * Current carousel payload: ['source' => remote|cache|default, 'items' => [...]].
     * Never throws and always returns at least the bundled default set.
     */
    public static function feed(): array
    {
        $now = time();
        $cache = Settings::get(self::CACHE_KEY, null);
        if (is_array($cache) && !empty($cache['items']) && ($cache['fetched_at'] ?? 0) + self::cacheTtl() > $now) {
            return ['source' => 'cache', 'items' => self::sanitizeItems($cache['items'])];
        }

        $endpoint = self::endpoint();
        if ($endpoint !== '') {
            $items = self::fetchRemote($endpoint);
            if ($items !== null && $items !== []) {
                Settings::set(self::CACHE_KEY, ['fetched_at' => $now, 'items' => $items]);
                return ['source' => 'remote', 'items' => self::sanitizeItems($items)];
            }
            // Fetch failed: keep serving a stale cache if we have one.
            if (is_array($cache) && !empty($cache['items'])) {
                return ['source' => 'cache', 'items' => self::sanitizeItems($cache['items'])];
            }
        }

        return ['source' => 'default', 'items' => self::defaultItems()];
    }

    /** Bundled offline fallback set shipped in assets/banners/default.json. */
    public static function defaultItems(): array
    {
        $file = APP_ROOT . '/assets/banners/default.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        $items = is_array($data) ? ($data['items'] ?? []) : [];
        return self::sanitizeItems(is_array($items) ? $items : []);
    }

    /**
     * Fetch + decode the remote feed, attaching the anonymized usage signal.
     * Returns the raw item list, or null on any failure (caller falls back).
     */
    private static function fetchRemote(string $endpoint): ?array
    {
        $url = $endpoint;
        $tele = self::telemetry();
        if ($tele !== []) {
            $url .= (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($tele);
        }

        try {
            $body = self::httpGet($url);
            if ($body === null) {
                return null;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                return null;
            }
            return $data['items'];
        } catch (Throwable) {
            return null;
        }
    }

    /** Minimal, short-timeout GET. Returns the body or null. */
    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Timeminator/' . app_version(),
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($body === false || $status >= 400) {
                return null;
            }
            return (string) $body;
        }

        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 6, 'header' => 'User-Agent: Timeminator/' . app_version() . "\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : (string) $body;
    }

    // ---------- Telemetry (Community-only, opt-outable) ----------

    /**
     * Build the anonymized usage signal for the live configuration. Returns an
     * empty array when telemetry is off (admin master switch). Never includes
     * any time-tracking data.
     */
    public static function telemetry(): array
    {
        if (!self::telemetryEnabled()) {
            return [];
        }
        return self::buildTelemetry(
            self::installId(),
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            self::conf(),
            client_ip()
        );
    }

    /**
     * Pure telemetry composer so the field selection is unit-testable without
     * touching Settings, the network, or $_SERVER. Each field defaults to on and
     * is individually switchable via the `telemetry` config block.
     *
     * @param array $conf the 'banners' config block
     */
    public static function buildTelemetry(string $installId, string $host, array $conf, string $ip): array
    {
        $fields = (array) ($conf['telemetry'] ?? []);
        $on = static fn(string $k): bool => (bool) ($fields[$k] ?? true);
        $salt = (string) ($conf['hash_salt'] ?? '');

        $out = ['app' => 'timeminator'];
        if ($on('version')) {
            $out['v'] = app_version();
        }
        if ($on('install_id')) {
            $out['iid'] = $installId;
        }
        if ($on('hashed_id')) {
            $out['hid'] = hash('sha256', $installId . '|' . $host . '|' . $salt);
        }
        if ($on('ip')) {
            $out['ip'] = self::truncateIp($ip);
        }
        return $out;
    }

    /**
     * Coarsen an IP so it identifies a network, not a device: IPv4 drops the
     * last octet, IPv6 keeps only the first three hextets (/48).
     */
    public static function truncateIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }
        if (strpos($ip, ':') !== false) {
            $groups = explode(':', $ip);
            return implode(':', array_slice($groups, 0, 3)) . '::';
        }
        $octets = explode('.', $ip);
        if (count($octets) === 4) {
            $octets[3] = '0';
            return implode('.', $octets);
        }
        return '';
    }

    // ---------- Sanitizing ----------

    /** @param array<int,mixed> $items */
    public static function sanitizeItems(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $clean = self::sanitizeItem($it);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }
        return $out;
    }

    /**
     * Normalize one creative to a safe, known shape. Rejects anything without
     * visible text; drops links/images that are not first-party so the remote
     * feed cannot point the slot at an arbitrary destination.
     */
    public static function sanitizeItem(array $it): ?array
    {
        $str = static fn(string $k): string => trim((string) ($it[$k] ?? ''));
        $title = $str('title');
        $text  = $str('text');
        if ($title === '' && $text === '') {
            return null;
        }

        $href  = $str('href');
        $image = $str('image');
        if ($href !== '' && !self::isAllowedHref($href)) {
            $href = '';
        }
        if ($image !== '' && !self::isAllowedImage($image)) {
            $image = '';
        }

        return [
            'id'    => $str('id') !== '' ? $str('id') : substr(hash('sha256', $title . $text . $href), 0, 12),
            'title' => $title,
            'text'  => $text,
            'cta'   => $str('cta'),
            'href'  => $href,
            'image' => $image,
        ];
    }

    /**
     * First-party hosts whose https links/images the feed may use. Defaults to
     * the FelixSchallerCOM brand portfolio; override with `banners.allowed_hosts`
     * in config.php. Subdomains of each listed host are always included.
     *
     * @return array<int,string>
     */
    public static function allowedHosts(): array
    {
        $hosts = self::conf()['allowed_hosts'] ?? ['felixschaller.com', 'xixum.ai', 'af-ax.com'];
        $out = [];
        foreach ((array) $hosts as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '') {
                $out[] = $h;
            }
        }
        return $out ?: ['felixschaller.com', 'xixum.ai', 'af-ax.com'];
    }

    private static function hostAllowed(string $host): bool
    {
        $host = strtolower($host);
        foreach (self::allowedHosts() as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                return true;
            }
        }
        return false;
    }

    /** Only https links to an allowed first-party host are accepted. */
    public static function isAllowedHref(string $href): bool
    {
        $p = parse_url($href);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') === '') {
            return false;
        }
        return self::hostAllowed($p['host']);
    }

    /**
     * Accept a same-origin relative asset (rendered under img-src 'self'), an
     * inline `data:image/*` URI (allowed by the default `img-src ... data:`), or
     * an https image on an allowed first-party host. Protocol-relative and other
     * schemes are rejected.
     */
    public static function isAllowedImage(string $image): bool
    {
        if (str_starts_with($image, '//')) {
            return false;
        }
        if (preg_match('~^data:image/(?:svg\+xml|png|jpeg|jpg|gif|webp)[;,]~i', $image)) {
            return true;
        }
        if (preg_match('~^https?://~i', $image)) {
            $p = parse_url($image);
            if (!$p || ($p['scheme'] ?? '') !== 'https') {
                return false;
            }
            return self::hostAllowed($p['host'] ?? '');
        }
        // Relative path: letters, digits and simple path chars only, no traversal.
        return (bool) preg_match('~^[A-Za-z0-9][A-Za-z0-9/_\-.]*$~', $image) && !str_contains($image, '..');
    }
}
