/* OrderOrbit · quantity breaks (preview; discounts ship with CRO Core) */
OrderOrbit.define('quantity-breaks', function (exp, ctx, h) {
  var c = exp.content;
  var base = ctx.productPrice != null ? ctx.productPrice / 100 : 29;
  var tiers = (c.tiers || []).map(function (t, i) {
    var qty = Number(t.quantity || 1);
    var unit = base * (1 - Number(t.discount || 0) / 100);
    var on = i + 1 === Number(c.default_tier);
    return '<label class="oo-tier' + (on ? ' oo-selected' : '') + '">' + (t.badge ? '<span class="oo-flag">' + h.esc(t.badge) + '</span>' : '') +
      '<input type="radio" name="oo-tier-' + h.esc(exp.id) + '" ' + (on ? 'checked' : '') + '><span class="oo-tier-name">Buy ' + qty + '</span>' +
      '<span class="oo-tier-price">' + h.esc(h.money(c.price_display === 'total' ? unit * qty : unit, ctx.currency)) + (c.price_display === 'total' ? '' : ' <small>each</small>') +
      (Number(t.discount) ? '<em>Save ' + h.esc(t.discount) + '%</em>' : '') + '</span></label>';
  }).join('');
  return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p><div class="oo-tiers">' + tiers + '</div></div>';
});
