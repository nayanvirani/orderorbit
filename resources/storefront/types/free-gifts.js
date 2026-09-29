/* OrderOrbit · free gift */
OrderOrbit.define('free-gifts', function (exp, ctx, h) {
  var c = exp.content;
  var total = (ctx.cartTotal || 0) / 100;
  var s = h.thresholds(c.thresholds, total);
  var msg = s.next
    ? h.fill(c.progress_message, { remaining: h.money(Number(s.next.amount) - total, ctx.currency), reward: s.next.reward })
    : h.fill(c.unlocked_message, { reward: s.last ? s.last.reward : '' });
  var gifts = (c.gift_products || []).slice(0, 2).map(function (p) {
    return '<div class="oo-gift' + (s.reached.length ? ' oo-unlocked' : '') + '">' + h.productImage(p) + '<span>' + h.esc(p.title) + '</span>' +
      (s.reached.length ? '<button type="button" class="oo-btn oo-btn-sm" data-oo-click="gift_claimed">' + (c.claim_mode === 'auto' ? 'Added' : 'Claim') + '</button>' : '<em>Locked</em>') + '</div>';
  }).join('');
  return '<div class="oo-body"><p class="oo-message" role="status">' + h.icon(h.GIFT) + ' ' + msg + '</p>' +
    '<div class="oo-progress"><i style="width:' + s.pct + '%"></i></div>' + h.ladder(s, ctx.currency) + (gifts ? '<div class="oo-gifts">' + gifts + '</div>' : '') + '</div>';
});
