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
