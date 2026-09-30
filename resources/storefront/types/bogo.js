/* OrderOrbit · BOGO / buy X get Y. The discount function makes the "get" items free or discounted at checkout. */
(function () {
  var S = OrderOrbit.shop;

  // The product on the page when it's part of the offer, else the first "buy" product.
  function primary(exp, ctx) {
    var list = exp.content.buy_products || [];
    var here = list.filter(function (p) { return ctx.product && OrderOrbit.h.numericId(p.id) === String(ctx.product); })[0];
    return { product: here || list[0], onPage: !!here };
  }

  OrderOrbit.define('bogo', function (exp, ctx, h) {
    var c = exp.content;
    var main = primary(exp, ctx);
    if (!main.product) return ctx.preview ? '<div class="oo-body"><p class="oo-empty">Choose the products shoppers buy.</p></div>' : null;
    var get = (c.get_products || [])[0];
    var pct = Number(c.get_discount || 100);
    var reward = (pct >= 100 ? 'free' : pct + '% off');
    var row = '<div class="oo-bogo">' +
      '<div class="oo-tile oo-fixed">' + h.productImage(main.product) + '<span class="oo-name">Buy ' + h.esc(c.buy_quantity) + ' × ' + h.esc(main.product.title) + '</span>' + (main.onPage ? '' : S.variantSelect(main.product, 0, ctx)) + '</div>' +
      '<span class="oo-plus" aria-hidden="true">+</span>' +
      '<div class="oo-tile oo-fixed">' + h.productImage(get || main.product) + '<span class="oo-name">Get ' + h.esc(c.get_quantity) + ' × ' + h.esc((get || main.product).title) + '</span><em class="oo-reward">' + h.esc(reward) + '</em>' + (get ? S.variantSelect(get, 1, ctx) : '') + '</div></div>';
    return '<div class="oo-body">' + (c.badge ? '<span class="oo-badge">' + h.esc(c.badge) + '</span>' : '') +
      '<p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') + row +
      '<button type="button" class="oo-btn" data-oo-click="bogo_add" data-oo-add>' + h.esc(c.cta_text) + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    prepare: function (exp) { return S.hydrate(exp, ['buy_products', 'get_products']); },
    setup: function (root, exp, ctx) {
      if (!root) return;
      var btn = root.querySelector('[data-oo-add]');
      btn.addEventListener('click', function () {
        var c = exp.content;
        var main = primary(exp, ctx);
        var get = (c.get_products || [])[0];
        var buyVariant = main.onPage ? S.pageVariant(ctx) : S.chosenVariant(root, main.product, 0);
        var bq = Number(c.buy_quantity || 1);
        var gq = Number(c.get_quantity || 1);
        S.add(exp, ctx, get
          ? [{ id: buyVariant, quantity: bq }, { id: S.chosenVariant(root, get, 1), quantity: gq }]
          : [{ id: buyVariant, quantity: bq + gq }], btn, root);
      });
    }
  });
})();
