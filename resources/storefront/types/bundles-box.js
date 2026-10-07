/* OrderOrbit Space · bundles: build your own box (loaded only for build-your-own boxes).
   Shoppers set a quantity for each product. The box can't be added below its minimum, can't go
   past its maximum or a product's limit, and is priced by its discount steps or its box price.
   It shares the mix & match engine: each unit is a pick, and "slots" is the box maximum. */
(function () {
  var h = OrderOrbit.h;

  function rate() { return (window.Shopify && Shopify.currency && Number(Shopify.currency.rate)) || 1; }
  function root() { return (window.Shopify && Shopify.routes && Shopify.routes.root) || '/'; }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  // The collection's products, for boxes filled from a collection (the storefront's own product feed).
  function load(c) {
    var col = (c.mix.collection || [])[0];
    if (c.mix.source !== 'collection' || !col || !col.handle) return Promise.resolve();
    return fetch(root() + 'collections/' + encodeURIComponent(col.handle) + '/products.json?limit=250', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : { products: [] }; })
      .then(function (data) {
        c.mix.pool = (data.products || []).map(function (p) {
          var variants = (p.variants || []).map(function (v) {
            return { id: v.id, title: v.title, price: Number(v.price) * rate(), available: v.available !== false };
          });
          var first = variants.filter(function (v) { return v.available; })[0] || variants[0] || {};
          return {
            id: 'gid://shopify/Product/' + p.id, title: p.title, handle: p.handle,
            image: p.images && p.images[0] ? p.images[0].src : null,
            price: first.price, variant_id: first.id, available: variants.some(function (v) { return v.available; }),
            variants: variants.length > 1 ? variants : null
          };
        });
        // Subscription plans and prices in the shopper's currency come with each product's own data.
        if ((c.subscription && c.subscription.enabled) || rate() !== 1) return Promise.all(c.mix.pool.map(OrderOrbit.shop.load));
      })
      .catch(function () {});
  }

  function card(c, p, j, B, ctx) {
    var many = c.settings.show_variants && p.variants && p.variants.length > 1;
    return '<div class="oo-xcard' + (p.available === false ? ' oo-xsold' : '') + '" data-oo-xcard="' + j + '">' +
      '<span class="oo-ximg">' + h.productImage(p) + '</span>' +
      '<span class="oo-xinfo"><span class="oo-xname">' + h.esc(p.title) + '</span><span class="oo-xprice">' + B.money(p.price || 0, ctx) + '</span>' +
      (many ? B.select('m:' + j, p.variants, ctx) : '') + '</span>' +
      (c.mix.show_quantity === false
        // Add / Remove: one of each product.
        ? '<span class="oo-xstep oo-xone"><button type="button" class="oo-xadd" data-oo-pick="' + j + '"' + (p.available === false ? ' disabled' : '') + '>' + h.esc(p.available === false ? 'Sold out' : c.mix.add_text || 'Add') + '</button>' +
          '<button type="button" class="oo-xrem" data-oo-unpick="" hidden>' + h.esc(c.mix.remove_text || 'Remove') + '</button><b data-oo-xq hidden>0</b></span>'
        : '<span class="oo-xstep"><button type="button" class="oo-xminus" data-oo-unpick="" aria-label="Remove one ' + h.esc(p.title) + '" disabled>−</button>' +
          '<b data-oo-xq aria-live="polite">0</b>' +
          '<button type="button" class="oo-xplus" data-oo-pick="' + j + '" aria-label="Add one ' + h.esc(p.title) + '"' + (p.available === false ? ' disabled' : '') + '>+</button></span>') + '</div>';
  }

  OrderOrbit.bundleBox = {
    load: load,

    html: function (c, ctx, B) {
      return '<div class="oo-xbox" data-oo-box>' +
        '<div class="oo-xhead"><span class="oo-xcount" data-oo-xcount></span><span class="oo-bmsg" data-oo-msg></span></div>' +
        '<div class="oo-xbar"><i data-oo-xbar></i></div>' +
        '<div class="oo-xgrid">' + c.mix.pool.map(function (p, j) { return card(c, p, j, B, ctx); }).join('') + '</div>' +
        '<div class="oo-xtray" data-oo-xtray hidden></div></div>';
    },

    // Redraws quantities, limits and the box tracker; returns the saving for the summary.
    update: function (root, c, ctx, picks, btn, B, extra) {
      var m = c.mix;
      var min = Math.max(1, Number(m.min) || 1), max = Number(m.slots) || 1, pp = Number(m.per_product) || 0;
      var count = picks.length;
      var per = {};
      picks.forEach(function (p) { per[p.j] = (per[p.j] || 0) + 1; });

      root.querySelectorAll('[data-oo-xcard]').forEach(function (el) {
        var j = Number(el.getAttribute('data-oo-xcard'));
        var q = per[j] || 0;
        var last = -1;
        picks.forEach(function (p, k) { if (p.j === j) last = k; });
        el.querySelector('[data-oo-xq]').textContent = q;
        el.classList.toggle('oo-xon', q > 0);
        var minus = el.querySelector('[data-oo-unpick]');
        minus.disabled = last < 0;
        minus.setAttribute('data-oo-unpick', last < 0 ? '' : last);
        // Full box, the product's limit, or sold out: no more of it.
        var plus = el.querySelector('[data-oo-pick]');
        plus.disabled = count >= max || (pp && q >= pp) || m.pool[j].available === false;
        if (m.show_quantity === false) {
          plus.hidden = q > 0;
          minus.hidden = !q;
          // The variant is fixed once the product is in the box.
          var sel = el.querySelector('select');
          if (sel) sel.disabled = q > 0;
        }
      });

      var total = picks.reduce(function (sum, p) { return sum + p.price; }, 0);
      var tier = null, next = null;
      // Steps bigger than the box can't be reached.
      (m.tiers || []).forEach(function (t) { if (count >= t.count) tier = t; else if (!next && t.count <= max) next = t; });
      var after = total;
      if (m.pricing === 'fixed') { if (count === max && count) after = Math.min(total, Number(m.fixed_price) * rate()); }
      else if (tier) after = total * (1 - tier.discount / 100);
      var saving = Math.max(0, total - after);

      var msg = count < min ? 'Add <b>' + plural(min - count, 'more item', 'more items') + '</b> to complete your box'
        : m.pricing !== 'fixed' && next ? 'Add <b>' + (next.count - count) + '</b> more to save <b>' + h.esc(next.discount) + '%</b>'
          : count >= max ? 'Your box is full' + (saving > 0 ? ': you save <b>' + B.money(saving, ctx) + '</b>' : '')
            : 'Your box is ready' + (saving > 0 ? ': you save <b>' + B.money(saving, ctx) + '</b>' : '');
      root.querySelector('[data-oo-msg]').innerHTML = msg;
      root.querySelector('[data-oo-xcount]').textContent = count + ' / ' + max + (m.min > 1 && m.min !== max ? ' · min ' + min : '');
      root.querySelector('[data-oo-xbar]').style.width = Math.min(100, count / max * 100) + '%';

      var tray = root.querySelector('[data-oo-xtray]');
      var lines = {};
      picks.forEach(function (p) { var k = p.j + '|' + p.v; (lines[k] = lines[k] || { p: p, n: 0 }).n++; });
      tray.hidden = !count;
      tray.innerHTML = Object.keys(lines).map(function (k) {
        var l = lines[k];
        return '<span class="oo-xchip">' + l.n + ' × ' + h.esc(m.pool[l.p.j].title) + (l.p.label && m.pool[l.p.j].variants ? ' · ' + h.esc(l.p.label) : '') + '</span>';
      }).join('');

      // Below the minimum the box can't be added.
      btn.disabled = count < min;
      btn.innerHTML = h.esc(c.settings.button_text) + (count ? ' · ' + B.money(after + extra, ctx) : '');
      if (c.subscription && c.subscription.enabled && OrderOrbit.bundleSub) {
        OrderOrbit.bundleSub.update(root, c, ctx, 'm', total, after, extra, btn, picks.map(function (p) { return { id: p.v, quantity: 1, properties: { _oo_bundle: 1 } }; }));
        btn.disabled = count < min;
      }
      return saving;
    }
  };
})();
