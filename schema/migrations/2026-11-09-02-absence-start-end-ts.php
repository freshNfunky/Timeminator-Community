<?php
declare(strict_types=1);

/**
 * Felix-Feedback 2026-11-09 (Fix #180): Ueberstundenabbau wird Zeit-basiert
 * erfasst (Issue #171) und soll im Kalender als Zeit-Block (nicht ganztaegige
 * Pille) erscheinen. Dafuer brauchen absences zwei neue nullable Spalten
 * start_ts + end_ts. Urlaub/Krankheit/Sonderurlaub bleiben weiter ueber
 * start_date/end_date; overtime-Entries bekommen zusaetzlich start_ts/end_ts.
 */

$pdo = DB::pdo();

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$columns = [];
if ($driver === 'sqlite') {
    foreach ($pdo->query('PRAGMA table_info(absences)') as $row) {
        $columns[strtolower((string) $row['name'])] = true;
    }
} else {
    foreach ($pdo->query('SHOW COLUMNS FROM absences') as $row) {
        $columns[strtolower((string) $row['Field'])] = true;
    }
}

if (!isset($columns['start_ts'])) {
    $pdo->exec('ALTER TABLE absences ADD COLUMN start_ts TEXT NULL');
}
if (!isset($columns['end_ts'])) {
    $pdo->exec('ALTER TABLE absences ADD COLUMN end_ts TEXT NULL');
}
