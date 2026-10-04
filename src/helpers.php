<?php
declare(strict_types=1);

/** Global app config, loaded in bootstrap. */
function cfg(?string $key = null, mixed $default = null): mixed
{
    global $APP_CONFIG;
    if ($key === null) {
        return $APP_CONFIG;
    }
    return $APP_CONFIG[$key] ?? $default;
}

/** HTML-escape. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL directory the app is served from, e.g. "/timeminator" or "". */
function base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir === '/' ? '' : $dir, '/');
}

/** Build a route URL through the front controller. */
function route(string $r = 'dashboard', array $params = []): string
{
    $url = base_path() . '/index.php?r=' . rawurlencode($r);
    if ($params) {
        $url .= '&' . http_build_query($params);
    }
    return $url;
}

/** URL to a bundled asset. */
function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function redirect_route(string $r = 'dashboard', array $params = []): never
{
    redirect(route($r, $params));
}

/**
 * Return the HTTP Referer only when it points back into this same app; else
 * $default. Used by side-effect POST handlers (dismiss / skip) so the admin
 * lands back on the page they were on, without letting an attacker POST from
 * elsewhere and pick the redirect target.
 */
function referrer_or_default(string $default): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return $default;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $scheme = ((($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'))
        ? 'https' : 'http';
    $parts = parse_url($ref);
    if (!is_array($parts) || empty($parts['host'])) {
        return $default;
    }
    if ($host !== '' && strcasecmp((string) $parts['host'], $host) !== 0) {
        return $default;
    }
    $path = (string) ($parts['path'] ?? '');
    $base = base_path();
    if ($base !== '' && !str_starts_with($path, $base . '/')) {
        return $default;
    }
    return $scheme . '://' . $host . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function get(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function post_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int) $v;
}

/** Current local datetime string. */
function now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $ok = is_string(post('_csrf'))
        && hash_equals($_SESSION['_csrf'] ?? '', (string) post('_csrf'));
    if (!$ok) {
        http_response_code(419);
        exit('CSRF token mismatch. Go back and try again.');
    }
}

// ---------- Flash messages ----------

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

/** @return array<int,array{type:string,msg:string}> */
function flash_take(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ---------- Formatting ----------

function fmt_hours(?int $minutes): string
{
    $m = (int) $minutes;
    return number_format($m / 60, 2, '.', '') . ' h';
}

function fmt_hm(?int $minutes): string
{
    $m = max(0, (int) $minutes);
    return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
}

function fmt_dt(?string $ts): string
{
    if (!$ts) {
        return '';
    }
    try {
        return (new DateTimeImmutable($ts))->format('d.m.Y H:i');
    } catch (Throwable) {
        return $ts;
    }
}

// ---------- Views ----------

/** Render a view file inside the layout. */
function view(string $name, array $data = [], ?string $title = null): void
{
    extract($data, EXTR_SKIP);
    $__view = __DIR__ . '/../views/' . $name . '.php';
    $__title = $title ?? cfg('app_name', 'Timeminator');
    ob_start();
    require $__view;
    $__content = ob_get_clean();
    require __DIR__ . '/../views/layout.php';
}

/** Render a view without the layout (e.g. installer, login). */
function view_bare(string $name, array $data = [], ?string $title = null): void
{
    extract($data, EXTR_SKIP);
    $title = $title ?? cfg('app_name', 'Timeminator');
    require __DIR__ . '/../views/' . $name . '.php';
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** App version from the VERSION file. */
function app_version(): string
{
    $f = __DIR__ . '/../VERSION';
    return is_file($f) ? trim((string) file_get_contents($f)) : '0.0.0';
}

/**
 * Client IP address, respecting a trusted-proxy allow-list from `trusted_proxies`
 * in config. When the request came directly, REMOTE_ADDR is used verbatim; when
 * the immediate peer is a listed trusted proxy, the last untrusted address in
 * X-Forwarded-For is returned instead. Everything else falls back to REMOTE_ADDR
 * so a header-forging client cannot spoof its IP.
 */
/**
 * Per-request CSP nonce used to authorize the single inline `<style>` block the
 * layout emits for dynamic color rules. Generated on first access and cached
 * for the request; empty string in the CLI (never rendered anyway).
 */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        try {
            $nonce = base64_encode(random_bytes(16));
        } catch (Throwable) {
            $nonce = '';
        }
    }
    return $nonce;
}

/**
 * Send response headers that harden the browser side of the app: a strict
 * Content-Security-Policy, X-Content-Type-Options, X-Frame-Options,
 * Referrer-Policy, Permissions-Policy and — under HTTPS — HSTS.
 *
 * The default policy has no `'unsafe-inline'`: views ship no inline scripts
 * and no `style=""` attributes; the layout emits one inline `<style>` block
 * (dynamic color rules) authorized by a per-request nonce. Hosters can override
 * the whole policy via `csp` in config.php, or pass an empty string to
 * suppress it.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $csp = cfg('csp', null);
    if ($csp === null) {
        $nonce = csp_nonce();
        // Allow HTML banner iframes from the first-party banner host(s). Derived
        // from banners.allowed_hosts so a custom banner subdomain works too.
        $bcfg = cfg('banners', []);
        $bhosts = is_array($bcfg) && !empty($bcfg['allowed_hosts'])
            ? (array) $bcfg['allowed_hosts']
            : ['felixschaller.com', 'xixum.ai', 'af-ax.com'];
        $frame = "'self'";
        foreach ($bhosts as $bh) {
            $bh = preg_replace('/[^a-z0-9.\-]/i', '', (string) $bh);
            if ($bh !== '') {
                $frame .= " https://$bh https://*.$bh";
            }
        }
        $csp = "default-src 'self'; "
             . "script-src 'self'; "
             . "style-src 'self' 'nonce-" . $nonce . "'; "
             . "style-src-attr 'none'; "
             . "img-src 'self' data:; "
             . "font-src 'self'; "
             . "connect-src 'self'; "
             . "frame-src " . $frame . "; "
             . "child-src " . $frame . "; "
             . "object-src 'none'; "
             . "base-uri 'self'; "
             . "frame-ancestors 'none'; "
             . "form-action 'self'";
    }
    if ($csp !== '') {
        header('Content-Security-Policy: ' . $csp);
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: interest-cohort=()');
    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

/**
 * Return the current set of client + project colors, deduplicated and
 * sanitized. Used by the layout to emit one `<style>` block that assigns
 * dynamic background colors to `.dot[data-color=...]` elements without
 * needing inline `style=""` attributes.
 *
 * Only colors matching `#RGB` or `#RRGGBB` are emitted so we can never inject
 * arbitrary CSS through this path.
 *
 * @return array<int,string>
 */
function collect_theme_colors(): array
{
    try {
        $rows = DB::all(
            "SELECT color FROM clients  WHERE color IS NOT NULL AND color <> ''
             UNION
             SELECT color FROM projects WHERE color IS NOT NULL AND color <> ''"
        );
    } catch (Throwable) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $c = trim((string) $r['color']);
        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c)) {
            $out[strtolower($c)] = true;
        }
    }
    return array_keys($out);
}

function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $trusted = (array) cfg('trusted_proxies', []);
    if (!$trusted || !in_array($remote, $trusted, true)) {
        return $remote;
    }
    $header = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($header === '') {
        return $remote;
    }
    $chain = array_map('trim', explode(',', $header));
    for ($i = count($chain) - 1; $i >= 0; $i--) {
        $addr = $chain[$i];
        if ($addr !== '' && !in_array($addr, $trusted, true)) {
            return $addr;
        }
    }
    return $remote;
}
