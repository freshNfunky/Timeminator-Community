<?php
/**
 * Team-Sentiment-Tracking (fixes #92 / split by #122).
 *
 * @var ?array $today        existing row for today (or null)
 * @var array  $history      user's own last 60 days
 * @var bool   $canTeam      whether the viewer has sentiment.view_team
 * @var ?array $teamSummary  teamSummary() payload or null
 * @var array  $teamDaily    team daily aggregate (last 60 days) or []
 */
$selectedMood = $today && isset($today['mood']) && $today['mood'] !== null ? (int) $today['mood'] : 0;
$selectedFlow = $today && isset($today['flow']) && $today['flow'] !== null ? (int) $today['flow'] : 0;
$noteValue    = (string) ($today['note'] ?? '');
?>
<div class="card">
  <h2><?= h(t('sent.today_title', 'Dein Eintrag heute')) ?><?= tip('sent.today_intro_v2') ?></h2>
  <form method="post" action="<?= h(route('sentiment_save')) ?>" class="sentiment-form">
    <?= csrf_field() ?>
    <input type="hidden" name="as_of" value="<?= h((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">

    <fieldset class="sentiment-fieldset">
      <legend><?= h(t('sent.mood_legend', 'Mood — persoenliche Stimmung')) ?></legend>
      <div class="sentiment-scale" role="radiogroup" aria-label="<?= h(t('sent.mood_legend', 'Mood')) ?>">
        <label class="sentiment-pick<?= $selectedMood === 0 ? ' is-selected' : '' ?>">
          <input type="radio" name="mood" value="" <?= $selectedMood === 0 ? 'checked' : '' ?>>
          <span class="sentiment-emoji" aria-hidden="true">—</span>
          <span class="sentiment-label"><?= h(t('sent.skip', 'ueberspringen')) ?></span>
        </label>
        <?php for ($s = 1; $s <= 5; $s++): ?>
          <label class="sentiment-pick<?= $selectedMood === $s ? ' is-selected' : '' ?>">
            <input type="radio" name="mood" value="<?= $s ?>" <?= $selectedMood === $s ? 'checked' : '' ?>>
            <span class="sentiment-emoji" aria-hidden="true"><?= Sentiment::moodEmoji($s) ?></span>
            <span class="sentiment-label"><?= h(Sentiment::moodLabel($s)) ?></span>
          </label>
        <?php endfor; ?>
      </div>
    </fieldset>

    <fieldset class="sentiment-fieldset">
      <legend><?= h(t('sent.flow_legend', 'Flow — wie laeuft die Arbeit')) ?></legend>
      <div class="sentiment-scale" role="radiogroup" aria-label="<?= h(t('sent.flow_legend', 'Flow')) ?>">
        <label class="sentiment-pick<?= $selectedFlow === 0 ? ' is-selected' : '' ?>">
          <input type="radio" name="flow" value="" <?= $selectedFlow === 0 ? 'checked' : '' ?>>
          <span class="sentiment-emoji" aria-hidden="true">—</span>
          <span class="sentiment-label"><?= h(t('sent.skip', 'ueberspringen')) ?></span>
        </label>
        <?php for ($s = 1; $s <= 5; $s++): ?>
          <label class="sentiment-pick<?= $selectedFlow === $s ? ' is-selected' : '' ?>">
            <input type="radio" name="flow" value="<?= $s ?>" <?= $selectedFlow === $s ? 'checked' : '' ?>>
            <span class="sentiment-emoji" aria-hidden="true"><?= Sentiment::flowEmoji($s) ?></span>
            <span class="sentiment-label"><?= h(Sentiment::flowLabel($s)) ?></span>
          </label>
        <?php endfor; ?>
      </div>
    </fieldset>

    <label class="sentiment-note-label"><?= h(t('dash.note')) ?>
      <input type="text" name="note" value="<?= h($noteValue) ?>"
             maxlength="500" placeholder="<?= h(t('sent.note_placeholder', 'Was ging gut? Was zieht runter?')) ?>">
    </label>

    <button class="btn btn-primary" type="submit">
      <?= h($today ? t('sent.update', 'Aktualisieren') : t('common.save')) ?>
    </button>
    <?php if ($today): ?>
      <span class="muted sentiment-saved">
        <?= h(sprintf(t('sent.last_saved', 'Zuletzt gespeichert %s.'), fmt_dt($today['updated_at'] ?? $today['created_at']))) ?>
      </span>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2><?= h(t('sent.history_title', 'Dein Verlauf (60 Tage)')) ?></h2>
  <?php if (!$history): ?>
    <p class="muted"><?= h(t('sent.history_empty', 'Noch keine Eintraege. Starte mit heute oben.')) ?></p>
  <?php else: ?>
    <?php
    // Compact personal history: two sparkline rows, Mood and Flow.
    $moodByDate = [];
    $flowByDate = [];
    foreach ($history as $h) {
        if ($h['mood'] !== null) { $moodByDate[$h['as_of']] = (int) $h['mood']; }
        if ($h['flow'] !== null) { $flowByDate[$h['as_of']] = (int) $h['flow']; }
    }
    $end   = new DateTimeImmutable('today');
    $slots = [];
    for ($i = 59; $i >= 0; $i--) {
        $d = $end->modify('-' . $i . ' day')->format('Y-m-d');
        $slots[] = [
            'd'    => $d,
            'mood' => $moodByDate[$d] ?? null,
            'flow' => $flowByDate[$d] ?? null,
        ];
    }
    $noEntry = t('sent.no_entry_short', 'keine Eintragung');
    ?>
    <div class="sentiment-dual">
      <div>
        <div class="sentiment-dual-label muted"><?= h(t('sent.mood_short', 'Mood')) ?></div>
        <div class="sentiment-sparkline" aria-label="<?= h(t('sent.spark_60_days_mood', 'Mood — letzte 60 Tage')) ?>">
          <?php foreach ($slots as $slot): ?>
            <?php $cls = $slot['mood'] ? 'is-s' . $slot['mood'] : 'is-empty'; ?>
            <span class="sentiment-tick <?= $cls ?>"
                  title="<?= h($slot['d']) ?><?= $slot['mood'] ? ': ' . h(Sentiment::moodLabel($slot['mood'])) : ': ' . h($noEntry) ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div class="sentiment-dual-label muted"><?= h(t('sent.flow_short', 'Flow')) ?></div>
        <div class="sentiment-sparkline" aria-label="<?= h(t('sent.spark_60_days_flow', 'Flow — letzte 60 Tage')) ?>">
          <?php foreach ($slots as $slot): ?>
            <?php $cls = $slot['flow'] ? 'is-s' . $slot['flow'] : 'is-empty'; ?>
            <span class="sentiment-tick <?= $cls ?>"
                  title="<?= h($slot['d']) ?><?= $slot['flow'] ? ': ' . h(Sentiment::flowLabel($slot['flow'])) : ': ' . h($noEntry) ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <table class="table">
      <thead><tr>
        <th><?= h(t('sent.col_date', 'Datum')) ?></th>
        <th><?= h(t('sent.mood_short', 'Mood')) ?></th>
        <th><?= h(t('sent.flow_short', 'Flow')) ?></th>
        <th><?= h(t('entries.note')) ?></th>
      </tr></thead>
      <tbody>
        <?php foreach (array_reverse($history) as $h): ?>
          <tr>
            <td><?= h($h['as_of']) ?></td>
            <td>
              <?php if ($h['mood'] !== null): ?>
                <span aria-hidden="true"><?= Sentiment::moodEmoji((int) $h['mood']) ?></span>
                <?= h(Sentiment::moodLabel((int) $h['mood'])) ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($h['flow'] !== null): ?>
                <span aria-hidden="true"><?= Sentiment::flowEmoji((int) $h['flow']) ?></span>
                <?= h(Sentiment::flowLabel((int) $h['flow'])) ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= h((string) ($h['note'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($canTeam): ?>
  <div class="card">
    <h2><?= h(t('sent.team_title', 'Team-Stimmung')) ?><?= tip_text(sprintf(t('sent.team_intro', 'Aggregierter Durchschnitt der letzten %d Tage. Nur Mittelwerte und Anzahl Antworten; keine Einzelstimmen, keine Namen.'), (int) $teamSummary['window_days'])) ?></h2>

    <div class="sentiment-team-summary">
      <div>
        <div class="sentiment-team-metric">
          <?php if ($teamSummary['avg_mood'] !== null): ?>
            <?= Sentiment::moodEmoji((int) round($teamSummary['avg_mood'])) ?>
            <strong><?= number_format($teamSummary['avg_mood'], 2, ',', '') ?></strong>
            <span class="muted">/ 5</span>
          <?php else: ?>
            <span class="muted"><?= h(t('sent.no_responses', 'keine Antworten')) ?></span>
          <?php endif; ?>
        </div>
        <div class="muted"><?= h(t('sent.mood_avg', 'Mood-Durchschnitt')) ?> · <?= (int) $teamSummary['n_mood'] ?> <?= h(t('sent.responses', 'Antworten')) ?></div>
      </div>
      <div>
        <div class="sentiment-team-metric">
          <?php if ($teamSummary['avg_flow'] !== null): ?>
            <?= Sentiment::flowEmoji((int) round($teamSummary['avg_flow'])) ?>
            <strong><?= number_format($teamSummary['avg_flow'], 2, ',', '') ?></strong>
            <span class="muted">/ 5</span>
          <?php else: ?>
            <span class="muted"><?= h(t('sent.no_responses', 'keine Antworten')) ?></span>
          <?php endif; ?>
        </div>
        <div class="muted"><?= h(t('sent.flow_avg', 'Flow-Durchschnitt')) ?> · <?= (int) $teamSummary['n_flow'] ?> <?= h(t('sent.responses', 'Antworten')) ?></div>
      </div>
      <div>
        <div class="sentiment-team-metric"><strong><?= (int) $teamSummary['contributors'] ?></strong></div>
        <div class="muted"><?= h(t('sent.contributors', 'Beteiligte')) ?></div>
      </div>
    </div>

    <?php if ($teamDaily): ?>
      <h3><?= h(t('sent.team_history_title', 'Verlauf (letzte 60 Tage)')) ?></h3>
      <?php
        $teamByDate = [];
        foreach ($teamDaily as $r) { $teamByDate[$r['as_of']] = $r; }
        $end = new DateTimeImmutable('today');
        $__noResp  = t('sent.no_responses', 'keine Antworten');
        $__respLbl = t('sent.responses', 'Antworten');
      ?>
      <div class="sentiment-dual">
        <div>
          <div class="sentiment-dual-label muted"><?= h(t('sent.mood_short', 'Mood')) ?></div>
          <div class="sentiment-sparkline sentiment-sparkline-team" aria-label="<?= h(t('sent.team_history_mood', 'Team-Mood-Verlauf')) ?>">
            <?php for ($i = 59; $i >= 0; $i--):
              $d = $end->modify('-' . $i . ' day')->format('Y-m-d');
              $r = $teamByDate[$d] ?? null;
              if ($r === null || $r['avg_mood'] === null): ?>
                <span class="sentiment-tick is-empty" title="<?= h($d) ?>: <?= h($__noResp) ?>"></span>
              <?php else:
                $rounded = (int) round($r['avg_mood']); ?>
                <span class="sentiment-tick is-s<?= $rounded ?>"
                      title="<?= h($d) ?>: <?= h(number_format($r['avg_mood'], 2, ',', '')) ?> (<?= (int) $r['n_mood'] ?> <?= h($__respLbl) ?>)"></span>
              <?php endif;
            endfor; ?>
          </div>
        </div>
        <div>
          <div class="sentiment-dual-label muted"><?= h(t('sent.flow_short', 'Flow')) ?></div>
          <div class="sentiment-sparkline sentiment-sparkline-team" aria-label="<?= h(t('sent.team_history_flow', 'Team-Flow-Verlauf')) ?>">
            <?php for ($i = 59; $i >= 0; $i--):
              $d = $end->modify('-' . $i . ' day')->format('Y-m-d');
              $r = $teamByDate[$d] ?? null;
              if ($r === null || $r['avg_flow'] === null): ?>
                <span class="sentiment-tick is-empty" title="<?= h($d) ?>: <?= h($__noResp) ?>"></span>
              <?php else:
                $rounded = (int) round($r['avg_flow']); ?>
                <span class="sentiment-tick is-s<?= $rounded ?>"
                      title="<?= h($d) ?>: <?= h(number_format($r['avg_flow'], 2, ',', '')) ?> (<?= (int) $r['n_flow'] ?> <?= h($__respLbl) ?>)"></span>
              <?php endif;
            endfor; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
