<?php
/**
 * Angebote — Pro-only teaser.
 *
 * Static mock of a quote editor + list. Nothing is persisted, nothing is
 * sent; all controls are inert.
 *
 * @var string $pro_url
 */
$__feature = t('nav.offers');
$__bullets = '<li>' . h(t('pro.off.b1', 'Angebote aus Projekt-Vorlagen bauen')) . '</li>'
           . '<li>' . h(t('pro.off.b2', 'Positionen mit Stunden- und Pauschalpreisen')) . '</li>'
           . '<li>' . h(t('pro.off.b3', 'Status Entwurf, versendet, angenommen, abgelehnt')) . '</li>'
           . '<li>' . h(t('pro.off.b4', 'Direkt in eine Rechnung konvertieren')) . '</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2><?= h(t('nav.offers')) ?></h2>
        <span class="pill pill-pro"><?= h(t('nav.pro_pill')) ?></span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled><?= h(t('pro.off.btn_new', '+ Neues Angebot')) ?></button>
        <button class="btn" type="button" disabled><?= h(t('pro.off.btn_template', 'Aus Vorlage')) ?></button>
      </div>
      <table class="table">
        <thead><tr>
          <th><?= h(t('pro.inv.col_no', 'Nr.')) ?></th>
          <th><?= h(t('struct.client', 'Kunde')) ?></th>
          <th><?= h(t('pro.off.col_title', 'Titel')) ?></th>
          <th class="r"><?= h(t('pro.off.col_sum', 'Summe')) ?></th>
          <th><?= h(t('pro.inv.col_status', 'Status')) ?></th>
        </tr></thead>
        <tbody>
          <tr><td>A-2026-014</td><td>Beispiel GmbH</td><td class="muted">Website-Relaunch — Phase 1</td><td class="r">12.400 &euro;</td><td><span class="pill"><?= h(t('pro.inv.status_draft', 'Entwurf')) ?></span></td></tr>
          <tr><td>A-2026-013</td><td>Muster AG</td><td class="muted">Audit &amp; Beratung</td><td class="r">3.800 &euro;</td><td><span class="pill"><?= h(t('pro.inv.status_sent', 'Versendet')) ?></span></td></tr>
          <tr><td>A-2026-012</td><td>Acme Studio</td><td class="muted">Monatliches Retainer</td><td class="r">2.100 &euro;</td><td><span class="pill"><?= h(t('pro.off.status_accepted', 'Angenommen')) ?></span></td></tr>
          <tr><td>A-2026-011</td><td>Lorem KG</td><td class="muted">Prototyp Discovery</td><td class="r">6.950 &euro;</td><td><span class="pill"><?= h(t('pro.off.status_rejected', 'Abgelehnt')) ?></span></td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-head"><h2><?= h(t('pro.off.edit_h2', 'Angebot bearbeiten')) ?></h2></div>
      <div class="pro-teaser-form">
        <label><?= h(t('pro.off.col_title', 'Titel')) ?><input type="text" value="Website-Relaunch — Phase 1" disabled></label>
        <label><?= h(t('struct.client', 'Kunde')) ?><input type="text" value="Beispiel GmbH" disabled></label>
        <label><?= h(t('pro.off.valid_until', 'Gueltig bis')) ?><input type="text" value="31.12.2026" disabled></label>
      </div>
      <table class="table">
        <thead><tr>
          <th><?= h(t('pro.off.col_item', 'Position')) ?></th>
          <th class="r"><?= h(t('pro.off.col_qty', 'Menge')) ?></th>
          <th class="r"><?= h(t('pro.off.col_price', 'Preis')) ?></th>
          <th class="r"><?= h(t('pro.off.col_sum', 'Summe')) ?></th>
        </tr></thead>
        <tbody>
          <tr><td><?= h(t('pro.off.sample_concept', 'Konzept')) ?></td><td class="r">16 h</td><td class="r">110 &euro;</td><td class="r">1.760 &euro;</td></tr>
          <tr><td><?= h(t('pro.off.sample_design', 'Design')) ?></td><td class="r">40 h</td><td class="r">110 &euro;</td><td class="r">4.400 &euro;</td></tr>
          <tr><td><?= h(t('pro.off.sample_implement', 'Umsetzung')) ?></td><td class="r">50 h</td><td class="r">95 &euro;</td><td class="r">4.750 &euro;</td></tr>
          <tr><td><?= h(t('pro.off.sample_pm', 'Pauschale Projektleitung')) ?></td><td class="r">1</td><td class="r">1.490 &euro;</td><td class="r">1.490 &euro;</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
