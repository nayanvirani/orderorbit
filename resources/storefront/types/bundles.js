/* OrderOrbit · bundle (preview; cart handling ships with CRO Core) */
OrderOrbit.define('bundles', function (exp, ctx, h) {
  var c = exp.content;
  var products = c.products || [];
  var min = Number(c.min_items || 1);
  var selected = Math.min(min, products.length);
  var remaining = Math.max(0, min - selected);
  var tiles = products.map(function (p, i) {
    return '<label class="oo-tile"><input type="checkbox" ' + (i < min ? 'checked' : '') + ' data-oo-select>' + h.productImage(p) + '<span class="oo-name">' + h.esc(p.title) + '</span>' +
      (p.price != null ? '<span class="oo-price">' + (c.show_compare_at && p.compare_at ? '<s>' + h.esc(h.money(p.compare_at, ctx.currency)) + '</s> ' : '') + h.esc(h.money(p.price, ctx.currency)) + '</span>' : '') + '</label>';
  }).join('') || '<p class="oo-empty">Choose products for this bundle.</p>';
  return '<div class="oo-body">' + (c.badge ? '<span class="oo-badge">' + h.esc(c.badge) + '</span>' : '') +
    '<p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') +
    '<div class="oo-tiles">' + tiles + '</div>' +
    '<p class="oo-message">' + (remaining ? h.fill(c.progress_message, { remaining: remaining }) : 'Saving unlocked') + '</p>' +
    '<div class="oo-progress"><i style="width:' + (min ? Math.min(100, (selected / min) * 100) : 100) + '%"></i></div>' +
    '<button type="button" class="oo-btn" data-oo-click="bundle_add"' + (remaining ? ' disabled' : '') + '>' + h.esc(c.cta_text) + '</button></div>';
});
