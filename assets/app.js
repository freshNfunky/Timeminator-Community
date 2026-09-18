// Timeminator - small UI helpers (no dependencies).
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

  // Auto-dismiss success flashes after a few seconds.
  document.querySelectorAll('.flash-ok').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4000);
  });
})();
