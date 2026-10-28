<?php
/**
 * v1.0 F2 — Projekt-Cockpit-Uebersicht.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<int,int>            $dueIds
 */
$num = fn($n, int $dec = 1) => $n === null ? '—' : number_format((float) $n, $dec, ',', '.');
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(t('cockpit.index_h2', 'Projekt-Cockpit')) ?></h2>
    <div class="row" style="gap:8px;align-items:center;margin:0">
      <?php if ($dueIds): ?>
        <a class="btn btn-primary btn-sm" href="<?= h(route('cockpit_review')) ?>">
          <?= sprintf(h(t('cockpit.n_reviews_due', '%d Reviews faellig')), count($dueIds)) ?>
        </a>
      <?php else: ?>
        <span class="pill pill-ok"><?= h(t('cockpit.all_reviews_current', 'Alle Reviews aktuell')) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted"><?= h(t('cockpit.no_projects', 'Noch keine aktiven Projekte.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= h(t('cockpit.col_project',  'Projekt')) ?></th>
        <th><?= h(t('cockpit.col_category', 'Kategorie')) ?></th>
        <th class="r"><?= h(t('cockpit.col_ist',       'Ist')) ?></th>
        <th class="r"><?= h(t('cockpit.col_target',    'Soll')) ?></th>
        <th class="r"><?= h(t('cockpit.col_time_pct',  'Zeit-%')) ?></th>
        <th class="r"><?= h(t('cockpit.col_progress',  'Fortschritt')) ?></th>
        <th><?= h(t('cockpit.col_planned_end', 'Ende')) ?></th>
        <th><?= h(t('cockpit.col_flags',     'Status')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $f = $r['flags'];
      $isDue = in_array($r['project_id'], $dueIds, true);
    ?>
      <tr>
        <td>
          <a href="<?= h(route('cockpit_project', ['id' => $r['project_id']])) ?>"><strong><?= h($r['project']) ?></strong></a>
          <?php if ($r['client']): ?><br><span class="muted"><?= h($r['client']) ?></span><?php endif; ?>
        </td>
        <td><?= h($r['category_label'] ?? '—') ?></td>
        <td class="r"><?= $num($r['ist_hours']) ?></td>
        <td class="r"><?= $num($r['target_hours']) ?></td>
        <td class="r <?= $r['zeit_pct'] !== null && $r['zeit_pct'] > 100 ? 'cell-red' : ($r['zeit_pct'] !== null && $r['zeit_pct'] > 85 ? 'cell-orange' : '') ?>"><?= $r['zeit_pct'] === null ? '—' : $num($r['zeit_pct'], 0) . '%' ?></td>
        <td class="r">
          <?php if ($r['progress'] === null): ?>—
          <?php else: ?>
            <strong><?= $num($r['progress'], 0) ?>%</strong>
            <?php if ($r['forecast'] !== null): ?> <span class="muted">(F: <?= $num($r['forecast'], 0) ?>%)</span><?php endif; ?>
            <?php if ($r['progress_as_of']): ?><br><span class="muted"><?= h((new DateTimeImmutable($r['progress_as_of']))->format('d.m.y')) ?></span><?php endif; ?>
          <?php endif; ?>
        </td>
        <td><?= $r['planned_end'] ? h((new DateTimeImmutable($r['planned_end']))->format('d.m.Y')) : '—' ?></td>
        <td>
          <?php if ($isDue): ?><span class="pill pill-warn"><?= h(t('cockpit.flag_review_due', 'Review faellig')) ?></span><?php endif; ?>
          <?php if ($f['deadline_overdue']): ?><span class="pill pill-warn"><?= h(t('cockpit.flag_overdue', 'ueberfaellig')) ?></span><?php endif; ?>
          <?php if (!$f['kickoff']): ?><span class="pill"><?= h(t('cockpit.flag_no_kickoff', 'kein Kickoff')) ?></span><?php endif; ?>
          <?php if ($f['closeout']): ?><span class="pill pill-ok"><?= h(t('cockpit.flag_closed', 'abgeschlossen')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('cockpit.pro_card_h3', 'Rechnungsstellung und Firmenbilanz')) ?> <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span></h3>
  </div>
  <p class="muted"><?= h(t('cockpit.pro_card_text', 'Rechnungsstatus (angeboten / Entwurf / versendet / bezahlt), Budget in EUR und die Firmenbilanz liegen in Timeminator Pro.')) ?></p>
  <div class="row" style="gap:8px">
    <a class="btn btn-ghost btn-sm" href="<?= h(route('pro_invoices')) ?>"><?= h(t('nav.invoices', 'Rechnungen')) ?> &rarr;</a>
    <a class="btn btn-ghost btn-sm" href="<?= h(route('pro_budget')) ?>"><?= h(t('nav.budget', 'Budget')) ?> &rarr;</a>
  </div>
</div>
