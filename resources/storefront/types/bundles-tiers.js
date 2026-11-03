/* Growvia · tiered gift bundles (G36): sale price header, pictures and a second line in each tier, the
   selected tier opened to list what's inside, the gifts row with locked slots and free shipping, the
   delivery date bar and the price in the button. Loaded only for bundles that turn one of these on. */
(function () {
  var h = OrderOrbit.h;
  var DAY = 864e5;
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var TRUCK = '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/></svg>';

  function money(v, ctx) { return h.esc(h.money(v, ctx.currency)); }
  function rate() { return (window.Shopify && Shopify.currency && Number(Shopify.currency.rate)) || 1; }

  // A unit's value: its compare-at price, else its price. Values the merchant typed are in shop currency.
  function unitValue(p) { return Number(p.compare_at || p.compare || p.price || 0); }
  function valueOf(note, p, q) { return note && note.value != null ? Number(note.value) * rate() : unitValue(p) * q; }

  function items(o, ctx, B) {
    var notes = o.notes || [];
    var list = o.kind === 'multi' ? (o.products || []).map(function (p, j) { return [p, Number(p.quantity || 1), notes[j]]; })
      : [[o.kind === 'mono' ? o.product[0] || {} : B.base(ctx), Number(o.quantity || 1), notes[0]]];
    return list.map(function (x) { return { p: x[0], q: x[1], name: (x[2] || {}).name, desc: (x[2] || {}).desc || '', value: valueOf(x[2], x[0], x[1]) }; });
  }

  function gifts(o, c) {
    if (!c.gifts.enabled) return [];
    return (o.gifts || []).filter(function (g) { return g.product[0]; }).map(function (g) {
      return { p: g.product[0], q: Number(g.quantity || 1), name: g.name, desc: g.desc || '', value: valueOf(g, g.product[0], Number(g.quantity || 1)) };
    });
  }

  // With "crossed-out price = total value", the tier's full price is what its items and gifts are worth.
  function adjust(o, c, ctx, B, pr) {
    if (!c.tiers.value_price) return pr;
    var worth = items(o, ctx, B).concat(gifts(o, c)).reduce(function (s, x) { return s + x.value; }, 0);
    var full = Math.max(pr.full, worth);
    return { full: full, after: pr.after, saving: Math.max(0, full - pr.after), pct: full ? Math.round((1 - pr.after / full) * 100) : 0 };
  }

  function priceHtml(p, ctx) { return '<b>' + money(p.after, ctx) + '</b>' + (p.saving > 0 ? '<s>' + money(p.full, ctx) + '</s>' : ''); }

  // The merchant's wording (Texts and visibility), with today's words when a bundle has none saved.
  function w(t, key, fallback) { var v = (t.words || {})[key]; return v != null ? v : fallback; }

  function line(x, ctx, free, t) {
    var pre = free ? (w(t, 'free', 'FREE') ? h.esc(w(t, 'free', 'FREE')) + ' ' : '') : t.show_qty === false ? '' : x.q + 'x ';
    var val = t.show_values !== false && x.value > 0 && w(t, 'value', '({amount} Value)') ? ' <span class="oo-tval">' + h.esc(w(t, 'value', '({amount} Value)')).replace('{amount}', money(x.value, ctx)) + '</span>' : '';
    return '<li><span class="oo-tcheck' + (free ? ' oo-tcheck-g' : '') + '" aria-hidden="true">✓</span><span><b>' + pre + h.esc(x.name || x.p.title || 'Product') + '</b>' +
      val + (x.desc && t.show_desc !== false ? '<small>' + h.esc(x.desc) + '</small>' : '') + '</span></li>';
  }

  function unitsOf(o) { return o.kind === 'multi' ? (o.products || []).reduce(function (s, p) { return s + Number(p.quantity || 1); }, 0) : Number(o.quantity || 1); }

  function secondLine(o, c, ctx, p, B) {
    var t = c.tiers;
    if (t.second_line === 'none') return '';
    if (t.second_line === 'each') return '<span class="oo-bsubt">' + money(p.after / Math.max(1, unitsOf(o)), ctx) + ' ' + h.esc(w(t, 'per', 'per')) + ' ' + h.esc(t.unit || 'item') + '</span>';
    // "You save {saving}" with nothing saved says nothing.
    return o.subtitle && !(p.saving <= 0 && /\{(saving|percent)\}/.test(o.subtitle)) ? '<span class="oo-bsubt">' + B.fill(o.subtitle, p, ctx) + '</span>' : '';
  }

  // Delivery date from now in the store's time zone: after the cutoff it ships the next working day;
  // weekends (when skipped) and holidays don't count.
  function delivery(d) {
    var tz = d.timezone || 'UTC';
    var parts = {};
    try {
      new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', hourCycle: 'h23' })
        .formatToParts(new Date()).forEach(function (x) { parts[x.type] = Number(x.value); });
    } catch (e) { return ''; }
    var cut = String(d.cutoff || '14:00').split(':').map(Number);
    var mins = parts.hour * 60 + parts.minute;
    var left = cut[0] * 60 + cut[1] - mins;
    var day = Date.UTC(parts.year, parts.month - 1, parts.day) + (left <= 0 ? DAY : 0);
    var holidays = String(d.holidays || '').split(',').map(function (x) { return x.trim(); });
    var off = function (t) { var w = new Date(t).getUTCDay(); return (d.skip_weekends && (w === 0 || w === 6)) || holidays.indexOf(new Date(t).toISOString().slice(0, 10)) !== -1; };
    while (off(day)) day += DAY;
    for (var n = Number(d.max_days || 0); n > 0;) { day += DAY; if (!off(day)) n--; }
    var date = new Date(day);
    var dd = date.getUTCDate();
    var th = dd % 10 === 1 && dd !== 11 ? 'st' : dd % 10 === 2 && dd !== 12 ? 'nd' : dd % 10 === 3 && dd !== 13 ? 'rd' : 'th';
    if (left <= 0) left += 1440;
    var time = (left >= 60 ? Math.floor(left / 60) + 'h ' : '') + (left % 60) + 'm';
    return h.esc(d.text || '').replace('{date}', '<b class="oo-tdate">' + MONTHS[date.getUTCMonth()] + ' ' + dd + th + '</b>').replace('{time}', '<b>' + time + '</b>');
  }

  // Each gift once, with the first offer that includes it; then free shipping from its offer on.
  function slots(c) {
    var list = [];
    var seen = {};
    c.offers.forEach(function (o, i) {
      gifts(o, c).forEach(function (g) { var id = g.p.id || g.p.title; if (!seen[id]) { seen[id] = 1; list.push({ g: g, at: i, id: id }); } });
    });
    var from = -1;
    c.offers.forEach(function (o, i) { if (o.id === c.tiers.free_shipping) from = i; });
    if (from >= 0) list.unshift({ ship: true, at: from });
    return list;
  }

  function slotOn(s, o, i, c) {
    if (s.ship) return i >= s.at;
    return gifts(o, c).some(function (g) { return (g.p.id || g.p.title) === s.id; });
  }

  OrderOrbit.bundleTiers = {
    // Shared with oo-bundles-mt.js.
    delivery: delivery,

    top: function (c) { return c.tiers.sale_header ? '<div class="oo-tsale" data-oo-tsale></div>' : ''; },

    card: function (o, i, c, ctx, selected, pr, B) {
      var t = c.tiers;
      var p = adjust(o, c, ctx, B, pr);
      var its = items(o, ctx, B);
      var gs = gifts(o, c);
      // One picture per unit (up to two per item, the second marked "×n"), then "+" and the gifts.
      var pics = t.pictures ? '<span class="oo-tpics">' + its.map(function (x) {
        var out = '';
        for (var u = 0; u < Math.min(2, x.q); u++) out += '<span class="oo-tpic">' + h.productImage(x.p) + (u === 1 && x.q > 2 ? '<i>×' + x.q + '</i>' : '') + '</span>';
        return out;
      }).join('') + (gs.length ? '<span class="oo-tplus-sign">+</span>' + gs.map(function (x) { return '<span class="oo-tpic oo-tpic-gift">' + h.productImage(x.p) + '</span>'; }).join('') : '') + '</span>' : '';
      var plus = w(t, 'plus', 'PLUS FREE GIFTS');
      var list = t.details ? '<ul class="oo-tlist">' + its.map(function (x) { return line(x, ctx, false, t); }).join('') +
        (gs.length ? (plus ? '<li class="oo-tplus"><span>' + h.esc(plus) + '</span></li>' : '') + gs.map(function (x) { return line(x, ctx, true, t); }).join('') : '') + '</ul>' : '';
      var giftTiles = !t.details && OrderOrbit.bundleExtras ? OrderOrbit.bundleExtras.gifts(o, c) : '';
      return '<label class="oo-boffer oo-ttier' + (selected ? ' oo-bsel' : '') + (o.highlight ? ' oo-bhl' : '') + '" data-oo-offer="' + i + '">' +
        (o.label ? '<span class="oo-blabel' + (o.label_style === 'outlined' ? ' oo-blabel-o' : '') + '">' + h.esc(o.label) + '</span>' : '') +
        '<span class="oo-brow"><input type="radio" name="oo-b-' + h.esc(c._id) + '" value="' + i + '"' + (selected ? ' checked' : '') + '>' +
        pics + '<span class="oo-bmain"><span class="oo-btitle">' + h.esc(o.title) + (o.badge ? ' <em class="oo-bbadge">' + h.esc(o.badge) + '</em>' : '') + '</span>' + secondLine(o, c, ctx, p, B) + '</span>' +
        '<span class="oo-bprice">' + priceHtml(p, ctx) + '</span></span>' +
        '<span class="oo-bdetail">' + list + B.detail(o, i, c, ctx) + giftTiles + '</span></label>';
    },

    after: function (c, ctx) {
      var t = c.tiers;
      var out = '';
      var all = t.gifts_row ? slots(c) : [];
      if (all.length) {
        out += '<div class="oo-tgifts" data-oo-tgifts><p class="oo-tg-title" data-oo-tgt></p><div class="oo-tg-row">' + all.map(function (s, k) {
          var value = s.ship ? Number(t.shipping_value || 0) * rate() : s.g.value;
          return (k ? '<span class="oo-tg-plus" aria-hidden="true">+</span>' : '') + '<span class="oo-tg" data-oo-tg="' + k + '"><span class="oo-tg-img">' + (s.ship ? TRUCK : h.productImage(s.g.p)) + '</span>' +
            (value > 0 ? '<s>' + money(value, ctx) + '</s>' : '') + '<b class="oo-tg-free">' + (w(t, 'free', 'FREE') ? h.esc(w(t, 'free', 'FREE')) + ' ' : '') + h.esc(s.ship ? w(t, 'shipping', 'Shipping') : s.g.name || s.g.p.title || 'Gift') + '</b>' +
            '<small class="oo-tg-lock"><b>' + h.esc(c.gifts.locked_text || 'Locked') + '</b>' + h.esc(w(t, 'with', 'With Buy {count}')).replace('{count}', unitsOf(c.offers[s.at] || {})).replace('{tier}', h.esc((c.offers[s.at] || {}).title || '')) + '</small></span>';
        }).join('') + '</div></div>';
      }
      if (t.delivery && t.delivery.enabled) out += '<p class="oo-tdel" data-oo-tdel>' + (t.show_clock === false ? '' : '🕑 ') + '<span>' + delivery(t.delivery) + '</span></p>';
      return out;
    },

    // Called on every change; returns the saving for the summary line.
    update: function (root, c, ctx, i, pr, btn, B, extra) {
      var t = c.tiers;
      var o = c.offers[i];
      var p = adjust(o, c, ctx, B, pr);
      var sel = root.querySelector('.oo-bsel .oo-bprice');
      if (sel) sel.innerHTML = priceHtml(p, ctx);
      var head = root.querySelector('[data-oo-tsale]');
      if (head) {
        head.innerHTML = '<b>' + money(p.after, ctx) + '</b>' + (p.saving > 0 ? '<s>' + money(p.full, ctx) + '</s>' : '') +
          (t.sale_badge && p.saving > 0 ? '<span class="oo-tbadge">' + B.fill(t.sale_badge, p, ctx) + '</span>' : '');
      }
      if (t.button_price) {
        btn.innerHTML = h.esc(c.settings.button_text) + ' <span class="oo-tbtn">| ' + money(p.after + extra, ctx) + (p.saving > 0 ? ' <s>' + money(p.full + extra, ctx) + '</s>' : '') + '</span>';
      }
      var row = root.querySelector('[data-oo-tgifts]');
      if (row) {
        var all = slots(c);
        var open = 0;
        all.forEach(function (s, k) {
          var on = slotOn(s, o, i, c);
          open += on ? 1 : 0;
          root.querySelector('[data-oo-tg="' + k + '"]').classList.toggle('oo-tg-locked', !on);
        });
        row.querySelector('[data-oo-tgt]').textContent = open === all.length ? t.unlocked_title : t.gifts_title;
      }
      var del = root.querySelector('[data-oo-tdel] span');
      if (del) del.innerHTML = delivery(t.delivery);
      return p.saving;
    }
  };
})();
