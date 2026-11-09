<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Vergleicht feed.json gegen feed.php am konfigurierten Banner-Host und
 * liefert ein klares "pass" oder "fail" mit konkreter Diff-Begruendung.
 *
 * Felix-Feedback 2026-11-06: 'lade einfach nur feed.php statt feed.json.
 * kontrolliere dass der output via tests der selbe ist. wenn nicht ist
 * feed der schuldige.'
 *
 * Aufruf (lokal, mit erreichbarem Banner-Host):
 *     TM_FEED_BASE_URL='https://your-banner-host/path-to-feed' \
 *         vendor/bin/phpunit --filter FeedDiffTest
 *
 * Der Test haengt die Endung .json bzw. .php selbst an die Basis-URL. Ohne
 * Env-Variable wird er SKIPpt - so laeuft die CI gruen ohne Netzzugriff.
 */
final class FeedDiffTest extends TestCase
{
    private function baseUrl(): string
    {
        $url = trim((string) (getenv('TM_FEED_BASE_URL') ?: ''));
        if ($url === '') {
            self::markTestSkipped('TM_FEED_BASE_URL not set; skipping live feed comparison.');
        }
        return rtrim($url, '/');
    }

    /**
     * Laedt eine URL als JSON-Array (dekodiert). Timeout 10 s, User-Agent
     * wie der Produktions-Banners.php. Liefert null bei Transport-Fehler.
     *
     * @return array<string,mixed>|null
     */
    private function fetchJson(string $url): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "Accept: application/json\r\nUser-Agent: timeminator-community-feed-diff-test\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /** Normalize eine Items-Liste fuer den Vergleich: sort, ignore ordering. */
    private function normalize(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            // Reihenfolge der Keys irrelevant; sortieren.
            ksort($it);
            $out[] = $it;
        }
        usort($out, static function (array $a, array $b): int {
            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });
        return $out;
    }

    public function testFeedPhpOutputMatchesFeedJson(): void
    {
        $base = $this->baseUrl();
        $jsonUrl = $base . '.json';
        $phpUrl  = $base . '.php';

        $json = $this->fetchJson($jsonUrl);
        $php  = $this->fetchJson($phpUrl);

        self::assertNotNull($json, "feed.json nicht erreichbar/nicht parsbar: {$jsonUrl}");
        self::assertNotNull($php,  "feed.php nicht erreichbar/nicht parsbar: {$phpUrl}");

        // 1. Top-Level-Shape: beide muessen einen 'items'-Wrapper haben.
        self::assertArrayHasKey('items', $json, "feed.json hat keinen 'items'-Wrapper - Shape kaputt");
        self::assertArrayHasKey('items', $php,  "feed.php hat keinen 'items'-Wrapper - Shape kaputt (das ist der Feed-Server-Bug)");
        self::assertIsArray($json['items'], "feed.json: 'items' ist kein Array");
        self::assertIsArray($php['items'],  "feed.php: 'items' ist kein Array");

        // 2. Normalisierte Items vergleichen.
        $a = $this->normalize($json['items']);
        $b = $this->normalize($php['items']);

        self::assertSame(
            count($a), count($b),
            "Item-Anzahl weicht ab: feed.json=" . count($a) . ", feed.php=" . count($b) . ". FAIL - Feed-Server liefert anderen Content."
        );

        // 3. Jedes Item einzeln - konkrete Diff-Stelle melden wenn abweichend.
        foreach ($a as $i => $jsonItem) {
            $phpItem = $b[$i] ?? null;
            self::assertNotNull($phpItem, "feed.php fehlt Item mit id=" . ($jsonItem['id'] ?? '?'));
            self::assertSame(
                $jsonItem, $phpItem,
                "Item-Diff bei id=" . ($jsonItem['id'] ?? '?') . ":\n"
                . "  feed.json: " . json_encode($jsonItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
                . "  feed.php : " . json_encode($phpItem,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
                . "FAIL - Feed-Server-Bug: die PHP-Variante liefert hier ein anderes Objekt."
            );
        }

        // Alles identisch? Dann ist der Umstieg feed.json -> feed.php sauber.
        self::assertSame($a, $b, 'Verdict: feed.php ist identisch zu feed.json. Umstieg ist sauber.');
    }
}
