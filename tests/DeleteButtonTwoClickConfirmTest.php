<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #172): Loeschen-Button im Entry-Dialog
 * hatte keine Reaktion. window.confirm() wird in modernen Browsern
 * innerhalb eines <dialog showModal()>-Kontexts gelegentlich still
 * unterdrueckt (Firefox "Weitere Dialoge dieser Seite verhindern" bzw.
 * Chrome-Setting fuer wiederholte Prompts). Ergebnis: Felix klickt
 * Loeschen, sieht nichts, haelt den Button fuer kaputt.
 *
 * Fix: Zwei-Klick-Confirm direkt im Dialog.
 */
final class DeleteButtonTwoClickConfirmTest extends TestCase
{
    private function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/calendar.js');
    }

    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/assets/app.css');
    }

    public function testDoDeleteUsesTwoClickConfirmInsteadOfWindowConfirm(): void
    {
        $js = $this->js();
        // Kein window.confirm() mehr im doDelete-Flow.
        self::assertStringNotContainsString(
            'window.confirm(D.i18n.confirm_delete)',
            $js,
            'window.confirm() darf im Delete-Flow nicht mehr verwendet werden - manche Browser unterdruecken es im Modal-Kontext.'
        );
        // Zustand + Reset-Funktion existieren.
        self::assertStringContainsString('deleteConfirmArmed', $js,
            'Armed-State fuer Zwei-Klick-Confirm fehlt');
        self::assertStringContainsString('function resetDeleteConfirm', $js,
            'Reset-Funktion fuer Confirm-State fehlt');
        // Erster Klick setzt is-confirm-armed Klasse, zweiter Klick triggert den Delete.
        self::assertMatchesRegularExpression(
            "~deleteBtn\\.classList\\.add\\('is-confirm-armed'\\)~",
            $js,
            'is-confirm-armed-Klasse muss beim ersten Klick gesetzt werden'
        );
        self::assertStringContainsString('postForm(url, { id: idInput.value })', $js,
            'Zweiter Klick muss weiterhin postForm auf die Delete-URL feuern');
    }

    public function testCloseDialogResetsArmedState(): void
    {
        $js = $this->js();
        // Beim Schliessen muss der Armed-State zurueckgesetzt werden, sonst
        // steht beim naechsten Oeffnen noch "Wirklich loeschen?"-Label drin.
        self::assertMatchesRegularExpression(
            '~function closeDialog\(\)\s*\{[^}]*resetDeleteConfirm\(\)~s',
            $js,
            'closeDialog muss resetDeleteConfirm aufrufen'
        );
    }

    public function testCssDefinesArmedState(): void
    {
        $css = $this->css();
        self::assertMatchesRegularExpression(
            '~\.btn-danger\.is-confirm-armed\s*\{~',
            $css,
            'CSS-Regel fuer .btn-danger.is-confirm-armed fehlt'
        );
    }
}
