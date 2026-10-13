<?php
/** @var ?array $entry @var array $tasks */
$val = fn($ts) => $ts ? (new DateTimeImmutable($ts))->format('Y-m-d\TH:i') : '';
$selTask = $entry['task_id'] ?? 0;
?>
<div class="card narrow">
  <form method="post" action="<?= h(route('entry_save')) ?>">
    <?= csrf_field() ?>
    <?php if ($entry): ?><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><?php endif; ?>

    <?php
    $selectedTaskId = $selTask;
    $wrapClass = 'tp-stack';
    require __DIR__ . '/../partials/task_picker.php';
    ?>

    <div class="row">
      <label class="grow"><?= h(t('entries.start', 'Start')) ?>
        <input type="datetime-local" name="start_ts" value="<?= h($val($entry['start_ts'] ?? null)) ?>" required>
      </label>
      <label class="grow"><?= h(t('entries.end', 'Ende')) ?>
        <input type="datetime-local" name="end_ts" value="<?= h($val($entry['end_ts'] ?? null)) ?>" required>
      </label>
    </div>

    <label><?= h(t('entries.note', 'Notiz')) ?>
      <input type="text" name="note" value="<?= h($entry['note'] ?? '') ?>" placeholder="<?= h(t('common.optional')) ?>">
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
      <a class="btn btn-ghost" href="<?= h(route('entries')) ?>"><?= h(t('common.cancel')) ?></a>
    </div>
  </form>
</div>
