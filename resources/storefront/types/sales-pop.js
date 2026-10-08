/* Growvia · Sales pop. Small recent-purchase notifications on every page, mounted by the
   app embed (no theme block). Purchases come from the store's real orders via OrderOrbit's feed:
   product, country and time only, never names. Auto closes, then waits a (randomised) gap. */
(function () {
  var h = OrderOrbit.h;
  var CHECK = '<path d="m5 12 5 5 9-10"/>';
  var KEY = 'oo_sp_closed';

  function lang() { return document.documentElement.lang || undefined; }

  function country(code) {
    if (!code) return '';
    try { return new Intl.DisplayNames([lang() || 'en'], { type: 'region' }).of(code) || code; } catch (e) { return code; }
  }

  function ago(iso) {
    var s = Math.max(0, (Date.now() - Date.parse(iso)) / 1000);
    var units = [['day', 86400], ['hour', 3600], ['minute', 60]];
    var rtf = null;
    try { rtf = new Intl.RelativeTimeFormat(lang(), { numeric: 'auto' }); } catch (e) { /* old browsers */ }
    for (var i = 0; i < units.length; i++) {
      if (s >= units[i][1]) {
        var n = Math.floor(s / units[i][1]);
        return rtf ? rtf.format(-n, units[i][0]) : n + ' ' + units[i][0] + (n > 1 ? 's' : '') + ' ago';
      }
    }
    return rtf ? rtf.format(0, 'minute') : 'just now';
  }

  function closed() { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } }

  function card(exp, p) {
    var c = exp.content;
    var where = country(p.c);
    var line = String(c.headline || '{buyer}');
    if (!where) line = line.replace(/\s*(?:in|from|,)?\s*\{country\}/gi, '');
    line = h.esc(line).replace(/\{buyer\}/g, h.esc(c.buyer_label || 'Someone')).replace(/\{country\}/g, h.esc(where));
    var img = c.show_image !== false && exp.style !== 'minimal' ? '<span class="oo-sp-img">' + h.productImage({ image: p.i, title: p.t }) + '</span>' : '';
    var meta = [
      c.show_time !== false && p.at ? h.esc(ago(p.at)) : '',
      c.verified_text ? '<span class="oo-sp-ver">' + h.icon(CHECK) + h.esc(c.verified_text) + '</span>' : ''
    ].filter(Boolean).join('<i aria-hidden="true">·</i>');
    var link = c.link_to_product !== false && p.u;
    var tag = link ? 'a' : 'div';
    return '<' + tag + ' class="oo-sp-card"' + (link ? ' href="' + h.esc(p.u) + '" data-oo-click="sales_pop_clicked"' : '') + '>' + img +
      '<span class="oo-sp-text"><span class="oo-sp-who">' + line + '</span>' +
      '<span class="oo-sp-what">' + h.esc(c.action_text || '') + ' <b>' + h.esc(p.t) + '</b></span>' +
      (meta ? '<span class="oo-sp-meta">' + meta + '</span>' : '') + '</span></' + tag + '>';
  }

  function closeButton(c) {
    return c.close_button !== false ? '<button type="button" class="oo-sp-x" aria-label="Close" data-oo-sp-close>×</button>' : '';
  }

  function shuffle(list) {
    for (var i = list.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = list[i]; list[i] = list[j]; list[j] = t;
    }
    return list;
  }

  OrderOrbit.define('sales-pop', function (exp, ctx) {
    var c = exp.content;
    if (ctx.preview) {
      var sample = { t: ctx.productTitle && ctx.productTitle !== 'Sample product' ? ctx.productTitle : 'Glow Serum', i: ctx.productImage || window.OO_SAMPLE_IMAGE, c: 'CA', at: new Date(Date.now() - 12 * 60000).toISOString(), u: '#' };
      return '<div class="oo-sp-stage" data-d="' + h.esc(c.position_desktop || 'bottom-left') + '" data-m="' + h.esc(c.position_mobile || 'bottom') + '">' +
        '<span class="oo-sp-page" aria-hidden="true"><i></i><i></i><i></i></span><div class="oo-sp-box">' + card(exp, sample) + closeButton(c) + '</div></div>';
    }
    if (!(exp.__items || []).length || closed()) return null;
    return '<div class="oo-sp-box" data-oo-sp aria-live="polite"></div>';
  }, {
    prepare: function (exp, ctx) {
      var c = exp.content;
      if (exp.__items || !c.feed) return null;
      return fetch(c.feed, { credentials: 'omit' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var items = (data.items || []).filter(function (p) { return p.t; });
          if (c.order !== 'latest') shuffle(items);
          if (c.match_product && ctx.productHandle) {
            var mine = '/products/' + ctx.productHandle;
            var other = function (p) { return (p.u || '').indexOf(mine) === -1 ? 1 : 0; };
            items.sort(function (a, b) { return other(a) - other(b); });
          }
          exp.__items = items;
        });
    },
    setup: function (root, exp, ctx) {
      if (!root) return;
      var c = exp.content;
      if (ctx.preview) return;
      root.classList.add('oo-sp-d-' + (c.position_desktop || 'bottom-left'), 'oo-sp-m-' + (c.position_mobile || 'bottom'));
      root.style.setProperty('--sp-offset', (c.offset != null ? c.offset : 16) + 'px');

      var mobile = window.matchMedia && window.matchMedia('(max-width: 749px)').matches;
      if ((mobile ? c.position_mobile : c.position_desktop) === 'hide') return;

      var box = root.querySelector('[data-oo-sp]');
      var items = exp.__items || [];
      var max = Math.max(1, Number(c.max_per_page) || 5);
      var i = 0, shown = 0, paused = false, timer = null;

      function gap() {
        var g = Math.max(3, Number(c.interval) || 12) * 1000;
        return c.random_gap !== false ? g * (0.5 + Math.random()) : g;
      }
      function show() {
        if (closed() || shown >= max) return;
        if (i >= items.length) { if (!c.loop) return; i = 0; }
        box.innerHTML = card(exp, items[i++]) + closeButton(c);
        shown++;
        root.classList.add('oo-sp-on');
        timer = setTimeout(hide, Math.max(2, Number(c.display_time) || 6) * 1000);
      }
      function hide() {
        if (paused) { timer = setTimeout(hide, 800); return; }
        root.classList.remove('oo-sp-on');
        timer = setTimeout(show, gap());
      }

      if (c.pause_on_hover !== false) {
        root.addEventListener('mouseenter', function () { paused = true; });
        root.addEventListener('mouseleave', function () { paused = false; });
      }
      root.addEventListener('click', function (e) {
        if (!e.target.closest('[data-oo-sp-close]')) return;
        e.preventDefault();
        try { sessionStorage.setItem(KEY, '1'); } catch (err) { /* private mode */ }
        clearTimeout(timer);
        root.classList.remove('oo-sp-on');
        OrderOrbit.track('experience_closed', exp);
      });
      timer = setTimeout(show, Math.max(0, Number(c.first_delay) || 0) * 1000);
    }
  });
})();
