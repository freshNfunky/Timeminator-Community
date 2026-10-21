<?php
/** @var ?array $edit  @var array $clients  @var array $tracks */
?>
<h3 class="form-inline-title"><?= h($edit ? t('projects.edit', 'Projekt bearbeiten') : t('projects.new', 'Neues Projekt')) ?></h3>
<form method="post" action="<?= h(route('project_save')) ?>" class="form-inline">
  <?= csrf_field() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
  <label><?= h(t('struct.client', 'Kunde')) ?>
    <select name="client_id" required>
      <option value=""><?= h(t('projects.pick_client', 'Kunde waehlen …')) ?></option>
      <?php foreach (($clients ?? []) as $__c): ?>
        <option value="<?= (int) $__c['id'] ?>" <?= ($edit['client_id'] ?? 0) == $__c['id'] ? 'selected' : '' ?>><?= h($__c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
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
