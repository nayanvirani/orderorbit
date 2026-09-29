/* OrderOrbit · product and cart upsells (preview; cart handling ships with the AOV release) */
(function () {
  function cards(h, products, c, ctx, limit) {
    return (products || []).slice(0, limit || 4).map(function (p) {
      var price = p.price != null ? Number(p.price) : null;
      var offer = c.discount_percent && price != null ? price * (1 - c.discount_percent / 100) : price;
      return '<div class="oo-card">' + h.productImage(p) + '<div class="oo-card-meta"><span class="oo-name">' + h.esc(p.title) + '</span>' +
        (price != null ? '<span class="oo-price">' + (c.discount_percent ? '<s>' + h.esc(h.money(price, ctx.currency)) + '</s> ' : '') + h.esc(h.money(offer, ctx.currency)) + '</span>' : '') +
        '</div><button type="button" class="oo-btn oo-btn-sm" data-oo-click="upsell_accepted">' + h.esc(c.cta_text || 'Add') + '</button></div>';
    }).join('') || '<p class="oo-empty">Choose products to recommend.</p>';
  }

  OrderOrbit.define('product-upsells', function (exp, ctx, h) {
    var c = exp.content;
    return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.offer_message ? '<p class="oo-sub">' + h.esc(c.offer_message) + '</p>' : '') +
      '<div class="oo-cards">' + cards(h, c.products, c, ctx, 4) + '</div></div>';
  });

  OrderOrbit.define('cart-upsells', function (exp, ctx, h) {
    var c = exp.content;
    return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.incentive ? '<p class="oo-sub">' + h.esc(c.incentive) + '</p>' : '') +
      '<div class="oo-cards">' + cards(h, c.products, c, ctx, c.max_shown) + '</div></div>';
  });
})();
