<?php
/**
 * v1.0 F2 — Sammel-Fortschrittsmeldung fuer alle faelligen Projekte.
 *
 * @var list<array<string,mixed>> $due
 */
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<div class="card">
  <div class="card-head">
    <h2><?= h(t('cockpit.review_h2', 'Faellige Reviews')) ?></h2>
    <span class="muted"><?= h(t('cockpit.review_subtitle', 'Projekte ohne frischen Fortschritts-Eintrag')) ?></span>
  </div>

  <?php if (!$due): ?>
    <p class="muted"><?= h(t('cockpit.review_none', 'Keine faelligen Reviews. Guter Zustand.')) ?></p>
  <?php else: ?>
  <form method="post" action="<?= h(route('cockpit_review_batch_save')) ?>">
    <?= csrf_field() ?>
    <label><?= h(t('cockpit.review_as_of', 'Stichtag')) ?>
      <input type="date" name="as_of" value="<?= h($today) ?>" required>
    </label>
    <table class="table" style="margin-top:12px">
      <thead>
        <tr>
          <th><?= h(t('cockpit.review_include', 'Review')) ?></th>
          <th><?= h(t('cockpit.col_project', 'Projekt')) ?></th>
          <th class="r"><?= h(t('cockpit.review_last',  'Letzter Eintrag')) ?></th>
          <th class="r"><?= h(t('cockpit.review_percent', 'Fortschritt %')) ?></th>
          <th class="r"><?= h(t('cockpit.review_forecast', 'Prognose %')) ?></th>
          <th><?= h(t('cockpit.review_mood_project', 'Mood (1-5)')) ?></th>
          <th><?= h(t('cockpit.review_mood_customer', 'Kunde (1-5)')) ?></th>
          <th><?= h(t('cockpit.review_comment', 'Kommentar')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($due as $d):
        $pid = (int) $d['project_id'];
      ?>
        <tr>
          <td><input type="checkbox" name="entries[<?= $pid ?>][include]" value="1" checked></td>
          <td>
            <strong><?= h($d['project']) ?></strong>
            <?php if ($d['client']): ?><br><span class="muted"><?= h($d['client']) ?></span><?php endif; ?>
          </td>
          <td class="r">
            <?php if ($d['last_review']): ?>
              <?= h((new DateTimeImmutable($d['last_review']))->format('d.m.Y')) ?>
              <br><span class="muted"><?= (int) $d['days_since'] ?>&nbsp;<?= h(t('cockpit.days_ago', 'Tage her')) ?></span>
            <?php else: ?>
              <span class="muted"><?= h(t('cockpit.never', 'noch nie')) ?></span>
            <?php endif; ?>
          </td>
          <td class="r"><input type="number" name="entries[<?= $pid ?>][percent]"          step="1" min="0" max="100" style="width:5em" required></td>
          <td class="r"><input type="number" name="entries[<?= $pid ?>][forecast_percent]" step="1" min="0" max="100" style="width:5em"></td>
          <td>
            <select name="entries[<?= $pid ?>][project_mood]">
              <option value=""></option><?php foreach ([1,2,3,4,5] as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
            </select>
          </td>
          <td>
            <select name="entries[<?= $pid ?>][customer_mood]">
              <option value=""></option><?php foreach ([1,2,3,4,5] as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
            </select>
          </td>
          <td><input type="text" name="entries[<?= $pid ?>][comment]" style="width:100%" maxlength="500"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="form-actions" style="margin-top:12px">
      <button class="btn btn-primary" type="submit"><?= h(t('cockpit.review_save', 'Reviews speichern')) ?></button>
      <a class="btn btn-ghost" href="<?= h(route('cockpit')) ?>"><?= h(t('common.cancel', 'abbrechen')) ?></a>
    </div>
  </form>
  <?php endif; ?>
</div>
