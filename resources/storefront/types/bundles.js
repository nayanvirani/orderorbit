/* OrderOrbit · bundles: mix & match (pick any N) or fixed (all together, e.g. frequently bought together).
   Added items merge into one bundle line in the cart (OrderOrbit cart transform). */
(function () {
  var S = OrderOrbit.shop;

  function products(exp) {
    return (exp.content.products || []).filter(function (p) { return p.available !== false; });
  }

  OrderOrbit.define('bundles', function (exp, ctx, h) {
    var c = exp.content;
    var list = products(exp);
    if (!list.length) return ctx.preview ? '<div class="oo-body"><p class="oo-empty">Choose products for this bundle.</p></div>' : null;
    var fixed = c.bundle_mode === 'fixed';
    var min = fixed ? list.length : Math.min(Number(c.min_items || 1), list.length);

    var tiles = list.map(function (p, i) {
      var price = p.price != null ? '<span class="oo-price">' + (c.show_compare_at && p.compare_at > p.price ? '<s>' + h.esc(h.money(p.compare_at, ctx.currency)) + '</s> ' : '') + h.esc(h.money(p.price, ctx.currency)) + '</span>' : '';
      var qty = Number(p.quantity || 1);
      var inner = h.productImage(p) + '<span class="oo-name">' + (qty > 1 ? qty + ' × ' : '') + h.esc(p.title) + '</span>' + price;
      return fixed
        ? (i ? '<span class="oo-plus" aria-hidden="true">+</span>' : '') + '<div class="oo-tile oo-fixed">' + inner + S.variantSelect(p, i, ctx) + '</div>'
        : '<div class="oo-pick"><label class="oo-tile"><input type="checkbox" data-oo-pick="' + i + '"' + (i < min ? ' checked' : '') + '>' + inner + '</label>' + S.variantSelect(p, i, ctx) + '</div>';
    }).join('');

    return '<div class="oo-body">' + (c.badge ? '<span class="oo-badge">' + h.esc(c.badge) + '</span>' : '') +
      '<p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') +
      '<div class="oo-tiles' + (fixed ? ' oo-tiles-fixed' : '') + '">' + tiles + '</div>' +
      (fixed ? '' : '<p class="oo-message" data-oo-msg></p><div class="oo-progress"><i data-oo-bar></i></div>') +
      '<p class="oo-total" data-oo-total></p>' +
      '<button type="button" class="oo-btn" data-oo-click="bundle_add" data-oo-add>' + h.esc(c.cta_text) + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    prepare: function (exp) { return S.hydrate(exp, ['products']); },
    setup: function (root, exp, ctx) {
      var h = OrderOrbit.h;
      var c = exp.content;
      var list = products(exp);
      if (!root || !list.length) return;
      var fixed = c.bundle_mode === 'fixed';
      var min = fixed ? list.length : Math.min(Number(c.min_items || 1), list.length);
      var max = fixed ? list.length : Math.max(min, Number(c.max_items || list.length));
      var btn = root.querySelector('[data-oo-add]');

      function picks() {
        if (fixed) return list.map(function (p, i) { return i; });
        return [].slice.call(root.querySelectorAll('[data-oo-pick]:checked')).map(function (el) { return Number(el.getAttribute('data-oo-pick')); });
      }

      function unitPrice(p, i) {
        var sel = root.querySelector('[data-oo-variant="' + i + '"]');
        var opt = sel && sel.options[sel.selectedIndex];
        return Number(opt ? opt.getAttribute('data-price') : p.price) || 0;
      }

      function update() {
        var chosen = picks();
        root.querySelectorAll('[data-oo-pick]').forEach(function (el) { el.disabled = !el.checked && chosen.length >= max; });
        var total = chosen.reduce(function (sum, i) { return sum + unitPrice(list[i], i) * Number(list[i].quantity || 1); }, 0);
        var remaining = Math.max(0, min - chosen.length);
        var after = remaining ? total : S.saving(total, c.discount_type, c.discount_value);
        var msg = root.querySelector('[data-oo-msg]');
        if (msg) msg.innerHTML = remaining ? h.fill(c.progress_message, { remaining: remaining }) : 'Bundle saving unlocked';
        var bar = root.querySelector('[data-oo-bar]');
        if (bar) bar.style.width = (min ? Math.min(100, (chosen.length / min) * 100) : 100) + '%';
        root.querySelector('[data-oo-total]').innerHTML = total ? 'Total ' + (after < total ? '<s>' + h.esc(h.money(total, ctx.currency)) + '</s> ' : '') + '<strong>' + h.esc(h.money(after, ctx.currency)) + '</strong>' : '';
        btn.disabled = remaining > 0;
      }

      root.addEventListener('change', update);
      btn.addEventListener('click', function () {
        // One group per click: the cart transform merges each group into one bundle line.
        var group = exp.id + '|' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        S.add(exp, ctx, picks().map(function (i) { return { id: S.chosenVariant(root, list[i], i), quantity: Number(list[i].quantity || 1), properties: { _oo_bundle: group } }; }), btn, root);
      });
      update();
    }
  });
})();
