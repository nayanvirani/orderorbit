/* OrderOrbit · shipping bar */
OrderOrbit.define('shipping-bar', function (exp, ctx, h) {
  var c = exp.content;
  var total = (ctx.cartTotal || 0) / 100;
  var s = h.thresholds(c.thresholds, total);
  var msg;
  if (!total && c.empty_message && s.sorted.length) {
    msg = h.fill(c.empty_message, { threshold: h.money(s.sorted[0].amount, ctx.currency), reward: s.sorted[0].reward });
  } else if (s.next) {
    msg = h.fill(c.progress_message, { remaining: h.money(Number(s.next.amount) - total, ctx.currency), reward: s.next.reward, threshold: h.money(s.next.amount, ctx.currency) });
  } else {
    msg = h.fill(c.unlocked_message, { reward: s.last ? s.last.reward : '' });
  }
  return '<div class="oo-body"><p class="oo-message" role="status">' + msg + '</p>' +
    '<div class="oo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(s.pct) + '"><i style="width:' + s.pct + '%"></i></div>' +
    h.ladder(s, ctx.currency) + '</div>';
});
