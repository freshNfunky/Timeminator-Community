<?php
/**
 * Team-Sentiment-Tracking permissions (fixes #92).
 *
 * Portable PHP migration: syncs the new `sentiment.record` and
 * `sentiment.view_team` permissions into the catalog, then grants
 * `sentiment.record` to the default "user" role (admin bypasses can() so it
 * already has it). Idempotent — runs once per install because the Migrator
 * records this basename in `applied_migrations`.
 */

/** @var PDO $pdo (unused, DB is a static) */
require_once APP_ROOT . '/src/seed.php';

sync_permissions();

$userRoleId = (int) DB::scalar('SELECT id FROM roles WHERE name = ?', ['user']);
if ($userRoleId > 0) {
    $permId = (int) DB::scalar('SELECT id FROM permissions WHERE code = ?', ['sentiment.record']);
    if ($permId > 0) {
        $sql = 'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?) '
             . (DB::driver() === 'mysql'
                 ? 'ON DUPLICATE KEY UPDATE role_id = role_id'
                 : 'ON CONFLICT DO NOTHING');
        DB::run($sql, [$userRoleId, $permId]);
    }
}
