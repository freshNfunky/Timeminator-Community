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
        try { renderCarousel(mount, bar, data.items, interval); }
        catch (e) { bar.remove(); }
      })
      .catch(function () { bar.remove(); });
  }

  function renderCarousel(mount, bar, items, interval) {
    mount.textContent = '';
    var cards = items.map(function (it) { return buildCard(it); });
    var broken = [];
    cards.forEach(function (c, i) {
      if (i > 0) { c.classList.add('hidden'); }
      mount.appendChild(c);
    });

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

    function aliveCount() {
      var n = 0;
      for (var i = 0; i < cards.length; i++) { if (!broken[i]) { n++; } }
      return n;
    }
    function nextAlive(from) {
      for (var step = 1; step <= cards.length; step++) {
        var i = (from + step) % cards.length;
        if (!broken[i]) { return i; }
      }
      return -1;
    }
    function show(i) {
      i = ((i % cards.length) + cards.length) % cards.length;
      if (broken[i]) { i = nextAlive(i - 1); if (i < 0) { return; } }
      cards[current].classList.add('hidden');
      if (dotEls[current]) { dotEls[current].classList.remove('active'); }
      current = i;
      cards[current].classList.remove('hidden');
      if (dotEls[current]) { dotEls[current].classList.add('active'); }
    }
    function tick() { var n = nextAlive(current); if (n >= 0) { show(n); } }
    function start() { if (aliveCount() > 1) { timer = window.setInterval(tick, interval); } }
    function stop() { if (timer) { window.clearInterval(timer); timer = null; } }
    function restart() { stop(); start(); }

    // A broken image creative must never break the whole slot: hide that card
    // (and its dot) and move on; if nothing renderable remains, drop the slot.
    cards.forEach(function (c, i) {
      var img = c.querySelector('img');
      if (!img) { return; }
      img.addEventListener('error', function () {
        broken[i] = true;
        c.classList.add('hidden');
        if (dotEls[i]) { dotEls[i].style.display = 'none'; }
        if (aliveCount() === 0) { stop(); bar.remove(); return; }
        if (current === i) { var n = nextAlive(i); if (n >= 0) { show(n); } }
        if (aliveCount() < 2) { stop(); }
      });
    });

    if (cards.length < 2) { dots.remove(); }

    mount.addEventListener('mouseenter', stop);
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
