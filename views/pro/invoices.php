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
$__feature = 'Rechnungen';
$__bullets = '<li>Rechnungen aus geleisteten Zeiten erzeugen</li>'
           . '<li>Stundensaetze pro Kunde / Projekt / Rolle</li>'
           . '<li>PDF-Export mit eigenem Briefpapier</li>'
           . '<li>Status: Entwurf, versendet, bezahlt</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2>Offene Rechnungen</h2>
        <span class="pill pill-pro">Pro</span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled>+ Rechnung aus Zeiten</button>
        <button class="btn" type="button" disabled>Vorlage</button>
        <button class="btn" type="button" disabled>PDF</button>
      </div>
      <table class="table">
        <thead><tr>
          <th>Nr.</th><th>Kunde</th><th>Projekte</th><th class="r">Stunden</th><th class="r">Betrag</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php $__i = 0; foreach ($groups as $g): $__i++;
          $__hours = $g['minutes'] / 60;
          $__rate  = 95 + (($__i * 7) % 40);
          $__sum   = (int) round($__hours * $__rate);
          $__status = ['Entwurf', 'Versendet', 'Bezahlt'][($__i - 1) % 3];
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
          <tr><td colspan="6" class="muted">Keine Zeiteintraege — in Pro siehst du hier deine Rechnungen.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
