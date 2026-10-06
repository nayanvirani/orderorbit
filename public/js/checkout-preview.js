/*
 * In-app previews for checkout, Thank You, Order Status and customer account blocks. The real blocks
 * are drawn by Shopify's UI extensions (orderorbit-checkout, orderorbit-account) with Shopify's own
 * components and the store's checkout branding; these previews give merchants a close, neutral
 * likeness of each layout: the same banners, boxes, icons, badges, grids and thumbnails.
 */
(function () {
  if (!window.OrderOrbit) return;
  var h = OrderOrbit.h;
  var SAMPLE_SVG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Crect width='120' height='120' fill='%23f1ece6'/%3E%3Crect x='46' y='26' width='28' height='12' rx='3' fill='%23cdbfae'/%3E%3Crect x='38' y='38' width='44' height='58' rx='10' fill='%23e2d6c8'/%3E%3C/svg%3E";
  var img = function () { return window.OO_SAMPLE_IMAGE || SAMPLE_SVG; };
  var SAMPLE_REVIEWS = [
    { author: 'Sample: Alex R.', rating: 5, quote: 'Your reviews appear here. Add real ones in the Content step.' },
    { author: 'Sample: Sam T.', rating: 5, quote: 'Shoppers see these right next to the pay button.' }
  ];
  var SAMPLE_MILESTONES = [
    { threshold: 50, reward: 'shipping', label: 'free shipping' },
    { threshold: 80, reward: 'gift', label: 'a free gift', product: { title: 'Travel-size serum' } },
    { threshold: 120, reward: 'percent', label: '10% off' }
  ];
  var SAMPLE_PRODUCTS = [{ title: 'Glow Serum', n: 5, price: 29 }, { title: 'Night Cream', n: 1, price: 34 }, { title: 'Travel Kit', n: 2, price: 19 }];

  // Close likenesses of Shopify's checkout tokens, for the Design step's box styles.
  var RADIUS = { none: '0', 'small-100': '2px', small: '4px', base: '6px', large: '10px', 'large-100': '14px', max: '999px' };
  var BORDER = { none: '0', base: '1px', large: '2px', 'large-100': '3px', 'large-200': '4px' };
  var PAD = { none: '0', small: '8px', base: '14px', large: '20px', 'large-200': '28px' };
  var TONE = { info: '#0b5cad', success: '#0c7a43', warning: '#8a5a00', critical: '#c5281c' };
  // Layouts drawn as a banner or without a frame unless the Design step sets a box style (as live).
  var BANNER = ['banner', 'announcement', 'unlocked', 'loyalty'];
  var PLAIN = ['compact', 'row', 'button', 'simple', 'plain', 'support'];
  var SUBDUED = ['premium', 'slider', 'education', 'faq'];

  // Shopify's icon set, drawn as line icons.
  var ICONS = {
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    delivery: '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8C10 4 6 4 6 6.5S9 8 12 8c3 0 6 0 6-1.5S14 4 12 8"/>',
    discount: '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    'return': '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
    check: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    question: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12 17h.01"/>',
    star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
    email: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    book: '<path d="M4 19V5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2 2 2 0 0 0 2 2h14"/>',
    repeat: '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>',
    heart: '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
    circle: '<circle cx="12" cy="12" r="8" stroke-dasharray="3 3"/>',
    megaphone: '<path d="M3 10v4h4l8 5V5L7 10z"/><path d="M18 9a4 4 0 0 1 0 6"/>',
    alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
    chat: '<path d="M4 5h16v11H9l-5 4z"/>',
    share: '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8 11 8-4M8 13l8 4"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3"/>',
    leaf: '<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M5 19 13 11"/>',
    shield: '<path d="M12 3 4 6v6c0 4.5 3.4 8.2 8 9 4.6-.8 8-4.5 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>'
  };
  var BADGE_ICON = { shipping: 'delivery', worldwide: 'globe', returns: 'return', secure: 'lock', guarantee: 'check', support: 'question', quality: 'star', natural: 'leaf', love: 'heart', gift: 'gift', shield: 'shield', delivery: 'delivery', star: 'star' };
  var SAMPLE_UPSELL = [{ title: 'Blending brush', price: 29, compare_at: 44 }];

  function icon(name, tone, size) {
    return '<svg class="ck-icon' + (size ? ' ck-icon-' + size : '') + (tone ? ' ck-t-' + tone : '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || ICONS.check) + '</svg>';
  }
  function badge(text, ic, tone) { return '<span class="ck-badge' + (tone ? ' ck-badge-' + tone : '') + '">' + (ic ? icon(ic) : '') + h.esc(text) + '</span>'; }
  function thumb(p, size) { return '<span class="ck-thumb' + (size ? ' ck-thumb-' + size : '') + '"><img src="' + h.esc((p && p.image) || img()) + '" alt=""></span>'; }
  function title(text) { return text ? '<p class="ck-title">' + h.esc(text) + '</p>' : ''; }
  function heading(text) { return text ? '<p class="ck-heading">' + h.esc(text) + '</p>' : ''; }
  function para(text, cls) { return text ? '<p class="ck-text' + (cls ? ' ' + cls : '') + '">' + h.esc(text) + '</p>' : ''; }
  function muted(html) { return '<p class="ck-muted">' + html + '</p>'; }
  function button(text, primary) { return text ? '<span class="ck-btn' + (primary ? ' ck-primary' : '') + '">' + h.esc(text) + '</span>' : ''; }
  function link(text) { return text ? '<u class="ck-link">' + h.esc(text) + '</u>' : ''; }
  function row(html, cls) { return '<div class="ck-row' + (cls ? ' ' + cls : '') + '">' + html + '</div>'; }
  function col(html, cls) { return '<div class="ck-col' + (cls ? ' ' + cls : '') + '">' + html + '</div>'; }
  function grid(items, n) { return '<div class="ck-grid" style="grid-template-columns:repeat(' + n + ',minmax(0,1fr))">' + items.join('') + '</div>'; }
  function tile(html) { return '<div class="ck-tile">' + html + '</div>'; }
  function divided(items) { return items.join('<hr class="ck-divider">'); }
  function bar(pct) { return '<div class="ck-bar"><i style="width:' + Math.max(0, Math.min(100, pct)) + '%"></i></div>'; }
  function stars(n, tone) { var out = ''; for (var i = 1; i <= 5; i++) out += i <= Math.round(n || 0) ? '★' : '☆'; return '<span class="ck-stars' + (tone ? ' ck-t-' + tone : '') + '">' + out + '</span>'; }
  function fill(tpl, vars) { return h.esc(tpl || '').replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? '<b>' + h.esc(vars[k]) + '</b>' : m; }); }
  function note(text) { return '<p class="ck-note">' + h.esc(text) + '</p>'; }

  function size(kind, value) {
    var n = Math.round(Number(value) || 0);
    if (kind === 'px' && n > 0) return n + 'px';
    if (kind === 'percent' && n > 0) return Math.min(100, n) + '%';
    return '';
  }

  /**
   * The block's frame, as live: a toned banner, a plain stack or a box. `as` asks for a banner,
   * plain or subdued frame for this layout; any box style from the Design step turns it into a box.
   */
  function frame(exp, opts, body) {
    opts = opts || {};
    var d = exp.design || {};
    var set = function (k) { return d[k] && d[k] !== 'auto'; };
    var style = exp.style || 'card';
    var custom = set('ck_background') || set('ck_border') || set('ck_radius') || set('ck_padding');
    // "Show as" and "Banner colour" from the Design step (Shopify's four banner tones).
    var forced = d.ck_frame && d.ck_frame !== 'auto' ? d.ck_frame : null;
    var kind = forced || (!custom && (opts.as === 'banner' || BANNER.indexOf(style) !== -1) ? 'banner'
      : !custom && (opts.as === 'plain' || PLAIN.indexOf(style) !== -1) ? 'plain' : 'box');
    if (set('ck_banner_tone')) opts = Object.assign({}, opts, { tone: d.ck_banner_tone });
    var css = [];
    if (kind === 'box') {
      var plain = !forced && (PLAIN.indexOf(style) !== -1 || opts.as === 'plain');
      var bg = set('ck_background') ? d.ck_background : opts.as === 'subdued' || SUBDUED.indexOf(style) !== -1 || BANNER.indexOf(style) !== -1 ? 'subdued' : 'base';
      var border = set('ck_border') ? d.ck_border : plain ? 'none' : 'base';
      css.push('background:' + (bg === 'subdued' ? '#f4f4f4' : bg === 'transparent' ? 'transparent' : '#fff'));
      css.push('border:' + (BORDER[border] || '0') + ' ' + (d.ck_border_style || 'solid') + ' #dedede');
      css.push('border-radius:' + RADIUS[set('ck_radius') ? d.ck_radius : plain ? 'none' : 'large']);
      css.push('padding:' + PAD[set('ck_padding') ? d.ck_padding : plain ? 'none' : 'base']);
    }
    var w = d.ck_width && d.ck_width !== 'full' ? size(d.ck_width, d.ck_width_value) : '';
    if (w) css.push('width:' + w + ';max-width:100%');
    if (d.ck_height === 'px') css.push('min-height:' + size('px', d.ck_height_value) + ';align-content:start');
    if (d.ck_tone && TONE[d.ck_tone]) css.push('--ck-text:' + TONE[d.ck_tone]);
    var cls = 'ck ck-' + kind + ' ck-s-' + h.esc(style) + (d.ck_text === 'subdued' ? ' ck-subdued' : '') + (d.ck_tone && TONE[d.ck_tone] ? ' ck-toned' : '');
    var head = opts.heading ? (kind === 'plain' ? title(opts.heading) : heading(opts.heading)) : '';
    if (kind === 'banner') {
      var tone = opts.tone || 'info';
      return '<div class="' + cls + ' ck-tone-' + tone + '" style="' + css.join(';') + '">' + icon(opts.bannerIcon || { info: 'info', success: 'check', warning: 'alert', critical: 'alert' }[tone], tone) +
        '<div class="ck-stack">' + head + body + '</div></div>';
    }
    return '<div class="' + cls + '" style="' + css.join(';') + '">' + head + body + '</div>';
  }

  function image(exp) {
    var c = exp.content;
    var src = c.image || img();
    var width = c.img_width === 'px' || c.img_width === 'percent' ? size(c.img_width, c.img_width_value) : '100%';
    var css = ['width:100%', 'display:block', 'border-radius:' + (RADIUS[c.img_radius || 'base'] || '6px')];
    if (BORDER[c.img_border] && c.img_border !== 'none') css.push('border:' + BORDER[c.img_border] + ' ' + (c.img_border_style || 'solid') + ' #cfcfcf');
    if (c.img_height === 'ratio') css.push('aspect-ratio:' + (c.img_ratio || '16/9'), 'object-fit:' + (c.img_fit || 'cover'));
    if (c.img_height === 'px') css.push('height:' + Math.max(20, Number(c.img_height_value) || 200) + 'px', 'object-fit:' + (c.img_fit || 'cover'));
    if (!c.image && c.img_height === 'natural' && c.img_width === 'fill') css.push('aspect-ratio:2/1', 'object-fit:cover');
    var align = { start: 'start', center: 'center', end: 'end' }[c.align] || 'center';
    return frame(exp, {}, '<div class="ck-image" style="justify-items:' + align + '"><div style="width:' + width + ';max-width:100%"><img src="' + h.esc(src) + '" alt="' + h.esc(c.alt || '') + '" style="' + css.join(';') + '"></div>' +
      (c.caption ? '<p class="ck-muted">' + h.esc(c.caption) + '</p>' : '') + '</div>' + (c.image ? '' : note('Sample image. Upload yours in the Content step.')));
  }

  // Progressive gifts in checkout, on a sample campaign (live, it follows the merchant's).
  function giftState(exp, ctx, filter, subtotal) {
    var ms = SAMPLE_MILESTONES.filter(filter);
    var next = ms.filter(function (m) { return subtotal < m.threshold; })[0];
    var top = ms.length ? ms[ms.length - 1].threshold : 1;
    return { ms: ms, next: next, value: subtotal, pct: Math.round(subtotal / top * 100), amount: function (v) { return h.money(v, ctx.currency); } };
  }
  var SAMPLE_NOTE = 'Preview with a sample Progressive gifts campaign. Live, it follows yours.';

  var R = {
    'checkout-reviews': function (exp) {
      var c = exp.content;
      var sample = !(c.reviews && c.reviews.length);
      var list = sample ? SAMPLE_REVIEWS : c.reviews;
      var rating = c.rating || (sample ? 4.9 : null), count = c.review_count || (sample ? 1240 : null);
      var summary = rating ? row(stars(rating, 'warning') + '<span class="ck-text"><b>' + h.esc(rating) + '</b>' + (count ? ' · ' + h.esc(Number(count).toLocaleString()) + ' reviews' : '') + '</span>') : '';
      var quote = function (r) { return col(stars(r.rating, 'warning') + (r.title ? '<p class="ck-text"><b>' + h.esc(r.title) + '</b></p>' : '') + para('“' + r.quote + '”') + muted(h.esc([r.author, r.date].filter(Boolean).join(', '))), 'ck-tight'); };
      var foot = c.footer ? muted(h.esc(c.footer)) : '';
      if (c.summary_label) summary = row('<span class="ck-text"><b>' + h.esc(c.summary_label) + '</b></span>' + stars(rating || 5, 'warning') + (count ? '<span class="ck-muted">' + h.esc(Number(count).toLocaleString()) + ' reviews</span>' : ''));
      if (exp.style === 'carousel') {
        var cur = list[0];
        if (sample) cur = { author: 'Sample: Deborah W.', rating: 5, title: 'Smooches to us!', quote: 'Your reviews appear here. Add real ones in the Content step.', date: 'Jun 5, 2026' };
        return frame(exp, { as: 'plain' }, row('<p class="ck-heading">' + h.esc(c.summary_label || 'Excellent') + '</p>' + stars(5, 'warning'), 'ck-center-row') +
          '<div class="ck-review-card">' + quote(cur) + '</div>' + row(button('←', false) + button('→', false), 'ck-center-row') + (foot ? col(foot, 'ck-center') : ''));
      }
      if (exp.style === 'card') {
        return frame(exp, { heading: c.headline }, summary + grid(list.slice(0, 2).map(function (r) { return tile(quote(r)); }), 2) + foot);
      }
      if (exp.style === 'slider') {
        var r = list[0];
        return frame(exp, {}, col(stars(r.rating, 'warning') + para('“' + r.quote + '”', 'ck-lead') + muted(h.esc(r.author)), 'ck-center') +
          row(button('‹', false) + '<span class="ck-muted">1 / ' + list.length + '</span>' + button('›', false), 'ck-center-row'));
      }
      if (exp.style === 'premium') {
        var top = list[0];
        return frame(exp, {}, row(col('<p class="ck-big">' + h.esc(rating || 5) + '</p>', 'ck-shrink') + col(stars(rating || 5, 'warning') + muted(h.esc(c.headline) + (count ? ' · ' + h.esc(Number(count).toLocaleString()) + ' reviews' : '')))) +
          '<hr class="ck-divider">' + para('“' + top.quote + '”', 'ck-lead') + row(muted(h.esc(top.author)) + badge('Verified buyer', 'check')));
      }
      return frame(exp, { as: 'plain' }, row(title(c.headline) + summary, 'ck-between') + divided(list.map(quote)) + foot);
    },

    'checkout-countdown': function (exp) {
      var c = exp.content;
      var timed = c.mode === 'hours' || c.mode === 'minutes';
      var length = timed ? (c.mode === 'hours' ? (Number(c.hours) || 1) * 3600e3 : (Number(c.minutes) || 1) * 60e3) : 0;
      var left = timed ? length : c.ends_at ? Math.max(0, Date.parse(c.ends_at) - Date.now()) : 2 * 3600e3 + 14 * 60e3 + 37e3;
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      var parts = [Math.floor(left / 3600e3), Math.floor(left % 3600e3 / 60e3), Math.floor(left % 60e3 / 1e3)].map(pad);
      var time = parts[0] === '00' ? Number(parts[1]) + ':' + parts[2] : parts.join(':');
      var info = timed ? note('Starts when each shopper reaches checkout' + (c.repeat === 'end' ? ', then stops.' : ' and starts again every ' + (c.mode === 'hours' ? (Number(c.hours) || 1) + ' h.' : (Number(c.minutes) || 1) + ' min.'))) : '';
      if (exp.style === 'reserved') return frame(exp, { as: 'banner', tone: 'success', bannerIcon: 'check' }, '<p class="ck-text"><b>' + h.esc(c.headline) + ' ' + time + '</b></p>') + info;
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'warning', bannerIcon: 'clock' }, para('Ends in ' + time) + info);
      if (exp.style === 'card') {
        return frame(exp, { heading: c.headline }, grid(['Hours', 'Minutes', 'Seconds'].map(function (l, i) { return tile('<p class="ck-big">' + parts[i] + '</p><p class="ck-muted">' + l + '</p>'); }), 3) + info);
      }
      if (exp.style === 'premium') {
        return frame(exp, {}, col(badge('Limited time', 'clock', 'critical') + '<p class="ck-big ck-t-critical">' + time + '</p>' + muted(h.esc(c.headline)), 'ck-center') + info);
      }
      return frame(exp, {}, row(icon('clock', 'critical') + '<p class="ck-text ck-grow"><b>' + h.esc(c.headline) + '</b></p><p class="ck-timer ck-t-critical">' + time + '</p>') + info);
    },

    'checkout-shipping': function (exp, ctx) {
      var c = exp.content;
      var st = giftState(exp, ctx, function (m) { return c.milestones === 'all' || m.reward === 'shipping'; }, (ctx.cartTotal || 4500) / 100);
      var msg = st.next ? fill(c.progress_message, { remaining: st.amount(st.next.threshold - st.value), reward: st.next.label }) : fill(c.unlocked_message, { reward: st.ms[st.ms.length - 1].label });
      if (exp.style === 'ladder') {
        return frame(exp, {}, para(msg.replace(/<\/?b>/g, '')) + divided(st.ms.map(function (m) {
          var done = st.value >= m.threshold;
          return row(icon(done ? 'check' : 'circle', done ? 'success' : null) + '<p class="ck-text ck-grow"><b>' + h.esc(m.label.charAt(0).toUpperCase() + m.label.slice(1)) + '</b></p>' +
            (done ? '<span class="ck-text ck-t-success">Unlocked</span>' : '<span class="ck-muted">' + h.esc(st.amount(m.threshold)) + '</span>'));
        })) + note(SAMPLE_NOTE));
      }
      if (exp.style === 'multi') {
        return frame(exp, {}, '<p class="ck-text">' + msg + '</p>' + bar(st.pct) + row(st.ms.map(function (m) {
          return badge(st.amount(m.threshold) + ' · ' + m.label, st.value >= m.threshold ? 'check' : null, st.value >= m.threshold ? null : 'subdued');
        }).join(''), 'ck-wrap') + note(SAMPLE_NOTE));
      }
      return frame(exp, {}, row(icon('delivery', 'info', 'large') + col('<p class="ck-text">' + msg + '</p>' + bar(st.pct), 'ck-grow')) + note(SAMPLE_NOTE));
    },

    'checkout-gift': function (exp, ctx) {
      var c = exp.content;
      var unlocked = exp.style === 'unlocked';
      var st = giftState(exp, ctx, function (m) { return m.reward === 'gift'; }, unlocked ? 92 : (ctx.cartTotal || 4500) / 100);
      var gift = st.ms[0];
      var msg = fill(c.progress_message, { remaining: st.amount(gift.threshold - st.value), reward: gift.label });
      if (unlocked) {
        return frame(exp, { heading: c.unlocked_message, tone: 'success', bannerIcon: 'gift' }, row(thumb(gift.product) + col('<p class="ck-text"><b>' + h.esc(gift.product.title) + '</b></p>' + muted('Free · ' + h.esc(st.amount(0))), 'ck-grow') + button(c.button_text, true)) + note(SAMPLE_NOTE));
      }
      if (exp.style === 'card') {
        return frame(exp, {}, row(thumb(gift.product, 'large') + col(badge('Free gift', 'gift') + '<p class="ck-text"><b>' + h.esc(gift.product.title) + '</b></p>' + '<p class="ck-muted">' + msg + '</p>' + bar(st.pct), 'ck-grow')) + note(SAMPLE_NOTE));
      }
      return frame(exp, {}, row(icon('gift', null, 'large') + '<p class="ck-text ck-grow">' + msg + '</p><span class="ck-muted">' + st.pct + '%</span>') + bar(st.pct) + note(SAMPLE_NOTE));
    },

    'checkout-promo': function (exp) {
      var c = exp.content;
      var sample = !c.headline;
      var headline = c.headline || 'Members save 15% today';
      var message = c.message || (sample ? 'Applies to everything in your cart.' : '');
      var code = c.code || (sample ? 'SAVE15' : '');
      var codeRow = code ? row('<span class="ck-code">' + h.esc(code) + '</span>' + button(c.apply_text, false)) : '';
      if (exp.style === 'announcement') return frame(exp, { heading: headline, tone: 'info', bannerIcon: 'megaphone' }, para(message) + (code ? para('Use code ' + code + ' at checkout.') : ''));
      if (exp.style === 'banner') return frame(exp, { heading: headline, tone: 'success', bannerIcon: 'discount' }, para(message) + (code ? row('<span class="ck-text"><b>' + h.esc(code) + '</b></span>' + button(c.apply_text, true)) : ''));
      if (exp.style === 'premium') {
        return frame(exp, {}, col(badge('Exclusive offer', 'star') + heading(headline) + para(message, 'ck-muted-text') + (code ? '<div class="ck-dashed"><span class="ck-code-big">' + h.esc(code) + '</span>' + button(c.apply_text, true) + '</div>' : ''), 'ck-center'));
      }
      return frame(exp, {}, row(icon('discount', 'info', 'large') + col(heading(headline) + para(message), 'ck-grow')) + codeRow);
    },

    'checkout-trust': function (exp) {
      var c = exp.content;
      var badges = c.badges || [];
      var desc = function (b) { return b.description ? '<p class="ck-muted">' + h.esc(b.description) + '</p>' : ''; };
      if (exp.style === 'benefits') {
        return frame(exp, { as: 'plain' }, (c.headline ? col(muted(h.esc(c.headline)), 'ck-center') : '') + badges.map(function (b) { return row(icon(BADGE_ICON[b.icon] || 'check', null, 'large') + col('<p class="ck-text">' + h.esc(b.label) + '</p>' + desc(b), 'ck-tight')); }).join('') + para(c.guarantee, 'ck-muted-text'));
      }
      if (exp.style === 'grid') {
        return frame(exp, { heading: c.headline }, grid(badges.map(function (b) { return tile(col(icon(BADGE_ICON[b.icon] || 'check', null, 'large') + '<p class="ck-text"><b>' + h.esc(b.label) + '</b></p>' + desc(b), 'ck-center')); }), Math.min(3, badges.length || 1)) + para(c.guarantee, 'ck-muted-text'));
      }
      if (exp.style === 'card') {
        return frame(exp, { heading: c.headline || 'Why shop with us' }, divided(badges.map(function (b) { return row(icon(BADGE_ICON[b.icon] || 'check', 'success') + col('<p class="ck-text">' + h.esc(b.label) + '</p>' + desc(b), 'ck-grow ck-tight') + icon('check', 'success')); })) + para(c.guarantee, 'ck-muted-text'));
      }
      if (exp.style === 'banner') {
        return frame(exp, { heading: c.headline || '30-day money-back guarantee', tone: 'success', bannerIcon: 'lock' }, para(c.guarantee || 'Not happy? Send it back within 30 days for a full refund.') + row(badges.map(function (b) { return '<span class="ck-muted">✓ ' + h.esc(b.label) + '</span>'; }).join(''), 'ck-wrap'));
      }
      return frame(exp, { heading: c.headline }, row(badges.map(function (b) { return row(icon(BADGE_ICON[b.icon] || 'check', 'success') + '<span class="ck-text">' + h.esc(b.label) + '</span>', 'ck-tight-row'); }).join(''), 'ck-wrap ck-spread') + para(c.guarantee, 'ck-muted-text'));
    },

    'ty-cross-sell': function (exp, ctx) {
      var c = exp.content;
      var list = (c.products && c.products.length ? c.products : SAMPLE_PRODUCTS).slice(0, exp.style === 'featured' ? 1 : exp.style === 'cards' ? 3 : 3);
      var price = function (p) { return p.price != null ? h.money(p.price, ctx.currency) : ''; };
      if (exp.style === 'featured') {
        var p = list[0];
        return frame(exp, { as: 'subdued' }, row('<span class="ck-hero"><img src="' + h.esc(p.image || img()) + '" alt=""></span>' + col(badge('Picked for you', 'star') + heading(p.title) + '<p class="ck-text">' + h.esc(price(p)) + '</p>' + para(c.message || c.headline, 'ck-muted-text') + button(c.button_text, true), 'ck-grow')));
      }
      if (exp.style === 'list') {
        return frame(exp, { heading: c.headline }, para(c.message) + divided(list.map(function (p) { return row(thumb(p, 'small') + col('<p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + muted(h.esc(price(p))), 'ck-grow') + button(c.button_text, false)); })));
      }
      return frame(exp, { heading: c.headline }, para(c.message) + grid(list.map(function (p) {
        return col('<span class="ck-cover"><img src="' + h.esc(p.image || img()) + '" alt=""></span><p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + muted(h.esc(price(p))) + link(c.button_text), 'ck-tight');
      }), list.length));
    },

    'ty-reorder': function (exp) {
      var c = exp.content;
      if (exp.style === 'button') return frame(exp, {}, row(icon('repeat', 'info', 'large') + col('<p class="ck-text"><b>' + h.esc(c.headline) + '</b></p>' + muted(h.esc(c.message)), 'ck-grow') + button(c.button_text, true)));
      return frame(exp, { heading: c.headline }, para(c.message) + row(SAMPLE_PRODUCTS.slice(0, 3).map(function (p) { return thumb(p); }).join('') + '<span class="ck-muted">3 items from this order</span>') + button(c.button_text, true, true).replace('ck-btn', 'ck-btn ck-block'));
    },

    'ty-review': function (exp) {
      var c = exp.content;
      if (exp.style === 'products') {
        return frame(exp, { heading: c.headline }, para(c.message) + divided(SAMPLE_PRODUCTS.slice(0, 2).map(function (p) { return row(thumb(p, 'small') + '<p class="ck-text ck-grow"><b>' + h.esc(p.title) + '</b></p>' + stars(0) + link('Review')); })));
      }
      return frame(exp, {}, col('<p class="ck-stars ck-stars-big ck-t-warning">★★★★★</p>' + heading(c.headline) + para(c.message, 'ck-muted-text') + button(c.button_text, true), 'ck-center'));
    },

    'ty-referral': function (exp) {
      var c = exp.content;
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'info', bannerIcon: 'share' }, para(c.message) + row('<span class="ck-text"><b>' + h.esc(c.code || 'FRIEND10') + '</b></span>' + link('Share')));
      return frame(exp, {}, col(icon('share', 'info', 'large') + heading(c.headline) + para(c.message, 'ck-muted-text') + '<div class="ck-dashed"><span class="ck-code-big">' + h.esc(c.code || 'FRIEND10') + '</span>' + button('Share', true) + '</div>', 'ck-center'));
    },

    'ty-survey': function (exp) {
      var c = exp.content;
      var options = c.options || [];
      if (exp.style === 'card') {
        return frame(exp, { as: 'subdued' }, row(icon('chat', 'info') + title(c.question)) + grid(options.map(function (o) { return button(o.label, false).replace('ck-btn', 'ck-btn ck-block'); }), 2) + muted('Tap an answer to send it.'));
      }
      return frame(exp, { heading: c.question }, '<ul class="ck-choices">' + options.map(function (o, i) { return '<li><span class="ck-radio' + (i === 0 ? ' on' : '') + '"></span>' + h.esc(o.label) + '</li>'; }).join('') + '</ul>' + button(c.button_text, true));
    },

    'ty-discount': function (exp) {
      var c = exp.content;
      var code = c.code || 'THANKYOU10';
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'success', bannerIcon: 'discount' }, para(c.message) + row('<span class="ck-text"><b>' + h.esc(code) + '</b></span>' + (c.expiry_text ? muted(h.esc(c.expiry_text)) : '')));
      return frame(exp, {}, col(icon('gift', 'success', 'large') + heading(c.headline) + para(c.message, 'ck-muted-text') + '<div class="ck-dashed"><span class="ck-code-big">' + h.esc(code) + '</span></div>' + (c.expiry_text ? muted(h.esc(c.expiry_text)) : ''), 'ck-center'));
    },

    'ty-message': function (exp) {
      var c = exp.content;
      var headline = c.headline || 'Your headline';
      if (exp.style === 'loyalty') return frame(exp, { heading: headline, tone: 'success', bannerIcon: 'star' }, para(c.message) + (c.button_text ? link(c.button_text) : ''));
      if (exp.style === 'education') return frame(exp, {}, row(icon('book', 'info', 'large') + col(heading(headline) + para(c.message, 'ck-muted-text') + button(c.button_text, false), 'ck-grow')));
      if (exp.style === 'support') return frame(exp, {}, '<hr class="ck-divider">' + row(icon('question', null, 'large') + col('<p class="ck-text"><b>' + h.esc(headline) + '</b></p>' + muted(h.esc(c.message)), 'ck-grow') + link(c.button_text)));
      return frame(exp, { heading: headline }, para(c.message) + button(c.button_text, false));
    },

    'post-purchase': function (exp, ctx) {
      var c = exp.content;
      var p = (c.offer_product || [])[0] || { title: 'Your offer product', price: 29 };
      var price = Number(p.price || 29), pct = Number(c.discount_percent || 0), now = price * (1 - pct / 100);
      var priceLine = '<p class="ck-text">' + (pct ? '<s class="ck-muted">' + h.esc(h.money(price, ctx.currency)) + '</s> ' : '') + '<b>' + h.esc(h.money(now, ctx.currency)) + '</b>' + (pct ? ' · ' + pct + '% off' : '') + '</p>';
      var second = c.downsell ? note('If they decline: “' + c.downsell_headline + '” with ' + (c.downsell_discount || 0) + '% off ' + (((c.downsell_product || [])[0] || {}).title || 'the second offer') + '.') : '';
      var accept = button(c.accept_text + ' · ' + h.money(now, ctx.currency), true).replace('ck-btn', 'ck-btn ck-block');
      if (exp.style === 'premium') {
        return frame(exp, {}, '<div class="ck-dark">' + heading(c.headline) + para(c.message) + '</div>' + row('<span class="ck-hero"><img src="' + h.esc(p.image || img()) + '" alt=""></span>' + col(heading(p.title) + priceLine + accept + muted(h.esc(c.decline_text)), 'ck-grow')) + second);
      }
      if (exp.style === 'minimal') return frame(exp, { heading: c.headline }, '<p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + priceLine + accept + muted(h.esc(c.decline_text)) + second);
      return frame(exp, { heading: c.headline }, row(thumb(p, 'large') + col('<p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + para(c.message, 'ck-muted-text') + priceLine, 'ck-grow')) + accept + muted(h.esc(c.decline_text)) + second);
    },

    'account-orders': function (exp, ctx) {
      var c = exp.content;
      var msg = fill(c.message, { count: 4, spent: h.money(312, ctx.currency), first_name: 'Alex' });
      var latest = row(thumb(SAMPLE_PRODUCTS[0], 'small') + col('<p class="ck-text"><b>#1052 · Oct 2, 2026</b></p>' + muted('Total ' + h.esc(h.money(86, ctx.currency))), 'ck-grow') + badge('In transit', 'delivery'));
      var foot = (c.show_reorder ? button(c.button_text, false) : '') + note('Preview with sample orders. Customers see their own.');
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'success', bannerIcon: 'heart' }, '<p class="ck-text">' + msg + '</p>' + (c.show_reorder ? link(c.button_text) : '') + note('Preview with sample orders. Customers see their own.'));
      if (exp.style === 'compact') return frame(exp, {}, title(c.headline) + latest + (c.show_tracking ? muted('UPS · 1Z999AA10123456784 · <u>Track</u>') : '') + foot);
      return frame(exp, { heading: c.headline }, '<p class="ck-text">' + msg + '</p>' + grid([['4', 'Orders'], [h.money(312, ctx.currency), 'Spent'], ['Oct 2', 'Last order']].map(function (s) { return tile('<p class="ck-big ck-mid">' + h.esc(s[0]) + '</p><p class="ck-muted">' + s[1] + '</p>'); }), 3) + (c.show_latest ? latest : '') + foot);
    },

    'account-tracking': function (exp) {
      var c = exp.content;
      var steps = ['Ordered', 'Shipped', 'Out for delivery', 'Delivered'];
      var track = muted('UPS · 1Z999AA10123456784 <u>' + h.esc(c.button_text) + '</u>');
      var help = c.help_text ? link(c.help_text) : '';
      if (exp.style === 'timeline') {
        return frame(exp, { heading: c.headline }, '<p class="ck-text"><b>#1052 · Arrives Oct 7</b></p><div class="ck-timeline">' + steps.map(function (s, i) {
          return row(icon(i <= 1 ? 'check' : 'circle', i <= 1 ? 'success' : null) + '<span class="ck-text' + (i === 1 ? ' ck-strong' : i > 1 ? ' ck-muted-text' : '') + '">' + s + '</span>' + (i === 1 ? '<span class="ck-muted">Oct 4</span>' : ''), 'ck-tl-step');
        }).join('') + '</div>' + track + help);
      }
      if (exp.style === 'compact') return frame(exp, {}, row(icon('delivery', 'info', 'large') + col('<p class="ck-text"><b>#1052 · In transit</b></p>' + muted('Arrives Oct 7 · UPS'), 'ck-grow') + button(c.button_text, false)));
      return frame(exp, { heading: c.headline }, row('<p class="ck-text ck-grow"><b>#1052</b></p>' + badge('In transit', 'delivery')) + bar(40) +
        '<div class="ck-steps-row">' + steps.map(function (s, i) { return '<span class="' + (i <= 1 ? 'on' : '') + '">' + s + '</span>'; }).join('') + '</div>' + '<p class="ck-text">Estimated delivery: <b>Oct 7, 2026</b></p>' + track + help);
    },

    'account-reorder': function (exp) {
      var c = exp.content;
      if (exp.style === 'button') return frame(exp, { as: 'plain' }, row(button(c.button_text, true) + '<span class="ck-muted">Adds this order\'s 2 items to your cart</span>'));
      var items = [['2 ×', SAMPLE_PRODUCTS[0]], ['1 ×', SAMPLE_PRODUCTS[1]]];
      var list = c.behavior === 'pick'
        ? '<ul class="ck-choices">' + items.map(function (i) { return '<li><span class="ck-check"></span>' + thumb(i[1], 'small') + i[0] + ' ' + h.esc(i[1].title) + '</li>'; }).join('') + '</ul>'
        : row(items.map(function (i) { return thumb(i[1]); }).join('') + '<span class="ck-muted">2 items</span>');
      var cta = button(c.behavior === 'pick' ? 'Add 2 to cart' : c.button_text, true);
      var menu = c.menu_action ? note('"' + c.button_text + '" also appears in each order\'s menu.') : '';
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'warning', bannerIcon: 'repeat' }, para(c.message) + cta + menu);
      return frame(exp, { heading: c.headline }, para(c.message) + list + cta + menu);
    },

    'account-rewards': function (exp, ctx) {
      var c = exp.content;
      var tiers = (c.tiers || []).slice().sort(function (a, b) { return (a.threshold || 0) - (b.threshold || 0); });
      var spent = 320, tier = null, next = null;
      tiers.forEach(function (t) { if (spent >= (t.threshold || 0)) tier = t; else if (!next) next = t; });
      var from = tier ? tier.threshold || 0 : 0, pct = next ? Math.round((spent - from) / Math.max(1, next.threshold - from) * 100) : 100;
      var vars = { tier: tier ? tier.name : '', next_tier: next ? next.name : '', remaining: h.money(next ? next.threshold - spent : 0, ctx.currency), spent: h.money(spent, ctx.currency) };
      var msg = '<p class="ck-text">' + (next ? fill(c.progress_message, vars) : fill(c.top_message, vars)) + '</p>';
      var who = note('Preview for a customer who has spent ' + h.money(spent, ctx.currency) + '.');
      if (exp.style === 'banner') return frame(exp, { heading: c.headline, tone: 'success', bannerIcon: 'star' }, msg + bar(pct) + who);
      if (exp.style === 'premium') {
        return frame(exp, {}, col(icon('star', 'warning', 'large') + muted(h.esc(c.headline)) + '<p class="ck-big">' + h.esc(tier ? tier.name : 'Member') + '</p>' + (tier && tier.perks ? para(tier.perks, 'ck-muted-text') : ''), 'ck-center') + bar(pct) + msg + who);
      }
      return frame(exp, { heading: c.headline }, row(badge(tier ? tier.name : 'Member', 'star') + (next ? '<span class="ck-muted">Next: ' + h.esc(next.name) + '</span>' : ''), 'ck-between') + bar(pct) + msg +
        grid(tiers.slice(0, 3).map(function (t) { return tile('<p class="ck-text"><b>' + h.esc(t.name) + '</b></p>' + muted(h.esc(t.perks || '')) + ''); }), Math.min(3, tiers.length || 1)) + who);
    },

    'account-reviews': function (exp) {
      var c = exp.content;
      var list = SAMPLE_PRODUCTS.slice(0, Math.max(1, Math.min(Number(c.max_products) || 2, 3)));
      if (exp.style === 'grid') {
        return frame(exp, { heading: c.headline }, para(c.message) + grid(list.slice(0, 2).map(function (p) { return tile(col('<span class="ck-cover"><img src="' + h.esc(img()) + '" alt=""></span><p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + stars(0, 'warning') + link(c.button_text), 'ck-center')); }), 2));
      }
      if (exp.style === 'compact') {
        return frame(exp, {}, row(thumb(list[0], 'large') + col('<p class="ck-text"><b>' + h.esc(c.headline) + '</b></p>' + muted('How do you like your ' + h.esc(list[0].title) + '?') + stars(0, 'warning'), 'ck-grow') + button(c.button_text, true)));
      }
      return frame(exp, { heading: c.headline }, para(c.message) + divided(list.map(function (p) { return row(thumb(p, 'small') + '<p class="ck-text ck-grow"><b>' + h.esc(p.title) + '</b></p>' + link(c.button_text)); })));
    },

    'account-products': function (exp) {
      var c = exp.content;
      var list = SAMPLE_PRODUCTS.slice(0, Math.max(1, Math.min(Number(c.max_products) || 3, 3)));
      if (exp.style === 'grid') {
        return frame(exp, { heading: c.headline }, para(c.message) + grid(list.map(function (p) { return col('<span class="ck-cover"><img src="' + h.esc(img()) + '" alt=""></span><p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + (p.n > 1 ? muted('Bought ' + p.n + ' times') : '') + button(c.button_text, false), 'ck-tight'); }), list.length));
      }
      if (exp.style === 'compact') {
        return frame(exp, {}, row(icon('heart', 'critical') + title(c.headline)) + row(list.map(function (p) { return col(thumb(p, 'large') + '<span class="ck-muted">' + h.esc(p.title) + '</span>', 'ck-center ck-tight'); }).join(''), 'ck-spread'));
      }
      return frame(exp, { heading: c.headline }, para(c.message) + divided(list.map(function (p) {
        return row(thumb(p, 'small') + col('<p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + muted((p.n > 1 ? 'Bought ' + p.n + ' times · ' : '') + '<u>' + h.esc(c.view_text) + '</u>'), 'ck-grow') + button(c.button_text, false));
      })));
    },

    'account-support': function (exp) {
      var c = exp.content;
      var links = [c.email ? ['email', 'Email us'] : null, c.phone ? ['phone', 'Call us · ' + c.phone] : null, c.help_url ? ['question', 'Help center'] : null, c.returns_url ? ['return', 'Returns'] : null].filter(Boolean);
      if (!links.length) links = [['email', 'Email us'], ['chat', 'Live chat'], ['return', 'Returns']];
      var faqs = (c.faqs || []).filter(function (f) { return f.question; });
      if (exp.style === 'compact') return frame(exp, {}, row(icon('question', null, 'large') + '<p class="ck-text ck-grow"><b>' + h.esc(c.headline) + '</b></p>' + links.map(function (l) { return link(l[1].split(' · ')[0]); }).join('')));
      if (exp.style === 'faq') {
        return frame(exp, { heading: c.headline }, para(c.message, 'ck-muted-text') + '<div class="ck-faq">' + faqs.map(function (f, i) {
          return '<div class="ck-faq-item"><p class="ck-text ck-strong">' + h.esc(f.question) + '<span>' + (i === 0 ? '−' : '+') + '</span></p>' + (i === 0 ? muted(h.esc(f.answer)) : '') + '</div>';
        }).join('') + '</div>' + row(links.map(function (l) { return link(l[1]); }).join(''), 'ck-wrap'));
      }
      return frame(exp, { heading: c.headline }, para(c.message, 'ck-muted-text') + grid(links.map(function (l) { return tile(row(icon(l[0], 'info') + '<span class="ck-text"><b>' + h.esc(l[1]) + '</b></span>')); }), links.length > 2 ? 3 : links.length));
    },

    'checkout-upsell': function (exp, ctx) {
      var c = exp.content;
      var list = (c.products && c.products.length ? c.products : SAMPLE_UPSELL).slice(0, exp.style === 'featured' ? 1 : 3);
      var pct = Number(c.discount_percent) || 0;
      var bullets = String(c.bullets || (c.products && c.products.length ? '' : 'Blends in seconds\nPrevents the cakey look\nOne pass = smooth, even skin')).split('\n').map(function (b) { return b.trim(); }).filter(Boolean).slice(0, 4);
      var priceRow = function (p) {
        var base = Number(p.price || 0), now = base * (1 - pct / 100), was = pct ? base : (p.compare_at > base ? Number(p.compare_at) : 0), off = was ? Math.round((1 - now / was) * 100) : 0;
        return row('<span class="ck-text"><b>' + h.esc(h.money(now, ctx.currency)) + '</b></span>' + (was ? '<s class="ck-muted">' + h.esc(h.money(was, ctx.currency)) + '</s>' : '') + (off > 0 ? badge('-' + off + '%') : ''), 'ck-tight-row');
      };
      var benefits = bullets.length ? col(bullets.map(function (b) { return '<p class="ck-muted ck-check-line">' + icon('check', 'success') + h.esc(b) + '</p>'; }).join(''), 'ck-tight') : '';
      var name = function (p) { return c.offer_title && list.length === 1 ? c.offer_title : p.title; };
      if (exp.style === 'featured') {
        var f = list[0];
        return frame(exp, { heading: c.headline }, '<span class="ck-cover ck-wide"><img src="' + h.esc(f.image || img()) + '" alt=""></span><p class="ck-text"><b>' + h.esc(c.offer_title || f.title) + '</b></p>' + benefits + priceRow(f) + button(c.button_text, true).replace('ck-btn', 'ck-btn ck-block'));
      }
      if (exp.style === 'compact') {
        return frame(exp, { heading: c.headline }, divided(list.map(function (p) { return row(thumb(p, 'small') + col('<p class="ck-text">' + h.esc(name(p)) + '</p>' + priceRow(p), 'ck-grow ck-tight') + button(c.button_text, false)); })));
      }
      return frame(exp, { as: 'plain', heading: c.headline }, list.map(function (p) {
        return '<div class="ck-offer">' + row(thumb(p) + col('<p class="ck-text"><b>' + h.esc(name(p)) + '</b></p>' + benefits + priceRow(p), 'ck-grow ck-tight') + button(c.button_text, true)) + '</div>';
      }).join(''));
    },

    'checkout-addon': function (exp, ctx) {
      var c = exp.content;
      var p = (c.product || [])[0] || { title: 'Shipping protection', price: 5 };
      var price = h.money(Number(p.price || 0), ctx.currency);
      var box = '<span class="ck-checkbox"></span>';
      if (exp.style === 'compact') return frame(exp, {}, row(box + '<span class="ck-text">' + h.esc(c.title + ' · ' + price) + '</span>') + (c.description ? muted(h.esc(c.description)) : ''));
      return frame(exp, { as: 'plain', heading: c.headline }, '<div class="ck-offer">' + row(icon(BADGE_ICON[c.icon] || 'shield', null, 'large') + col('<p class="ck-text"><b>' + h.esc(c.title) + '</b></p><p class="ck-muted">Add for ' + h.esc(price) + '</p>' + (c.description ? '<p class="ck-muted">' + h.esc(c.description) + '</p>' : ''), 'ck-grow ck-tight') + box) + '</div>' +
        ((c.product || []).length ? '' : note('Preview with a sample add-on. Choose your add-on product in the Content step.')));
    },

    'checkout-image': image,
    'ty-image': image
  };

  Object.keys(R).forEach(function (type) { OrderOrbit.define(type, R[type]); });
})();
