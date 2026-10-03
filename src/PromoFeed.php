<?php
declare(strict_types=1);

/**
 * Promo-sidebar feed loader.
 *
 * Pulls the live banner feed from `promo_feed_url`
 * (default `https://banner.felixschaller.com/feed.json`) server-side so a
 * strict CSP (`connect-src 'self'; frame-src 'self' data:`) stays strict
 * — the browser only ever talks to this install. On any failure the
 * bundled `assets/promo-fallback.json` is served instead so the slot is
 * never empty.
 *
 * Each feed item is a slide in the promo carousel. Shape:
 *   { "href": "https://...",         // required CTA destination
 *     "html_url": "https://...",     // preferred: cross-origin iframe to the
 *                                    // promo feed host, so HTML+CSS creatives
 *                                    // live on banner.felixschaller.com
 *     "svg_url":  "https://...",     // SVG served from the feed host (<img>)
 *     "html": "<p>...</p>",          // inline fallback snippet (sandboxed iframe
 *                                    // via srcdoc)
 *     "svg":  "<svg>...</svg>",      // inline SVG source
 *     "title":"…", "body":"…",       // text fallback
 *     "cta":"Mehr" }
 *
 * Links are gated through `promo_feed_allowed_hosts` (defaults to
 * felixschaller.com + its subdomains + xixum.ai + af-ax.com) so a
 * compromised feed can never point viewers at an arbitrary site.
 */
final class PromoFeed
{
    private const CACHE_FILE = 'promo_feed_cache.json';
    private const CACHE_TTL  = 3600; // 1 hour

    /** @return array<int, array<string, string>> Sanitized item list. */
    public static function items(): array
    {
        $cached = self::readCache();
        if ($cached !== null) {
            return $cached;
        }

        $url = (string) cfg('promo_feed_url', 'https://banner.felixschaller.com/feed.json');
        $raw = $url !== '' ? self::fetch($url) : null;

        $items = null;
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
                $items = self::sanitize($data['items']);
            }
        }

        if ($items === null || $items === []) {
            $items = self::fallback();
        }

        self::writeCache($items);
        return $items;
    }

    /** Force a refresh on the next call (used by admin "clear cache" actions). */
    public static function invalidateCache(): void
    {
        $file = self::cachePath();
        if ($file !== '' && is_file($file)) {
            @unlink($file);
        }
    }

    /** @return array<int, array<string, string>>|null */
    private static function readCache(): ?array
    {
        $file = self::cachePath();
        if ($file === '' || !is_file($file)) {
            return null;
        }
        if ((time() - (int) @filemtime($file)) > self::CACHE_TTL) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** @param array<int, array<string, string>> $items */
    private static function writeCache(array $items): void
    {
        $file = self::cachePath();
        if ($file === '') {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function cachePath(): string
    {
        return __DIR__ . '/../data/' . self::CACHE_FILE;
    }

    /** HTTP GET with a short timeout; returns the body or null on any error. */
    private static function fetch(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_USERAGENT      => 'Timeminator-Community/' . app_version(),
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 200 || !is_string($body)) {
            return null;
        }
        return $body;
    }

    /** @return array<int, string> */
    private static function allowedHosts(): array
    {
        $hosts = cfg('promo_feed_allowed_hosts', ['felixschaller.com', 'xixum.ai', 'af-ax.com']);
        return is_array($hosts) ? array_values(array_filter(array_map('strval', $hosts))) : [];
    }

    private static function hostAllowed(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }
        $host = strtolower($host);
        foreach (self::allowedHosts() as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    /** Host of the configured promo feed URL — iframes/images must live there. */
    public static function feedHost(): string
    {
        return strtolower((string) parse_url(
            (string) cfg('promo_feed_url', 'https://banner.felixschaller.com/feed.json'),
            PHP_URL_HOST
        ));
    }

    /** Cross-origin iframe/img URLs must be https on the promo feed host. */
    private static function feedAssetUrlAllowed(string $url): bool
    {
        if (!preg_match('~^https://~i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && $host === self::feedHost();
    }

    /**
     * Keep only items with a safe https CTA on an allow-listed host. Drops
     * `html` / `svg` content that contains forbidden sinks so a compromised
     * feed cannot inject script into our viewers' pages.
     *
     * @param array<int, mixed> $items
     * @return array<int, array<string, string>>
     */
    private static function sanitize(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $href = isset($it['href']) ? trim((string) $it['href']) : '';
            if ($href === '' || !preg_match('~^https://~i', $href) || !self::hostAllowed($href)) {
                continue;
            }

            $slug  = isset($it['slug']) ? preg_replace('~[^A-Za-z0-9_-]~', '', (string) $it['slug']) : '';
            $clean = ['slug' => $slug ?? '', 'href' => $href];

            // Preferred: cross-origin iframe/img URL hosted on the feed server.
            $htmlUrl = isset($it['html_url']) ? trim((string) $it['html_url']) : '';
            if ($htmlUrl !== '' && self::feedAssetUrlAllowed($htmlUrl)) {
                $clean['html_url'] = $htmlUrl;
            }
            $svgUrl = isset($it['svg_url']) ? trim((string) $it['svg_url']) : '';
            if ($svgUrl !== '' && self::feedAssetUrlAllowed($svgUrl)) {
                $clean['svg_url'] = $svgUrl;
            }
            // Inline fallbacks for self-contained creatives (also sandboxed).
            $html = isset($it['html']) ? (string) $it['html'] : '';
            if ($html !== '' && !self::looksDangerous($html)) {
                $clean['html'] = $html;
            }
            $svg = isset($it['svg']) ? (string) $it['svg'] : '';
            if ($svg !== '' && !self::looksDangerous($svg)) {
                $clean['svg'] = $svg;
            }
            foreach (['title', 'body', 'cta'] as $key) {
                if (isset($it[$key]) && is_string($it[$key])) {
                    $clean[$key] = trim($it[$key]);
                }
            }
            // Need at least one renderable payload.
            if (!isset($clean['html_url']) && !isset($clean['svg_url'])
                && !isset($clean['html']) && !isset($clean['svg'])
                && !isset($clean['title']) && !isset($clean['body'])) {
                continue;
            }
            $out[] = $clean;
        }
        return $out;
    }

    /** Cheap denylist for the most hostile constructs. Belt-and-braces: the
     *  HTML path is rendered via sandboxed `srcdoc`, so even a bypass here
     *  cannot escape the iframe. */
    private static function looksDangerous(string $src): bool
    {
        return (bool) preg_match('~<script\b|on[a-z]+\s*=|javascript:|vbscript:|data:text/html~i', $src);
    }

    /**
     * Bundled defaults so the slot is never empty. Mirrors the live feed
     * shape; shipped in-repo at `assets/promo-fallback.json`.
     *
     * @return array<int, array<string, string>>
     */
    public static function fallback(): array
    {
        $file = __DIR__ . '/../assets/promo-fallback.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            return [];
        }
        return self::sanitize($data['items']);
    }
}
