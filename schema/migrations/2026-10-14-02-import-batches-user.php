<?php
declare(strict_types=1);

/**
 * Pro-Sync A.2 (#108) — attribute import batches to the user who confirmed
 * them. Pre-A.2 batches stay unattributed (user_id = NULL); from this
 * migration on, every `ctrl_imports_confirm` write records the current
 * user id.
 *
 * Portable across SQLite and MySQL. Idempotent — fresh installs whose
 * base schema already carries the column just no-op, legacy installs get
 * the column added.
 */

$pdo    = DB::pdo();
$driver = DB::driver();

$columnExists = static function (string $table, string $column) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $column) { return true; }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
};

$indexExists = static function (string $table, string $index) use ($pdo, $driver): bool {
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA index_list($table)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $index) { return true; }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
};

if (!$columnExists('import_batches', 'user_id')) {
    if ($driver === 'sqlite') {
        // SQLite can't add a FOREIGN KEY via ALTER TABLE, but a plain
        // nullable column is fine. The LEFT JOIN in the admin view
        // tolerates orphaned user_ids, so the missing FK is acceptable.
        $pdo->exec('ALTER TABLE import_batches ADD COLUMN user_id INTEGER NULL');
    } else {
        $pdo->exec(
            'ALTER TABLE import_batches
                ADD COLUMN user_id INT NULL,
                ADD CONSTRAINT fk_import_batches_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE SET NULL'
        );
    }
}

if (!$indexExists('import_batches', 'idx_import_batches_user')) {
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_import_batches_user ON import_batches (user_id)');
    } else {
        $pdo->exec('CREATE INDEX idx_import_batches_user ON import_batches (user_id)');
    }
}
