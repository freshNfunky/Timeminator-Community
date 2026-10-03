// Timeminator - small UI helpers (no dependencies).
//
// All style changes go through classList so a strict CSP without
// `'unsafe-inline'` / `style-src-attr` can be enforced.
(function () {
  'use strict';

  // Live running-timer clock, driven by the server-provided start timestamp.
  var running = document.querySelector('.timer-running[data-start]');
  var clock = document.getElementById('timerClock');
  if (running && clock) {
    var startStr = running.getAttribute('data-start').replace(' ', 'T');
    var start = new Date(startStr);
    var tick = function () {
      var secs = Math.max(0, Math.floor((Date.now() - start.getTime()) / 1000));
      var h = Math.floor(secs / 3600);
      var m = Math.floor((secs % 3600) / 60);
      var s = secs % 60;
      clock.textContent =
        String(h).padStart(2, '0') + ':' +
        String(m).padStart(2, '0') + ':' +
        String(s).padStart(2, '0');
    };
    tick();
    setInterval(tick, 1000);
  }

  // Auto-dismiss success flashes after a few seconds. Uses classList only
  // (no `.style.opacity` assignment) so a strict CSP does not block it.
  document.querySelectorAll('.flash-ok').forEach(function (el) {
    setTimeout(function () {
      el.classList.add('fading-out');
      setTimeout(function () { el.remove(); }, 500);
    }, 4000);
  });

  // Global confirm-on-submit for forms carrying `data-confirm="text"`.
  // Replaces per-form `onsubmit="return confirm('...')"` (CSP-hostile inline JS).
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      ev.preventDefault();
    }
  });

  // Scroll the week-calendar grid to the earliest booking of the week on
  // load, so the user doesn't start staring at the empty pre-dawn hours.
  // Offset is set by the server as `--cal-scroll` on `.cal-week` inside a
  // nonce-authorized <style> block (see views/entries/calendar_week.php).
  var calWeek = document.querySelector('.cal-week');
  if (calWeek) {
    var scrollTo = parseFloat(getComputedStyle(calWeek).getPropertyValue('--cal-scroll')) || 0;
    if (scrollTo > 56) {
      calWeek.scrollTop = scrollTo - 24;
    }
  }

  // Promo-sidebar carousel. Rotates through .promo-banner children of a
  // .promo-carousel container every data-promo-interval ms (default 6s).
  // Dots are clickable and reset the timer. CSP-strict: only classList
  // mutations, no inline style assignments.
  var promoCarousel = document.querySelector('.promo-carousel');
  if (promoCarousel) {
    var banners = promoCarousel.querySelectorAll('.promo-banner');
    var dots    = document.querySelectorAll('.promo-dot');
    if (banners.length > 1) {
      var promoIdx   = 0;
      var promoInt   = parseInt(promoCarousel.getAttribute('data-promo-interval'), 10) || 6000;
      var promoTimer = null;
      var showPromo  = function (i) {
        promoIdx = ((i % banners.length) + banners.length) % banners.length;
        banners.forEach(function (b, j) { b.classList.toggle('is-active', j === promoIdx); });
        dots.forEach(function (d, j)    { d.classList.toggle('is-active', j === promoIdx); });
      };
      var armPromo = function () {
        if (promoTimer) { clearInterval(promoTimer); }
        promoTimer = setInterval(function () { showPromo(promoIdx + 1); }, promoInt);
      };
      dots.forEach(function (d, j) {
        d.addEventListener('click', function () { showPromo(j); armPromo(); });
      });
      armPromo();
    }
  }

  // Collapsible nav groups: close an open .nav-group when clicking outside
  // of it. The native <details> element handles open/close on the summary
  // itself; this just dismisses stale dropdowns.
  document.addEventListener('click', function (ev) {
    document.querySelectorAll('details.nav-group[open]').forEach(function (d) {
      if (!d.contains(ev.target)) { d.open = false; }
    });
  });

  // Toggle visibility of elements listed in a radio's `data-toggle-target`
  // attribute — space-separated element ids to reveal, followed by an
  // optional "|" and the ids to hide. Replaces `onchange="element.style..."`.
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-toggle-target')) {
      return;
    }
    var spec = el.getAttribute('data-toggle-target') || '';
    var parts = spec.split('|');
    (parts[0] || '').split(/\s+/).forEach(function (id) {
      if (!id) return;
      var t = document.getElementById(id);
      if (t) { t.classList.remove('hidden'); }
    });
    (parts[1] || '').split(/\s+/).forEach(function (id) {
      if (!id) return;
      var t = document.getElementById(id);
      if (t) { t.classList.add('hidden'); }
    });
  });
})();
