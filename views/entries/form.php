<?php
/** @var ?array $entry @var array $tasks */
$val = fn($ts) => $ts ? (new DateTimeImmutable($ts))->format('Y-m-d\TH:i') : '';
$selTask = $entry['task_id'] ?? 0;
?>
<div class="card narrow">
  <form method="post" action="<?= h(route('entry_save')) ?>">
    <?= csrf_field() ?>
    <?php if ($entry): ?><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><?php endif; ?>

    <label>Aufgabe
      <select name="task_id" required>
        <option value="">Aufgabe waehlen …</option>
        <?php
        $cur = null;
        foreach ($tasks as $t):
            $g = $t['client_name'] . ' / ' . $t['project_name'];
            if ($g !== $cur) { if ($cur !== null) echo '</optgroup>'; echo '<optgroup label="' . h($g) . '">'; $cur = $g; }
        ?>
          <option value="<?= (int) $t['id'] ?>" <?= $selTask == $t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
        <?php endforeach; if ($cur !== null) echo '</optgroup>'; ?>
      </select>
    </label>

    <div class="row">
      <label class="grow">Start
        <input type="datetime-local" name="start_ts" value="<?= h($val($entry['start_ts'] ?? null)) ?>" required>
      </label>
      <label class="grow">Ende
        <input type="datetime-local" name="end_ts" value="<?= h($val($entry['end_ts'] ?? null)) ?>" required>
      </label>
    </div>

    <label>Notiz
      <input type="text" name="note" value="<?= h($entry['note'] ?? '') ?>" placeholder="optional">
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Speichern</button>
      <a class="btn btn-ghost" href="<?= h(route('entries')) ?>">Abbrechen</a>
    </div>
  </form>
</div>
