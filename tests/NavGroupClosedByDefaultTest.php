<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #176): Nav-Dropdown-Gruppen
 * (<details class="nav-group">) sollen nach dem Klick auf einen Eintrag
 * im Dropdown nicht geoeffnet bleiben. Vorher: wenn die aktive Seite in
 * der Gruppe lag, rendert der Server das <details>-Element mit
 * open-Attribut - das Dropdown stand nach Navigation offen.
 *
 * Fix: open-Attribut faellt weg; die Gruppe ist immer geschlossen,
 * Aktivitaet wird nur ueber .active am summary signalisiert.
 */
final class NavGroupClosedByDefaultTest extends TestCase
{
    public function testNavTemplateHasNoAutoOpenAttribute(): void
    {
        $nav = (string) file_get_contents(dirname(__DIR__) . '/views/partials/nav.php');
        self::assertStringNotContainsString(
            "? ' open' : ''",
            $nav,
            'Kein nav-group darf auto-open gerendert werden - Dropdown bleibt sonst nach Navigation offen'
        );
    }

    public function testActiveMarkerStaysOnSummary(): void
    {
        // Active-Klasse am summary bleibt als Kontext-Signal erhalten.
        $nav = (string) file_get_contents(dirname(__DIR__) . '/views/partials/nav.php');
        self::assertStringContainsString("Active ? ' active' : ''", $nav,
            'Active-Klasse am summary muss weiter gerendert werden');
    }
}
