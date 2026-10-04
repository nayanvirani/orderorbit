/* Cart-value thresholds and their milestone ladder (shipping bar, free gift). */
(function () {
  var h = OrderOrbit.h;
  if (h.thresholds) return;
  // Gift icon for the gift and threshold widgets (kept out of the core to keep it small).
  h.GIFT = h.GIFT || '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>';
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
