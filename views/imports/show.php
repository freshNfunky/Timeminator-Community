<?php
/**
 * @var array $batch
 * @var array $entries
 */
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(sprintf(t('imports.show_h2', 'Batch #%d — %s'), (int) $batch['id'], $batch['label'])) ?></h2>
    <a class="btn btn-sm btn-ghost" href="<?= h(route('imports')) ?>">← <?= h(t('imports.back_to_list', 'Zurueck zur Liste')) ?></a>
  </div>

  <dl class="kv-list" style="display:grid;grid-template-columns:max-content 1fr;gap:4px 16px;max-width:640px">
    <dt><?= h(t('imports.col_user', 'Benutzer')) ?></dt>
    <dd>
      <?php if (!empty($batch['user_username'])): ?>
        <?= h($batch['user_display_name'] ?: $batch['user_username']) ?>
      <?php else: ?>
        <span class="muted"><?= h(t('imports.user_unknown', 'unbekannt (Alt-Import vor A.2)')) ?></span>
      <?php endif; ?>
    </dd>
    <dt><?= h(t('imports.col_source', 'Quelle')) ?></dt><dd><?= h($batch['source']) ?></dd>
    <dt><?= h(t('imports.col_created', 'Angelegt')) ?></dt><dd><?= h(fmt_dt($batch['created_at'])) ?></dd>
    <dt><?= h(t('imports.col_entries', 'Buchungen')) ?></dt><dd><?= count($entries) ?></dd>
    <?php if (!empty($batch['note'])): ?>
      <dt><?= h(t('entries.note', 'Notiz')) ?></dt><dd><?= h($batch['note']) ?></dd>
    <?php endif; ?>
  </dl>

  <?php if (!$entries): ?>
    <p class="muted" style="margin-top:16px"><?= h(t('imports.show_empty', 'Dieser Batch enthaelt keine Buchungen mehr (koennten vor einem Revert geloescht worden sein).')) ?></p>
  <?php else: ?>
    <h3 style="margin-top:16px"><?= h(t('imports.show_entries_h3', 'Enthaltene Buchungen')) ?></h3>
    <table class="table">
      <thead>
        <tr>
          <th><?= h(t('entries.start', 'Start')) ?></th>
          <th><?= h(t('entries.end', 'Ende')) ?></th>
          <th><?= h(t('users.col_user', 'Benutzer')) ?></th>
          <th><?= h(t('struct.client', 'Kunde')) ?></th>
          <th><?= h(t('projects.col_project', 'Projekt')) ?></th>
          <th><?= h(t('dash.col_task', 'Aufgabe')) ?></th>
          <th class="r"><?= h(t('entries.col_duration', 'Dauer')) ?></th>
          <th><?= h(t('entries.note', 'Notiz')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td class="nowrap"><?= h(fmt_dt($e['start_ts'])) ?></td>
            <td class="nowrap"><?= h($e['end_ts'] ? fmt_dt($e['end_ts']) : '—') ?></td>
            <td><?= h($e['user_username'] ?? '—') ?></td>
            <td><?= h($e['client_name'] ?? '—') ?></td>
            <td><?= h($e['project_name'] ?? '—') ?></td>
            <td><?= h($e['task_name'] ?? '—') ?></td>
            <td class="r nowrap"><?= h(fmt_hm((int) ($e['duration_min'] ?? 0))) ?></td>
            <td class="muted"><?= h($e['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="form-actions" style="margin-top:16px">
    <form method="post" action="<?= h(route('imports_delete')) ?>" class="inline" data-confirm="<?= h(sprintf(t('imports.delete_confirm', 'Batch #%d mit %d Buchungen unwiderruflich loeschen?'), (int) $batch['id'], count($entries))) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $batch['id'] ?>">
      <button class="btn btn-danger" type="submit"><?= h(t('imports.revert_btn', 'Batch rueckgaengig machen (alle Buchungen loeschen)')) ?></button>
    </form>
  </div>
</div>
