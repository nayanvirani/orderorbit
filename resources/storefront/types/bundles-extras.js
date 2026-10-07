/* OrderOrbit Space · bundles: free gift tiles and add-on upsells (loaded only for bundles that use them). */
(function () {
  var h = OrderOrbit.h;
  var money = function (v, ctx) { return h.esc(h.money(v, ctx.currency)); };
  var ticked = function (root) { return [].slice.call(root.querySelectorAll('[data-oo-up]:checked')); };
  var unit = function (c, p) { return Number(p.price || 0) * (1 - Number(c.upsells.discount_percent || 0) / 100); };
  var tile = function (g, badge) {
    var p = g.product[0] || {};
    return '<span class="oo-bgift">' + h.productImage(p) + '<small>' + (g.quantity > 1 ? g.quantity + ' × ' : '') + h.esc(p.title || 'Gift') + '</small><b>' + h.esc(badge) + '</b></span>';
  };
  var boxItems = function (c) { return c.gifts.enabled ? (c.gifts.items || []).filter(function (g) { return g.product[0]; }) : []; };
  // Mix & match and boxes: the gift unlocks at this many items (by default a box's minimum, or a full mix & match).
  var unlockAt = function (c) { return Number(c.gifts.unlock) || (c.bundle_type === 'byob' ? Number(c.mix.min) || 1 : Number(c.mix.slots)); };

  OrderOrbit.bundleExtras = {
    // The free gifts an offer unlocks, under the offer.
    gifts: function (o, c) {
      if (!c.gifts.enabled || !o.gifts.length) return '';
      return '<div class="oo-bgifts">' + o.gifts.map(function (g) { return tile(g, 'FREE'); }).join('') + '</div>';
    },

    // The gift a mix & match or box unlocks, with what's left to unlock it.
    boxGifts: function (c) {
      var list = boxItems(c);
      if (!list.length) return '';
      return '<div class="oo-bboxgift" data-oo-boxgift>' + (c.gifts.title ? '<p class="oo-bups-title">' + h.esc(c.gifts.title) + '</p>' : '') +
        '<div class="oo-bgifts">' + list.map(function (g) { return tile(g, 'FREE'); }).join('') + '</div><small data-oo-giftmsg></small></div>';
    },

    giftUpdate: function (root, c, count) {
      var el = root.querySelector('[data-oo-boxgift]');
      if (!el) return;
      var need = unlockAt(c) - count;
      el.classList.toggle('oo-bgift-on', need <= 0);
      el.querySelectorAll('.oo-bgift b').forEach(function (b) { b.textContent = need > 0 ? c.gifts.locked_text || 'Locked' : 'FREE'; });
      el.querySelector('[data-oo-giftmsg]').innerHTML = need > 0 ? 'Add <b>' + need + '</b> more to unlock your free gift' : 'Free gift unlocked';
    },

    // The unlocked gifts, as [variant id, quantity].
    giftItems: function (c, count) {
      return count >= unlockAt(c) ? boxItems(c).map(function (g) { return [g.product[0].variant_id, g.quantity]; }) : [];
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
