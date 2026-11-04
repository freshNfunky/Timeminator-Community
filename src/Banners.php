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

    // Baseline endpoint used when the operator has not set `banners.endpoint`
    // in config.php. Keeps the Community edition working out of the box after
    // a fresh install - without this, a config missing the banners block would
    // silently fall back to the bundled offline SVG set forever. Served from
    // an ad-blocker-neutral subdomain (not `banner.*`).
    //
    // The REST endpoint (feed.php) serves every locale from one URL via a
    // `?lang=xx` query (feed.php?lang=en), so there is no per-language file to
    // publish or discover; it also lets the operator see roughly how many
    // Community instances are live. Older installs that still point at the
    // static `feed.json` keep working unchanged - the server publishes both.
    private const DEFAULT_ENDPOINT = 'https://assets.felixschaller.com/feed.php';

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
        return (string) (self::conf()['endpoint'] ?? self::DEFAULT_ENDPOINT);
    }

    /** Feed endpoint: admin override wins over the config default. */
    public static function endpoint(): string
    {
        $override = (string) Settings::get('banner_endpoint_override', '');
        return $override !== '' ? $override : self::endpointDefault();
    }

    /**
     * Active UI language as a 2-letter code ('de', 'en', …), or '' when the app
     * exposes no locale. Drives which localized feed variant is fetched.
     */
    public static function currentLang(): string
    {
        if (!class_exists('I18n') || !method_exists('I18n', 'current')) {
            return '';
        }
        $l = substr(strtolower((string) I18n::current()), 0, 2);
        return preg_match('/^[a-z]{2}$/', $l) ? $l : '';
    }

    /**
     * Locale-scoped feed URL for a base endpoint: feed.json -> feed-<lang>.json
     * (the suffix is inserted before the .json extension; any query string is
     * preserved). Returns '' when the endpoint has no .json to localize.
     */
    public static function localeEndpoint(string $endpoint, string $lang): string
    {
        $lang = substr(strtolower($lang), 0, 2);
        if (!preg_match('/^[a-z]{2}$/', $lang)) {
            return '';
        }
        $parts = explode('?', $endpoint, 2);
        if (!preg_match('/\.json$/i', $parts[0])) {
            return '';
        }
        $path = preg_replace('/\.json$/i', '-' . $lang . '.json', $parts[0]);
        return $path . (isset($parts[1]) ? '?' . $parts[1] : '');
    }

    /**
     * Ordered feed URLs to try for a locale, most specific first. Two endpoint
     * styles are supported so the consumer stays backwards-compatible with both
     * the dynamic REST endpoint and plain static hosting:
     *
     *   feed.php   -> [ feed.php?lang=xx , feed.php ]   (REST: the server picks
     *                 the language and falls back to international itself)
     *   feed.json  -> [ feed-xx.json , feed.json ]      (static per-language
     *                 files, international base as the fallback)
     *
     * The base endpoint is always tried last, so a missing or empty locale feed
     * degrades to the international one. With no active locale, only the base is
     * returned. The `?lang` query rides alongside the telemetry query that
     * fetchRemote() appends.
     *
     * @return list<string>
     */
    public static function feedCandidates(string $base, string $lang): array
    {
        if ($base === '') {
            return [];
        }
        $lang = substr(strtolower($lang), 0, 2);
        $out  = [];
        if (preg_match('/^[a-z]{2}$/', $lang)) {
            if (preg_match('~\.json($|\?)~i', $base)) {
                // Static hosting: feed.json -> feed-xx.json.
                $loc = self::localeEndpoint($base, $lang);
                if ($loc !== '' && $loc !== $base) {
                    $out[] = $loc;
                }
            } else {
                // REST endpoint (feed.php or any non-.json URL): ?lang=xx.
                $out[] = $base . (strpos($base, '?') === false ? '?' : '&') . 'lang=' . $lang;
            }
        }
        $out[] = $base;
        return $out;
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
        $now  = time();
        $lang = self::currentLang();
        // Cache per locale so 'de' and 'en' viewers don't overwrite each other.
        $cacheKey = self::CACHE_KEY . ($lang !== '' ? '_' . $lang : '');

        $cache = Settings::get($cacheKey, null);
        if (is_array($cache) && !empty($cache['items']) && ($cache['fetched_at'] ?? 0) + self::cacheTtl() > $now) {
            return ['source' => 'cache', 'items' => self::sanitizeItems($cache['items'])];
        }

        $base = self::endpoint();
        if ($base !== '') {
            // Try the locale feed first, then fall back to the international
            // base feed when the localization is absent or empty. feed.php
            // takes ?lang=xx; a static feed.json uses feed-xx.json files.
            $endpointMissing = false;
            foreach (self::feedCandidates($base, $lang) as $url) {
                $status = 0;
                $items = self::fetchRemote($url, $status);
                if ($items !== null && $items !== []) {
                    Settings::set($cacheKey, ['fetched_at' => $now, 'items' => $items]);
                    return ['source' => 'remote', 'items' => self::sanitizeItems($items)];
                }
                if (self::isEndpointMissing($status)) {
                    $endpointMissing = true;
                }
            }

            // The dynamic endpoint is absent on an otherwise-reachable server -
            // e.g. an older banner server that still publishes only the static
            // feed.json, not feed.php. Fall back to that JSON baseline, but
            // ONLY on a 404/410/501 (endpoint missing, host up): never on a
            // timeout or connection error, where the JSON is just as
            // unreachable and a stale cache is the better answer.
            if ($endpointMissing) {
                $jsonBase = self::jsonFallbackEndpoint($base);
                if ($jsonBase !== '' && $jsonBase !== $base) {
                    $status = 0;
                    $items = self::fetchRemote($jsonBase, $status);
                    if ($items !== null && $items !== []) {
                        Settings::set($cacheKey, ['fetched_at' => $now, 'items' => $items]);
                        return ['source' => 'remote', 'items' => self::sanitizeItems($items)];
                    }
                }
            }

            // All fetches failed: keep serving a stale per-locale cache if we have one.
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

    /** 404-style statuses: the endpoint is missing but the host answered, so a
     *  fall back from feed.php to the static feed.json makes sense. */
    private static function isEndpointMissing(int $status): bool
    {
        return in_array($status, [404, 410, 501], true);
    }

    /**
     * The static JSON baseline for a dynamic .php endpoint (feed.php ->
     * feed.json), international. Returns '' when the endpoint is not a .php URL
     * (a configured static feed.json needs no such fallback). The query string
     * is dropped - the baseline is the international feed older servers serve.
     */
    public static function jsonFallbackEndpoint(string $endpoint): string
    {
        $path = explode('?', $endpoint, 2)[0];
        if (!preg_match('/\.php$/i', $path)) {
            return '';
        }
        return preg_replace('/\.php$/i', '.json', $path);
    }

    /**
     * Fetch + decode the remote feed, attaching the anonymized usage signal.
     * Returns the raw item list, or null on any failure (caller falls back).
     * $status is set to the HTTP status code (0 when the host was unreachable),
     * so the caller can tell "endpoint missing" from "server down".
     */
    private static function fetchRemote(string $endpoint, ?int &$status = null): ?array
    {
        $status = 0;
        $url = $endpoint;
        $tele = self::telemetry();
        if ($tele !== []) {
            $url .= (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query($tele);
        }

        try {
            $body = self::httpGet($url, $status);
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

    /**
     * Minimal, short-timeout GET. Returns the body or null; sets $status to the
     * HTTP status code, or 0 when the host could not be reached at all (DNS,
     * connect, TLS or timeout) - which the caller must not treat as a 404.
     */
    private static function httpGet(string $url, ?int &$status = null): ?string
    {
        $status = 0;
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
        // Recover the status from the response headers (set even on a 4xx/5xx,
        // where $body is false). http_get_last_response_headers() is PHP 8.4+;
        // on older runtimes the status stays 0 here - the curl branch above is
        // the real path in every shipped deployment and reports it fully.
        if (function_exists('http_get_last_response_headers')) {
            $hdrs = http_get_last_response_headers();
            if (is_array($hdrs)) {
                foreach ($hdrs as $h) {
                    if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $h, $m)) {
                        $status = (int) $m[1]; // last status line wins (after redirects)
                    }
                }
            }
        }
        if ($body === false || $status >= 400) {
            return null;
        }
        return (string) $body;
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

        $href   = $str('href');
        $image  = $str('image');
        $iframe = $str('iframe');
        if ($href !== '' && !self::isAllowedHref($href)) {
            $href = '';
        }
        if ($image !== '' && !self::isAllowedImage($image)) {
            $image = '';
        }
        if ($iframe !== '' && !self::isAllowedIframe($iframe)) {
            $iframe = '';
        }

        return [
            'id'     => $str('id') !== '' ? $str('id') : substr(hash('sha256', $title . $text . $href), 0, 12),
            'title'  => $title,
            'text'   => $text,
            'cta'    => $str('cta'),
            'href'   => $href,
            'image'  => $image,
            'iframe' => $iframe,
        ];
    }

    /**
     * A banner HTML page to embed as an iframe: only https on an allowed
     * first-party host (same rule as links/images). The page is served from the
     * banner subdomain; the browser needs it in the CSP `frame-src` (see
     * Banners::frameSrc() / send_security_headers()).
     */
    public static function isAllowedIframe(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') === '') {
            return false;
        }
        return self::hostAllowed($p['host']);
    }

    /**
     * CSP source list for `frame-src`/`child-src`: every allowed first-party host
     * and its subdomains over https. Used so HTML banner iframes can load.
     */
    public static function frameSrc(): string
    {
        $out = [];
        foreach (self::allowedHosts() as $h) {
            $out[] = 'https://' . $h;
            $out[] = 'https://*.' . $h;
        }
        return implode(' ', $out);
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
        $hosts = self::conf()['allowed_hosts'] ?? ['felixschaller.com', 'timeminator.com', 'xixum.ai', 'af-ax.com', 'claude.ai'];
        $out = [];
        foreach ((array) $hosts as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '') {
                $out[] = $h;
            }
        }
        return $out ?: ['felixschaller.com', 'timeminator.com', 'xixum.ai', 'af-ax.com', 'claude.ai'];
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
