<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Code-Review Fix #134):
 *
 * ctrl_entry_api_save liess frueher Auth::id()===null stillschweigend
 * durchrutschen und castete es zu user_id=0. Repo::bookingConflict matcht
 * dann WHERE user_id=0, trifft niemanden, und der Overlap-Guard wird zum
 * No-Op. Fix: explizite 401, bevor irgendetwas angefasst wird.
 *
 * Reiner Source-Vertrag-Test, weil require_perm den Null-Auth-Pfad in der
 * Praxis ohnehin blockt - aber wir wollen den Edge-Case nicht wieder
 * durchfallen sehen.
 */
final class EntryApiSaveAuthTest extends TestCase
{
    private function ctrl(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/src/controllers/entries_ctrl.php');
    }

    public function testApiSaveReturns401BeforeAnyWriteIfAuthIsNull(): void
    {
        $src = $this->ctrl();
        self::assertStringContainsString(
            "\$actorUid = Auth::id();",
            $src,
            'ctrl_entry_api_save muss Auth::id() explizit in lokale Variable binden'
        );
        self::assertStringContainsString(
            "http_response_code(401)",
            $src,
            'Null-Auth-Pfad muss 401 setzen, nicht still durchreichen'
        );
        self::assertStringContainsString(
            "echo json_encode(['ok' => false, 'error' => 'auth']);",
            $src,
            'Null-Auth-Pfad muss einen JSON-Fehler liefern'
        );
    }

    public function testApiSaveNoLongerPassesCastedNullAsUserIdZero(): void
    {
        $src = $this->ctrl();
        // Das alte Muster `(int) $uidForCheck` mit nullable $uidForCheck
        // darf nicht mehr auftauchen - $uidForCheck ist jetzt immer ein
        // echter int-Actor-Uid > 0.
        self::assertStringNotContainsString(
            '$uidForCheck = $id ? null : Auth::id();',
            $src,
            'Nullable $uidForCheck-Fallback ist nicht mehr zulaessig'
        );
        self::assertStringNotContainsString(
            'Repo::bookingConflict((int) $uidForCheck',
            $src,
            'Der (int)-Cast auf einen potenziell-nullable uid darf nicht zurueckkommen'
        );
    }

    public function testApiSavePassesNewEntryWithActorUidNotCastedNull(): void
    {
        $src = $this->ctrl();
        // Positiv: neuer Entry wird mit $actorUid gespeichert, nicht mit
        // Auth::id() direkt (Auth::id() kann theoretisch null sein, auch
        // wenn require_perm die Pfade schuetzt).
        self::assertStringContainsString(
            'Repo::createEntry($actorUid',
            $src,
            'createEntry muss den validierten $actorUid verwenden'
        );
    }
}
