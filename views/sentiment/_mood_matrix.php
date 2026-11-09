<?php
/**
 * Mitarbeiter-x-Woche-Smiley-Matrix (#102 Team-Scope / #104 Belegschafts-Scope).
 *
 * Felix-Feedback 2026-11-08: "Matrix wo ich auf der vertikalen Achse die
 * Mitarbeiter sehe und auf der horizontalen die jeweiligen Wochen, wo ich
 * dann immer sehe, wie hat sich der Verlauf der Teamstimmung ueber die
 * Woche veraendert."
 *
 * Erwartete Variablen (per `require` gesetzt):
 *
 *   @var list<array{id:int,label:string}>                 $__matrixLabels
 *   @var array<int,array<string,array{mood:?float,flow:?float}>> $__matrixData
 *   @var string                                           $__matrixH2
 *   @var string                                           $__matrixHint
 */
$__matrixLabels ??= [];
$__matrixData   ??= [];
if (empty($__matrixLabels) || empty($__matrixData)) { return; }

// Wochen-Spalten in Soll-Reihenfolge rekonstruieren: wir haben die gleichen
// Keys in jedem Row-Array. Ersten User-Key nehmen, Keys sind datumssortiert.
$__firstUid = (int) array_key_first($__matrixData);
$__weeks    = $__firstUid !== 0 ? array_keys($__matrixData[$__firstUid] ?? []) : [];
?>
<div class="card sent-matrix-card">
  <h2><?= h($__matrixH2) ?><?= tip_text($__matrixHint) ?></h2>
  <div class="sent-matrix-wrap">
    <table class="sent-matrix">
      <thead>
        <tr>
          <th class="sent-matrix-corner" scope="col"><?= h(t('sent.matrix_corner', 'Mitarbeiter')) ?></th>
          <?php foreach ($__weeks as $__wk):
            try { $__d = new DateTimeImmutable($__wk); } catch (Throwable) { $__d = null; }
          ?>
            <th scope="col" class="sent-matrix-weekhead" title="<?= h($__wk) ?>"><?= h($__d ? 'KW ' . $__d->format('W') : $__wk) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($__matrixLabels as $__u):
          $__uid = (int) $__u['id'];
          $__row = $__matrixData[$__uid] ?? [];
        ?>
          <tr>
            <th scope="row" class="sent-matrix-user"><?= h((string) $__u['label']) ?></th>
            <?php foreach ($__weeks as $__wk):
              $__cell = $__row[$__wk] ?? ['mood' => null, 'flow' => null];
              $__mood = $__cell['mood'] !== null ? (int) round($__cell['mood']) : null;
              $__flow = $__cell['flow'] !== null ? (int) round($__cell['flow']) : null;
            ?>
              <td class="sent-matrix-cell"
                  title="<?= h($__u['label']) ?> · KW <?= h($__wk) ?><?= $__mood !== null ? ' · Mood ' . h(Sentiment::moodLabel($__mood)) : '' ?><?= $__flow !== null ? ' · Flow ' . h(Sentiment::flowLabel($__flow)) : '' ?>">
                <?php if ($__mood !== null): ?>
                  <span aria-hidden="true"><?= Sentiment::moodEmoji($__mood) ?></span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
                <?php if ($__flow !== null): ?>
                  <span aria-hidden="true" class="sent-matrix-flow"><?= Sentiment::flowEmoji($__flow) ?></span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
