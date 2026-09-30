/* Cart-value thresholds and their milestone ladder (shipping bar, free gift). */
(function () {
  var h = OrderOrbit.h;
  if (h.thresholds) return;
  // Cart-value thresholds (shipping bar, free gift) and their milestone ladder.
  h.thresholds = function(list, total) {
    var sorted = (list || []).filter(function (t) { return t && t.amount != null; }).sort(function (a, b) { return a.amount - b.amount; });
    var next = null;
    var reached = [];
    sorted.forEach(function (t) { if (total >= Number(t.amount)) reached.push(t); else if (!next) next = t; });
    var top = sorted.length ? Number(sorted[sorted.length - 1].amount) : 0;
    return { sorted: sorted, next: next, reached: reached, last: reached[reached.length - 1] || null, pct: top ? Math.min(100, (total / top) * 100) : 0 };
  };

  h.ladder = function(state, currency) {
    if (state.sorted.length < 2) return '';
    var top = Number(state.sorted[state.sorted.length - 1].amount);
    return '<div class="oo-ladder">' + state.sorted.map(function (t) {
      var done = state.reached.indexOf(t) !== -1;
      return '<span class="oo-milestone' + (done ? ' oo-done' : '') + '" style="left:' + (top ? (Number(t.amount) / top) * 100 : 0) + '%" title="' + h.esc(t.reward) + '">' + h.icon(h.GIFT) + '<small>' + h.esc(h.money(t.amount, currency)) + '</small></span>';
    }).join('') + '</div>';
  };

})();
