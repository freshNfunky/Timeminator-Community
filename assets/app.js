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

  // Community banner carousel (Issue #22). Loads creatives from a first-party
  // route (which server-side proxies the banner subdomain and falls back to a
  // bundled default set), then rotates through them. Fully non-blocking: the
  // main page never waits on this, and any failure just hides the slot.
  initBannerCarousel();

  function initBannerCarousel() {
    var mount = document.getElementById('spRotator');
    var bar = document.getElementById('sidepanel');
    if (!mount || !bar) {
      return;
    }

    // Collapse toggle, remembered per browser.
    var toggle = document.getElementById('spToggle');
    var STORE_KEY = 'tm_sp_collapsed';
    try {
      if (window.localStorage && localStorage.getItem(STORE_KEY) === '1') {
        bar.classList.add('collapsed');
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); toggle.textContent = '+'; }
      }
    } catch (e) { /* storage blocked — default to expanded */ }

    if (toggle) {
      toggle.addEventListener('click', function () {
        var collapsed = bar.classList.toggle('collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.textContent = collapsed ? '+' : '–';
        try { if (window.localStorage) { localStorage.setItem(STORE_KEY, collapsed ? '1' : '0'); } } catch (e) {}
      });
    }

    var endpoint = mount.getAttribute('data-endpoint');
    if (!endpoint) { return; }
    var interval = parseInt(mount.getAttribute('data-interval'), 10) || 7000;

    fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.enabled || !Array.isArray(data.items) || data.items.length === 0) {
          bar.remove();
          return;
        }
        renderCarousel(mount, data.items, interval);
      })
      .catch(function () { bar.remove(); });
  }

  function renderCarousel(mount, items, interval) {
    mount.textContent = '';
    var cards = items.map(function (it) { return buildCard(it); });
    cards.forEach(function (c, i) {
      if (i > 0) { c.classList.add('hidden'); }
      mount.appendChild(c);
    });

    if (cards.length < 2) { return; }

    var dots = document.createElement('div');
    dots.className = 'sp-dots';
    var dotEls = cards.map(function (_, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sp-dot' + (i === 0 ? ' active' : '');
      b.setAttribute('aria-label', 'Banner ' + (i + 1));
      b.addEventListener('click', function () { show(i); restart(); });
      dots.appendChild(b);
      return b;
    });
    mount.appendChild(dots);

    var current = 0;
    var timer = null;

    function show(i) {
      cards[current].classList.add('hidden');
      dotEls[current].classList.remove('active');
      current = (i + cards.length) % cards.length;
      cards[current].classList.remove('hidden');
      dotEls[current].classList.add('active');
    }
    function tick() { show(current + 1); }
    function start() { timer = window.setInterval(tick, interval); }
    function restart() { if (timer) { window.clearInterval(timer); } start(); }

    mount.addEventListener('mouseenter', function () { if (timer) { window.clearInterval(timer); timer = null; } });
    mount.addEventListener('mouseleave', function () { if (!timer) { start(); } });
    start();
  }

  function buildCard(it) {
    // HTML banner: embed as a sandboxed iframe (fluid, its own clickable links).
    // Not wrapped in <a> — the creative carries its own CTA link. The sandbox
    // allows scripts and link-clicks opening a new tab, but not same-origin
    // access to this page nor top-level navigation.
    if (it.iframe) {
      var box = document.createElement('div');
      box.className = 'sp-item is-iframe';
      var frame = document.createElement('iframe');
      frame.src = it.iframe;
      frame.title = it.title || 'Anzeige';
      frame.loading = 'lazy';
      frame.setAttribute('sandbox', 'allow-scripts allow-popups allow-popups-to-escape-sandbox');
      frame.setAttribute('referrerpolicy', 'no-referrer');
      box.appendChild(frame);
      return box;
    }

    var card = document.createElement('a');
    card.className = 'sp-item';
    if (it.href) {
      card.href = it.href;
      card.target = '_blank';
      card.rel = 'noopener noreferrer';
    }

    // Image-dominant creative (portrait skyscraper SVG): the image IS the ad.
    // The copy is baked into the creative, so we only render the image, with the
    // title as its accessible label. Otherwise fall back to a text card.
    if (it.image) {
      card.classList.add('is-image');
      var img = document.createElement('img');
      img.src = it.image;
      img.alt = it.title || it.cta || 'Anzeige';
      img.loading = 'lazy';
      card.appendChild(img);
      return card;
    }

    if (it.title) {
      var t = document.createElement('span');
      t.className = 'sp-title';
      t.textContent = it.title;
      card.appendChild(t);
    }
    if (it.text) {
      var p = document.createElement('span');
      p.className = 'sp-text';
      p.textContent = it.text;
      card.appendChild(p);
    }
    if (it.cta && it.href) {
      var cta = document.createElement('span');
      cta.className = 'sp-cta';
      cta.textContent = it.cta;
      card.appendChild(cta);
    }
    return card;
  }
})();
