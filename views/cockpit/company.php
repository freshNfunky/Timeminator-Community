<?php
/**
 * v1.0 F3 — Firmen-Cockpit.
 *
 * In Timeminator Pro ist das die kaufmaennische Jahressicht (Umsatz,
 * Angebote, Rechnungen, Kosten, Ergebnis). In Community sind nur die
 * zeitbasierten Teile portiert; die EUR-Tiles werden als `.pro-teaser`
 * angezeigt und verlinken auf /pro_invoices bzw. /pro_budget.
 *
 * @var int                                      $year
 * @var list<int>                                $year_options
 * @var string                                   $from
 * @var string                                   $to
 * @var string                                   $week_from
 * @var list<string>                             $week_keys
 * @var list<array{id:int,display_name:?string,username:string}> $matrix_users
 * @var array<int,array<string,int>>             $matrix_ist
 * @var array<int,array<string,int>>             $matrix_soll
 * @var list<array<string,mixed>>                $process_hours
 * @var int                                      $process_total_min
 * @var list<array<string,mixed>>                $by_category
 */
$fmtHr = fn(float $h, int $dec = 1) => number_format($h, $dec, ',', '.');
$weekMonday = static function (string $wk): string {
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $wk, $m)) { return $wk; }
    return (new DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2], 1)->format('Y-m-d');
};
$currentWeek = (new DateTimeImmutable('today'))->format('o-\WW');
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(t('cockpit.company_h2', 'Firmen-Cockpit')) ?></h2>
    <form method="get" style="margin:0">
      <input type="hidden" name="r" value="cockpit_company">
      <label><?= h(t('cockpit.company_year', 'Jahr')) ?>
        <select name="year" onchange="this.form.submit()">
          <?php foreach ($year_options as $y): ?>
            <option value="<?= (int) $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>
  <p class="muted" style="margin-bottom:0">
    <?= h(t('cockpit.company_intro', 'Diese Edition zeigt die zeitbasierten Jahreskennzahlen deiner Firma. Umsatz, Rechnungen und Firmenbilanz sind Teil von Timeminator Pro.')) ?>
  </p>
</div>

<!-- Pro-Teaser-Tiles: Umsatz-/Rechnungs-/Kosten-/Ergebnis-KPIs -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.company_kpis_h3', 'Jahreskennzahlen (kaufmaennisch)')) ?>
      <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
    </h3>
    <span class="muted"><?= h(t('cockpit.company_kpis_subtitle', 'Angebote, Rechnungen, Kosten und Ergebnis liegen in Timeminator Pro.')) ?></span>
  </div>
  <div class="row" style="gap:12px;flex-wrap:wrap;margin:0">
    <?php foreach ([
      ['co.tile_offers_won',   'Angebote gewonnen',   'pro_offers'],
      ['co.tile_invoiced',     'Rechnungsstellung',   'pro_invoices'],
      ['co.tile_paid',         'Bezahlt',             'pro_invoices'],
      ['co.tile_open',         'Offen',               'pro_invoices'],
      ['co.tile_cost_external','Kosten extern',       'pro_budget'],
      ['co.tile_cost_internal','Kosten intern',       'pro_budget'],
      ['co.tile_result',       'Ergebnis',            'pro_budget'],
    ] as [$key, $default, $target]): ?>
      <a class="pro-teaser-tile" href="<?= h(route($target)) ?>">
        <div class="tile-label"><?= h(t($key, $default)) ?></div>
        <div class="tile-value">— €</div>
        <div class="tile-cta"><?= h(t('cockpit.in_pro', 'in Pro verfuegbar &rarr;')) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Woche Ist/Soll-Matrix firmenweit (nutzt PlanRepo aus F2) -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.company_matrix_h3', 'Woche Ist / Soll — firmenweit')) ?></h3>
    <span class="muted"><?= h(t('cockpit.company_matrix_subtitle', 'Letzte 8 Wochen + 2 Wochen voraus · Soll aus Projekt-Allokationen · Ist aus Buchungen')) ?></span>
  </div>
  <?php if (!$matrix_users): ?>
    <p class="muted"><?= h(t('cockpit.company_matrix_empty', 'Keine Buchungen oder Planungen im Fenster.')) ?></p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="table">
        <thead>
          <tr>
            <th><?= h(t('cockpit.plan_col_member', 'Mitarbeiter')) ?></th>
            <?php foreach ($week_keys as $wk):
              $isCur = $wk === $currentWeek;
            ?>
              <th class="r" title="ab <?= h($weekMonday($wk)) ?>" <?= $isCur ? 'style="background:var(--panel-alt,#f0f0f0)"' : '' ?>>
                KW<?= h(substr($wk, -2)) ?>
                <br><span class="muted" style="font-weight:normal;font-size:11px"><?= h((new DateTimeImmutable($weekMonday($wk)))->format('d.m.')) ?></span>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($matrix_users as $u):
          $uid = (int) $u['id'];
          $name = $u['display_name'] ?: $u['username'];
        ?>
          <tr>
            <td><strong><?= h($name) ?></strong></td>
            <?php foreach ($week_keys as $wk):
              $istMin  = (int) ($matrix_ist[$uid][$wk]  ?? 0);
              $sollMin = (int) ($matrix_soll[$uid][$wk] ?? 0);
              $cls = '';
              if ($sollMin > 0 && $istMin > $sollMin * 1.1)  { $cls = 'cell-red';    }
              elseif ($sollMin > 0 && $istMin < $sollMin * 0.5) { $cls = 'cell-orange'; }
              elseif ($sollMin > 0) { $cls = 'cell-green'; }
            ?>
              <td class="r <?= $cls ?>" title="Ist <?= $fmtHr($istMin / 60) ?> h / Soll <?= $fmtHr($sollMin / 60) ?> h">
                <?= $istMin > 0 ? $fmtHr($istMin / 60) : '—' ?>
                <br><span class="muted" style="font-size:11px"><?= $sollMin > 0 ? $fmtHr($sollMin / 60) : '—' ?></span>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin-top:6px">
      <?= h(t('cockpit.company_matrix_legend', 'Oben: Ist · unten: Soll. Farbe: gruen = Soll gesetzt, orange = unter 50 % des Soll, rot = ueber 110 % des Soll.')) ?>
    </p>
  <?php endif; ?>
