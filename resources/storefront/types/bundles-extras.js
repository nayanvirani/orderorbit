/* OrderOrbit Space · bundles: free gift tiles and add-on upsells (loaded only for bundles that use them). */
(function () {
  var h = OrderOrbit.h;
  var money = function (v, ctx) { return h.esc(h.money(v, ctx.currency)); };
  var ticked = function (root) { return [].slice.call(root.querySelectorAll('[data-oo-up]:checked')); };
  var unit = function (c, p) { return Number(p.price || 0) * (1 - Number(c.upsells.discount_percent || 0) / 100); };

  OrderOrbit.bundleExtras = {
    // The free gifts an offer unlocks, under the offer.
    gifts: function (o, c) {
      if (!c.gifts.enabled || !o.gifts.length) return '';
      return '<div class="oo-bgifts">' + o.gifts.map(function (g) {
        var p = g.product[0] || {};
        return '<span class="oo-bgift">' + h.productImage(p) + '<small>' + (g.quantity > 1 ? g.quantity + ' × ' : '') + h.esc(p.title || 'Gift') + '</small><b>FREE</b></span>';
      }).join('') + '</div>';
    },

    // Add-ons shoppers can tick, at the add-on discount.
    upsells: function (c, ctx) {
      var u = c.upsells;
      if (!u.enabled || !u.products.length) return '';
      return '<div class="oo-bups">' + (u.title ? '<p class="oo-bups-title">' + h.esc(u.title) + '</p>' : '') + u.products.map(function (p, j) {
        var price = Number(p.price || 0);
        return '<label class="oo-bup"><input type="checkbox" data-oo-up="' + j + '">' + h.productImage(p) + '<span>' + h.esc(p.title) + '</span><span class="oo-bprice"><b>' +
          money(unit(c, p), ctx) + '</b>' + (u.discount_percent ? '<s>' + money(price, ctx) + '</s>' : '') + '</span></label>';
      }).join('') + '</div>';
    },

    // Ticked add-ons as cart items (priced by the OrderOrbit discount through _oo_offer).
    items: function (root, c, exp) {
      return ticked(root).map(function (el) {
        var p = c.upsells.products[Number(el.getAttribute('data-oo-up'))];
        return { id: p.variant_id, quantity: 1, properties: { _oo_offer: exp.id + ':u' } };
      });
    },

    total: function (root, c) {
      return ticked(root).reduce(function (sum, el) { return sum + unit(c, c.upsells.products[Number(el.getAttribute('data-oo-up'))]); }, 0);
    },

    // Upsell accepted / declined when the bundle is added.
    track: function (root, c, exp) {
      if (!c.upsells.enabled || !c.upsells.products.length) return;
      var taken = ticked(root).length;
      OrderOrbit.track(taken ? 'upsell_accepted' : 'upsell_declined', exp, { quantity: taken || 1 });
    }
  };
})();
