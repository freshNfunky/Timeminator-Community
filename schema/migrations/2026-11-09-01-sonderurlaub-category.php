<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (#146): "Die Wahl zwischen zwei Arten - regulaerer
 * Urlaub ueber Urlaubskonto und Sonderurlaub (z.B. Oma stirbt), der nicht
 * aufs Urlaubskonto geht."
 *
 * Default-Seed fuer die Sonderurlaub-Kategorie, damit der neue Drag-im-
 * Header-Flow sofort eine passende zweite Option neben "Urlaub" anbietet.
 * account=NULL = kein Konto-Effekt, nur statistisch.
 */

$pdo = DB::pdo();
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare('SELECT COUNT(*) FROM absence_categories WHERE ckey = ?');
$stmt->execute(['sonderurlaub']);
if ((int) $stmt->fetchColumn() === 0) {
    $ins = $pdo->prepare(
        'INSERT INTO absence_categories (ckey, label, position, active, account, created_at)
         VALUES (?, ?, ?, 1, NULL, ?)'
    );
    $ins->execute(['sonderurlaub', 'Sonderurlaub', 15, $now]);
}
