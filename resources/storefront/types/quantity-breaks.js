/* OrderOrbit · quantity breaks: pick a tier, add that many of the product on the page. */
(function () {
  var S = OrderOrbit.shop;

  OrderOrbit.define('quantity-breaks', function (exp, ctx, h) {
    if (!ctx.product && !ctx.preview) return null;
    var c = exp.content;
    var base = ctx.productPrice != null ? ctx.productPrice / 100 : 29;
    var tiers = (c.tiers || []).map(function (t, i) {
      var qty = Number(t.quantity || 1);
      var unit = base * (1 - Number(t.discount || 0) / 100);
      var on = i + 1 === Number(c.default_tier);
      return '<label class="oo-tier' + (on ? ' oo-selected' : '') + '">' + (t.badge ? '<span class="oo-flag">' + h.esc(t.badge) + '</span>' : '') +
        '<input type="radio" name="oo-tier-' + h.esc(exp.id) + '" value="' + qty + '"' + (on ? ' checked' : '') + '><span class="oo-tier-name">Buy ' + qty + '</span>' +
        '<span class="oo-tier-price">' + (Number(t.discount) ? '<s>' + h.esc(h.money(c.price_display === 'total' ? base * qty : base, ctx.currency)) + '</s> ' : '') +
        h.esc(h.money(c.price_display === 'total' ? unit * qty : unit, ctx.currency)) + (c.price_display === 'total' ? '' : ' <small>each</small>') +
        (Number(t.discount) ? '<em>Save ' + h.esc(t.discount) + '%</em>' : '') + '</span></label>';
    }).join('');
    return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p><div class="oo-tiers">' + tiers + '</div>' +
      '<button type="button" class="oo-btn" data-oo-click="tier_add" data-oo-add>' + h.esc(c.cta_text || 'Add to cart') + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    setup: function (root, exp, ctx) {
      if (!root) return;
      root.addEventListener('change', function () {
        root.querySelectorAll('.oo-tier').forEach(function (el) { el.classList.toggle('oo-selected', el.querySelector('input').checked); });
      });
      var btn = root.querySelector('[data-oo-add]');
      btn.addEventListener('click', function () {
        var picked = root.querySelector('input[type="radio"]:checked');
        S.add(exp, ctx, [{ id: S.pageVariant(ctx), quantity: Number(picked ? picked.value : 1) }], btn, root);
      });
    }
  });
})();
