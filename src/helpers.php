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
