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
    var mount = document.getElementById('bannerCarousel');
    var bar = document.getElementById('bannerbar');
    if (!mount || !bar) {
      return;
    }

    // Collapse toggle, remembered per browser.
    var toggle = document.getElementById('bannerbarToggle');
    var STORE_KEY = 'tm_banner_collapsed';
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
    dots.className = 'banner-dots';
    var dotEls = cards.map(function (_, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'banner-dot' + (i === 0 ? ' active' : '');
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
    // One flexible card layout for every creative: top 2/3 is the hero
    // image (object-fit:cover, so a 9:16 or 3:4 asset fills the slot
    // without letterboxing), bottom 1/3 is title + body + CTA. The split
    // is CSS so the ~200x1000 column reflows gracefully when the
    // viewport changes. If the item has no image, the text block
    // expands to fill the whole card.
    var card = document.createElement('a');
    card.className = 'banner-card';
    if (it.href) {
      card.href = it.href;
      card.target = '_blank';
      card.rel = 'noopener noreferrer';
    }

    if (it.image) {
      var hero = document.createElement('div');
      hero.className = 'banner-hero';
      var img = document.createElement('img');
      img.src = it.image;
      img.alt = it.title || it.cta || 'Anzeige';
      img.loading = 'lazy';
      hero.appendChild(img);
      // Gradient scrim that fades from the hero into the body panel,
      // so the title doesn't have to live on a hard image/text seam.
      var scrim = document.createElement('div');
      scrim.className = 'banner-scrim';
      hero.appendChild(scrim);
      card.appendChild(hero);
    } else {
      card.classList.add('is-textonly');
    }

    var body = document.createElement('div');
    body.className = 'banner-body';
    if (it.title) {
      var t = document.createElement('span');
      t.className = 'banner-title';
      t.textContent = it.title;
      body.appendChild(t);
    }
    if (it.text) {
      var p = document.createElement('span');
      p.className = 'banner-text';
      p.textContent = it.text;
      body.appendChild(p);
    }
    if (it.cta && it.href) {
      var cta = document.createElement('span');
      cta.className = 'banner-cta';
      cta.textContent = it.cta;
      body.appendChild(cta);
    }
    // No body copy at all? Treat the card as a full-image creative
    // (baked SVG / artwork): let the hero fill the whole slot and
    // skip the empty text region.
    if (body.children.length === 0) {
      card.classList.add('is-full-image');
    } else {
      card.appendChild(body);
    }
    return card;
  }
})();
