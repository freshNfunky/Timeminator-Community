<?php
declare(strict_types=1);

/**
 * Firmen-Prozesse-Sicht (v1.0 F3, Epic #146, Issue #162).
 *
 * In Timeminator Pro ist das Firmen-Cockpit eine kaufmaennische Jahres-
 * sicht: Angebote gewonnen, Rechnungsstellung, bezahlt, offen, Kosten,
 * Ergebnis, Umsatz je Projektart. ~95% davon ist commercial — in Community
 * zeigen wir diese Tiles nur als `.pro-teaser` mit Link auf /pro_invoices
 * und /pro_budget.
 *
 * Portierbar sind zwei Blocks (reine Zeit): eine firmenweite Woche-Ist/
 * Soll-Matrix (nutzt PlanRepo aus F2) sowie eine Stunden-Zusammenfassung
 * der internen Prozesse (ProcessRepo).
 */

function ctrl_cockpit_company(): void
{
    require_perm('stats.view');

    // Jahresauswahl: aktuelles Jahr als Default; Range 2020..aktuelles Jahr.
    $thisYear  = (int) (new DateTimeImmutable('today'))->format('Y');
    $yearRaw   = (string) get('year', (string) $thisYear);
    $year      = is_numeric($yearRaw) ? (int) $yearRaw : $thisYear;
    if ($year < 2020 || $year > $thisYear + 1) { $year = $thisYear; }
    $from = $year . '-01-01';
    $to   = $year . '-12-31';

    // -- Woche Ist/Soll-Matrix: 8 Wochen rueckwaerts + 2 voraus.
    $today     = new DateTimeImmutable('today');
    $weekFrom  = $today->modify('monday this week')->modify('-7 weeks')->format('Y-m-d');
    $weekTo    = $today->modify('monday this week')->modify('+2 weeks')->format('Y-m-d');
    $weekKeys  = PlanRepo::weekKeys($weekFrom, $weekTo);
    $istPUW    = PlanRepo::istByProjectUserWeek($weekFrom, $weekTo);
    $sollPUW   = PlanRepo::sollByProjectUserWeek($weekFrom, $weekTo);

    // Flache Aggregation: firmenweit userId => weekKey => minutes (Ist/Soll).
    $istByUserWeek  = [];
    $sollByUserWeek = [];
    foreach ($istPUW as $byUser) {
        foreach ($byUser as $uid => $byWeek) {
            foreach ($byWeek as $wk => $min) {
                $istByUserWeek[(int) $uid][$wk] = ($istByUserWeek[(int) $uid][$wk] ?? 0) + (int) $min;
            }
        }
    }
    foreach ($sollPUW as $byUser) {
        foreach ($byUser as $uid => $byWeek) {
            foreach ($byWeek as $wk => $min) {
                $sollByUserWeek[(int) $uid][$wk] = ($sollByUserWeek[(int) $uid][$wk] ?? 0) + (int) $min;
            }
        }
    }

    // Nutzer, die mindestens eine Ist- oder Soll-Zelle im Fenster haben.
    $activeIds = array_values(array_unique(array_merge(array_keys($istByUserWeek), array_keys($sollByUserWeek))));
    $users = $activeIds
        ? DB::all(
            'SELECT id, display_name, username FROM users WHERE id IN (' .
            implode(',', array_fill(0, count($activeIds), '?')) .
            ') ORDER BY COALESCE(NULLIF(display_name, ""), username)',
            $activeIds
        )
        : [];

    // -- Interne Prozesse: Stundensumme pro Kategorie im gewaehlten Jahr.
    $processHours = DB::all(
        "SELECT pc.id, pc.label, pc.position,
                COALESCE(SUM(pe.duration_min), 0) AS minutes,
                COUNT(pe.id) AS entries
           FROM process_categories pc
      LEFT JOIN process_entries pe
             ON pe.category_id = pc.id
            AND substr(pe.start_ts, 1, 10) >= ?
            AND substr(pe.start_ts, 1, 10) <= ?
          WHERE pc.active = 1
          GROUP BY pc.id, pc.label, pc.position
          ORDER BY pc.position, pc.label",
        [$from, $to]
    );
    $processTotalMin = 0;
    foreach ($processHours as &$row) {
        $row['minutes'] = (int) $row['minutes'];
        $row['hours']   = round($row['minutes'] / 60, 1);
        $processTotalMin += $row['minutes'];
    }
    unset($row);

    // -- Projekt-Buchungs-Summe im Jahr, nach Projektkategorie gruppiert
    // (ohne EUR — das ist der kleine zeitbasierte Teil der sonst commercial
    // gewinn-/umsatz-fokussierten "Projekte nach Projektart"-Tabelle).
    $byCategory = DB::all(
        "SELECT COALESCE(pc.id, 0)       AS cat_id,
                COALESCE(pc.label, ?)    AS cat_label,
                COALESCE(pc.position, 9999) AS cat_pos,
                COUNT(DISTINCT p.id)     AS project_count,
                COALESCE(SUM(te.duration_min), 0) AS minutes
           FROM projects p
      LEFT JOIN project_categories pc ON pc.id = p.category_id
      LEFT JOIN time_entries te
             ON te.project_id = p.id
            AND te.duration_min IS NOT NULL
            AND substr(te.start_ts, 1, 10) >= ?
            AND substr(te.start_ts, 1, 10) <= ?
          WHERE p.active = 1
          GROUP BY COALESCE(pc.id, 0), COALESCE(pc.label, ?), COALESCE(pc.position, 9999)
          ORDER BY cat_pos, cat_label",
        [t('cockpit.project_uncategorized', 'Ohne Kategorie'), $from, $to, t('cockpit.project_uncategorized', 'Ohne Kategorie')]
    );
    foreach ($byCategory as &$cat) {
        $cat['minutes'] = (int) $cat['minutes'];
        $cat['hours']   = round($cat['minutes'] / 60, 1);
    }
    unset($cat);

    $yearOptions = [];
    for ($y = $thisYear + 1; $y >= 2020; $y--) {
        $yearOptions[] = $y;
    }

    view('cockpit/company', [
        'year'             => $year,
        'year_options'     => $yearOptions,
        'from'             => $from,
        'to'               => $to,
        'week_from'        => $weekFrom,
        'week_keys'        => $weekKeys,
        'matrix_users'     => $users,
        'matrix_ist'       => $istByUserWeek,
        'matrix_soll'      => $sollByUserWeek,
        'process_hours'    => $processHours,
        'process_total_min'=> $processTotalMin,
        'by_category'      => $byCategory,
    ], t('cockpit.company_title', 'Firmen-Cockpit'));
}
