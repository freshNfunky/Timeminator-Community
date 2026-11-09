<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (#114): "Das Feature ist jetzt so weit
 * abgenommen. Bitte mit Regressionstests soweit festzurren, sodass die
 * nicht mehr geaendert wird."
 *
 * Dieser Test friert den abgenommenen Zustand der Stimmungs-Matrix ein:
 *
 *   - genau 12 Wochenspalten (weeksBack = 12)
 *   - Corner-Zelle "Mitarbeiter"
 *   - KW-Beschriftung inkl. ISO-Wochenzahl
 *   - Belegschafts-Matrix nutzt dasselbe Partial wie die Team-Matrix
 *   - Team-Karte mit Metriken, Verlauf und Teammitgliedern steht
 *     oberhalb der Team-Matrix
 *   - Leere Zellen rendern "—"
 *
 * Wer das Default-Layout veraendert, bricht diesen Test und muss ihn
 * bewusst mitziehen.
 */
final class MoodMatrixFreezeTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testWeeksBackIsTwelveWeeks(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/sentiment_ctrl.php');
        self::assertMatchesRegularExpression(
            '~Sentiment::weeklyMoodMatrix\s*\(\s*\$\w+Ids\s*,\s*12\s*\)~',
            $ctrl,
            'Matrix laeuft ueber genau 12 Wochen - weder Team- noch Company-Scope darf das aendern'
        );
    }

    public function testMatrixPartialHasFixedMarkupShape(): void
    {
        $partial = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/_mood_matrix.php');

        // Corner-Zelle "Mitarbeiter" + Wochen-Header + Zellen-Markup.
        self::assertStringContainsString('sent-matrix-corner', $partial, 'Corner-Zelle fehlt');
        self::assertStringContainsString("t('sent.matrix_corner'", $partial, 'Corner-Label-Key fehlt');
        self::assertStringContainsString('sent-matrix-weekhead', $partial, 'Wochen-Header-Zelle fehlt');
        self::assertStringContainsString('sent-matrix-user',     $partial, 'User-Zeilenkopf fehlt');
        self::assertStringContainsString('sent-matrix-cell',     $partial, 'Datenzelle fehlt');
        self::assertStringContainsString("'KW ' . \$__d->format('W')", $partial,
            'Wochen-Header muss "KW " + ISO-Wochenzahl zeigen');
    }

    public function testMatrixPartialRendersDashForEmptyCells(): void
    {
        $partial = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/_mood_matrix.php');
        self::assertMatchesRegularExpression(
            '~else:\s*\?>\s*<span class="muted">—</span>~s',
            $partial,
            'Leere Mood-Zellen muessen "—" zeigen (muted em-dash)'
        );
    }

    public function testBothMatricesUseTheSamePartial(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        $occurrences = substr_count($view, "require __DIR__ . '/_mood_matrix.php'");
        self::assertSame(2, $occurrences,
            'Team- UND Belegschafts-Matrix muessen dasselbe _mood_matrix.php-Partial einbinden');
    }

    public function testTeamSummaryCardSitsAboveTeamMatrixInTheView(): void
    {
        // Felix-Feedback 2026-11-09: "Ich habe hier nochmal ein Dashboard
        // oben drueber fuer Teamstimmung". Reihenfolge einfrieren: die
        // Team-Summary-Karte (sentiment-team-summary) kommt VOR dem
        // ersten Matrix-Require.
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        $summaryPos = strpos($view, 'sentiment-team-summary');
        $matrixPos  = strpos($view, "require __DIR__ . '/_mood_matrix.php'");
        self::assertNotFalse($summaryPos, 'Team-Summary-Karte fehlt');
        self::assertNotFalse($matrixPos,  'Matrix-Partial-Einbindung fehlt');
        self::assertLessThan($matrixPos, $summaryPos,
            'Team-Summary-Karte muss VOR der Team-Matrix stehen (Felix: "Dashboard oben drueber")');
    }

    public function testTeamMembersListIsShownBeneathTheSummary(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        self::assertStringContainsString('sent-team-members', $view,
            'Teammitglieder-Liste muss unter der Team-Karte bleiben (Felix #101-Teilabnahme)');
    }

    public function testWeeklyMoodMatrixHelperReturnsTwelveColumns(): void
    {
        // End-to-end: der Helper muss 12 Keys pro User liefern,
        // Wochenanfang-konform zu CalendarLocale.
        $uid = TestSupport::seedUser('alice');
        $m = Sentiment::weeklyMoodMatrix([$uid], 12);
        self::assertCount(12, $m[$uid],
            'Der Helper muss 12 Wochen pro User liefern - Default-Konstante eingefroren');
    }

    public function testFlowSmileyRendersAlongsideMoodInCell(): void
    {
        // Felix' Abnahme zeigt in der Zelle einen Mood-Smiley + daneben
        // eine kleinere Flow-Smiley. Beide Dim-Render muessen im Partial
        // enthalten sein.
        $partial = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/_mood_matrix.php');
        self::assertStringContainsString('Sentiment::moodEmoji', $partial);
        self::assertStringContainsString('Sentiment::flowEmoji', $partial);
        self::assertStringContainsString('sent-matrix-flow', $partial,
            'Flow-Emoji in der Zelle braucht die Marker-Klasse sent-matrix-flow fuer das kleinere Styling');
    }
}
