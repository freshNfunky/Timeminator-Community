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

  // Week-calendar click & drag — fixes #64. Mousedown on a free spot in
  // a `.cal-day-grid`, drag down, release → `?r=entry_form&start_ts=…
  // &end_ts=…`. 15-min snap, so a 10-second drag still produces useful
  // times. Clicks shorter than ~4 px drop the end so the entry form
  // starts at that instant with no end — the user can then dial in both.
  // CSP-strict: all positioning uses CSS variables set via
  // element.style.setProperty, never inline `style=""` from JS.
  var SNAP_MIN = 15;
  document.querySelectorAll('.cal-day-grid').forEach(function (grid) {
    var day = grid.getAttribute('data-day');
    if (!day) { return; }

    var active = null; // { startPct, endPct, placeholder, startedAtY }

    function minutesFromClientY(ev) {
      var r = grid.getBoundingClientRect();
      var y = Math.max(0, Math.min(r.height, ev.clientY - r.top));
      var mins = Math.round((y / r.height) * 1440);
      return Math.max(0, Math.min(1440, Math.round(mins / SNAP_MIN) * SNAP_MIN));
    }
    function fmtTs(mins) {
      var h = Math.floor(mins / 60);
      var m = mins % 60;
      return day + 'T' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    function cancel() {
      if (active && active.placeholder && active.placeholder.parentNode) {
        active.placeholder.parentNode.removeChild(active.placeholder);
      }
      active = null;
    }

    grid.addEventListener('mousedown', function (ev) {
      // Only react to left click on an empty slot (not on an existing event).
      if (ev.button !== 0) { return; }
      if (ev.target.closest('.cal-event')) { return; }
      ev.preventDefault();
      var startMin = minutesFromClientY(ev);
      var ph = document.createElement('div');
      ph.className = 'cal-event cal-event-draft';
      ph.style.setProperty('--draft-top', (startMin / 1440 * 100) + '%');
      ph.style.setProperty('--draft-height', (SNAP_MIN / 1440 * 100) + '%');
      grid.appendChild(ph);
      active = { startMin: startMin, endMin: startMin + SNAP_MIN, placeholder: ph, startedAtY: ev.clientY };
    });

    document.addEventListener('mousemove', function (ev) {
      if (!active) { return; }
      var now = minutesFromClientY(ev);
      // Allow dragging up or down. Normalize so start < end.
      var lo = Math.min(active.startMin, now);
      var hi = Math.max(active.startMin, now);
      if (hi - lo < SNAP_MIN) { hi = lo + SNAP_MIN; }
      active.endMin = hi;
      active.placeholder.style.setProperty('--draft-top', (lo / 1440 * 100) + '%');
      active.placeholder.style.setProperty('--draft-height', ((hi - lo) / 1440 * 100) + '%');
      active._lo = lo;
      active._hi = hi;
    });

    document.addEventListener('mouseup', function (ev) {
      if (!active) { return; }
      var dragged = Math.abs(ev.clientY - active.startedAtY) > 4;
      var lo = active._lo != null ? active._lo : active.startMin;
      var hi = active._hi != null ? active._hi : active.endMin;
      cancel();
      var qs = 'r=entry_form&start_ts=' + encodeURIComponent(fmtTs(lo));
      if (dragged) {
        qs += '&end_ts=' + encodeURIComponent(fmtTs(hi));
      }
      // Keep whatever path prefix the current page used (handles installs
      // under a sub-path) by using location.pathname.
      window.location.href = window.location.pathname + '?' + qs;
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { cancel(); }
    });
  });

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
