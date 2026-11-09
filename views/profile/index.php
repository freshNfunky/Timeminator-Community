<?php
/**
 * Benutzer-Profil / Meine Einstellungen (Felix-Feedback 2026-11-09, #111).
 *
 * @var string $sentimentGranularity  aktuelle User-Preference ('day' | 'week')
 * @var string $systemDefault         System-Default (Admin-weit)
 */
?>
<h1 class="page-title"><?= h(t('profile.title', 'Meine Einstellungen')) ?></h1>

<div class="card">
  <h2><?= h(t('profile.sentiment_section', 'Stimmungs-Rhythmus')) ?><?= tip('profile.sentiment_tip') ?></h2>
  <form method="post" action="<?= h(route('profile_save')) ?>" class="form-inline">
    <?= csrf_field() ?>
    <label>
      <input type="radio" name="sentiment_granularity" value="week" <?= $sentimentGranularity === 'week' ? 'checked' : '' ?>>
      <?= h(t('sent.granularity_week', 'Woechentlich')) ?>
    </label>
    <label>
      <input type="radio" name="sentiment_granularity" value="day" <?= $sentimentGranularity === 'day' ? 'checked' : '' ?>>
      <?= h(t('sent.granularity_day', 'Taeglich')) ?>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
    </div>
  </form>
</div>
