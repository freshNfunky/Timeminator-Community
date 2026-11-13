<?php
/**
 * v1.0 F1: Vertragsdaten (employee_terms) + Jahresuebertrag (account_carryovers)
 * fuer einen Mitarbeiter. Wird nur eingeblendet wenn ein User bearbeitet wird.
 *
 * @var array        $edit
 * @var list<array>  $terms_history
 * @var array        $carryover
 * @var int          $carryover_year
 * @var array        $holiday_catalog
 */
if (!$edit) { return; }
$current = $terms_history[0] ?? null;
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<div class="card" style="margin-top:16px">
  <div class="card-head">
    <h3><?= h(t('employee.terms_h3', 'Vertragsdaten')) ?></h3>
    <span class="muted"><?= h(t('employee.terms_subtitle', 'Beschaeftigungsart, Wochenstunden, Urlaubsanspruch - historisiert ab Datum')) ?></span>
  </div>

  <?php if ($terms_history): ?>
    <table class="table" style="margin-bottom:14px">
      <thead>
        <tr>
          <th><?= h(t('employee.col_valid_from',     'Gueltig ab')) ?></th>
          <th><?= h(t('employee.col_type',           'Beschaeftigung')) ?></th>
          <th class="r"><?= h(t('employee.col_weekly_hours',  'Wochenstunden')) ?></th>
          <th class="r"><?= h(t('employee.col_vacation_days', 'Urlaubsanspruch')) ?></th>
          <th><?= h(t('employee.col_region',         'Feiertagsregion')) ?></th>
          <th><?= h(t('employee.col_workdays',       'Werktage')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($terms_history as $__t): ?>
        <tr>
          <td><?= h($__t['valid_from']) ?></td>
          <td><?= h(EmployeeRepo::typeLabel($__t['employment_type'])) ?></td>
          <td class="r"><?= h(number_format((float) $__t['weekly_hours'], 1, ',', '.')) ?></td>
          <td class="r"><?= h(number_format((float) $__t['vacation_days'], 1, ',', '.')) ?></td>
          <td><?= h($__t['holiday_region'] ?? '-') ?></td>
          <td><?php
            $__mask = Workdays::parseMask($__t['workday_mask'] ?? null);
            if ($__mask === null) {
                echo h(t('employee.workdays_default', 'System-Default'));
            } else {
                $__short = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
                $__en    = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                $__labels = (I18n::current() === 'en') ? $__en : $__short;
                $__parts = [];
                foreach ($__mask as $__d) { $__parts[] = $__labels[$__d] ?? (string) $__d; }
                echo h(implode(', ', $__parts));
            } ?></td>
          <td class="r">
            <form method="post" action="<?= h(route('employee_terms_delete')) ?>" onsubmit="return confirm('<?= h(t('employee.confirm_delete', 'Vertragsstand entfernen?')) ?>');" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $__t['id'] ?>">
              <input type="hidden" name="user_id" value="<?= (int) $edit['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"><?= h(t('common.delete', 'loeschen')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted"><?= h(t('employee.terms_empty', 'Noch keine Vertragsdaten hinterlegt. Lege einen Stand mit Gueltig-ab-Datum an - fuer Angestellte wird daraus das Mitarbeiter-Cockpit gerechnet.')) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= h(route('employee_terms_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int) $edit['id'] ?>">
    <label><?= h(t('employee.form_valid_from', 'Gueltig ab'))
      ?> <input type="date" name="valid_from" value="<?= h($current['valid_from'] ?? $today) ?>" required></label>
    <label><?= h(t('employee.form_type', 'Beschaeftigung')) ?>
      <select name="employment_type">
        <?php foreach (array_keys(EmployeeRepo::TYPES) as $__k): ?>
          <option value="<?= h($__k) ?>" <?= ($current['employment_type'] ?? '') === $__k ? 'selected' : '' ?>><?= h(EmployeeRepo::typeLabel($__k)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= h(t('employee.form_weekly_hours', 'Wochenstunden'))
      ?> <input type="number" name="weekly_hours" min="0" max="80" step="0.5" value="<?= h((string) ($current['weekly_hours'] ?? '40')) ?>"></label>
    <label><?= h(t('employee.form_vacation_days', 'Urlaubsanspruch (Tage)'))
      ?> <input type="number" name="vacation_days" min="0" max="60" step="0.5" value="<?= h((string) ($current['vacation_days'] ?? '25')) ?>"></label>
    <?php
      // M1.1 #192: Werktage pro Mitarbeiter. Default beim NEUEN Stand = globaler Default.
      $__currentMask = Workdays::parseMask($current['workday_mask'] ?? null);
      $__defaultMask = $__currentMask ?? Workdays::days();
      $__dayLabels = [
          1 => t('workday.mon', 'Mo'), 2 => t('workday.tue', 'Di'), 3 => t('workday.wed', 'Mi'),
          4 => t('workday.thu', 'Do'), 5 => t('workday.fri', 'Fr'), 6 => t('workday.sat', 'Sa'),
          7 => t('workday.sun', 'So'),
      ];
    ?>
    <fieldset class="employee-workdays">
      <legend><?= h(t('employee.form_workdays', 'Werktage')) ?><?= tip('employee.form_workdays_tip') ?></legend>
      <?php for ($__i = 1; $__i <= 7; $__i++): ?>
        <label class="inline">
          <input type="checkbox" name="workday_mask[]" value="<?= $__i ?>" <?= in_array($__i, $__defaultMask, true) ? 'checked' : '' ?>>
          <?= h($__dayLabels[$__i]) ?>
        </label>
      <?php endfor; ?>
    </fieldset>
    <label><?= h(t('employee.form_region', 'Feiertagsregion'))
      ?>
      <select name="holiday_region">
        <option value="__none__"><?= h(t('employee.region_none', 'keine')) ?></option>
        <?php foreach ($holiday_catalog as $__group):
          $__sel = ($current['holiday_region'] ?? '');
        ?>
          <optgroup label="<?= h(t($__group['label_key'], $__group['country'])) ?>">
            <?php foreach ($__group['options'] as $__opt): ?>
              <option value="<?= h($__opt['code']) ?>" <?= $__sel === $__opt['code'] ? 'selected' : '' ?>>
                <?= h(t($__opt['label_key'], $__opt['code'])) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    </div>
  </form>
</div>

<div class="card" style="margin-top:12px">
  <div class="card-head">
    <h3><?= h(t('employee.carryover_h3', 'Jahresuebertrag')) ?> <?= (int) $carryover_year ?></h3>
    <span class="muted"><?= h(t('employee.carryover_subtitle', 'Resturlaub + Ueberstundensaldo aus dem Vorjahr - fliesst in Resturlaub und Ueberstundenkonto ein')) ?></span>
  </div>
  <form method="post" action="<?= h(route('account_carryover_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int) $edit['id'] ?>">
    <input type="hidden" name="year" value="<?= (int) $carryover_year ?>">
    <?php
    // Felix-Feedback 2026-11-07: Default beim NEUEN Carryover ist der volle
    // Jahres-Urlaubsanspruch aus den aktuellen Vertragsdaten (Jahresanfang
    // = 100 % verfuegbar). Nur wenn der Nutzer schon mal gespeichert hat,
    // zeigen wir den persistierten Wert.
    $carryVacDefault = $carryover !== null
        ? (string) ($carryover['vacation_days'] ?? 0)
        : (string) ($current['vacation_days'] ?? 0);
    $carryOtDefault = $carryover !== null
        ? (string) ($carryover['overtime_hours'] ?? 0)
        : '0';
    ?>
    <label><?= h(t('employee.carry_vacation', 'Resturlaub (Tage)'))
      ?> <input type="number" name="vacation_days" min="0" max="60" step="0.5" value="<?= h($carryVacDefault) ?>"></label>
    <label><?= h(t('employee.carry_overtime', 'Ueberstunden (Stunden)'))
      ?> <input type="number" name="overtime_hours" step="0.25" value="<?= h($carryOtDefault) ?>"></label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('common.save')) ?></button>
    </div>
  </form>
</div>
