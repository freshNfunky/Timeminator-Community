<?php
/**
 * Team-Sentiment-Tracking (fixes #92).
 *
 * @var ?array $today        existing row for today (or null)
 * @var array  $history      user's own last 60 days
 * @var bool   $canTeam      whether the viewer has sentiment.view_team
 * @var ?array $teamSummary  teamSummary() payload or null
 * @var array  $teamDaily    team daily aggregate (last 60 days) or []
 */
$selectedScore = (int) ($today['score'] ?? 0);
$noteValue     = (string) ($today['note'] ?? '');
?>
<div class="card">
  <h2>Deine Stimmung heute</h2>
  <p class="muted">Erfasse mit einem Klick, wie der Arbeitstag fuer dich war.
    Du kannst eine kurze Notiz dazuschreiben; nur du siehst sie.</p>
  <form method="post" action="<?= h(route('sentiment_save')) ?>" class="sentiment-form">
    <?= csrf_field() ?>
    <input type="hidden" name="as_of" value="<?= h((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">

    <div class="sentiment-scale" role="radiogroup" aria-label="Stimmung">
      <?php for ($s = 1; $s <= 5; $s++): ?>
        <label class="sentiment-pick<?= $selectedScore === $s ? ' is-selected' : '' ?>">
          <input type="radio" name="score" value="<?= $s ?>" <?= $selectedScore === $s ? 'checked' : '' ?>>
          <span class="sentiment-emoji" aria-hidden="true"><?= Sentiment::emoji($s) ?></span>
          <span class="sentiment-label"><?= h(Sentiment::label($s)) ?></span>
        </label>
      <?php endfor; ?>
    </div>

    <label class="sentiment-note-label">Notiz (optional)
      <input type="text" name="note" value="<?= h($noteValue) ?>"
             maxlength="500" placeholder="Was ging gut? Was zieht runter?">
    </label>

    <button class="btn btn-primary" type="submit">
      <?= $today ? 'Aktualisieren' : 'Speichern' ?>
    </button>
    <?php if ($today): ?>
      <span class="muted sentiment-saved">
        Zuletzt gespeichert <?= h(fmt_dt($today['updated_at'] ?? $today['created_at'])) ?>.
      </span>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Dein Verlauf (60 Tage)</h2>
  <?php if (!$history): ?>
    <p class="muted">Noch keine Eintraege. Starte mit heute oben.</p>
  <?php else: ?>
    <?php
    // Compact personal history as a sparkline-like bar row + list.
    $byDate = [];
    foreach ($history as $h) { $byDate[$h['as_of']] = (int) $h['score']; }
    // Fill last 60 days end-to-start so gaps become empty slots in the row.
    $end   = new DateTimeImmutable('today');
    $slots = [];
    for ($i = 59; $i >= 0; $i--) {
        $d = $end->modify('-' . $i . ' day')->format('Y-m-d');
        $slots[] = ['d' => $d, 'score' => $byDate[$d] ?? null];
    }
    ?>
    <div class="sentiment-sparkline" aria-label="Letzte 60 Tage">
      <?php foreach ($slots as $slot): ?>
        <?php $cls = $slot['score'] ? 'is-s' . $slot['score'] : 'is-empty'; ?>
        <span class="sentiment-tick <?= $cls ?>"
              title="<?= h($slot['d']) ?><?= $slot['score'] ? ': ' . h(Sentiment::label($slot['score'])) : ': keine Eintragung' ?>"></span>
      <?php endforeach; ?>
    </div>

    <table class="table">
      <thead><tr><th>Datum</th><th>Stimmung</th><th>Notiz</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($history) as $h): ?>
          <tr>
            <td><?= h($h['as_of']) ?></td>
            <td>
              <span aria-hidden="true"><?= Sentiment::emoji((int) $h['score']) ?></span>
              <?= h(Sentiment::label((int) $h['score'])) ?>
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
    <h2>Team-Stimmung</h2>
    <p class="muted">Aggregierter Durchschnitt der letzten <?= (int) $teamSummary['window_days'] ?> Tage.
      Nur Mittelwerte und Anzahl Antworten; keine Einzelstimmen, keine Namen.</p>

    <div class="sentiment-team-summary">
      <div>
        <div class="sentiment-team-metric">
          <?php if ($teamSummary['avg'] !== null): ?>
            <?= Sentiment::emoji((int) round($teamSummary['avg'])) ?>
            <strong><?= number_format($teamSummary['avg'], 2, ',', '') ?></strong>
            <span class="muted">/ 5</span>
          <?php else: ?>
            <span class="muted">keine Antworten</span>
          <?php endif; ?>
        </div>
        <div class="muted">Durchschnitt</div>
      </div>
      <div>
        <div class="sentiment-team-metric"><strong><?= (int) $teamSummary['responses'] ?></strong></div>
        <div class="muted">Antworten</div>
      </div>
      <div>
        <div class="sentiment-team-metric"><strong><?= (int) $teamSummary['contributors'] ?></strong></div>
        <div class="muted">Beteiligte</div>
      </div>
    </div>

    <?php if ($teamDaily): ?>
      <h3>Verlauf (letzte 60 Tage)</h3>
      <div class="sentiment-sparkline sentiment-sparkline-team" aria-label="Team-Verlauf">
        <?php
        $teamByDate = [];
        foreach ($teamDaily as $r) { $teamByDate[$r['as_of']] = $r; }
        $end = new DateTimeImmutable('today');
        for ($i = 59; $i >= 0; $i--) {
            $d = $end->modify('-' . $i . ' day')->format('Y-m-d');
            $r = $teamByDate[$d] ?? null;
            if ($r === null) {
                echo '<span class="sentiment-tick is-empty" title="' . h($d) . ': keine Antworten"></span>';
            } else {
                $rounded = (int) round($r['avg_score']);
                echo '<span class="sentiment-tick is-s' . $rounded . '" title="' . h($d) . ': '
                   . h(number_format($r['avg_score'], 2, ',', '')) . ' (' . (int) $r['n'] . ' Antworten)"></span>';
            }
        }
        ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
