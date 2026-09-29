/* OrderOrbit · sticky add to cart (preview; visibility runtime ships with the AOV release) */
OrderOrbit.define('sticky-atc', function (exp, ctx, h) {
  var c = exp.content;
  return '<div class="oo-body oo-sticky-body">' + (c.show_image ? '<span class="oo-img oo-img-empty" aria-hidden="true"></span>' : '') +
    '<div class="oo-card-meta"><span class="oo-name">' + h.esc(ctx.productTitle || 'Product name') + '</span>' + (c.show_price ? '<span class="oo-price">' + h.esc(h.money((ctx.productPrice || 2900) / 100, ctx.currency)) + '</span>' : '') + '</div>' +
    '<button type="button" class="oo-btn" data-oo-click="sticky_atc_clicked">' + h.esc(c.button_text) + '</button></div>';
});
