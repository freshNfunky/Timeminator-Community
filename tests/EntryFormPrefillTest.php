<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Pure-logic coverage for `entry_form_prefill_from_request()` — the helper
 * the week-calendar click & drag relies on to seed start_ts / end_ts in
 * the entry form from a GET URL.
 */
final class EntryFormPrefillTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APP_ROOT . '/src/controllers/entries_ctrl.php';
        $_GET = [];
    }

    public function testReturnsNullWhenNoPrefillRequested(): void
    {
        self::assertNull(entry_form_prefill_from_request());
    }

    public function testAcceptsHtmlDatetimeLocalFormat(): void
    {
        $_GET['start_ts'] = '2026-10-04T09:00';
        $_GET['end_ts']   = '2026-10-04T10:30';
        $out = entry_form_prefill_from_request();
        self::assertSame('2026-10-04 09:00:00', $out['start_ts']);
        self::assertSame('2026-10-04 10:30:00', $out['end_ts']);
    }

    public function testAcceptsSqlDatetimeFormat(): void
    {
        $_GET['start_ts'] = '2026-10-04 09:00:00';
        $out = entry_form_prefill_from_request();
        self::assertSame('2026-10-04 09:00:00', $out['start_ts']);
        self::assertNull($out['end_ts']);
    }

    public function testDropsUnparseableValuesInsteadOfCrashing(): void
    {
        $_GET['start_ts'] = 'nonsense';
        $out = entry_form_prefill_from_request();
        self::assertNotNull($out, 'invalid values must drop silently, not kill the request');
        self::assertNull($out['start_ts']);
    }

    public function testAcceptsStartOnlyForClickWithoutDrag(): void
    {
        // Short-click (dragged < 4 px) → only start_ts is sent so the form
        // opens with the start pinned to the clicked slot and the user
        // picks the end.
        $_GET['start_ts'] = '2026-10-04T14:15';
        $out = entry_form_prefill_from_request();
        self::assertSame('2026-10-04 14:15:00', $out['start_ts']);
        self::assertNull($out['end_ts']);
    }
}
