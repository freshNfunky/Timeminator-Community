<?php
declare(strict_types=1);

/**
 * i18n infrastructure (fixes #95).
 *
 * Timeminator Community was German-only through 0.8.0 because its DACH focus
 * made that the simplest choice. Opening up to the international audience
 * means every user-facing string moves into locale files and the UI learns
 * to pick a language per request:
 *
 *   1. `?lang=en` query (one-shot override; persists into the session so a
 *      shared link lands people in the language the sender meant).
 *   2. Session preference (set via the Admin -> System toggle or #1).
 *   3. HTTP `Accept-Language` header, matched against the supported set.
 *   4. The config/default locale (`de`).
 *
 * `t('key', $default)` is intentionally terse because it ends up in nearly
 * every view line. A missing key falls back to the default locale's entry,
 * then to the explicit `$default` arg, then to the key itself (visible but
 * never fatal).
 */
final class I18n
{
    public const DEFAULT       = 'de';
    public const SUPPORTED     = ['de', 'en'];
    private const SESSION_KEY  = '_locale';
    private const QUERY_PARAM  = 'lang';

    /** @var array<string,array<string,string>> cached locale catalogs by code */
    private static array $catalog = [];
    private static ?string $current = null;

    /** Resolve + remember the request's locale. Call once at boot. */
    public static function boot(): void
    {
        $locale = self::detect();
        self::$current = $locale;
    }

    /** Current locale code (always one of SUPPORTED). */
    public static function current(): string
    {
        if (self::$current === null) {
            self::$current = self::detect();
        }
        return self::$current;
    }

    /**
     * Pick a locale for this request. `?lang=` wins (and gets stored),
     * then session, then Accept-Language, then default.
     */
    public static function detect(): string
    {
        // 1) explicit query override — the only path that writes session,
        //    so a shared `?lang=en` URL leaves the recipient in English.
        $q = isset($_GET[self::QUERY_PARAM]) ? (string) $_GET[self::QUERY_PARAM] : '';
        $q = self::sanitize($q);
        if ($q !== null) {
            self::storeInSession($q);
            return $q;
        }

        // 2) previously stored session preference (reads $_SESSION directly so
        //    tests can populate it without an actual session_start()).
        if (isset($_SESSION) && is_array($_SESSION) && isset($_SESSION[self::SESSION_KEY])) {
            $s = self::sanitize((string) $_SESSION[self::SESSION_KEY]);
            if ($s !== null) {
                return $s;
            }
        }

        // 3) Accept-Language header
        $accept = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $picked = self::fromAcceptLanguage($accept);
        if ($picked !== null) {
            return $picked;
        }

        // 4) config default (always ends up a supported code)
        $cfg = self::sanitize((string) cfg('locale_default', self::DEFAULT));
        return $cfg ?? self::DEFAULT;
    }

    /**
     * Match an Accept-Language header against SUPPORTED. Returns null when
     * nothing matches, so callers can fall through to the default.
     *
     * Supports `de`, `de-DE`, `en-US` style tags with q-values; q-order
     * decides the winner, ties broken by the order in SUPPORTED.
     */
    public static function fromAcceptLanguage(string $header): ?string
    {
        if (trim($header) === '') { return null; }
        $parts = array_map('trim', explode(',', $header));
        $ranked = [];
        foreach ($parts as $i => $part) {
            if ($part === '') { continue; }
            $q = 1.0;
            $tag = $part;
            if (str_contains($part, ';')) {
                [$tag, $rest] = array_map('trim', explode(';', $part, 2));
                if (preg_match('/q\s*=\s*([0-9.]+)/', $rest, $m)) {
                    $q = (float) $m[1];
                }
            }
            $base = strtolower(explode('-', $tag)[0]);
            if (in_array($base, self::SUPPORTED, true)) {
                $ranked[] = ['code' => $base, 'q' => $q, 'i' => $i];
            }
        }
        if (!$ranked) { return null; }
        usort($ranked, static fn($a, $b) => $b['q'] <=> $a['q'] ?: $a['i'] <=> $b['i']);
        return $ranked[0]['code'];
    }

    /** User-chosen locale saved for later requests. */
    public static function setSession(string $code): bool
    {
        $sane = self::sanitize($code);
        if ($sane === null) { return false; }
        self::storeInSession($sane);
        self::$current = $sane;
        return true;
    }

    private static function storeInSession(string $code): void
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION[self::SESSION_KEY] = $code;
        }
    }

    /** Normalize a candidate locale code against SUPPORTED, else null. */
    public static function sanitize(string $v): ?string
    {
        $v = strtolower(trim($v));
        if ($v === '') { return null; }
        // Strip region (de-DE -> de) for the SUPPORTED check.
        if (str_contains($v, '-')) { $v = explode('-', $v)[0]; }
        return in_array($v, self::SUPPORTED, true) ? $v : null;
    }

    /** @return array<int,array{code:string,label:string,native:string}> */
    public static function available(): array
    {
        return [
            ['code' => 'de', 'label' => 'German',  'native' => 'Deutsch'],
            ['code' => 'en', 'label' => 'English', 'native' => 'English'],
        ];
    }

    /**
     * Translate a key for the current (or given) locale with graceful fallback:
     * missing in `$locale` -> default locale -> explicit `$default` -> `$key`.
     */
    public static function translate(string $key, ?string $default = null, ?string $locale = null): string
    {
        $locale = $locale ?: self::current();
        $cat = self::catalog($locale);
        if (array_key_exists($key, $cat)) {
            return $cat[$key];
        }
        if ($locale !== self::DEFAULT) {
            $fallback = self::catalog(self::DEFAULT);
            if (array_key_exists($key, $fallback)) {
                return $fallback[$key];
            }
        }
        return $default ?? $key;
    }

    /** Lazy-load (and cache) a locale catalog. Missing file = empty array. */
    public static function catalog(string $code): array
    {
        if (isset(self::$catalog[$code])) {
            return self::$catalog[$code];
        }
        $file = APP_ROOT . '/lang/' . $code . '.php';
        $data = is_file($file) ? (require $file) : [];
        self::$catalog[$code] = is_array($data) ? $data : [];
        return self::$catalog[$code];
    }

    /** Test-only: forget cached state so a fresh boot can be driven. */
    public static function reset(): void
    {
        self::$catalog = [];
        self::$current = null;
    }
}

/**
 * Convenience helper. Short on purpose; it ends up in nearly every view
 * line. Keep the second-arg default pattern — it makes a new string
 * discoverable (visible immediately, then move it into lang/de.php when
 * convenient) without a hard failure.
 */
function t(string $key, ?string $default = null): string
{
    return I18n::translate($key, $default);
}
