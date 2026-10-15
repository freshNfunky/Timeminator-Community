<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Team-Sentiment-Tracking (#92) with Mood + Flow split (#122).
 *
 * Covers the per-dimension write path, the "at least one dimension" guard,
 * independent per-day team aggregates, contributor counting and label
 * resolution for both Mood and Flow.
 */
final class SentimentTest extends TestCase
{
    protected function setUp(): void
    {
        TestSupport::bootInMemoryDb();
    }

    public function testScoreNormalizationClampsOutOfRangeAndGarbage(): void
    {
        self::assertSame(1, Sentiment::normalizeScore(1));
        self::assertSame(5, Sentiment::normalizeScore(5));
        self::assertNull(Sentiment::normalizeScore(0));
        self::assertNull(Sentiment::normalizeScore(6));
        self::assertNull(Sentiment::normalizeScore(''));
        self::assertNull(Sentiment::normalizeScore(null));
        self::assertSame(3, Sentiment::normalizeScore('3'));
    }

    public function testDateNormalizationDefaultsToToday(): void
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        self::assertSame($today, Sentiment::normalizeDate(null));
        self::assertSame($today, Sentiment::normalizeDate(''));
        self::assertSame($today, Sentiment::normalizeDate('not-a-date'));
        self::assertSame('2026-10-04', Sentiment::normalizeDate('2026-10-04'));
    }

    public function testRecordWithOnlyMoodKeepsFlowNull(): void
    {
        $uid = TestSupport::seedUser('alice');
        self::assertTrue(Sentiment::record($uid, '2026-10-04', 3, null, null));
        $row = DB::one('SELECT * FROM sentiment_entries WHERE user_id = ?', [$uid]);
        self::assertSame(3, (int) $row['mood']);
        self::assertNull($row['flow']);
    }

    public function testRecordWithOnlyFlowKeepsMoodNull(): void
    {
        $uid = TestSupport::seedUser('alice');
        self::assertTrue(Sentiment::record($uid, '2026-10-04', null, 5, 'in the zone'));
        $row = DB::one('SELECT * FROM sentiment_entries WHERE user_id = ?', [$uid]);
        self::assertNull($row['mood']);
        self::assertSame(5, (int) $row['flow']);
        self::assertSame('in the zone', $row['note']);
    }

    public function testRecordWithNeitherDimensionIsRejected(): void
    {
        $uid = TestSupport::seedUser('alice');
        self::assertFalse(Sentiment::record($uid, '2026-10-04', null, null, 'ignored'));
        self::assertSame(0, (int) DB::scalar('SELECT COUNT(*) FROM sentiment_entries'));
    }

    public function testRecordUpsertsBothDimensionsOnSameDay(): void
    {
        $uid = TestSupport::seedUser('alice');
        $day = '2026-10-04';

        Sentiment::record($uid, $day, 2, 1, 'rough morning');
        Sentiment::record($uid, $day, 4, 3, 'turned around');

        self::assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM sentiment_entries'));
        $row = DB::one('SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?', [$uid, $day]);
        self::assertSame(4, (int) $row['mood']);
        self::assertSame(3, (int) $row['flow']);
        self::assertSame('turned around', $row['note']);
        self::assertNotNull($row['updated_at']);
    }

    public function testHistoryReturnsBothDimensionsPerRow(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $today = new DateTimeImmutable('today');

        Sentiment::record($alice, $today->format('Y-m-d'), 4, 3, null);
        Sentiment::record($alice, $today->modify('-3 day')->format('Y-m-d'), 3, null, null);
        Sentiment::record($alice, $today->modify('-120 day')->format('Y-m-d'), 1, 1, 'ancient');
        Sentiment::record($bob,   $today->format('Y-m-d'), 2, 5, 'bob entry');

        $hist = Sentiment::historyForUser($alice, 60);
        self::assertCount(2, $hist, 'ancient entry outside 60-day window must be excluded');
        self::assertSame(3, $hist[0]['mood']);
        self::assertNull($hist[0]['flow'], 'row without flow stays null in history');
        self::assertSame(4, $hist[1]['mood']);
        self::assertSame(3, $hist[1]['flow']);
    }

    public function testTeamSummaryCountsPerDimensionAndContributors(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $today = new DateTimeImmutable('today');

        // Alice logs mood + flow on two days.
        Sentiment::record($alice, $today->format('Y-m-d'),            4, 2, null);
        Sentiment::record($alice, $today->modify('-1 day')->format('Y-m-d'), 5, null, null);
        // Bob only logs flow today.
        Sentiment::record($bob,   $today->format('Y-m-d'), null, 4, null);

        $sum = Sentiment::teamSummary(30);
        self::assertSame(2, $sum['n_mood'], 'two mood rows (alice today + alice yesterday)');
        self::assertSame(2, $sum['n_flow'], 'two flow rows (alice today + bob today)');
        self::assertSame(2, $sum['contributors']);
        self::assertEqualsWithDelta(4.5, $sum['avg_mood'], 0.01);
        self::assertEqualsWithDelta(3.0, $sum['avg_flow'], 0.01);
    }

    public function testTeamDailyProducesIndependentPerDimensionAverages(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $day   = (new DateTimeImmutable('today'))->format('Y-m-d');

        Sentiment::record($alice, $day, 4, null, null);
        Sentiment::record($bob,   $day, null, 2, null);

        $daily = Sentiment::teamDaily(60);
        self::assertCount(1, $daily);
        self::assertSame($day, $daily[0]['as_of']);
        self::assertSame(1, $daily[0]['n_mood']);
        self::assertSame(1, $daily[0]['n_flow']);
        self::assertEqualsWithDelta(4.0, $daily[0]['avg_mood'], 0.01);
        self::assertEqualsWithDelta(2.0, $daily[0]['avg_flow'], 0.01);
    }

    public function testTeamSummaryIsEmptyWhenNoRows(): void
    {
        $sum = Sentiment::teamSummary(30);
        self::assertNull($sum['avg_mood']);
        self::assertNull($sum['avg_flow']);
        self::assertSame(0, $sum['n_mood']);
        self::assertSame(0, $sum['n_flow']);
        self::assertSame(0, $sum['contributors']);
    }

    public function testMoodAndFlowEmojisAndLabelsCoverEveryScore(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            self::assertNotEmpty(Sentiment::moodEmoji($s));
            self::assertNotEmpty(Sentiment::flowEmoji($s));
            self::assertNotEmpty(Sentiment::moodLabel($s));
            self::assertNotEmpty(Sentiment::flowLabel($s));
        }
        // Make sure the two scales are actually visually distinct.
        self::assertNotSame(Sentiment::moodEmoji(5), Sentiment::flowEmoji(5));
        self::assertNotSame(Sentiment::moodEmoji(1), Sentiment::flowEmoji(1));
    }
}
