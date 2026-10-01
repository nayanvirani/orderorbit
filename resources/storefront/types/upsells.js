/* OrderOrbit · product and cart upsells. Items added here carry the offer, so its incentive applies at checkout. */
(function () {
  var S = OrderOrbit.shop;

  function visible(exp, ctx, limit) {
    return (exp.content.products || []).filter(function (p) {
      if (ctx.preview) return true;
      if (p.available === false) return false;
      if (ctx.product && OrderOrbit.h.numericId(p.id) === String(ctx.product)) return false;
      return !S.inCart(ctx, p);
    }).slice(0, limit);
  }

  function cards(h, list, c, ctx) {
    return list.map(function (p, i) {
      var price = p.price != null ? Number(p.price) : null;
      var pct = Number(c.discount_percent || 0);
      var offer = pct && price != null ? price * (1 - pct / 100) : price;
      return '<div class="oo-card">' + h.productImage(p) + '<div class="oo-card-meta"><span class="oo-name">' + h.esc(p.title) + '</span>' +
        (price != null ? '<span class="oo-price">' + (pct ? '<s>' + h.esc(h.money(price, ctx.currency)) + '</s> ' : '') + h.esc(h.money(offer, ctx.currency)) + '</span>' : '') +
        S.variantSelect(p, i, ctx) + '</div><button type="button" class="oo-btn oo-btn-sm" data-oo-click="upsell_accepted" data-oo-add="' + i + '">' + h.esc(c.cta_text || 'Add') + '</button></div>';
    }).join('');
  }

  function render(limitKey, subKey) {
    return function (exp, ctx, h) {
      var c = exp.content;
      var list = visible(exp, ctx, limitKey ? Number(c[limitKey] || 3) : 4);
      if (!list.length) return ctx.preview ? '<div class="oo-body"><p class="oo-empty">Choose products to recommend.</p></div>' : null;
      return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c[subKey] ? '<p class="oo-sub">' + h.esc(c[subKey]) + '</p>' : '') +
        '<div class="oo-cards">' + cards(h, list, c, ctx) + '</div><p class="oo-status" data-oo-status role="status"></p></div>';
    };
  }

  function hook(limitKey) {
    return {
      prepare: function (exp) { return S.hydrate(exp, ['products']); },
      setup: function (root, exp, ctx) {
        if (!root) return;
        var list = visible(exp, ctx, limitKey ? Number(exp.content[limitKey] || 3) : 4);
        root.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-oo-add]');
          if (!btn) return;
          var i = Number(btn.getAttribute('data-oo-add'));
          OrderOrbit.track('upsell_accepted', exp);
          S.add(exp, ctx, [{ id: S.chosenVariant(root, list[i], i), quantity: 1 }], btn, root);
        });
      }
    };
  }

  OrderOrbit.define('product-upsells', render(null, 'offer_message'), hook(null));
  OrderOrbit.define('cart-upsells', render('max_shown', 'incentive'), hook('max_shown'));
})();
