<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Felix-Feedback 2026-11-09 (Fix #144): "Hier in den Presets auch wieder
 * zweimal Presets."
 *
 * Das war systemisch - mehrere Views renderten eine zweite
 * h1.page-title zusaetzlich zu der, die Layout-Shell schon setzt.
 * Dieser Test zurrt die Regel fest: nur views/layout.php darf
 * <h1 class="page-title"> rendern. Fuer Tooltip neben dem Titel setzt
 * die View $__titleTip = 'i18n.key' per Convention (view()/layout
 * teilen sich den Scope).
 */
final class SinglePageTitleTest extends TestCase
{
    public function testOnlyLayoutRendersPageTitleH1(): void
    {
        $root = dirname(__DIR__) . '/views';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $offenders = [];
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
            $rel = substr($file->getPathname(), strlen($root) + 1);
            if ($rel === 'layout.php') { continue; }
            $content = (string) file_get_contents($file->getPathname());
            if (strpos($content, 'h1 class="page-title"') !== false) {
                $offenders[] = $rel;
            }
        }
        self::assertSame([], $offenders,
            'Nur views/layout.php darf <h1 class="page-title"> rendern - ' .
            'Views duerfen keine zweite Titel-H1 setzen (Felix-Feedback Fix #144). ' .
            'Betroffene Dateien: ' . implode(', ', $offenders));
    }

    public function testLayoutHonorsOptionalTitleTipVariable(): void
    {
        // Layout rendert tip($__titleTip) neben dem page-title, wenn die
        // View diese Variable vor dem Einbetten setzt.
        $layout = (string) file_get_contents(dirname(__DIR__) . '/views/layout.php');
        self::assertMatchesRegularExpression(
            '~!empty\(\$__titleTip\s*\?\?\s*null\)~',
            $layout,
            'Layout muss $__titleTip-Variable pruefen (Convention: View setzt sie am Anfang)'
        );
        self::assertStringContainsString('tip((string) $__titleTip)', $layout,
            'Layout muss tip($__titleTip) rendern, wenn die Convention-Variable gesetzt ist'
        );
    }

    public function testKeyViewsOptInToTitleTipConvention(): void
    {
        // Spot-Check einiger Views, die vorher einen tip()-Call in ihrer
        // eigenen H1 hatten: sie muessen jetzt $__titleTip setzen,
        // damit der Tooltip nicht ersatzlos verloren geht.
        $root = dirname(__DIR__) . '/views';
        $expect = [
            'absences/index.php'                => 'absences.help',
            'processes/index.php'               => 'processes.help',
            'structure/project_categories.php'  => 'project_cat.help',
            'structure/process_categories.php'  => 'process_cat.help',
            'structure/absence_categories.php'  => 'absence_cat.help',
        ];
        foreach ($expect as $rel => $tipKey) {
            $src = (string) file_get_contents($root . '/' . $rel);
            self::assertStringContainsString(
                "\$__titleTip = '{$tipKey}'",
                $src,
                "{$rel} muss \$__titleTip = '{$tipKey}' setzen (Tooltip gehoert an den Layout-Titel)"
            );
        }
    }
}
