<?php
/**
 * Benutzer-Profil / Meine Einstellungen (Felix-Feedback 2026-11-09, #111).
 *
 * @var string $sentimentGranularity  aktuelle User-Preference ('day' | 'week')
 */
?>
<?php /* Felix-Feedback 2026-11-09 (Fix #135): kein zweites <h1
   class="page-title"> hier - die Layout-Shell rendert den Titel bereits
   aus dem view()-Call. Vorher gab's zwei "Meine Einstellungen"-H1s
   uebereinander. */ ?>

<div class="card profile-card">
  <h2 class="profile-section-title"><?= h(t('profile.sentiment_section', 'Stimmungs-Rhythmus')) ?><?= tip('profile.sentiment_tip') ?></h2>
  <form method="post" action="<?= h(route('profile_save')) ?>" class="profile-form">
    <?= csrf_field() ?>
    <?php /* Felix-Feedback 2026-11-09 (Fix #136): Radios als kompakte
       Row, Input + Label auf einer Baseline (align-items:center). Vorher
       stand die Button-Row darunter mit eigenem Block-Layout und wirkte
       zerfleddert. */ ?>
    <div class="profile-radio-row" role="radiogroup" aria-label="<?= h(t('profile.sentiment_section', 'Stimmungs-Rhythmus')) ?>">
      <label class="profile-radio">
        <input type="radio" name="sentiment_granularity" value="week" <?= $sentimentGranularity === 'week' ? 'checked' : '' ?>>
        <span><?= h(t('sent.granularity_week', 'Woechentlich')) ?></span>
      </label>
      <label class="profile-radio">
        <input type="radio" name="sentiment_granularity" value="day" <?= $sentimentGranularity === 'day' ? 'checked' : '' ?>>
        <span><?= h(t('sent.granularity_day', 'Taeglich')) ?></span>
      </label>
      <button class="btn btn-primary profile-save" type="submit"><?= h(t('common.save', 'Speichern')) ?></button>
    </div>
  </form>
</div>
