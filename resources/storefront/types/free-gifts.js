/* OrderOrbit · free gifts. One gift unit is free per threshold reached; the discount function makes it free at checkout. */
(function () {
  var S = OrderOrbit.shop;

  function state(exp, ctx) {
    var h = OrderOrbit.h;
    var gifts = S.linesFor(ctx, exp);
    var giftSpend = gifts.reduce(function (sum, l) { return sum + (l.price || 0); }, 0);
    var total = Math.max(0, (ctx.cartTotal || 0) - giftSpend) / 100;
    var s = h.thresholds(exp.content.thresholds, total);
    s.total = total;
    s.claimed = gifts.reduce(function (n, l) { return n + l.qty; }, 0);
    s.gifts = gifts;
    return s;
  }

  OrderOrbit.define('free-gifts', function (exp, ctx, h) {
    var c = exp.content;
    var s = state(exp, ctx);
    var msg = s.next
      ? h.fill(c.progress_message, { remaining: h.money(Number(s.next.amount) - s.total, ctx.currency), reward: s.next.reward })
      : h.fill(c.unlocked_message, { reward: s.last ? s.last.reward : '' });
    var open = s.reached.length > s.claimed;
    var gifts = (c.gift_products || []).slice(0, 4).map(function (p, i) {
      var have = S.inCart(ctx, p) && s.claimed > 0;
      var action = have ? '<em>In your cart</em>'
        : open ? '<button type="button" class="oo-btn oo-btn-sm" data-oo-click="gift_claimed" data-oo-add="' + i + '">Claim</button>'
          : '<em>' + (s.reached.length ? 'Claimed' : 'Locked') + '</em>';
      return '<div class="oo-gift' + (s.reached.length ? ' oo-unlocked' : '') + '">' + h.productImage(p) + '<span>' + h.esc(p.title) + '</span>' + S.variantSelect(p, i, ctx) + action + '</div>';
    }).join('');
    return '<div class="oo-body"><p class="oo-message" role="status">' + h.icon(h.GIFT) + ' ' + msg + '</p>' +
      '<div class="oo-progress"><i style="width:' + s.pct + '%"></i></div>' + h.ladder(s, ctx.currency) + (gifts ? '<div class="oo-gifts">' + gifts + '</div>' : '') +
      '<p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    prepare: function (exp) { return S.hydrate(exp, ['gift_products']); },
    setup: function (root, exp, ctx) {
      if (!root) return;
      var list = exp.content.gift_products || [];
      var s = state(exp, ctx);
      var add = function (i, btn) { return S.add(exp, Object.assign({}, ctx), [{ id: S.chosenVariant(root, list[i], i), quantity: 1 }], btn, root); };
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-oo-add]');
        if (btn) add(Number(btn.getAttribute('data-oo-add')), btn);
      });
      if (ctx.preview) return;

      // Gifts beyond what the cart has earned would be charged, so take them out.
      if (s.claimed > s.reached.length) {
        var extra = s.claimed - s.reached.length;
        var line = s.gifts[s.gifts.length - 1];
        S.change(line.key, Math.max(0, line.qty - extra)).then(OrderOrbit.refreshCart);
        return;
      }
      // Auto mode adds each earned gift once per session (a shopper who removes it keeps it removed).
      if (exp.content.claim_mode === 'auto' && s.reached.length > s.claimed && list.length) {
        var key = 'oo_gift_' + exp.id + '_' + s.reached.length;
        try { if (sessionStorage.getItem(key)) return; sessionStorage.setItem(key, '1'); } catch (err) { /* private mode */ }
        var auto = Object.assign({}, exp, { behavior: Object.assign({}, exp.behavior, { after_add: 'stay' }) });
        S.add(auto, ctx, [{ id: list[Math.min(s.claimed, list.length - 1)].variant_id, quantity: 1 }], null, root);
      }
    }
  });
})();
