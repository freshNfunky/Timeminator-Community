<?php
/**
 * Presets-Hub (v1.0 F6 + F5). Übersicht aller Katalog-/Vorlagen-Sektionen,
 * Community-Scope: Projektarten + Prozessarten + Abwesenheitsarten.
 * Vier Sektionen (Billing, Textvorlagen, Senioritaet, Arbeitstage) sind
 * Pro-only Teaser.
 *
 * @var array  $project_categories
 * @var array  $process_categories
 * @var array  $absence_categories
 * @var string $pro_url
 */
?>
<h1 class="page-title"><?= h(t('presets.h1', 'Presets')) ?></h1>
<p class="page-sub muted"><?= h(t('presets.subtitle', 'Katalog- und Vorlagen-Einstellungen zentral an einer Stelle. Projektarten und Prozessarten liegen vorne, weitere Vorlagen folgen in Pro.')) ?></p>

<div class="card">
  <div class="card-head">
    <h2><?= h(t('presets.project_cat_h2', 'Projektarten')) ?></h2>
    <a class="btn btn-sm" href="<?= h(route('project_categories')) ?>"><?= h(t('presets.manage', 'Verwalten')) ?></a>
  </div>
  <p class="muted"><?= h(t('presets.project_cat_hint', 'Kundenprojekt / Internes Projekt / Foerderprojekt — plus eigene Kategorien. Steuert u. a. ob ein Kunde Pflicht ist.')) ?></p>
  <?php if ($project_categories): ?>
    <ul class="preset-chip-list">
      <?php foreach ($project_categories as $__c): ?>
        <li class="preset-chip<?= $__c['active'] ? '' : ' is-inactive' ?>">
          <?= h($__c['label']) ?>
          <?php if (!$__c['needs_client']): ?><span class="muted"> · ohne Kunde</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2><?= h(t('presets.process_cat_h2', 'Prozessarten (interne Prozesse)')) ?></h2>
    <a class="btn btn-sm" href="<?= h(route('process_categories')) ?>"><?= h(t('presets.manage', 'Verwalten')) ?></a>
  </div>
  <p class="muted"><?= h(t('presets.process_cat_hint', 'Nicht-projektbezogene Taetigkeiten: Angebotserstellung, Akquise, Weiterbildung, Firmen-Events. Buchen ueber „Interne Prozesse".')) ?></p>
  <?php if ($process_categories): ?>
    <ul class="preset-chip-list">
      <?php foreach ($process_categories as $__c): ?>
        <li class="preset-chip<?= $__c['active'] ? '' : ' is-inactive' ?>"><?= h($__c['label']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2><?= h(t('presets.absence_cat_h2', 'Abwesenheitsarten')) ?></h2>
    <a class="btn btn-sm" href="<?= h(route('absence_categories')) ?>"><?= h(t('presets.manage', 'Verwalten')) ?></a>
  </div>
  <p class="muted"><?= h(t('presets.absence_cat_hint', 'Urlaub, Krankheit, Ueberstundenabbau — plus eigene Arten. Konto-Zuordnung (Urlaubs- vs. Ueberstundenkonto) nutzt F1 Mitarbeiter-Cockpit spaeter.')) ?></p>
  <?php if ($absence_categories): ?>
    <ul class="preset-chip-list">
      <?php foreach ($absence_categories as $__c): ?>
        <li class="preset-chip<?= $__c['active'] ? '' : ' is-inactive' ?>">
          <?= h($__c['label']) ?>
          <?php if (!empty($__c['account'])): ?>
            <span class="muted"> · <?= h(AbsenceRepo::accountLabel($__c['account'])) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card pro-teaser-row">
  <div class="card-head">
    <h2><?= h(t('presets.pro_h2', 'Weitere Vorlagen')) ?></h2>
    <span class="pill pill-pro"><?= h(t('nav.pro_pill', 'Pro')) ?></span>
  </div>
  <p class="muted"><?= h(t('presets.pro_intro', 'Pro ergaenzt diese Seite um weitere Vorlagen-Sektionen fuer die kaufmaennische Seite:')) ?></p>
  <ul class="preset-pro-list">
    <li><strong><?= h(t('presets.pro_billing', 'Abrechnungskategorien')) ?></strong> <span class="muted">— pauschale vs. aufwandsbezogene Posten, Umsatzsteuerprofile, Kunden-Overrides</span></li>
    <li><strong><?= h(t('presets.pro_templates', 'Anschreiben- und Zahlungshinweis-Vorlagen')) ?></strong> <span class="muted">— mehrsprachige Textbausteine fuer Rechnungen und Angebote</span></li>
    <li><strong><?= h(t('presets.pro_seniority', 'Senioritaetsstufen')) ?></strong> <span class="muted">— stundenbasierte Kostenprofile pro Rolle fuer die Projekt-Kostenkurve</span></li>
    <li><strong><?= h(t('presets.pro_workdays', 'Arbeitstage-Preset')) ?></strong> <span class="muted">— global vs. per-User-Arbeitstage-Muster</span></li>
  </ul>
  <a class="btn btn-primary" href="<?= h($pro_url) ?>" target="_blank" rel="noopener"><?= h(t('presets.pro_cta', 'Pro ansehen')) ?></a>
</div>
