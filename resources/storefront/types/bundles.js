/* OrderOrbit · bundles (quantity breaks, variant offers, fixed packs, gift bundles, mix & match).
   Adds are tagged _oo_bundle = "<bundle>|<offer index or m>|<group>": one-product offers are priced by
   the OrderOrbit discount, packs and mix & match merge into one line through the cart transform. */
(function () {
  var S = OrderOrbit.shop;
  var h = OrderOrbit.h;
  var SAMPLE_VARIANTS = [{ id: 1, title: 'Blue', price: 29, available: true }, { id: 2, title: 'Red', price: 29, available: true }];

  function money(v, ctx) { return h.esc(h.money(v, ctx.currency)); }

  // Shared with oo-bundles-mix.js.
  var B = { money: money, select: function () { return select.apply(null, arguments); } };

  function afterOffer(full, o) {
    var v = Number(o.discount_value || 0);
    var rate = (window.Shopify && Shopify.currency && Number(Shopify.currency.rate)) || 1;
    if (o.discount_type === 'percentage') return full * (1 - Math.min(100, v) / 100);
    if (o.discount_type === 'amount') return Math.max(0, full - v * rate);
    if (o.discount_type === 'fixed_price') return Math.min(full, v * rate);
    return full;
  }

  // The page product (quantity offers) with its live variants.
  function base(ctx) {
    var p = ctx.pageProduct || {};
    return {
      title: p.title || ctx.productTitle || 'Product', image: p.image || ctx.productImage || (ctx.preview ? window.OO_SAMPLE_IMAGE : null),
      price: p.price != null ? p.price : (ctx.productPrice != null ? ctx.productPrice / 100 : 29),
      compare: p.compare_at, variants: p.variants || (ctx.preview ? SAMPLE_VARIANTS : null)
    };
  }

  function units(o) { return o.kind === 'multi' ? 0 : Number(o.quantity || 1); }

  // Total of n units of a product: each unit at the variant picked in its picker (key:u), else at
  // the product's price, so offers with differently priced variants show what the cart charges.
  function picked(root, key, n, price) {
    var sum = 0;
    for (var u = 0; u < n; u++) {
      var sel = root && root.querySelector('[data-oo-v="' + key + ':' + u + '"]');
      var opt = sel && sel.options[sel.selectedIndex];
      sum += opt && opt.getAttribute('data-price') ? Number(opt.getAttribute('data-price')) : Number(price || 0);
    }
    return sum;
  }

  function prices(o, ctx, i, root) {
    var full = o.kind === 'quantity' ? picked(root, i + ':p', units(o), base(ctx).price)
      : o.kind === 'mono' ? picked(root, i + ':p', units(o), (o.product[0] || {}).price)
        : (o.products || []).reduce(function (sum, p, j) { return sum + picked(root, i + ':' + j, Number(p.quantity || 1), p.price); }, 0);
    var after = afterOffer(full, o);
    return { full: full, after: after, saving: Math.max(0, full - after), pct: full ? Math.round((1 - after / full) * 100) : 0 };
  }

  function fillText(text, pr, ctx) {
    return h.esc(text || '').replace('{saving}', money(pr.saving, ctx)).replace('{percent}', pr.pct + '%');
  }

  function select(name, variants, ctx) {
    return '<select class="oo-variant" data-oo-v="' + name + '">' + variants.map(function (v, i) {
      return '<option value="' + v.id + '" data-price="' + (v.price != null ? v.price : '') + '"' + (v.available === false ? ' disabled' : '') + '>' + h.esc(v.title || v.public_title || 'Option ' + (i + 1)) + (v.available === false ? ' (sold out)' : '') + '</option>';
    }).join('') + '</select>';
  }

  // "#1 [Blue ▾] #2 [Blue ▾]" pickers, one per unit, like the reference layouts.
  function unitPickers(key, variants, count, ctx) {
    if (!variants || variants.length < 2) return '';
    var out = '';
    for (var u = 0; u < count; u++) out += '<span class="oo-bunit"><small>#' + (u + 1) + '</small>' + select(key + ':' + u, variants, ctx) + '</span>';
    return '<div class="oo-bunits">' + out + '</div>';
  }

  function giftTiles(o, c, ctx) {
    if (!c.gifts.enabled || !o.gifts.length) return '';
    return '<div class="oo-bgifts">' + o.gifts.map(function (g) {
      var p = g.product[0] || {};
      return '<span class="oo-bgift">' + h.productImage(p) + '<small>' + (g.quantity > 1 ? g.quantity + ' × ' : '') + h.esc(p.title || 'Gift') + '</small><b>FREE</b></span>';
    }).join('') + '</div>';
  }

  function detail(o, i, c, ctx) {
    var show = c.settings.show_variants;
    if (o.kind === 'quantity') return show ? unitPickers(i + ':p', base(ctx).variants, units(o), ctx) : '';
    if (o.kind === 'mono') {
      var m = o.product[0] || {};
      return show ? unitPickers(i + ':p', m.variants, units(o), ctx) : '';
    }
    return '<div class="oo-bpack">' + (o.products || []).map(function (p, j) {
      return '<div class="oo-bitem">' + h.productImage(p) + '<span class="oo-bitem-name">' + (p.quantity > 1 ? p.quantity + ' × ' : '') + h.esc(p.title) + '</span>' +
        '<span class="oo-bitem-price">' + money(Number(p.price || 0) * Number(p.quantity || 1), ctx) + '</span>' +
        (show ? unitPickers(i + ':' + j, p.variants, Number(p.quantity || 1), ctx) : '') + '</div>';
    }).join('') + '</div>';
  }

  function thumb(o, ctx) {
    var p = o.kind === 'quantity' ? base(ctx) : o.kind === 'mono' ? o.product[0] || {} : (o.products || [])[0] || {};
    var n = o.kind === 'multi' ? (o.products || []).reduce(function (s, x) { return s + Number(x.quantity || 1); }, 0) : units(o);
    return '<span class="oo-bthumb">' + h.productImage(p) + (n > 1 ? '<i>×' + n + '</i>' : '') + '</span>';
  }

  function priceHtml(pr, ctx) { return '<b>' + money(pr.after, ctx) + '</b>' + (pr.saving > 0 ? '<s>' + money(pr.full, ctx) + '</s>' : ''); }

  function offerCard(o, i, c, ctx, selected) {
    var pr = prices(o, ctx, i);
    var badge = o.badge || (pr.pct > 0 ? '−' + pr.pct + '%' : '');
    return '<label class="oo-boffer' + (selected ? ' oo-bsel' : '') + (o.highlight ? ' oo-bhl' : '') + '" data-oo-offer="' + i + '">' +
      (o.label ? '<span class="oo-blabel">' + h.esc(o.label) + '</span>' : '') +
      '<span class="oo-brow"><input type="radio" name="oo-b-' + h.esc(c._id) + '" value="' + i + '"' + (selected ? ' checked' : '') + '>' + thumb(o, ctx) +
      '<span class="oo-bmain"><span class="oo-btitle">' + h.esc(o.title) + (badge ? ' <em class="oo-bbadge">' + h.esc(badge) + '</em>' : '') + '</span>' +
      (o.subtitle ? '<span class="oo-bsubt">' + fillText(o.subtitle, pr, ctx) + '</span>' : '') + '</span>' +
      '<span class="oo-bprice">' + priceHtml(pr, ctx) + '</span></span>' +
      '<span class="oo-bdetail">' + detail(o, i, c, ctx) + giftTiles(o, c, ctx) + '</span></label>';
  }

  function header(s) {
    return (s.title ? '<div class="oo-bhead' + (s.hide_lines ? '' : ' oo-blines') + '"><span>' + h.esc(s.title) + '</span></div>' : '') +
      (s.subtitle ? '<p class="oo-bsub">' + h.esc(s.subtitle) + '</p>' : '') + OrderOrbit.timers.bundle(s.timer);
  }

  function upsells(c, ctx) {
    var u = c.upsells;
    if (!u.enabled || !u.products.length) return '';
    return '<div class="oo-bups">' + (u.title ? '<p class="oo-bups-title">' + h.esc(u.title) + '</p>' : '') + u.products.map(function (p, j) {
      var price = Number(p.price || 0);
      return '<label class="oo-bup"><input type="checkbox" data-oo-up="' + j + '">' + h.productImage(p) + '<span>' + h.esc(p.title) + '</span><span class="oo-bprice"><b>' +
        money(price * (1 - Number(u.discount_percent || 0) / 100), ctx) + '</b>' + (u.discount_percent ? '<s>' + money(price, ctx) + '</s>' : '') + '</span></label>';
    }).join('') + '</div>';
  }

  // Design settings from the app's Design tab, as CSS variables on the widget.
  function vars(d) {
    var map = { accent: 'accent', sel: 'selected_background', line: 'border', lbg: 'label_background', lfg: 'label_text', bbg: 'badge_background', bfg: 'badge_text',
      gbg: 'gift_background', sbg: 'summary_background', sfg: 'summary_text', muted: 'muted', btn: 'button_background', btnfg: 'button_text', text: 'text', bg: 'background' };
    var out = '';
    for (var k in map) if (d[map[k]]) out += '--b-' + k + ':' + d[map[k]] + ';';
    ['radius', 'border_width', 'title_size', 'offer_title_size', 'price_size', 'image_size'].forEach(function (k) { if (d[k] != null) out += '--b-' + k.replace(/_/g, '-') + ':' + d[k] + 'px;'; });
    return h.esc(out);
  }

  // Subscribe & save (oo-bundles-sub.js), when the bundle has subscriptions on.
  function sub(c) { return c.subscription && c.subscription.enabled && OrderOrbit.bundleSub; }

  // ------------------------------------------------------------------ render
  OrderOrbit.define('bundles', function (exp, ctx) {
    var c = exp.content;
    if (!c || !c.settings) return null;
    c._id = exp.id;
    var mix = c.bundle_type === 'mix-match';
    if (mix ? !OrderOrbit.bundleMix : !c.offers.length) return null;
    var pre = 0;
    c.offers.forEach(function (o, i) { if (o.preselected) pre = i; });
    var s = c.settings;
    return '<div class="oo-body oo-bundle oo-bl-' + s.layout + ' oo-bs-' + (s.style || 'cards') + ' oo-bk-' + (s.skin || 'classic') + '" style="' + vars(exp.design || {}) + '">' + header(s) +
      (mix ? OrderOrbit.bundleMix.html(c, ctx, B) : '<div class="oo-boffers">' + c.offers.map(function (o, i) { return offerCard(o, i, c, ctx, i === pre); }).join('') + '</div>') +
      (sub(c) ? sub(c).html(c, ctx, mix ? 'm' : pre) : '') + upsells(c, ctx) + (c.summary.enabled ? '<p class="oo-bsum" data-oo-sum hidden></p>' : '') +
      '<button type="button" class="oo-btn oo-badd" data-oo-click="bundle_add" data-oo-add>' + h.esc(s.button_text) + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    always: true,
    prepare: function (exp, ctx) {
      var c = exp.content;
      var mix = Promise.all([c.bundle_type === 'mix-match' && OrderOrbit.need('bundles-mix'), c.subscription && c.subscription.enabled && OrderOrbit.need('bundles-sub')]);
      // The admin preview uses saved product data; the storefront loads live variants and prices.
      if (ctx.preview) return mix;
      var jobs = [mix, S.hydrate({ content: { a: c.mix.pool, b: c.upsells.products } }, ['a', 'b'])];
      c.offers.forEach(function (o) {
        jobs.push(S.hydrate({ content: { a: o.products, b: o.product, g: o.gifts.map(function (g) { return g.product[0]; }).filter(Boolean) } }, ['a', 'b', 'g']));
      });
      if (ctx.productHandle && !ctx.pageProduct) jobs.push(S.load({ handle: ctx.productHandle }).then(function (p) { ctx.pageProduct = p; }));
      return Promise.all(jobs);
    },
    setup: function (root, exp, ctx) {
      if (!root) return;
      var c = exp.content;
      var mix = c.bundle_type === 'mix-match';
      var btn = root.querySelector('[data-oo-add]');
      var picks = [];

      function current() {
        var r = root.querySelector('.oo-boffers input:checked');
        return r ? Number(r.value) : 0;
      }

      function v(key, fallback) {
        var sel = root.querySelector('[data-oo-v="' + key + '"]');
        return sel ? sel.value : fallback;
      }

      function upsellItems() {
        return [].slice.call(root.querySelectorAll('[data-oo-up]:checked')).map(function (el) {
          var p = c.upsells.products[Number(el.getAttribute('data-oo-up'))];
          return { id: p.variant_id, quantity: 1, properties: { _oo_offer: exp.id + ':u' } };
        });
      }

      function upsellTotal() {
        return [].slice.call(root.querySelectorAll('[data-oo-up]:checked')).reduce(function (sum, el) {
          return sum + Number(c.upsells.products[Number(el.getAttribute('data-oo-up'))].price || 0) * (1 - Number(c.upsells.discount_percent || 0) / 100);
        }, 0);
      }

      function update() {
        var sum = root.querySelector('[data-oo-sum]');
        var saving = 0;
        if (mix) {
          saving = OrderOrbit.bundleMix.update(root, c, ctx, picks, btn, B, upsellTotal());
        } else {
          var i = current();
          root.querySelectorAll('.oo-boffer').forEach(function (el) { el.classList.toggle('oo-bsel', Number(el.getAttribute('data-oo-offer')) === i); });
          var pr = prices(c.offers[i], ctx, i, root);
          root.querySelector('.oo-bsel .oo-bprice').innerHTML = priceHtml(pr, ctx);
          saving = pr.saving;
          btn.innerHTML = h.esc(c.settings.button_text) + ' · ' + money(pr.after + upsellTotal(), ctx);
          if (sub(c)) sub(c).update(root, c, ctx, i, pr.full, pr.after, upsellTotal(), btn);
        }
        if (sum) { sum.hidden = !(saving > 0); sum.innerHTML = fillText(c.summary.text, { saving: saving, pct: 0 }, ctx); }
      }

      function items() {
        var tag = exp.id + '|' + (mix ? 'm' : current()) + '|' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
        var list = [];
        var add = function (id, qty, gift) {
          var props = { _oo_bundle: tag };
          if (gift) props._oo_gift = '1';
          var same = list.filter(function (x) { return String(x.id) === String(id) && !!x.properties._oo_gift === !!gift; })[0];
          if (same) same.quantity += qty; else list.push({ id: id, quantity: qty, properties: props });
        };
        if (mix) {
          picks.forEach(function (p) { add(p.v, 1); });
        } else {
          var i = current();
          var o = c.offers[i];
          var u;
          if (o.kind === 'quantity') for (u = 0; u < units(o); u++) add(v(i + ':p:' + u, S.pageVariant(ctx)), 1);
          if (o.kind === 'mono') for (u = 0; u < units(o); u++) add(v(i + ':p:' + u, (o.product[0] || {}).variant_id), 1);
          if (o.kind === 'multi') (o.products || []).forEach(function (p, j) {
            for (var k = 0; k < Number(p.quantity || 1); k++) add(v(i + ':' + j + ':' + k, p.variant_id), 1);
          });
          o.gifts.forEach(function (g) { if (g.product[0]) add(g.product[0].variant_id, g.quantity, true); });
        }
        list = list.concat(upsellItems());
        return sub(c) ? sub(c).items(root, c, ctx, mix ? 'm' : current(), list) : list;
      }

      // The bundle replaces the theme's variant picker, quantity, add to cart, buy-now and
      // subscription widgets on this page, so shoppers don't add the product twice.
      if (!ctx.preview && c.settings.hide_theme_form !== false) {
        document.documentElement.classList.add('oo-bundle-on');
        try { if (c.settings.hide_selectors) document.querySelectorAll(c.settings.hide_selectors).forEach(function (el) { el.setAttribute('data-oo-hide', ''); }); } catch (err) { /* invalid selector */ }
      }

      root.addEventListener('change', update);
      root.addEventListener('click', function (e) {
        var pick = e.target.closest('[data-oo-pick]');
        var unpick = e.target.closest('[data-oo-unpick]');
        if (pick && picks.length < c.mix.slots) {
          // Each pick keeps the variant chosen at that moment, so one product can fill
          // several slots in different variants.
          var j = Number(pick.getAttribute('data-oo-pick'));
          var p = c.mix.pool[j];
          var sel = root.querySelector('[data-oo-v="m:' + j + '"]');
          var opt = sel && sel.options[sel.selectedIndex];
          picks.push({ j: j, v: sel ? sel.value : p.variant_id, label: opt ? opt.text : '', price: opt && opt.getAttribute('data-price') !== '' ? Number(opt.getAttribute('data-price')) : Number(p.price || 0) });
          update();
        }
        if (unpick) { e.preventDefault(); picks.splice(Number(unpick.getAttribute('data-oo-unpick')), 1); update(); }
      });
      btn.addEventListener('click', function () {
        // Upsell accept / decline for the bundle's add-ons.
        if (c.upsells.enabled && c.upsells.products.length) {
          var taken = root.querySelectorAll('[data-oo-up]:checked').length;
          OrderOrbit.track(taken ? 'upsell_accepted' : 'upsell_declined', exp, { quantity: taken || 1 });
        }
        var after = Object.assign({}, exp, { behavior: Object.assign({}, exp.behavior) });
        S.add(after, ctx, items(), btn, root);
      });
      update();
    }
  });
})();
