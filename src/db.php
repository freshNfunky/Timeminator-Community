<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper with a MySQL / SQLite driver switch.
 * Queries are written portably (no dialect-specific date functions); heavier
 * bucketing is done in PHP so the same SQL runs on both drivers.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function boot(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = $config['db_driver'] ?? 'sqlite';
        self::$driver = $driver;

        if ($driver === 'mysql') {
            $m = $config['mysql'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $m['host'],
                (int)($m['port'] ?? 3306),
                $m['database'],
                $m['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $m['username'], $m['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } else {
            $path = $config['sqlite']['path'];
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::$pdo = $pdo;
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('DB not booted');
        }
        return self::$pdo;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Apply a whole .sql file. */
    public static function applySqlFile(string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read schema file: $file");
        }
        $pdo = self::pdo();
        foreach (self::splitSqlStatements($sql) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            // Skip MySQL-only session pragmas on SQLite and vice versa.
            if (self::$driver === 'sqlite' && preg_match('/^(SET |ENGINE)/i', $stmt)) {
                continue;
            }
            $pdo->exec($stmt);
        }
    }

    /**
     * Split a SQL string into statements. Respects single-, double- and
     * backtick-quoted strings (with backslash escapes), MySQL `#` and SQL
     * `--` line comments and `/* ... *\/` block comments, so a semicolon
     * inside a literal or a comment is not mistaken for a terminator.
     */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            if (($inSingle || $inDouble) && $ch === '\\' && $next !== '') {
                $current .= $ch . $next;
                $i++;
                continue;
            }
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if (($ch === '-' && $next === '-') || $ch === '#') {
                    $eol = strpos($sql, "\n", $i);
                    if ($eol === false) {
                        $i = $len;
                    } else {
                        $i = $eol;
                        $current .= "\n";
                    }
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $end = strpos($sql, '*/', $i + 2);
                    if ($end === false) {
                        $i = $len;
                    } else {
                        $i = $end + 1;
                    }
                    continue;
                }
                if ($ch === ';') {
                    $trimmed = trim($current);
                    if ($trimmed !== '') {
                        $statements[] = $trimmed;
                    }
                    $current = '';
                    continue;
                }
            }
            if ($ch === "'" && !$inDouble && !$inBacktick) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
            } elseif ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }
            $current .= $ch;
        }
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }
}
