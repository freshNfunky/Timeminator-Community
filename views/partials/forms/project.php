<?php
/** @var ?array $edit  @var array $clients  @var array $tracks  @var array $project_categories */
$project_categories = $project_categories ?? Repo::projectCategories(true);
?>
<h3 class="form-inline-title"><?= h($edit ? t('projects.edit', 'Projekt bearbeiten') : t('projects.new', 'Neues Projekt')) ?></h3>
<form method="post" action="<?= h(route('project_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <?php
    // Felix-Feedback 2026-11-09 (Fix #174): Beim Edit darf die Projektart
    // NICHT zwischen intern und nicht-intern wechselbar sein. Internes
    // Projekt hat als "Kunde" immer den Sentinel __internal__ - wuerde
    // man auf Kundenprojekt wechseln, saesse das Projekt auf diesem
    // Sentinel-Kunden. Umgekehrt: Kundenprojekt -> Intern wuerde den
    // echten Kunden "verlieren". V1.2 (Pro-Issue) wird den Wechsel mit
    // Kunde-Switch erlauben; bis dahin: sperren auf Seite der Gleichart.
    $__editCategoryRow = null;
    $__editIsInternal  = null;
    if ($edit && !empty($edit['category_id'])) {
        foreach ($project_categories as $__pc) {
            if ((int) $__pc['id'] === (int) $edit['category_id']) {
                $__editCategoryRow = $__pc;
                $__editIsInternal  = !((int) $__pc['needs_client']);
                break;
            }
        }
    }
  ?>
  <?php if ($edit && $__editIsInternal === true && $__editCategoryRow): ?>
    <?php /* Felix-Feedback 2026-11-09 (Fix #177): Interne Projekte haben
       in v1.0 keinen Wechsel - statt Combobox nur den readonly-Label
       "Internes Projekt" + hidden category_id. Keine Prosa-Hinweise. */ ?>
    <label><?= h(t('projects.category', 'Projektart')) ?><?= tip('projects.category_hint', 'Legt fest, ob ein Kunde Pflicht ist und wie er heisst (z. B. Foerdermittelgeber bei Foerderprojekten). Verwalten in Presets.') ?>
      <div class="input-readonly"><?= h($__editCategoryRow['label']) ?></div>
      <input type="hidden" name="category_id" value="<?= (int) $__editCategoryRow['id'] ?>">
    </label>
  <?php else: ?>
    <label><?= h(t('projects.category', 'Projektart')) ?><?= tip('projects.category_hint', 'Legt fest, ob ein Kunde Pflicht ist und wie er heisst (z. B. Foerdermittelgeber bei Foerderprojekten). Verwalten in Presets.') ?>
      <select name="category_id" required>
        <?php if (!$edit): ?>
          <option value=""><?= h(t('projects.pick_category', 'Projektart waehlen …')) ?></option>
        <?php endif; ?>
        <?php foreach ($project_categories as $__pc):
          // Edit-Modus + gegensaetzliche Projektart -> Option ueberspringen
          // (nicht-interne Projekte sehen also nur nicht-interne Optionen).
          if ($edit && $__editIsInternal !== null) {
              $__pcIsInternal = !((int) $__pc['needs_client']);
              if ($__pcIsInternal !== $__editIsInternal) { continue; }
          }
        ?>
          <option value="<?= (int) $__pc['id'] ?>" <?= ($edit['category_id'] ?? 0) == $__pc['id'] ? 'selected' : '' ?>
                  data-needs-client="<?= (int) $__pc['needs_client'] ?>"
                  data-client-label="<?= h($__pc['client_label'] ?: t('project_cat.client_default', 'Kunde')) ?>">
            <?= h($__pc['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <?php /* Felix-Feedback 2026-11-09 (Fix #175): Kunde-Feld folgt der
     Kaskaden-Hierarchie des Entry-Dialogs - erst Projektart waehlen,
     dann oeffnet sich das Kunde-Feld; bei Internes Projekt entfaellt
     es komplett; bei Foerderprojekt wird das Label auf "Projekttraeger"
     umgestellt. data-base-label fuer Label-Swap wie im Entry-Dialog. */ ?>
  <label class="project-client-label"
         data-base-label="<?= h(t('struct.client', 'Kunde')) ?>"
         data-lock-hint="<?= h(t('projects.client_lock_hint', 'Zuerst Projektart waehlen')) ?>"
         data-base-placeholder="<?= h(t('projects.pick_client', 'Kunde waehlen …')) ?>"
         <?= ($edit && $__editIsInternal === true) ? 'hidden' : '' ?>>
    <span class="project-client-label-text"><?= h(t('struct.client', 'Kunde')) ?></span>
    <select name="client_id" class="project-client-select">
      <option value=""><?= h(t('projects.pick_client', 'Kunde waehlen …')) ?></option>
      <?php foreach (($clients ?? []) as $__c): ?>
        <?php if (($__c['code'] ?? '') === '__internal__') continue; ?>
        <option value="<?= (int) $__c['id'] ?>" <?= ($edit['client_id'] ?? 0) == $__c['id'] ? 'selected' : '' ?>><?= h($__c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <script nonce="<?= h(csp_nonce()) ?>">
  (function () {
    // Felix-Feedback 2026-11-09 (Fix #175): Kaskaden-Verhalten fuer die
    // Projekt-Form analog Entry-Dialog. Keine flache Liste; erst
    // Projektart, dann Kunde.
    var form = document.currentScript.closest('form');
    if (!form) return;
    var catSel     = form.querySelector('select[name="category_id"]');
    var clientSel  = form.querySelector('.project-client-select');
    var clientLab  = form.querySelector('.project-client-label');
    var clientText = form.querySelector('.project-client-label-text');
    if (!catSel || !clientSel || !clientLab || !clientText) return;
    var placeholder = clientSel.querySelector('option[value=""]');
    var baseLabel   = clientLab.getAttribute('data-base-label') || clientText.textContent;
    var basePlh     = clientLab.getAttribute('data-base-placeholder') || (placeholder ? placeholder.textContent : '');
    var lockHint    = clientLab.getAttribute('data-lock-hint') || 'Zuerst Projektart waehlen';
    function apply() {
      var opt         = catSel.selectedOptions[0];
      var needsClient = opt && opt.value ? opt.getAttribute('data-needs-client') === '1' : true;
      var customLabel = opt ? (opt.getAttribute('data-client-label') || '') : '';
      // Internes Projekt: Kunde-Feld komplett ausblenden.
      clientLab.hidden    = !needsClient;
      clientSel.disabled  = !needsClient || !catSel.value;
      if (!needsClient)  { clientSel.value = ''; }
      // Label-Swap bei Foerderprojekt.
      clientText.textContent = (needsClient && customLabel) ? customLabel : baseLabel;
      // Placeholder-Swap: Lock-Hint solange keine Art gewaehlt.
      if (placeholder) {
        placeholder.textContent = catSel.value ? basePlh : lockHint;
      }
    }
    catSel.addEventListener('change', apply);
    apply();
  })();
  </script>
  <label><?= h(t('struct.name', 'Name')) ?> <input type="text" name="name" value="<?= h($edit['name'] ?? '') ?>" required></label>
  <label><?= h(t('struct.code', 'Kennung (Code)')) ?> <input type="text" name="code" value="<?= h($edit['code'] ?? '') ?>" placeholder="<?= h(t('struct.code_placeholder_short', 'automatisch, falls leer')) ?>"></label>
  <label><?= h(t('projects.track_override', 'Gleis / Gruppe (ueberschreibt Kunde)')) ?>
    <input type="text" name="track" list="tracklist-<?= $edit ? (int) $edit['id'] : 'new' ?>" value="<?= h($edit['track'] ?? '') ?>">
    <datalist id="tracklist-<?= $edit ? (int) $edit['id'] : 'new' ?>"><?php foreach (($tracks ?? []) as $__tr): ?><option value="<?= h($__tr) ?>"><?php endforeach; ?></datalist>
  </label>
  <label><?= h(t('clients.color', 'Farbe')) ?> <input type="color" name="color" value="<?= h($edit['color'] ?? '#10b981') ?>"></label>
  <label><?= h(t('planning.planned_end', 'Geplantes Projekt-Ende')) ?><?= tip('planning.planned_end_hint') ?>
    <input type="date" name="planned_end" value="<?= h((string) ($edit['planned_end'] ?? '')) ?>">
  </label>
  <label><?= h(t('planning.target_hours', 'Soll-Stunden (Gesamt)')) ?>
    <input type="text" inputmode="decimal" name="target_hours"
           value="<?= h($edit && $edit['target_hours'] !== null ? number_format((float) $edit['target_hours'], 2, '.', '') : '') ?>"
           placeholder="<?= h(t('planning.target_hours_placeholder', 'z.B. 120.00 — optional')) ?>">
    <small class="muted"><?= h(t('planning.target_hours_hint', 'Gesamtes Projekt-Soll in Stunden. Woechentlich/monatlich/pro Person: Pro.')) ?></small>
  </label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= (!$edit || $edit['active']) ? 'checked' : '' ?>> <?= h(t('struct.active')) ?></label>
  <p class="muted note-pro"><?= t('projects.budget_note_pro', 'Zeit-/Geldbudget, Planung und Fortschritts-Report sind Teil von <b>Timeminator Pro</b>.') /* contains <b>, intentional */ ?></p>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    <?php if ($edit): ?><a class="btn btn-ghost" href="<?= h(route('projects')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a><?php endif; ?>
  </div>
</form>
