<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (Fix #183 - final): die Migration -03 lief
 * bereits in einigen Installations mit einer frueheren Logik durch und
 * wurde als applied markiert. Dadurch sind jetzt moeglicherweise ZWEI
 * absence_categories mit account='sick' uebrig (z.B. ckey=krank +
 * ckey=krankheit, beide label='Krankheit'). Diese Migration konsolidiert
 * sie endgueltig auf EINE Zeile.
 */

$pdo = DB::pdo();

$rows = $pdo->query("SELECT id FROM absence_categories WHERE account = 'sick' ORDER BY id ASC")
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

$pdo->exec("UPDATE absence_categories SET label = 'Krankheit' WHERE account = 'sick'");
