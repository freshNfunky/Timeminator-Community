<?php
/**
 * @var array $jobs
 * @var string $token
 * @var string $web_cron
 */
?>
<div class="card">
  <h2><?= h(t('cron.h2', 'Jobs')) ?><?= tip('cron.intro') ?></h2>

  <?php if (!$jobs): ?>
    <p class="muted"><?= h(t('cron.no_jobs', 'Keine Jobs registriert.')) ?></p>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th><?= h(t('cron.col_name', 'Job')) ?></th>
        <th><?= h(t('cron.col_interval', 'Intervall')) ?></th>
        <th><?= h(t('cron.col_last_run', 'Zuletzt ausgefuehrt')) ?></th>
        <th><?= h(t('cron.col_result', 'Ergebnis')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr>
          <td>
            <code><?= h($j['name']) ?></code>
            <?php if (!empty($j['description'])): ?>
              <div class="muted" style="font-size:.9em"><?= h($j['description']) ?></div>
            <?php endif; ?>
          </td>
          <td class="nowrap">
            <?php
              $m = (int) $j['interval_min'];
              echo h($m >= 60 ? sprintf('%d h', (int) ($m / 60)) : sprintf('%d min', $m));
            ?>
          </td>
          <td class="nowrap">
            <?= $j['last_run_at'] ? h(fmt_dt($j['last_run_at'])) : '<span class="muted">—</span>' ?>
          </td>
          <td>
            <?php if ($j['last_error']): ?>
              <span class="bad-mark"><?= h(t('cron.result_error', 'Fehler')) ?></span>
              <div class="muted" style="font-size:.9em"><?= h($j['last_error']) ?></div>
            <?php elseif ($j['last_result']): ?>
              <span class="ok-mark"><?= h(t('cron.result_ok', 'OK')) ?></span>
              <span class="muted"><?= h($j['last_result']) ?></span>
              <?php if ($j['last_duration_ms'] !== null): ?>
                <span class="muted">· <?= (int) $j['last_duration_ms'] ?> ms</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="muted"><?= h(t('cron.result_never', 'noch nie gelaufen')) ?></span>
            <?php endif; ?>
          </td>
          <td class="r">
            <form method="post" action="<?= h(route('cron_run_one')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="name" value="<?= h($j['name']) ?>">
              <button class="btn btn-sm" type="submit"><?= h(t('cron.run_now', 'Jetzt ausfuehren')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= h(t('cron.web_cron_h2', 'Web-Cron-Endpoint')) ?><?= tip('cron.web_cron_intro') ?></h2>
  <p><code style="word-break:break-all"><?= h($web_cron) ?></code></p>
  <h3 style="margin-top:16px"><?= h(t('cron.system_cron_h3', 'System-Cron (klassisch)')) ?><?= tip('cron.system_cron_intro') ?></h3>
  <pre><code>*/15 * * * * curl -s "<?= h($web_cron) ?>" &gt; /dev/null</code></pre>

  <form method="post" action="<?= h(route('cron_regen_token')) ?>" class="inline" style="margin-top:16px"
        data-confirm="<?= h(t('cron.regen_confirm', 'Neuen Token erzeugen? Der bisherige wird dann abgelehnt und externe Cron-Hitter muessen aktualisiert werden.')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-sm btn-danger" type="submit"><?= h(t('cron.regen_token', 'Token regenerieren')) ?></button>
  </form>
</div>
