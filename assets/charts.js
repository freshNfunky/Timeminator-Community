// Timeminator - tiny dependency-free canvas charts. No CDN, works offline.
window.TmCharts = (function () {
  'use strict';

  var PALETTE = ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4',
                 '#ec4899','#84cc16','#f97316','#6366f1','#14b8a6','#a855f7'];
  function color(i){ return PALETTE[i % PALETTE.length]; }

  // Theme-aware colors read from CSS custom properties, with hard-coded
  // fallbacks so a page without app.css still renders.
  function cssVar(name, fallback){
    try {
      var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      return v || fallback;
    } catch (_) { return fallback; }
  }
  function theme(){
    return {
      panel: cssVar('--panel', '#ffffff'),
      ink:   cssVar('--ink',   '#1e293b'),
      muted: cssVar('--muted', '#64748b'),
      line:  cssVar('--line',  '#e2e8f0'),
      brand: cssVar('--brand', '#2563eb')
    };
  }

  function setup(canvas){
    // Size from the (stable) parent container, never from the canvas itself:
    // canvas.width/height are BITMAP attributes that also drive the element's
    // own rendered box size unless canvas.style.width/height pin it down. Read
    // canvas.clientWidth back here instead and each re-render (filter change,
    // resize, orientation change) would multiply it by dpr again, doubling the
    // on-screen size every time - which is exactly the runaway-growth bug this
    // replaces (canvas balloons 300 -> 600 -> 1200px... on repeated renders).
    var dpr = window.devicePixelRatio || 1;
    var box = canvas.parentNode;
    var w = (box && box.clientWidth) || 400;
    var h = (box && box.clientHeight) || 300;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    ctx.font = '11px -apple-system,Segoe UI,Roboto,sans-serif';
    ctx.textBaseline = 'middle';
    return { ctx: ctx, w: w, h: h };
  }

  function niceMax(v){
    if (v <= 0) return 1;
    var pow = Math.pow(10, Math.floor(Math.log10(v)));
    var n = v / pow;
    var step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
    return step * pow;
  }

  function empty(ctx, w, h){
    ctx.fillStyle = theme().muted;
    ctx.textAlign = 'center';
    ctx.fillText('Keine Daten', w / 2, h / 2);
  }

  // Doughnut + returns legend HTML into legendEl.
  function doughnut(canvas, labels, values, legendEl){
    var s = setup(canvas), ctx = s.ctx;
    var total = values.reduce(function(a,b){ return a + b; }, 0);
    if (total <= 0){ empty(ctx, s.w, s.h); if(legendEl) legendEl.innerHTML=''; return; }
    var cx = s.h / 2 + 8, cy = s.h / 2, rO = Math.min(cx, cy) - 8, rI = rO * 0.58;
    var a0 = -Math.PI / 2;
    for (var i = 0; i < values.length; i++){
      var a1 = a0 + (values[i] / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, rO, a0, a1);
      ctx.closePath();
      ctx.fillStyle = color(i);
      ctx.fill();
      a0 = a1;
    }
    var t = theme();
    ctx.beginPath(); ctx.fillStyle = t.panel; ctx.arc(cx, cy, rI, 0, Math.PI*2); ctx.fill();
    ctx.fillStyle = t.ink; ctx.textAlign = 'center';
    ctx.font = 'bold 15px -apple-system,Segoe UI,sans-serif';
    ctx.fillText(total.toFixed(1) + ' h', cx, cy);
    if (legendEl){
      var rows = labels.map(function(l, i){
        var pct = total ? Math.round(values[i] / total * 100) : 0;
        return '<li><span class="lg-dot" style="background:' + color(i) + '"></span>' +
               '<span class="lg-lbl">' + escapeHtml(l) + '</span>' +
               '<span class="lg-val">' + values[i].toFixed(2) + ' h · ' + pct + '%</span></li>';
      }).join('');
      legendEl.innerHTML = '<ul class="chart-legend">' + rows + '</ul>';
    }
  }

  function barsV(canvas, labels, values){
    var s = setup(canvas), ctx = s.ctx;
    if (!values.length || Math.max.apply(null, values) <= 0){ empty(ctx, s.w, s.h); return; }
    var padL = 34, padB = 46, padT = 10, padR = 8;
    var plotW = s.w - padL - padR, plotH = s.h - padT - padB;
    var max = niceMax(Math.max.apply(null, values));
    var t = theme();
    // gridlines + y labels
    ctx.textAlign = 'right'; ctx.strokeStyle = t.line; ctx.fillStyle = t.muted;
    for (var g = 0; g <= 4; g++){
      var yv = max * g / 4, y = padT + plotH - (yv / max) * plotH;
      ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + plotW, y); ctx.stroke();
      ctx.fillText(yv.toFixed(yv < 10 ? 1 : 0), padL - 5, y);
    }
    var n = values.length, bw = plotW / n, bar = Math.min(bw * 0.66, 46);
    var showEvery = Math.ceil(n / Math.max(1, Math.floor(plotW / 46)));
    for (var i = 0; i < n; i++){
      var x = padL + bw * i + (bw - bar) / 2;
      var bh = (values[i] / max) * plotH;
      ctx.fillStyle = t.brand;
      roundRect(ctx, x, padT + plotH - bh, bar, bh, 3); ctx.fill();
      if (i % showEvery === 0){
        ctx.save(); ctx.translate(x + bar / 2, padT + plotH + 6);
        ctx.rotate(-Math.PI / 4); ctx.fillStyle = t.muted; ctx.textAlign = 'right';
        ctx.fillText(shorten(labels[i], 12), 0, 6); ctx.restore();
      }
    }
  }

  function barsH(canvas, labels, values){
    var s = setup(canvas), ctx = s.ctx;
    if (!values.length || Math.max.apply(null, values) <= 0){ empty(ctx, s.w, s.h); return; }
    var padL = 120, padR = 44, padT = 6, padB = 6;
    var plotW = s.w - padL - padR, plotH = s.h - padT - padB;
    var max = Math.max.apply(null, values);
    var t = theme();
    var n = values.length, rowH = plotH / n, bar = Math.min(rowH * 0.62, 30);
    for (var i = 0; i < n; i++){
      var y = padT + rowH * i + (rowH - bar) / 2;
      var bwv = (values[i] / max) * plotW;
      ctx.fillStyle = color(i);
      roundRect(ctx, padL, y, Math.max(2, bwv), bar, 3); ctx.fill();
      ctx.fillStyle = t.ink; ctx.textAlign = 'right';
      ctx.fillText(shorten(labels[i], 18), padL - 8, y + bar / 2);
      ctx.fillStyle = t.muted; ctx.textAlign = 'left';
      ctx.fillText(values[i].toFixed(2) + ' h', padL + bwv + 6, y + bar / 2);
    }
  }

  // Grouped vertical bars: series = [{label,data,color}], labels shared.
  function groupedBars(canvas, labels, series){
    var s = setup(canvas), ctx = s.ctx;
    var all = [];
    series.forEach(function(se){ all = all.concat(se.data); });
    if (!all.length || Math.max.apply(null, all) <= 0){ empty(ctx, s.w, s.h); return; }
    var padL = 38, padB = 60, padT = 24, padR = 8;
    var plotW = s.w - padL - padR, plotH = s.h - padT - padB;
    var max = niceMax(Math.max.apply(null, all));
    var t = theme();
    ctx.textAlign = 'right'; ctx.strokeStyle = t.line; ctx.fillStyle = t.muted;
    for (var g = 0; g <= 4; g++){
      var yv = max * g / 4, y = padT + plotH - (yv / max) * plotH;
      ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + plotW, y); ctx.stroke();
      ctx.fillText(yv.toFixed(yv < 10 ? 1 : 0), padL - 5, y);
    }
    var n = labels.length, groupW = plotW / Math.max(1, n), k = series.length;
    var barW = Math.min((groupW * 0.7) / k, 26);
    for (var i = 0; i < n; i++){
      var gx = padL + groupW * i + (groupW - barW * k) / 2;
      for (var j = 0; j < k; j++){
        var val = series[j].data[i] || 0;
        var bh = (val / max) * plotH;
        ctx.fillStyle = series[j].color;
        roundRect(ctx, gx + j * barW, padT + plotH - bh, barW - 2, bh, 2); ctx.fill();
      }
      ctx.save(); ctx.translate(padL + groupW * i + groupW / 2, padT + plotH + 6);
      ctx.rotate(-Math.PI / 5); ctx.fillStyle = t.muted; ctx.textAlign = 'right';
      ctx.fillText(shorten(labels[i], 16), 0, 6); ctx.restore();
    }
    // legend
    var lx = padL, ly = 12;
    series.forEach(function(se){
      ctx.fillStyle = se.color; ctx.fillRect(lx, ly - 6, 10, 10);
      ctx.fillStyle = t.ink; ctx.textAlign = 'left'; ctx.fillText(se.label, lx + 14, ly);
      lx += 22 + ctx.measureText(se.label).width + 14;
    });
  }

  function roundRect(ctx, x, y, w, h, r){
    r = Math.min(r, w / 2, h / 2); if (r < 0) r = 0;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function shorten(s, n){ s = String(s); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
  function escapeHtml(s){ return String(s).replace(/[&<>"]/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  return { doughnut: doughnut, barsV: barsV, barsH: barsH, groupedBars: groupedBars };
})();
