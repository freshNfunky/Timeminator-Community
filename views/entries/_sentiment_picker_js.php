<?php
/**
 * Shared sentiment-picker JS fuer Kalender-Week/-Month-View.
 *
 * Eingezogen aus calendar_week.php (Felix-Feedback 2026-11-08:
 * Mood-Picker braucht die gleiche Mechanik auch im Monat).
 * Haengt Listener an jedes `.cal-sent-day` / `.cal-sent-week` /
 * `.cal-sent-toggle`, egal in welcher View es rendert.
 *
 * CSP-strict: nonce vorne, keine inline styles.
 *
 * @var bool $canRecordSentiment
 */
?>
<?php if (!empty($canRecordSentiment)): ?>
<script nonce="<?= h(csp_nonce()) ?>">
(function () {
  var daySaveUrl = <?= json_encode(route('sentiment_api_save'))      ?>;
  var weekSaveUrl= <?= json_encode(route('sentiment_api_fill_week')) ?>;
  var granUrl    = <?= json_encode(route('sentiment_granularity_api'))?>;

  function postForm(url, body) {
    return fetch(url, {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function (r) { return r.json().catch(function(){ return {ok:false}; }); });
  }

  function closeOpenPopover(except) {
    document.querySelectorAll('.cal-sent-popover').forEach(function (p) {
      if (except && p === except) return;
      p.hidden = true;
    });
    document.querySelectorAll('.cal-sent-current.is-open').forEach(function (b) {
      b.classList.remove('is-open');
    });
  }
  var EMOJI = {
    mood: {1:'\u{1F61E}',2:'\u{1F615}',3:'\u{1F610}',4:'\u{1F642}',5:'\u{1F604}'},
    flow: {1:'\u{1F6D1}',2:'\u{1F422}',3:'\u{1F610}',4:'\u{1F3C3}',5:'\u{1F680}'}
  };
  document.querySelectorAll('.cal-sent-day').forEach(function (cell) {
    var day  = cell.getAttribute('data-day');
    var csrf = cell.getAttribute('data-csrf');
    cell.querySelectorAll('.cal-sent-cell').forEach(function (dimCell) {
      var dim  = dimCell.getAttribute('data-dim');
      var cur  = dimCell.querySelector('.cal-sent-current');
      var pop  = dimCell.querySelector('.cal-sent-popover');
      if (!cur || !pop) return;
      cur.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (cur.disabled) return;
        var open = !pop.hidden;
        closeOpenPopover();
        if (!open) { pop.hidden = false; cur.classList.add('is-open'); }
      });
      pop.querySelectorAll('.cal-sent-opt').forEach(function (opt) {
        opt.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          var score = opt.getAttribute('data-score');
          var current = dimCell.getAttribute('data-current');
          var isClear = String(current) === String(score);
          var body = new URLSearchParams();
          body.set('_csrf', csrf);
          body.set('day', day);
          body.set('dim', dim);
          body.set('score', isClear ? '' : score);
          pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = true; });
          postForm(daySaveUrl, body).then(function (res) {
            pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = false; });
            if (!res.ok) return;
            var next = isClear ? '' : score;
            dimCell.setAttribute('data-current', next);
            pop.querySelectorAll('.cal-sent-opt').forEach(function (b) {
              var match = String(b.getAttribute('data-score')) === String(next);
              b.classList.toggle('is-selected', match);
              b.setAttribute('aria-selected', match ? 'true' : 'false');
            });
            cur.textContent = next ? EMOJI[dim][next] : EMOJI[dim][3];
            cur.classList.toggle('is-empty', !next);
            closeOpenPopover();
          });
        });
      });
    });
  });
  document.addEventListener('click', function () { closeOpenPopover(); });

  document.querySelectorAll('.cal-sent-week').forEach(function (block) {
    var monday = block.getAttribute('data-monday');
    var csrf   = block.getAttribute('data-csrf');
    block.querySelectorAll('.cal-sent-cell').forEach(function (dimCell) {
      var dim  = dimCell.getAttribute('data-dim');
      var cur  = dimCell.querySelector('.cal-sent-current');
      var pop  = dimCell.querySelector('.cal-sent-popover');
      if (!cur || !pop) return;
      cur.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var open = !pop.hidden;
        closeOpenPopover();
        if (!open) { pop.hidden = false; cur.classList.add('is-open'); }
      });
      pop.querySelectorAll('.cal-sent-opt').forEach(function (opt) {
        opt.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          var score = opt.getAttribute('data-score');
          var body = new URLSearchParams();
          body.set('_csrf', csrf);
          body.set('monday', monday);
          body.set('dim', dim);
          body.set('score', score);
          pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = true; });
          postForm(weekSaveUrl, body).then(function (res) {
            pop.querySelectorAll('.cal-sent-opt').forEach(function (b) { b.disabled = false; });
            if (!res.ok) return;
            cur.textContent = EMOJI[dim][score] || '—';
            cur.classList.remove('is-empty');
            closeOpenPopover();
          });
        });
      });
    });
  });

  document.querySelectorAll('.cal-sent-toggle').forEach(function (grp) {
    var csrf = grp.getAttribute('data-csrf');
    grp.querySelectorAll('button[data-g]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var g = btn.getAttribute('data-g');
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        body.set('granularity', g);
        postForm(granUrl, body).then(function (res) {
          if (!res.ok) return;
          window.location.reload();
        });
      });
    });
  });
})();
</script>
<?php endif; ?>
