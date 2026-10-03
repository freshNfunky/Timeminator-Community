<?php
/**
 * Angebote — Pro-only teaser.
 *
 * Static mock of a quote editor + list. Nothing is persisted, nothing is
 * sent; all controls are inert.
 *
 * @var string $pro_url
 */
$__feature = 'Angebote';
$__bullets = '<li>Angebote aus Projekt-Vorlagen bauen</li>'
           . '<li>Positionen mit Stunden- und Pauschalpreisen</li>'
           . '<li>Status Entwurf, versendet, angenommen, abgelehnt</li>'
           . '<li>Direkt in eine Rechnung konvertieren</li>';
?>
<div class="pro-teaser">
  <div class="pro-teaser-mock" aria-hidden="true">
    <div class="card">
      <div class="card-head">
        <h2>Angebote</h2>
        <span class="pill pill-pro">Pro</span>
      </div>
      <div class="pro-teaser-toolbar">
        <button class="btn btn-primary" type="button" disabled>+ Neues Angebot</button>
        <button class="btn" type="button" disabled>Aus Vorlage</button>
      </div>
      <table class="table">
        <thead><tr><th>Nr.</th><th>Kunde</th><th>Titel</th><th class="r">Summe</th><th>Status</th></tr></thead>
        <tbody>
          <tr><td>A-2026-014</td><td>Beispiel GmbH</td><td class="muted">Website-Relaunch — Phase 1</td><td class="r">12.400 &euro;</td><td><span class="pill">Entwurf</span></td></tr>
          <tr><td>A-2026-013</td><td>Muster AG</td><td class="muted">Audit &amp; Beratung</td><td class="r">3.800 &euro;</td><td><span class="pill">Versendet</span></td></tr>
          <tr><td>A-2026-012</td><td>Acme Studio</td><td class="muted">Monatliches Retainer</td><td class="r">2.100 &euro;</td><td><span class="pill">Angenommen</span></td></tr>
          <tr><td>A-2026-011</td><td>Lorem KG</td><td class="muted">Prototyp Discovery</td><td class="r">6.950 &euro;</td><td><span class="pill">Abgelehnt</span></td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-head"><h2>Angebot bearbeiten</h2></div>
      <div class="pro-teaser-form">
        <label>Titel<input type="text" value="Website-Relaunch — Phase 1" disabled></label>
        <label>Kunde<input type="text" value="Beispiel GmbH" disabled></label>
        <label>Gueltig bis<input type="text" value="31.12.2026" disabled></label>
      </div>
      <table class="table">
        <thead><tr><th>Position</th><th class="r">Menge</th><th class="r">Preis</th><th class="r">Summe</th></tr></thead>
        <tbody>
          <tr><td>Konzept</td><td class="r">16 h</td><td class="r">110 &euro;</td><td class="r">1.760 &euro;</td></tr>
          <tr><td>Design</td><td class="r">40 h</td><td class="r">110 &euro;</td><td class="r">4.400 &euro;</td></tr>
          <tr><td>Umsetzung</td><td class="r">50 h</td><td class="r">95 &euro;</td><td class="r">4.750 &euro;</td></tr>
          <tr><td>Pauschale Projektleitung</td><td class="r">1</td><td class="r">1.490 &euro;</td><td class="r">1.490 &euro;</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/pro_upsell.php'; ?>
</div>