</div>

<!-- Projekte nach Projektart (Zeit-Teil, EUR bleibt Pro) -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.company_cats_h3', 'Projekte nach Projektart')) ?></h3>
    <span class="muted"><?= h(sprintf(t('cockpit.company_cats_subtitle', 'Jahr %d · Stunden & Projektanzahl · Umsatz je Projektart ist Pro'), $year)) ?></span>
  </div>
  <?php if (!$by_category): ?>
    <p class="muted"><?= h(t('cockpit.company_cats_empty', 'Keine aktiven Projekte.')) ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('cockpit.col_category', 'Kategorie')) ?></th>
          <th class="r"><?= h(t('cockpit.company_cats_count', 'Projekte')) ?></th>
          <th class="r"><?= h(t('cockpit.company_cats_hours', 'Stunden (Jahr)')) ?></th>
          <th><?= h(t('cockpit.company_cats_revenue', 'Umsatz')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_category as $cat): ?>
        <tr>
          <td><?= h((string) $cat['cat_label']) ?></td>
          <td class="r"><?= (int) $cat['project_count'] ?></td>
          <td class="r"><?= $fmtHr((float) $cat['hours']) ?></td>
          <td><span class="muted">— €</span> <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Interne Prozesse (Stunden-Zusammenfassung) -->
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.company_processes_h3', 'Interne Prozesse')) ?></h3>
    <span class="muted"><?= h(sprintf(t('cockpit.company_processes_subtitle', 'Jahr %d · Stunden je Prozess-Kategorie · gesamt %s h'), $year, $fmtHr($process_total_min / 60))) ?></span>
  </div>
  <?php if (!$process_hours): ?>
    <p class="muted"><?= h(t('cockpit.company_processes_empty', 'Keine Prozess-Kategorien aktiv.')) ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('cockpit.company_processes_cat',     'Prozess')) ?></th>
          <th class="r"><?= h(t('cockpit.company_processes_count', 'Buchungen')) ?></th>
          <th class="r"><?= h(t('cockpit.company_processes_hours', 'Stunden')) ?></th>
          <th class="r"><?= h(t('cockpit.company_processes_share', 'Anteil')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($process_hours as $p):
        $share = $process_total_min > 0 ? round(((int) $p['minutes']) / $process_total_min * 100, 1) : 0.0;
      ?>
        <tr>
          <td><?= h((string) $p['label']) ?></td>
          <td class="r"><?= (int) $p['entries'] ?></td>
          <td class="r"><?= $fmtHr((float) $p['hours']) ?></td>
          <td class="r"><?= $process_total_min > 0 ? $fmtHr($share, 1) . '%' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
