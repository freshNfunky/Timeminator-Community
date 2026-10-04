<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Team-Sentiment-Tracking (#92).
 *
 * Covers the core guarantees: score clamping, UPSERT semantics (one row per
 * user per day), per-user history, team-wide aggregates and the
 * contributor-count distinction between "my notes" and "team sentiment".
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

    public function testRecordInsertsThenUpdatesSameDay(): void
    {
        $uid = TestSupport::seedUser('alice');
        $day = '2026-10-04';

        Sentiment::record($uid, $day, 2, 'rough morning');
        $row = DB::one('SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?', [$uid, $day]);
        self::assertSame(2, (int) $row['score']);
        self::assertSame('rough morning', $row['note']);

        // Second write same day: UPSERT, not duplicate.
        Sentiment::record($uid, $day, 4, 'turned around');
        self::assertSame(1, (int) DB::scalar('SELECT COUNT(*) FROM sentiment_entries'));

        $row = DB::one('SELECT * FROM sentiment_entries WHERE user_id = ? AND as_of = ?', [$uid, $day]);
        self::assertSame(4, (int) $row['score']);
        self::assertSame('turned around', $row['note']);
        self::assertNotNull($row['updated_at']);
    }

    public function testHistoryReturnsOnlyThatUsersEntriesWithinWindow(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $today = new DateTimeImmutable('today');

        Sentiment::record($alice, $today->format('Y-m-d'), 4, null);
        Sentiment::record($alice, $today->modify('-3 day')->format('Y-m-d'), 3, null);
        Sentiment::record($alice, $today->modify('-120 day')->format('Y-m-d'), 1, 'ancient');
        Sentiment::record($bob,   $today->format('Y-m-d'), 2, 'bob entry');

        $hist = Sentiment::historyForUser($alice, 60);
        self::assertCount(2, $hist, 'ancient entry outside 60-day window must be excluded');
        $scores = array_column($hist, 'score');
        self::assertSame([3, 4], array_map('intval', $scores), 'history is ASC by day');
    }

    public function testTeamSummaryCountsContributorsNotJustResponses(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $today = new DateTimeImmutable('today');

        Sentiment::record($alice, $today->format('Y-m-d'), 4, null);
        Sentiment::record($alice, $today->modify('-1 day')->format('Y-m-d'), 5, null);
        Sentiment::record($bob,   $today->format('Y-m-d'), 2, null);

        $sum = Sentiment::teamSummary(30);
        self::assertSame(3, $sum['responses']);
        self::assertSame(2, $sum['contributors']);
        self::assertNotNull($sum['avg']);
        self::assertEqualsWithDelta(3.666, $sum['avg'], 0.01);
    }

    public function testTeamDailyGroupsByDate(): void
    {
        $alice = TestSupport::seedUser('alice');
        $bob   = TestSupport::seedUser('bob');
        $day   = (new DateTimeImmutable('today'))->format('Y-m-d');

        Sentiment::record($alice, $day, 4, null);
        Sentiment::record($bob,   $day, 2, null);

        $daily = Sentiment::teamDaily(60);
        self::assertCount(1, $daily);
        self::assertSame($day, $daily[0]['as_of']);
        self::assertSame(2, $daily[0]['n']);
        self::assertEqualsWithDelta(3.0, $daily[0]['avg_score'], 0.01);
    }

    public function testTeamSummaryIsEmptyWhenNoRows(): void
    {
        $sum = Sentiment::teamSummary(30);
        self::assertNull($sum['avg']);
        self::assertSame(0, $sum['responses']);
        self::assertSame(0, $sum['contributors']);
    }

    public function testEmojiAndLabelCoverEveryScore(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            self::assertNotEmpty(Sentiment::emoji($s));
            self::assertNotEmpty(Sentiment::label($s));
        }
    }
}
