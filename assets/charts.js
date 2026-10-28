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
    // Label comes from <body data-i18n-chart-empty="…"> so the UI language
    // follows I18n::current(); defensive fallback keeps charts readable even
    // if the attribute is missing (e.g. a page that forgot to use the layout).
    var lbl = (document.body && document.body.getAttribute('data-i18n-chart-empty')) || 'Keine Daten';
    ctx.fillText(lbl, w / 2, h / 2);
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

  /**
   * Stacked area over time (v1.0 F1 Mitarbeiter-Cockpit). spec:
   * { xLabels, weekStarts?, series: [{label, color, data}], target: [h|null],
   *   unit, targetLabel? }. Each series stacks on the previous; dashed line
   *   draws the week target; hover tooltip over the chart-box.
   */
  function stackedArea(canvas, spec, legendEl){
    var s = setup(canvas), ctx = s.ctx, th = theme();
    var xs = spec.xLabels || [], series = spec.series || [], target = spec.target || [];
    var n = xs.length, unit = spec.unit || 'h';
    if (!n || !series.length){ empty(ctx, s.w, s.h); if (legendEl) legendEl.innerHTML = ''; return; }
    var totals = [];
    for (var i = 0; i < n; i++){
      var sum = 0;
      series.forEach(function(se){ sum += Number(se.data[i] || 0); });
      totals.push(sum);
    }
    var maxV = Math.max.apply(null, totals.concat(target.filter(function(v){ return v !== null && v !== undefined; })));
    var yMax = niceMax(maxV * 1.05);
    ctx.font = '11px -apple-system,Segoe UI,Roboto,sans-serif';
    var padL = Math.ceil(ctx.measureText(yMax + ' ' + unit).width) + 14, padR = 16, padT = 12, padB = 40;
    var plotW = s.w - padL - padR, plotH = s.h - padT - padB;
    var xAt = function(i){ return padL + (n <= 1 ? plotW / 2 : (plotW * i) / (n - 1)); };
    var yAt = function(v){ return padT + plotH - (v / yMax) * plotH; };

    ctx.strokeStyle = th.line; ctx.fillStyle = th.muted; ctx.lineWidth = 1; ctx.textAlign = 'right';
    for (var g = 0; g <= 4; g++){
      var gv = (yMax * g) / 4, gy = Math.round(yAt(gv)) + 0.5;
      ctx.beginPath(); ctx.moveTo(padL, gy); ctx.lineTo(padL + plotW, gy); ctx.stroke();
      ctx.fillText((Math.round(gv * 10) / 10) + ' ' + unit, padL - 6, gy);
    }
    ctx.textAlign = 'center';
    var step = Math.max(1, Math.ceil(n / Math.max(1, Math.floor(plotW / 46))));
    for (var xi = 0; xi < n; xi += step){ ctx.fillText(xs[xi], xAt(xi), padT + plotH + 16); }

    var lower = new Array(n).fill(0);
    series.forEach(function(se){
      var upper = lower.map(function(l, i){ return l + Number(se.data[i] || 0); });
      ctx.beginPath();
      for (var i = 0; i < n; i++){ i ? ctx.lineTo(xAt(i), yAt(upper[i])) : ctx.moveTo(xAt(i), yAt(upper[i])); }
      for (var j = n - 1; j >= 0; j--){ ctx.lineTo(xAt(j), yAt(lower[j])); }
      ctx.closePath();
      ctx.globalAlpha = 0.85; ctx.fillStyle = se.color; ctx.fill(); ctx.globalAlpha = 1;
      ctx.strokeStyle = se.color; ctx.lineWidth = 1;
      ctx.beginPath();
      for (var k = 0; k < n; k++){ k ? ctx.lineTo(xAt(k), yAt(upper[k])) : ctx.moveTo(xAt(k), yAt(upper[k])); }
      ctx.stroke();
      lower = upper;
    });

    var hasTarget = target.some(function(v){ return v !== null && v !== undefined; });
    if (hasTarget){
      ctx.setLineDash([6, 4]); ctx.strokeStyle = th.ink; ctx.lineWidth = 1.5; ctx.beginPath();
      var started = false;
      target.forEach(function(v, i){
        if (v === null || v === undefined){ started = false; return; }
        started ? ctx.lineTo(xAt(i), yAt(v)) : ctx.moveTo(xAt(i), yAt(v)); started = true;
      });
      ctx.stroke(); ctx.setLineDash([]);
    }

    if (legendEl){
      var rows = series.slice().reverse().map(function(se){
        return '<li><span class="lg-sw" style="background:' + se.color + '"></span><span class="lg-lbl">' + escapeHtml(se.label) + '</span></li>';
      });
      if (hasTarget) rows.push('<li><span class="lg-dash"></span><span class="lg-lbl">' + escapeHtml(spec.targetLabel || 'Week target') + '</span></li>');
      legendEl.innerHTML = '<ul class="chart-legend">' + rows.join('') + '</ul>';
    }

    var box = canvas.parentNode, tip = box.querySelector('.chart-tip');
    if (!tip){ tip = document.createElement('div'); tip.className = 'chart-tip'; tip.hidden = true; box.appendChild(tip); }
    canvas.onmousemove = function(ev){
      var r = canvas.getBoundingClientRect(), mx = ev.clientX - r.left;
      var i = Math.max(0, Math.min(n - 1, Math.round(((mx - padL) / plotW) * (n - 1))));
      var lines = series.slice().reverse().filter(function(se){ return Number(se.data[i] || 0) > 0; }).map(function(se){
        return '<span class="lg-sw" style="background:' + se.color + '"></span>' + escapeHtml(se.label) + ': <strong>' + (Math.round(Number(se.data[i]) * 10) / 10) + ' ' + unit + '</strong>';
      });
      var t = target[i];
      var totalLabel = spec.totalLabel || 'total';
      var ofLabel = spec.ofLabel || 'of';
      var fromLabel = spec.weekFromLabel || 'from';
      tip.innerHTML = '<div><strong>' + escapeHtml(xs[i]) + '</strong>'
        + (spec.weekStarts ? ' <span class="muted">' + escapeHtml(fromLabel) + ' ' + escapeHtml(spec.weekStarts[i]) + '</span>' : '') + '</div>'
        + lines.join('<br>') + '<div class="muted">' + escapeHtml(totalLabel) + ' ' + (Math.round(totals[i] * 10) / 10) + ' ' + unit + (t !== null && t !== undefined ? ' ' + escapeHtml(ofLabel) + ' ' + t + ' ' + unit : '') + '</div>';
      tip.hidden = false;
      var left = xAt(i) + 12;
      if (left + 190 > s.w) left = xAt(i) - 12 - 190;
      tip.style.left = Math.max(4, left) + 'px'; tip.style.top = '8px';
    };
    canvas.onmouseleave = function(){ tip.hidden = true; };
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

  /**
   * Lean multi-line chart (v1.0 F2 Projekt-Cockpit Burn-Up). spec:
   * { xLabels, series: [{label, color, data, dashed?}], unit, targetLabel? }.
   * No dual-axis, no money formatting — one y-axis for all series.
   */
  function lines(canvas, spec, legendEl){
    var s = setup(canvas), ctx = s.ctx, th = theme();
    var xs = spec.xLabels || [], series = spec.series || [], unit = spec.unit || '';
    var n = xs.length;
    var allVals = [];
    series.forEach(function(se){ (se.data || []).forEach(function(v){ if (v !== null && v !== undefined) allVals.push(v); }); });
    if (!n || !series.length || !allVals.length){ empty(ctx, s.w, s.h); if (legendEl) legendEl.innerHTML = ''; return; }
    var yMax = niceMax(Math.max.apply(null, allVals) * 1.05);
    ctx.font = '11px -apple-system,Segoe UI,Roboto,sans-serif';
    var padL = Math.ceil(ctx.measureText(yMax + ' ' + unit).width) + 14, padR = 16, padT = 12, padB = 40;
    var plotW = s.w - padL - padR, plotH = s.h - padT - padB;
    var xAt = function(i){ return padL + (n <= 1 ? plotW / 2 : (plotW * i) / (n - 1)); };
    var yAt = function(v){ return padT + plotH - (v / yMax) * plotH; };

    ctx.strokeStyle = th.line; ctx.fillStyle = th.muted; ctx.lineWidth = 1; ctx.textAlign = 'right';
    for (var g = 0; g <= 4; g++){
      var gv = (yMax * g) / 4, gy = Math.round(yAt(gv)) + 0.5;
      ctx.beginPath(); ctx.moveTo(padL, gy); ctx.lineTo(padL + plotW, gy); ctx.stroke();
      ctx.fillText((Math.round(gv * 10) / 10) + (unit ? ' ' + unit : ''), padL - 6, gy);
    }
    ctx.textAlign = 'center';
    var step = Math.max(1, Math.ceil(n / Math.max(1, Math.floor(plotW / 60))));
    for (var xi = 0; xi < n; xi += step){ ctx.fillText(xs[xi], xAt(xi), padT + plotH + 16); }

    series.forEach(function(se){
      ctx.strokeStyle = se.color || '#2563eb'; ctx.lineWidth = 1.5;
      if (se.dashed) { ctx.setLineDash([6, 4]); } else { ctx.setLineDash([]); }
      ctx.beginPath();
      var started = false;
      (se.data || []).forEach(function(v, i){
        if (v === null || v === undefined) { started = false; return; }
        started ? ctx.lineTo(xAt(i), yAt(v)) : ctx.moveTo(xAt(i), yAt(v));
        started = true;
      });
      ctx.stroke();
    });
    ctx.setLineDash([]);

    if (legendEl){
      legendEl.innerHTML = '<ul class="chart-legend">' + series.map(function(se){
        return '<li>' + (se.dashed
            ? '<span class="lg-dash" style="border-top-color:' + (se.color || '#2563eb') + '"></span>'
            : '<span class="lg-sw" style="background:' + (se.color || '#2563eb') + '"></span>')
          + '<span class="lg-lbl">' + escapeHtml(se.label || '') + '</span></li>';
      }).join('') + '</ul>';
    }
  }

  return { doughnut: doughnut, barsV: barsV, barsH: barsH, groupedBars: groupedBars, stackedArea: stackedArea, lines: lines };
})();
