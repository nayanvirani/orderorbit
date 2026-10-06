/* OrderOrbit Space · bundles: subscribe & save (loaded only for bundles with subscriptions on).
   Plans come from the store's subscription app: Shopify selling plans on each product. Shoppers
   choose one-time or a delivery frequency; each paid item is added on its product's plan for that
   frequency (matched by the plan's options, e.g. "1 month"). The bundle discount applies to the
   first delivery; later deliveries are priced by the selling plan. */
(function () {
  var h = OrderOrbit.h;
  var SAMPLE = [['1 month', 0.9], ['2 months', 0.9], ['3 months', 0.9]];

  function money(v, ctx) { return h.esc(h.money(v, ctx.currency)); }
  function planKey(sp) { return ((sp.options || []).map(function (o) { return o.value; }).join(' ') || sp.name || '').toLowerCase(); }
  // "Deliver every 2 weeks" reads as "2 weeks" next to the frequency label.
  function planLabel(sp) { return ((sp.options || []).map(function (o) { return o.value; }).join(' ') || sp.name).replace(/^(deliver(y|ed)?\s+)?every\s+/i, ''); }

  // The products an offer adds ("m" = mix & match pool).
  function productsFor(c, ctx, key) {
    if (key === 'm') return c.mix.pool;
    var o = c.offers[key] || {};
    return o.kind === 'multi' ? o.products || [] : o.kind === 'mono' ? o.product.slice(0, 1) : [ctx.pageProduct || {}];
  }

  // { key: { id, ratio } } for one product: its plans and what they cost vs. one-time (first variant).
  function plansOf(p) {
    if (!p || !p.plans) return null;
    var v = (p.plans.variants || [])[0] || {};
    var out = {};
    p.plans.groups.forEach(function (g) {
      (g.selling_plans || []).forEach(function (sp) {
        var k = planKey(sp);
        var a = (v.selling_plan_allocations || []).filter(function (x) { return x.selling_plan_id === sp.id; })[0];
        // Subscriptions only: no pre-order or try-before-you-buy plans, no prepaid plans.
        if (out[k] || sp.recurring_deliveries === false || (a && a.per_delivery_price !== a.price)) return;
        out[k] = { id: sp.id, label: planLabel(sp), ratio: a && v.price ? a.price / v.price : 1 };
      });
    });
    return out;
  }

  /**
   * Frequencies every product of the offer can be bought on: [{ key, label, ratio }] (the ratio is
   * the average subscription price vs. one-time). Empty when a product has no matching plan.
   */
  function frequencies(c, ctx, key) {
    if (ctx.preview) return SAMPLE.map(function (s) { return { key: s[0], label: s[0], ratio: s[1] }; });
    var all = productsFor(c, ctx, key).map(plansOf);
    if (!all.length || all.some(function (m) { return !m; })) return [];
    return Object.keys(all[0]).filter(function (k) { return all.every(function (m) { return m[k]; }); }).map(function (k) {
      return { key: k, label: all[0][k].label, ratio: all.reduce(function (s, m) { return s + m[k].ratio; }, 0) / all.length };
    });
  }

  function more(s, freqs) {
    var ben = (s.benefits || '').split('\n').filter(Boolean);
    return '<span class="oo-bso-more">' +
      '<span class="oo-bso-freq"><span>' + h.esc(s.frequency_label) + '</span><select data-oo-freq aria-label="' + h.esc(s.frequency_label || 'Delivery frequency') + '">' +
      freqs.map(function (f) { return '<option value="' + h.esc(f.key) + '">' + h.esc(f.label) + '</option>'; }).join('') + '</select></span>' +
      '<small class="oo-bso-rec" data-oo-sp="rec"></small>' +
      (ben.length ? '<span class="oo-bso-ben">' + ben.map(function (b) { return '<span>✓ ' + h.esc(b) + '</span>'; }).join('') + '</span>' : '') + '</span>';
  }

  function body(c, ctx, key) {
    var s = c.subscription;
    var freqs = frequencies(c, ctx, key);
    if (!freqs.length) return '';
    var name = 'oo-s-' + h.esc(c._id);
    var sub = s.default !== 'once';
    var save = ' <em class="oo-bso-save" data-oo-spct></em>';
    if (s.layout === 'checkbox') {
      return '<label class="oo-bso-check"><input type="checkbox" data-oo-subcheck' + (sub ? ' checked' : '') + '><span><b>' + h.esc(s.subscribe_label) + '</b>' + save + '</span></label>' + more(s, freqs);
    }
    var radio = function (value, on) { return '<input type="radio" name="' + name + '" value="' + value + '"' + (on ? ' checked' : '') + '>'; };
    if (s.layout === 'toggle') {
      return '<span class="oo-bso-switch" role="radiogroup">' +
        '<label>' + radio('once', !sub) + '<span>' + h.esc(s.once_label) + '</span></label>' +
        '<label>' + radio('sub', sub) + '<span>' + h.esc(s.subscribe_label) + save + '</span></label></span>' + more(s, freqs);
    }
    return '<label class="oo-bso" data-oo-so="once">' + radio('once', !sub) + '<span class="oo-bso-t">' + h.esc(s.once_label) + '</span><b data-oo-sp="once"></b></label>' +
      '<label class="oo-bso oo-bso-sub" data-oo-so="sub">' + radio('sub', sub) + '<span class="oo-bso-t">' + h.esc(s.subscribe_label) + save + '</span><b data-oo-sp="sub"></b>' + more(s, freqs) + '</label>';
  }

  function subscribed(el) {
    var check = el.querySelector('[data-oo-subcheck]');
    if (check) return check.checked;
    var r = el.querySelector('input[type="radio"]:checked');
    return !!r && r.value === 'sub';
  }

  function chosen(c, ctx, key, el) {
    var sel = el.querySelector('[data-oo-freq]');
    var freqs = frequencies(c, ctx, key);
    return freqs.filter(function (f) { return sel && f.key === sel.value; })[0] || freqs[0];
  }

  OrderOrbit.bundleSub = {
    html: function (c, ctx, key) {
      return '<div class="oo-bsx oo-bsx-' + h.esc(c.subscription.layout) + '" data-oo-sub data-key="' + key + '">' + body(c, ctx, key) + '</div>';
    },

    // Redraws for the selected offer; prices the button for a subscription's first delivery.
    update: function (root, c, ctx, key, full, after, extra, btn) {
      var el = root.querySelector('[data-oo-sub]');
      if (!el) return;
      if (el.getAttribute('data-key') !== String(key)) {
        var was = el.innerHTML ? subscribed(el) : null;
        el.innerHTML = body(c, ctx, key);
        el.setAttribute('data-key', key);
        if (was === false) el.querySelectorAll('input').forEach(function (i) { i.checked = i.type === 'radio' ? i.value === 'once' : false; });
      }
      el.hidden = !el.innerHTML;
      var f = chosen(c, ctx, key, el);
      var on = !!f && subscribed(el);
      el.classList.toggle('oo-bsx-on', on);
      if (!f) return;
      var pct = Math.round((1 - f.ratio) * 100);
      var set = function (sel, html) { el.querySelectorAll(sel).forEach(function (n) { n.innerHTML = html; }); };
      set('[data-oo-spct]', pct > 0 ? 'Save ' + pct + '%' : '');
      set('[data-oo-sp="once"]', money(after + extra, ctx));
      set('[data-oo-sp="sub"]', money(after * f.ratio + extra, ctx));
      set('[data-oo-sp="rec"]', !full ? '' : h.esc(c.subscription.recurring_text || '').replace('{price}', money(full * f.ratio, ctx)).replace('{frequency}', h.esc(f.label)));
      if (on && full) btn.innerHTML = h.esc(c.settings.button_text) + ' · ' + money(after * f.ratio + extra, ctx);
    },

    // Puts each paid bundle item on its product's plan for the chosen frequency.
    items: function (root, c, ctx, key, list) {
      var el = root.querySelector('[data-oo-sub]');
      var f = el && chosen(c, ctx, key, el);
      if (!f || !subscribed(el)) return list;
      var byVariant = {};
      productsFor(c, ctx, key).forEach(function (p) {
        var plan = (plansOf(p) || {})[f.key];
        if (plan) ((p.plans || {}).variants || []).forEach(function (v) { byVariant[v.id] = plan.id; });
      });
      list.forEach(function (item) {
        var props = item.properties || {};
        if (props._oo_bundle && !props._oo_gift && byVariant[h.numericId(item.id)]) item.selling_plan = byVariant[h.numericId(item.id)];
      });
      return list;
    }
  };
})();
