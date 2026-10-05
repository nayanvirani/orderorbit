/* OrderOrbit · progressive gifts: free gifts, free shipping and order discounts unlocked by cart value
   or item count. Gift lines carry _oo_offer = experience id and _oo_gift = milestone index; the
   OrderOrbit discount makes them free while the milestone is reached. */
(function () {
  var S = OrderOrbit.shop;
  var h = OrderOrbit.h;
  var CHECK = '<path d="m5 12 4.5 4.5L19 7"/>';
  var TRUCK = '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>';
  var TAG = '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="7.5" r="1.5"/>';

  var tried = {};

  function isGift(l, exp) { return l.offer === exp.id && l.gift != null; }

  // Progress in the unlock unit (money or items), gifts excluded.
  function state(exp, ctx) {
    var c = exp.content;
    var lines = ctx.cartLines || [];
    var paid = lines.filter(function (l) { return !isGift(l, exp); });
    var count = c.settings.unlock === 'count';
    var progress = ctx.preview ? (ctx.previewProgress != null ? ctx.previewProgress : count ? 2 : (ctx.cartTotal || 0) / 100)
      : count ? paid.reduce(function (n, l) { return n + l.qty; }, 0)
        : Math.max(0, (ctx.cartTotal || 0) - lines.filter(function (l) { return isGift(l, exp); }).reduce(function (n, l) { return n + (l.price || 0); }, 0)) / 100;
    var ms = c.milestones;
    var next = null;
    ms.forEach(function (m) { if (!next && progress < m.threshold) next = m; });
    var top = ms.length ? ms[ms.length - 1].threshold : 0;
    return { progress: progress, next: next, count: count, pct: top ? Math.min(100, progress / top * 100) : 0, reached: ms.filter(function (m) { return progress >= m.threshold; }).length };
  }

  function amount(v, st, ctx) { return st.count ? v + ' item' + (v === 1 ? '' : 's') : h.money(v, ctx.currency); }

  function iconFor(m, done) { return h.icon(done ? CHECK : m.reward === 'shipping' ? TRUCK : m.reward === 'percent' || m.reward === 'amount' ? TAG : h.GIFT); }

  function message(exp, st, ctx) {
    var c = exp.content.settings;
    if (!st.next) return h.esc(c.unlocked_message);
    return h.fill(c.progress_message, { remaining: amount(Math.round((st.next.threshold - st.progress) * 100) / 100, st, ctx), reward: st.next.label });
  }

  // Bar labels: each gets the room up to its neighbours, so close milestones never overlap.
  // When that room is too tight, labels alternate below and above the bar.
  function barLabels(ms) {
    var top = ms[ms.length - 1].threshold;
    var at = ms.map(function (m) { return m.threshold / top * 100; });
    var room = function (step) {
      return at.map(function (a, i) {
        var prev = i - step >= 0 ? at[i - step] : null;
        var next = i + step < at.length ? at[i + step] : null;
        var left = prev == null ? a : (a - prev) / 2;
        // The last label ends at the bar's end, aligned right.
        if (next == null) return i === at.length - 1 ? left : Math.min(left, 100 - a) * 2;
        return Math.min(left, (next - a) / 2) * 2;
      });
    };
    var w = room(1);
    var stagger = ms.length > 2 && Math.min.apply(null, w) < 24;
    return { at: at, w: stagger ? room(2) : w, stagger: stagger };
  }

  function barHtml(exp, st) {
    var ms = exp.content.milestones;
    var l = barLabels(ms);
    return '<div class="oo-pg-bar' + (l.stagger ? ' oo-pg-stagger' : '') + '"><div class="oo-pg-track"><i style="width:' + st.pct + '%"></i></div>' + ms.map(function (m, i) {
      var done = st.progress >= m.threshold;
      var last = i === ms.length - 1;
      return '<span class="oo-pg-mark' + (done ? ' oo-pg-done' : '') + (last ? ' oo-pg-end' : '') + '" style="--at:' + l.at[i] + '%"><i>' + iconFor(m, done) + '</i></span>' +
        '<small class="oo-pg-lbl' + (last ? ' oo-pg-end' : '') + (l.stagger && i % 2 ? ' oo-pg-up' : '') + (done ? ' oo-pg-done' : '') + '" style="--at:' + l.at[i] + '%;--w:' + l.w[i] + '%">' + h.esc(m.label) + '</small>';
    }).join('') + '</div>';
  }

  function milestonesHtml(exp, st, ctx, cls) {
    return exp.content.milestones.map(function (m) {
      var done = st.progress >= m.threshold;
      return '<span class="' + cls + (done ? ' oo-pg-done' : '') + '" style="--at:' + (m.threshold / exp.content.milestones[exp.content.milestones.length - 1].threshold * 100) + '%">' +
        '<i>' + iconFor(m, done) + '</i><small>' + h.esc(m.label) + '</small></span>';
    }).join('');
  }

  // Gifts to claim (or pick, for "choose your gift") once a milestone is reached.
  function giftsHtml(exp, st, ctx) {
    var claimed = (ctx.cartLines || []).filter(function (l) { return isGift(l, exp); }).map(function (l) { return String(l.gift); });
    return exp.content.milestones.map(function (m) {
      if ((m.reward !== 'gift' && m.reward !== 'choice') || st.progress < m.threshold || !m.products.length) return '';
      if (claimed.indexOf(String(m.index)) !== -1) return '<p class="oo-pg-claimed">' + h.icon(CHECK) + ' ' + h.esc(m.label) + ': in your cart</p>';
      if (m.reward === 'gift' && exp.content.settings.claim === 'auto') return '';
      return '<div class="oo-pg-pick"><p>' + h.esc(m.reward === 'choice' ? 'Choose your gift' : m.label) + '</p><div class="oo-pg-picks">' + m.products.map(function (p, j) {
        return '<button type="button" class="oo-pg-gift" data-oo-gift="' + m.index + ':' + j + '">' + h.productImage(p) + '<span>' + h.esc(p.title) + '</span><b>FREE</b></button>';
      }).join('') + '</div></div>';
    }).join('');
  }

  function vars(d) {
    return h.esc('--pg-accent:' + (d.accent || '#111') + ';--pg-track:' + (d.track || '#e7e7e7') + ';--pg-muted:' + (d.muted || '#8a8a8a') + ';--pg-line:' + (d.border || '#e3e3e3') +
      ';--pg-h:' + (d.bar_height || 8) + 'px;--pg-fs:' + (d.title_size || 16) + 'px');
  }

  OrderOrbit.define('progressive-gifts', function (exp, ctx) {
    var c = exp.content;
    if (!c || !c.milestones || !c.milestones.length) return null;
    var st = state(exp, ctx);
    if (!st.progress && !c.settings.show_empty && !ctx.preview) return null;
    var layout = c.settings.layout;
    var head = (c.settings.title ? '<p class="oo-pg-title">' + h.esc(c.settings.title) + '</p>' : '') + '<p class="oo-pg-msg" role="status">' + message(exp, st, ctx) + '</p>';
    var body;
    if (layout === 'minimal') {
      body = '<div class="oo-pg-track"><i style="width:' + st.pct + '%"></i></div><div class="oo-pg-ends"><span>' + h.esc(amount(Math.min(st.progress, c.milestones[c.milestones.length - 1].threshold), st, ctx)) + '</span><span>' + h.esc((st.next || c.milestones[c.milestones.length - 1]).label) + '</span></div>';
    } else if (layout === 'steps') {
      body = '<div class="oo-pg-steps">' + milestonesHtml(exp, st, ctx, 'oo-pg-step') + '</div>';
    } else if (layout === 'cards') {
      body = '<div class="oo-pg-cards">' + milestonesHtml(exp, st, ctx, 'oo-pg-card') + '</div>';
    } else if (layout === 'radial') {
      var deg = Math.round(st.reached / c.milestones.length * 360);
      body = '<div class="oo-pg-radial"><span class="oo-pg-ring" style="--deg:' + deg + 'deg"><b>' + st.reached + '/' + c.milestones.length + '</b></span><div class="oo-pg-list">' + milestonesHtml(exp, st, ctx, 'oo-pg-li') + '</div></div>';
      return '<div class="oo-body oo-pg oo-pg-l-radial" style="' + vars(exp.design || {}) + '">' + body.replace('<div class="oo-pg-list">', '<div class="oo-pg-list">' + head) + giftsHtml(exp, st, ctx) + '<p class="oo-status" data-oo-status role="status"></p></div>';
    } else {
      body = barHtml(exp, st);
    }
    return '<div class="oo-body oo-pg oo-pg-l-' + layout + '" style="' + vars(exp.design || {}) + '">' + head + body + giftsHtml(exp, st, ctx) + '<p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    prepare: function (exp) {
      return Promise.all(exp.content.milestones.map(function (m) { return S.hydrate({ content: { p: m.products } }, ['p']); }));
    },
    setup: function (root, exp, ctx) {
      if (!root) return;
      var c = exp.content;
      var st = state(exp, ctx);
      var stay = Object.assign({}, exp, { behavior: Object.assign({}, exp.behavior, { after_add: 'stay' }) });
      var addGift = function (m, j, btn) {
        var p = m.products[j];
        return S.add(stay, ctx, [{ id: p.variant_id, quantity: m.quantity || 1, properties: { _oo_gift: String(m.index) } }], btn, root);
      };
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-oo-gift]');
        if (!btn) return;
        var parts = btn.getAttribute('data-oo-gift').split(':');
        var m = c.milestones.filter(function (x) { return String(x.index) === parts[0]; })[0];
        if (m) addGift(m, Number(parts[1]), btn);
      });
      if (ctx.preview) return;

      // Each milestone reports "reward unlocked" once per session.
      c.milestones.forEach(function (m) {
        if (st.progress < m.threshold) return;
        var seen = 'oo_pgu_' + exp.id + '_' + m.index;
        try { if (sessionStorage.getItem(seen)) return; sessionStorage.setItem(seen, '1'); } catch (err) { return; }
        OrderOrbit.track('reward_unlocked', exp, { milestone: m.index, reward: m.reward });
      });

      var gifts = (ctx.cartLines || []).filter(function (l) { return isGift(l, exp); });
      // oo-gift-lock.js (loaded by the core while the cart holds a gift) takes out gifts whose milestone
      // is no longer reached and trims extra units; wait for it before adding more.
      var settled = c.milestones.every(function (m) {
        var units = gifts.filter(function (l) { return String(l.gift) === String(m.index); }).reduce(function (n, l) { return n + l.qty; }, 0);
        return units <= (st.progress >= m.threshold ? (m.quantity || 1) : 0);
      });
      if (!settled) return;
      // Auto mode adds each reached gift that isn't in the cart. Shoppers can't remove gifts (oo-gift-lock.js),
      // so a missing one was taken out when the cart dropped below its milestone: add it again once it's
      // reached. One try per gift per page load, so an unavailable gift doesn't loop.
      if (c.settings.claim === 'auto') {
        c.milestones.forEach(function (m) {
          if (m.reward !== 'gift' || st.progress < m.threshold || !m.products.length) return;
          if (gifts.some(function (l) { return String(l.gift) === String(m.index); })) return;
          var key = exp.id + '_' + m.index;
          if (tried[key]) return;
          tried[key] = true;
          addGift(m, 0, null);
        });
      }
    }
  });
})();
