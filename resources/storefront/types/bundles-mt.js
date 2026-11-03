/* Growvia · mix and match quantity tiers (G44): shoppers add any quantity of each item; the tier follows
   the total quantity. Rules: free (the cheapest items are free for each full set), percent, or fixed (a
   set of the cheapest items costs the set price, extra items pay full price). The Growvia discount at
   checkout applies the same rule to lines tagged _oo_bundle = "<id>|t|<group>". */
(function () {
  var h = OrderOrbit.h;

  function rate() { return (window.Shopify && Shopify.currency && Number(Shopify.currency.rate)) || 1; }

  // The tiles: several products, or one product's variants.
  function items(m) {
    if (m.source === 'variants') {
      var p = m.pool[0] || {};
      var vs = p.variants && p.variants.length ? p.variants : [{ id: p.variant_id, title: p.title, price: p.price }];
      return vs.map(function (v) { return { id: v.id, title: v.title || p.title, price: Number(v.price != null ? v.price : p.price || 0), image: p.image, sold: v.available === false }; });
    }
    return m.pool.map(function (p) { return { id: p.variant_id, title: p.title, price: Number(p.price || 0), image: p.image, sold: p.available === false }; });
  }

  // The tiers with a first "1 item" step, best last.
  function tiers(m) { return [{ count: 1, value: 0, first: true }].concat(m.tiers.filter(function (t) { return t.count > 1; })); }

  function reached(m, count) {
    var best = null;
    m.tiers.forEach(function (t) { if (count >= t.count) best = t; });
    return best;
  }

  // What the chosen units cost under the tier reached: [full, total].
  function price(m, units) {
    var full = units.reduce(function (s, v) { return s + v; }, 0);
    var t = reached(m, units.length);
    if (!t) return [full, full];
    var sorted = units.slice().sort(function (a, b) { return a - b; });
    var sets = Math.floor(units.length / t.count);
    if (m.rule === 'percent') return [full, full * (1 - t.value / 100)];
    if (m.rule === 'free') return [full, full - sorted.slice(0, sets * t.value).reduce(function (s, v) { return s + v; }, 0)];
    var inSets = sorted.slice(0, sets * t.count).reduce(function (s, v) { return s + v; }, 0);
    return [full, full - inSets + sets * t.value * rate()];
  }

  // Plain text; escaped where it's shown.
  function label(m, t, ctx) {
    if (t.first) return m.first_label || '1 item';
    if (t.label) return t.label;
    if (m.rule === 'free') return (t.count - t.value) + '+' + t.value;
    if (m.rule === 'percent') return t.count + ' items';
    return t.count + ' for ' + h.money(t.value * rate(), ctx.currency);
  }

  // The tier's saving on items of the reference price, for its badge and price per unit.
  function each(m, t, ref) {
    if (t.first) return ref;
    if (m.rule === 'free') return ref * (t.count - t.value) / t.count;
    if (m.rule === 'percent') return ref * (1 - t.value / 100);
    return Math.min(ref, t.value * rate() / t.count);
  }

  function badge(m, t, ref) {
    if (t.first || m.show_badges === false) return '';
    if (t.badge) return t.badge;
    var pct = ref ? Math.round((1 - each(m, t, ref) / ref) * 100) : 0;
    return pct > 0 ? '−' + pct + '%' : '';
  }

  function perUnit(m, v, ctx, B) { return m.units > 0 ? B.money(v / m.units, ctx) + '/' + h.esc(m.unit_label || 'unit') : ''; }

  function tierBox(m, ctx, B, ref) {
    var list = tiers(m);
    var steps = list.map(function (t, i) {
      var b = badge(m, t, ref);
      var pos = list.length > 1 ? i / (list.length - 1) * 100 : 0;
      return '<div class="oo-mt-step" data-oo-mt-step="' + i + '" style="--pos:' + pos + '%">' +
        '<span class="oo-mt-name">' + h.esc(label(m, t, ctx)) + (b ? ' <em>' + h.esc(b) + '</em>' : '') + '</span>' +
        (m.tier_style === 'slider' ? '<i class="oo-mt-knob"></i>' : '') + (m.units > 0 ? '<small>' + perUnit(m, each(m, t, ref), ctx, B) + '</small>' : '') + '</div>';
    }).join('');
    return '<div class="oo-mt-tiers oo-mt-' + m.tier_style + '" style="--n:' + list.length + '">' + (m.best_label ? '<span class="oo-mt-best">' + h.esc(m.best_label) + '</span>' : '') +
      (m.tier_style === 'slider' ? '<div class="oo-mt-track"><b data-oo-mt-fill></b></div>' : '') + '<div class="oo-mt-steps">' + steps + '</div></div>';
  }

  function tile(it, j, m, ctx, B) {
    return '<div class="oo-mt-item' + (it.sold ? ' oo-mt-sold' : '') + '" data-oo-mt-item="' + j + '"><div class="oo-mt-pic"><div class="oo-mt-img">' + h.productImage({ image: it.image, title: it.title }) + '</div>' +
      '<span class="oo-mt-ctl"><button type="button" class="oo-mt-add" data-oo-mt-add="' + j + '"' + (it.sold ? ' disabled' : '') + '>' + h.esc(it.sold ? m.sold_text || 'Sold out' : m.add_text) + '</button>' +
      '<span class="oo-mt-step-ctl"><button type="button" data-oo-mt-sub="' + j + '" aria-label="One less ' + h.esc(it.title) + '">−</button><b data-oo-mt-q="' + j + '">0</b>' +
      '<button type="button" data-oo-mt-add="' + j + '" aria-label="One more ' + h.esc(it.title) + '">+</button></span></span></div>' +
      '<span class="oo-mt-title">' + h.esc(it.title) + '</span>' + (m.show_price === false ? '' : '<span class="oo-mt-price">' + B.money(it.price, ctx) + (m.units > 0 ? ' · ' + perUnit(m, it.price, ctx, B) : '') + '</span>') + '</div>';
  }

  OrderOrbit.bundleMt = {
    html: function (exp, ctx, B) {
      var c = exp.content;
      var m = c.mt;
      var list = items(m);
      if (!list.length) return null;
      var ref = list[0].price;
      var del = OrderOrbit.bundleTiers && c.tiers && c.tiers.delivery && c.tiers.delivery.enabled;
      return '<div class="oo-body oo-bundle oo-bk-' + h.esc(c.settings.skin || 'mixtiers') + ' oo-mt oo-mt-l-' + m.layout + '" style="' + B.vars(exp.design || {}) + '--mt-cd:' + m.columns_desktop + ';--mt-cm:' + m.columns_mobile + '">' +
        B.header(c.settings) + (m.show_tiers === false ? '' : tierBox(m, ctx, B, ref)) +
        '<div class="oo-mt-items">' + list.map(function (it, j) { return tile(it, j, m, ctx, B); }).join('') + '</div>' +
        '<p class="oo-mt-nudge" data-oo-mt-nudge' + (m.show_nudge === false ? ' hidden' : '') + '></p>' +
        '<button type="button" class="oo-btn oo-badd" data-oo-click="bundle_add" data-oo-add disabled>' + h.esc(c.settings.button_text) + '</button>' +
        (del ? '<p class="oo-tdel" data-oo-tdel>' + (c.tiers.show_clock === false ? '' : '🕑 ') + '<span>' + OrderOrbit.bundleTiers.delivery(c.tiers.delivery) + '</span></p>' : '') +
        '<p class="oo-status" data-oo-status role="status"></p></div>';
    },

    setup: function (root, exp, ctx) {
      var c = exp.content;
      var m = c.mt;
      var list = items(m);
      var qty = list.map(function () { return 0; });
      var btn = root.querySelector('[data-oo-add]');
      var money = function (v) { return h.esc(h.money(v, ctx.currency)); };

      function update() {
        var units = [];
        list.forEach(function (it, j) { for (var k = 0; k < qty[j]; k++) units.push(it.price); });
        var count = units.length;
        var pr = price(m, units);
        var steps = tiers(m);
        var at = 0;
        steps.forEach(function (t, i) { if (count >= t.count) at = i; });
        root.querySelectorAll('[data-oo-mt-step]').forEach(function (el, i) { el.classList.toggle('oo-on', count > 0 && i <= at); el.classList.toggle('oo-cur', count > 0 && i === at); });
        var fill = root.querySelector('[data-oo-mt-fill]');
        if (fill) fill.style.width = (count && steps.length > 1 ? at / (steps.length - 1) * 100 : 0) + '%';
        list.forEach(function (it, j) {
          root.querySelector('[data-oo-mt-q="' + j + '"]').textContent = qty[j];
          root.querySelector('[data-oo-mt-item="' + j + '"]').classList.toggle('oo-mt-has', qty[j] > 0);
        });
        var full = m.max > 0 && count >= m.max;
        root.querySelectorAll('[data-oo-mt-add]').forEach(function (b) { if (!list[Number(b.getAttribute('data-oo-mt-add'))].sold) b.disabled = full; });
        var next = steps.filter(function (t) { return t.count > count; })[0];
        root.querySelector('[data-oo-mt-nudge]').innerHTML = !count ? h.esc(m.empty != null ? m.empty : 'Choose your items')
          : next ? h.esc(m.nudge || '').replace('{count}', '<b>' + (next.count - count) + '</b>').replace('{tier}', '<b>' + h.esc(label(m, next, ctx)) + (badge(m, next, list[0].price) ? ' (' + h.esc(badge(m, next, list[0].price)) + ')' : '') + '</b>')
            : h.esc(m.done || '');
        btn.disabled = !count;
        btn.innerHTML = h.esc(c.settings.button_text) + (count && m.button_price !== false ? ' · ' + money(pr[1]) + (pr[0] - pr[1] > 0.004 ? ' <s>' + money(pr[0]) + '</s>' : '') : '');
      }

      root.addEventListener('click', function (e) {
        var add = e.target.closest('[data-oo-mt-add]');
        var sub = e.target.closest('[data-oo-mt-sub]');
        if (add && !add.disabled) qty[Number(add.getAttribute('data-oo-mt-add'))]++;
        if (sub) { var j = Number(sub.getAttribute('data-oo-mt-sub')); qty[j] = Math.max(0, qty[j] - 1); }
        if (add || sub) update();
      });
      btn.addEventListener('click', function () {
        var tag = exp.id + '|t|' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
        var lines = list.map(function (it, j) { return qty[j] ? { id: it.id, quantity: qty[j], properties: { _oo_bundle: tag } } : null; }).filter(Boolean);
        OrderOrbit.shop.add(exp, ctx, lines, btn, root);
      });
      update();
    }
  };
})();
