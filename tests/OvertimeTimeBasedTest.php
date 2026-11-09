<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #171): Ueberstundenabbau laeuft
 * stundenweise gegen die Arbeitszeit - also Zeit-Feld (start/end) im
 * Grid, nicht Datum+Stunden wie Urlaub/Krankheit. Vorher musste man
 * Von-Bis-Datum + separate Stundenzahl erfassen, was semantisch
 * unlogisch war ("8 Stunden Ueberstundenabbau an einem Tag" = ein
 * Zeit-Block von start_ts bis end_ts).
 */
final class OvertimeTimeBasedTest extends TestCase
{
    private function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
    }

    private function ctrl(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/src/controllers/absences_ctrl.php');
    }

    public function testSetTypeToOvertimeShowsTimeRowNotDateRow(): void
    {
        $js = $this->js();
        // isOvertime-Flag in setType, der die Zeit-/Datum-Umschaltung steuert.
        self::assertStringContainsString('isOvertime', $js,
            'setType muss ein isOvertime-Flag kennen');
        self::assertMatchesRegularExpression(
            "~absenceAccountOf\(v\)\s*===\s*'overtime'~",
            $js,
            'isOvertime leitet sich aus absenceAccountOf(v) === overtime ab'
        );
        // timeRow sichtbar bei overtime, dateRow hidden.
        self::assertMatchesRegularExpression(
            '~timeRow\.hidden\s*=\s*\(k\s*===\s*\'absence\'\s*&&\s*!isOvertime\)~',
            $js,
            'timeRow muss bei overtime sichtbar bleiben'
        );
        self::assertMatchesRegularExpression(
            '~dateRow\.hidden\s*=\s*\(k\s*!==\s*\'absence\'\s*\|\|\s*isOvertime\)~',
            $js,
            'dateRow muss bei overtime versteckt sein'
        );
        // hoursWrap haengt nicht mehr an der overtime-Bedingung - ist generell hidden.
        self::assertStringContainsString('hoursWrap.hidden = true', $js,
            'hoursWrap darf nicht mehr via overtime-Bedingung angezeigt werden (Zeiten reichen)');
    }

    public function testSubmitSendsStartTsEndTsForOvertime(): void
    {
        $js = $this->js();
        // Overtime-Branch im submit() nutzt start_ts/end_ts.
        self::assertMatchesRegularExpression(
            '~var\s+isOvertime\s*=\s*\(absenceAccountOf\(typeSel\.value\)\s*===\s*\'overtime\'\)~',
            $js,
            'submit() muss overtime erkennen'
        );
        self::assertMatchesRegularExpression(
            '~start_ts:\s*startIn\.value,\s*end_ts:\s*endIn\.value~',
            $js,
            'Overtime-Branch muss start_ts/end_ts statt start_date/end_date senden'
        );
    }

    public function testBackendAcceptsStartTsEndTsAndDerivesDate(): void
    {
        $ctrl = $this->ctrl();
        // Controller liest start_ts/end_ts, leitet start_date + hours aus Zeit-Diff ab.
        self::assertStringContainsString("post('start_ts')", $ctrl,
            'ctrl_absence_api_save muss start_ts aus POST lesen');
        self::assertStringContainsString("post('end_ts')", $ctrl,
            'ctrl_absence_api_save muss end_ts aus POST lesen');
        self::assertMatchesRegularExpression(
            '~new\s+DateTimeImmutable\(str_replace\(\'T\',\s*\' \',\s*\$startTs\)\)~',
            $ctrl,
            'start_ts muss zu DateTimeImmutable geparst werden'
        );
        // Hours werden aus Diff berechnet, nicht aus hours-Post gelesen bei overtime.
        self::assertMatchesRegularExpression(
            '~\$diffMin\s*=\s*\(int\)\s*round\(\(\$eObj->getTimestamp\(\)\s*-\s*\$sObj->getTimestamp\(\)\)\s*/\s*60\)~',
            $ctrl,
            'Minuten-Diff zwischen end_ts und start_ts fuer hours-Ableitung'
        );
    }
}
