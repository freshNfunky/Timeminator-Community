<?php
declare(strict_types=1);

/**
 * Authentication and role-based authorization.
 *
 * A user has one role; a role has many permissions. The 'admin' role always
 * passes every check, so newly added permissions never lock the admin out.
 * New roles and permissions can be created in the admin area at runtime.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function boot(): void
    {
        if (!empty($_SESSION['uid'])) {
            self::$user = DB::one(
                'SELECT u.*, r.name AS role_name, r.label AS role_label
                   FROM users u
                   LEFT JOIN roles r ON r.id = u.role_id
                  WHERE u.id = ? AND u.active = 1',
                [(int) $_SESSION['uid']]
            );
            if (self::$user === null) {
                // account gone or disabled -> drop session
                self::logout(false);
            }
        }
        self::$loaded = true;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function id(): ?int
    {
        return self::$user ? (int) self::$user['id'] : null;
    }

    public static function attempt(string $username, string $password): bool
    {
        $u = DB::one('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
        if (!$u || !password_verify($password, (string) $u['password_hash'])) {
            return false;
        }
        // Opportunistic rehash if algorithm/cost changed.
        if (password_needs_rehash((string) $u['password_hash'], PASSWORD_DEFAULT)) {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT), (int) $u['id'],
            ]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        DB::run('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), (int) $u['id']]);
        self::boot();
        return true;
    }

    public static function logout(bool $regenerate = true): void
    {
        $_SESSION = [];
        self::$user = null;
        if ($regenerate) {
            session_regenerate_id(true);
        }
    }

    /** @return array<int,string> permission codes for the current user */
    public static function permissions(): array
    {
        if (!self::$user) {
            return [];
        }
        if (!isset(self::$user['_perms'])) {
            $rows = DB::all(
                'SELECT p.code FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE rp.role_id = ?',
                [(int) self::$user['role_id']]
            );
            self::$user['_perms'] = array_column($rows, 'code');
        }
        return self::$user['_perms'];
    }

    public static function can(string $permission): bool
    {
        if (!self::$user) {
            return false;
        }
        if ((self::$user['role_name'] ?? '') === 'admin') {
            return true;
        }
        return in_array($permission, self::permissions(), true);
    }

    public static function isAdmin(): bool
    {
        return self::$user && (self::$user['role_name'] ?? '') === 'admin';
    }
}

function require_login(): void
{
    if (!Auth::check()) {
        redirect_route('login');
    }
}

function require_perm(string $permission): void
{
    require_login();
    if (!Auth::can($permission)) {
        http_response_code(403);
        view('error', [
            'code' => 403,
            'message' => 'Keine Berechtigung fuer diese Aktion (' . h($permission) . ').',
        ], 'Kein Zugriff');
        exit;
    }
}
