<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (Fix #183): "Krankheit" und "Ausfall wegen
 * Krankheit" (bzw. mehrere Krankheits-Kategorien mit account='sick')
 * sind redundant - alle fuehren das gleiche Krankheitskonto. Diese
 * Migration konsolidiert alle sick-Kategorien auf EINE, haengt
 * bestehende Absences um und loescht die Duplikate.
 *
 * Idempotent: laeuft man die Migration mehrfach, bleibt am Ende genau
 * eine sick-Kategorie namens "Krankheit" uebrig.
 */

$pdo = DB::pdo();

// Alle Kategorien mit account='sick' holen, sortiert nach id (kleinste zuerst).
$rows = $pdo->query('SELECT id FROM absence_categories WHERE account = \'sick\' ORDER BY id ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) > 1) {
    $keepId = (int) $rows[0]['id'];
    $move = $pdo->prepare('UPDATE absences SET category_id = ? WHERE category_id = ?');
    $del  = $pdo->prepare('DELETE FROM absence_categories WHERE id = ?');
    for ($i = 1; $i < count($rows); $i++) {
        $dupId = (int) $rows[$i]['id'];
        $move->execute([$keepId, $dupId]);
        $del->execute([$dupId]);
    }
}

// Verbleibende sick-Kategorie auf Standard-Label "Krankheit" setzen,
// damit kein "Ausfall wegen Krankheit"-Label mehr uebrigbleibt.
$pdo->exec("UPDATE absence_categories SET label = 'Krankheit' WHERE account = 'sick'");
