<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Smoke test for the Pro-feature teaser pages.
 *
 * The views themselves need an authenticated session + database and are
 * covered by the broader request-cycle checks; this test guards the two
 * things that are easy to break accidentally:
 *   - the controller file parses and exposes the four `ctrl_pro_*` handlers
 *   - `pro_teaser_context()` returns a `pro_url` string from cfg()
 *   - every teaser view file exists and parses (php -l)
 */
final class ProTeaserTest extends TestCase
{
    public function testControllerLoadsAndExposesHandlers(): void
    {
        require_once APP_ROOT . '/src/controllers/pro_teaser_ctrl.php';
        self::assertTrue(function_exists('ctrl_pro_invoices'));
        self::assertTrue(function_exists('ctrl_pro_offers'));
        self::assertTrue(function_exists('ctrl_pro_budget'));
        self::assertTrue(function_exists('ctrl_pro_roles'));
        self::assertTrue(function_exists('pro_teaser_context'));
    }

    public function testContextCarriesProUrlFromConfig(): void
    {
        require_once APP_ROOT . '/src/controllers/pro_teaser_ctrl.php';
        $GLOBALS['APP_CONFIG']['pro_url'] = 'https://example.test/';
        $ctx = pro_teaser_context();
        self::assertSame('https://example.test/', $ctx['pro_url']);
    }

    /**
     * Lint the view files so a stray PHP syntax error is caught in CI
     * instead of only showing up when a user clicks the nav entry.
     */
    public function testTeaserViewsParse(): void
    {
        $views = [
            APP_ROOT . '/views/pro/invoices.php',
            APP_ROOT . '/views/pro/offers.php',
            APP_ROOT . '/views/pro/budget.php',
            APP_ROOT . '/views/pro/roles.php',
            APP_ROOT . '/views/partials/pro_upsell.php',
        ];
        foreach ($views as $file) {
            self::assertFileExists($file, $file . ' missing');
            $out = [];
            $rc  = 0;
            exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
            self::assertSame(0, $rc, "php -l $file:\n" . implode("\n", $out));
        }
    }

    public function testRoutesTableWiresProHandlers(): void
    {
        // Grep the main router instead of booting it — the router calls
        // `redirect()` + `exit()` on first-run, which we don't want in a
        // unit test. A table check is enough to catch typos.
        $src = file_get_contents(APP_ROOT . '/index.php');
        self::assertIsString($src);
        foreach (['pro_invoices', 'pro_offers', 'pro_budget', 'pro_roles'] as $r) {
            self::assertStringContainsString("'$r'", $src, "route $r not wired");
        }
    }
}
