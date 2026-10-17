<?php
/**
 * Budget / Soll — Pro-only teaser.
 *
 * Pulls the user's real projects so the first column is familiar; the
 * budget/used columns are deterministic shams derived from the project
 * id. Progress bars are coloured per bucket (ok, warn, over). All
 * positioning for the bars is emitted into a single nonce-authorized
 * <style> block below so no inline style="" attributes are needed.
 *
 * @var string $pro_url
 * @var array  $rows [{client, project, budget_h, used_h, pct}]
 */
$__feature = t('pro.budget.feature', 'Budget / Soll');
$__bullets = '<li>' . h(t('pro.bud.b1', 'Soll-Stunden und -Budget pro Projekt')) . '</li>'
           . '<li>' . h(t('pro.bud.b2', 'Live-Fortschritt gegen Ist-Zeiten')) . '</li>'
           . '<li>' . h(t('pro.bud.b3', 'Warnung ab 80 %, Alarm ab 100 %')) . '</li>'
           . '<li>' . h(t('pro.bud.b4', 'Monatliche Burn-Rate pro Kunde')) . '</li>';
?>
<style nonce="<?= h(csp_nonce()) ?>">
<?php foreach ($rows as $__i => $__r): ?>
.pro-budget-bar-<?= (int) $__i ?>{width:<?= (int) $__r['pct'] ?>%}
<?php endforeach; ?>
</style>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2><?= h(t('pro.bud.projects_h2', 'Projektbudgets')) ?></h2>
        <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
      </div>
      <table class="table">
        <thead><tr>
          <th><?= h(t('pro.bud.col_client_project', 'Kunde / Projekt')) ?></th>
          <th class="r"><?= h(t('pro.bud.col_target', 'Soll')) ?></th>
          <th class="r"><?= h(t('pro.bud.col_actual', 'Ist')) ?></th>
          <th><?= h(t('pro.bud.col_progress', 'Fortschritt')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $__i => $__r):
          $__cls = $__r['pct'] >= 100 ? 'over' : ($__r['pct'] >= 80 ? 'warn' : 'ok');
        ?>
          <tr>
            <td><?= h($__r['client']) ?> <span class="muted">/ <?= h($__r['project']) ?></span></td>
            <td class="r"><?= (int) $__r['budget_h'] ?> h</td>
            <td class="r"><?= (int) $__r['used_h'] ?> h</td>
            <td>
              <div class="pro-budget-track">
                <div class="pro-budget-bar pro-budget-<?= h($__cls) ?> pro-budget-bar-<?= (int) $__i ?>"></div>
              </div>
              <span class="muted"><?= (int) $__r['pct'] ?> %</span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="muted"><?= h(t('pro.bud.empty', 'Keine Projekte — in Pro siehst du hier dein Soll / Ist.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
