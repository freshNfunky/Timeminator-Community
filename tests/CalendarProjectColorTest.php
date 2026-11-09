<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #169): im Wochen-Kalender wurden
 * Projekt-Bloecke nach KUNDEN-Farbe eingefaerbt. Alle Projekte eines
 * Kunden sahen folglich gleich aus, egal welche Farbe im Projekt
 * selbst gesetzt war (z.B. pink im Projekt -> trotzdem blau im
 * Kalender). Fix: primaer project_color, nur als Fallback client_color.
 */
final class CalendarProjectColorTest extends TestCase
{
    public function testWeekViewUsesProjectColorAsPrimary(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/entries/calendar_week.php');
        // Primaerquelle: project_color.
        self::assertStringContainsString(
            "project_color",
            $view,
            'Wochen-Kalender muss project_color als primaere Farbquelle lesen'
        );
        // Fallback auf client_color MUSS NACH project_color kommen
        // (string-reihenfolge): project_color-Zeile vor client_color-Zeile.
        $posProj = strpos($view, "project_color");
        $posCli  = strpos($view, "client_color");
        self::assertNotFalse($posProj, 'project_color fehlt');
        self::assertNotFalse($posCli,  'client_color fehlt (fuer Fallback)');
        self::assertLessThan(
            $posCli,
            $posProj,
            'project_color muss VOR client_color stehen (Projekt-Farbe hat Vorrang)'
        );
    }

    public function testRepoEntriesQueryIncludesBothColors(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__) . '/src/Repo.php');
        // Beide Spalten sind bereits in Repo::entries - Fallback-Logik
        // braucht beide verfuegbar.
        self::assertStringContainsString('c.color AS client_color', $repo,
            'Repo::entries muss client_color liefern (Fallback)');
        self::assertStringContainsString('p.color AS project_color', $repo,
            'Repo::entries muss project_color liefern (Primaerquelle)');
    }
}
