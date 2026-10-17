<?php
/**
 * Rechnungen — Pro-only teaser.
 *
 * Left column shows a dimmed mock of what the invoice list would look
 * like, filled from the user's own entries so the client names are
 * plausible. Nothing on this page writes, exports or sends — the
 * controls are visually "there" but inert via .pro-teaser-locked.
 *
 * @var string $pro_url
 * @var array  $groups  [{client, minutes, projects: {name=>true, ...}}]
 */
$__feature = t('nav.invoices');
$__bullets = '<li>' . h(t('pro.inv.b1', 'Rechnungen aus geleisteten Zeiten erzeugen')) . '</li>'
           . '<li>' . h(t('pro.inv.b2', 'Stundensaetze pro Kunde / Projekt / Rolle')) . '</li>'
           . '<li>' . h(t('pro.inv.b3', 'PDF-Export mit eigenem Briefpapier')) . '</li>'
           . '<li>' . h(t('pro.inv.b4', 'Status: Entwurf, versendet, bezahlt')) . '</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2><?= h(t('pro.inv.open_h2', 'Offene Rechnungen')) ?></h2>
        <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled><?= h(t('pro.inv.btn_new', '+ Rechnung aus Zeiten')) ?></button>
        <button class="btn" type="button" disabled><?= h(t('pro.inv.btn_template', 'Vorlage')) ?></button>
        <button class="btn" type="button" disabled><?= h(t('pro.inv.btn_pdf', 'PDF')) ?></button>
      </div>
      <table class="table">
        <thead><tr>
          <th><?= h(t('pro.inv.col_no', 'Nr.')) ?></th>
          <th><?= h(t('struct.client', 'Kunde')) ?></th>
          <th><?= h(t('nav.projects', 'Projekte')) ?></th>
          <th class="r"><?= h(t('pro.inv.col_hours', 'Stunden')) ?></th>
          <th class="r"><?= h(t('pro.inv.col_amount', 'Betrag')) ?></th>
          <th><?= h(t('pro.inv.col_status', 'Status')) ?></th>
        </tr></thead>
        <tbody>
        <?php
          $__statuses = [
              t('pro.inv.status_draft', 'Entwurf'),
              t('pro.inv.status_sent',  'Versendet'),
              t('pro.inv.status_paid',  'Bezahlt'),
          ];
          $__i = 0; foreach ($groups as $g): $__i++;
          $__hours = $g['minutes'] / 60;
          $__rate  = 95 + (($__i * 7) % 40);
          $__sum   = (int) round($__hours * $__rate);
          $__status = $__statuses[($__i - 1) % 3];
        ?>
          <tr>
            <td>R-2026-<?= sprintf('%03d', 100 + $__i) ?></td>
            <td><?= h($g['client']) ?></td>
            <td class="muted"><?= h(implode(', ', array_slice(array_keys($g['projects']), 0, 2))) ?></td>
            <td class="r"><?= h(number_format($__hours, 1, ',', '.')) ?> h</td>
            <td class="r"><?= h(number_format($__sum, 0, ',', '.')) ?> &euro;</td>
            <td><span class="pill"><?= h($__status) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
          <tr><td colspan="6" class="muted"><?= h(t('pro.inv.empty', 'Keine Zeiteintraege — in Pro siehst du hier deine Rechnungen.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
