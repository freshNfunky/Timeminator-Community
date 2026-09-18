// Timeminator - statistics page. Uses TmCharts (charts.js), no external deps.
(function () {
  'use strict';
  var form = document.getElementById('statsForm');
  if (!form || !window.TmCharts) return;
  var endpoint = form.getAttribute('data-endpoint');

  function render(data){
    document.getElementById('totalHours').textContent = data.total_hours.toFixed(2) + ' h';
    document.getElementById('scopeName').textContent = data.scope ? data.scope.name : 'Gesamt';
    document.getElementById('rangeLbl').textContent = data.from + ' → ' + data.to;
    var grpLbl = { project:'nach Projekt', client:'nach Kunde', track:'nach Gleis' }[data.group] || '';
    document.getElementById('distGroupLbl').textContent = grpLbl;

    TmCharts.doughnut(
      document.getElementById('chartDist'),
      data.distribution.labels, data.distribution.hours,
      document.getElementById('distLegend')
    );
    TmCharts.barsV(
      document.getElementById('chartSeries'),
      data.series.labels, data.series.hours
    );
    TmCharts.barsH(
      document.getElementById('chartTracks'),
      data.tracks.labels, data.tracks.hours
    );
  }

  function load(){
    var params = new URLSearchParams(new FormData(form)).toString();
    fetch(endpoint + '&' + params, { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(render)
      .catch(function(){ document.getElementById('totalHours').textContent = 'Fehler'; });
  }

  form.addEventListener('submit', function(e){ e.preventDefault(); load(); });
  window.addEventListener('resize', function(){
    clearTimeout(window.__tmr); window.__tmr = setTimeout(load, 250);
  });
  load();
})();
