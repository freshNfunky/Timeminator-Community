<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * View-Vertrag fuer die Team-Stimmungs-Matrix (#101/#102/#104).
 *
 * Die eigentliche Aggregation ist in TeamSentimentTest gepinnt; hier nur
 * Markup + Permissionen + Partials.
 */
final class MoodMatrixViewTest extends TestCase
{
    public function testControllerLoadsTeamMatrixForTeamViewers(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__) . '/src/controllers/sentiment_ctrl.php');
        self::assertStringContainsString('Sentiment::teamMemberIds()', $ctrl);
        self::assertStringContainsString('Sentiment::userLabels', $ctrl);
        self::assertStringContainsString('Sentiment::weeklyMoodMatrix', $ctrl);
        self::assertStringContainsString("Auth::can('sentiment.view_team')", $ctrl);
        self::assertStringContainsString("Auth::can('sentiment.view_company')", $ctrl);
        self::assertStringContainsString("require_any_perm('sentiment.record'", $ctrl,
            'Sentiment-Index darf fuer HR/GL/Assistenz sichtbar sein, auch wenn sie sentiment.record nicht haben');
    }

    public function testViewRendersTeamMembersAndTwoMatrices(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        self::assertStringContainsString('sent-team-members', $view,
            'Teammitglieder-Liste muss gerendert werden (Felix #101)');
        self::assertStringContainsString('sent.matrix_team_title', $view,
            'Team-Matrix-Titel-Key fehlt');
        self::assertStringContainsString('sent.matrix_company_title', $view,
            'Belegschafts-Matrix-Titel-Key fehlt');
        self::assertStringContainsString("require __DIR__ . '/_mood_matrix.php'", $view,
            'Matrix-Partial muss zweimal eingebunden werden');
    }

    public function testPartialRendersSmileysPerUserAndWeek(): void
    {
        $p = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/_mood_matrix.php');
        self::assertStringContainsString('Sentiment::moodEmoji', $p);
        self::assertStringContainsString('Sentiment::flowEmoji', $p);
        self::assertStringContainsString('sent-matrix-user', $p);
        self::assertStringContainsString('sent-matrix-cell', $p);
    }

    public function testPersonalEditIsHiddenFromViewersWithoutSentimentRecord(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__) . '/views/sentiment/index.php');
        self::assertStringContainsString('if ($canRecord):', $view,
            'Personal-Edit-Karte muss hinter $canRecord gegatet sein');
    }

    public function testI18nKeysPresentInBothLanguages(): void
    {
        $de = (string) file_get_contents(dirname(__DIR__) . '/lang/de.php');
        $en = (string) file_get_contents(dirname(__DIR__) . '/lang/en.php');
        foreach ([
            'sent.team_members_h3',
            'sent.matrix_team_title',
            'sent.matrix_company_title',
            'sent.matrix_corner',
        ] as $key) {
            self::assertStringContainsString("'$key'", $de, "DE-Key $key fehlt");
            self::assertStringContainsString("'$key'", $en, "EN-Key $key fehlt");
        }
    }
}
