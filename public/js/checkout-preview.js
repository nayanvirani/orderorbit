/*
 * In-app previews for checkout, Thank You and Order Status blocks. The real blocks are drawn by
 * Shopify's checkout UI extension (orderorbit-checkout) with Shopify's own components and the
 * store's checkout branding; these previews give merchants a close, neutral likeness in the
 * builder, template pickers and feature pages.
 */
(function () {
  if (!window.OrderOrbit) return;
  var h = OrderOrbit.h;
  var SAMPLE_REVIEWS = [
    { author: 'Sample: Alex R.', rating: 5, quote: 'Your reviews appear here. Add real ones in the Content step.' },
    { author: 'Sample: Sam T.', rating: 4, quote: 'Shoppers see these right next to the pay button.' }
  ];
  var SAMPLE_GIFTS = { content: { settings: { unlock: 'value' }, milestones: [
    { index: 0, threshold: 50, reward: 'shipping', label: 'free shipping' },
    { index: 1, threshold: 80, reward: 'gift', label: 'a free gift', products: [{ title: 'Travel-size serum', image: window.OO_SAMPLE_IMAGE }] }
  ] } };

  // Close likenesses of Shopify's checkout tokens, for the Design step's box styles.
  var RADIUS = { none: '0', 'small-100': '2px', small: '4px', base: '6px', large: '10px', 'large-100': '14px', max: '999px' };
  var BORDER = { none: '0', base: '1px', large: '2px', 'large-100': '3px', 'large-200': '4px' };
  var PAD = { none: '0', small: '8px', base: '14px', large: '20px', 'large-200': '28px' };
  var TONE = { info: '#0b5cad', success: '#0c7a43', warning: '#8a5a00', critical: '#c5281c' };
  var BANNER = ['banner', 'announcement', 'unlocked'];
  var PLAIN = ['compact', 'row', 'button', 'simple', 'plain'];

  function size(kind, value) {
    var n = Math.round(Number(value) || 0);
    if (kind === 'px' && n > 0) return n + 'px';
    if (kind === 'percent' && n > 0) return Math.min(100, n) + '%';
    return '';
  }

  function wrap(exp, body) {
    var d = exp.design || {};
    var set = function (k) { return d[k] && d[k] !== 'auto'; };
    var style = exp.style || 'card';
    var css = [];
    var custom = set('ck_background') || set('ck_border') || set('ck_radius') || set('ck_padding');
    if (custom) {
      var plain = PLAIN.indexOf(style) !== -1;
      style = 'card';
      var bg = set('ck_background') ? d.ck_background : 'base';
      css.push('background:' + (bg === 'subdued' ? '#f4f4f4' : bg === 'transparent' ? 'transparent' : '#fff'));
      var border = set('ck_border') ? d.ck_border : plain ? 'none' : 'base';
      css.push('border:' + (BORDER[border] || '0') + ' ' + (d.ck_border_style || 'solid') + ' #cfcfcf');
      css.push('border-radius:' + RADIUS[set('ck_radius') ? d.ck_radius : plain ? 'none' : 'base']);
      css.push('padding:' + PAD[set('ck_padding') ? d.ck_padding : plain ? 'none' : 'base']);
    }
    var w = d.ck_width && d.ck_width !== 'full' ? size(d.ck_width, d.ck_width_value) : '';
    if (w) css.push('width:' + w + ';max-width:100%');
    if (d.ck_height === 'px') css.push('min-height:' + size('px', d.ck_height_value) + ';align-content:start');
    if (d.ck_tone && TONE[d.ck_tone]) css.push('--ck-text:' + TONE[d.ck_tone]);
    var cls = 'ck ck-' + h.esc(style) + (d.ck_text === 'subdued' ? ' ck-subdued' : '') + (d.ck_tone && TONE[d.ck_tone] ? ' ck-toned' : '');
    return '<div class="' + cls + '" style="' + css.join(';') + '">' + body + '</div>';
  }

  function image(exp) {
    var c = exp.content;
    var src = c.image || window.OO_SAMPLE_IMAGE;
    var width = c.img_width === 'px' || c.img_width === 'percent' ? size(c.img_width, c.img_width_value) : '100%';
    var css = ['width:100%', 'display:block', 'border-radius:' + (RADIUS[c.img_radius || 'base'] || '6px')];
    if (BORDER[c.img_border] && c.img_border !== 'none') css.push('border:' + BORDER[c.img_border] + ' ' + (c.img_border_style || 'solid') + ' #cfcfcf');
    if (c.img_height === 'ratio') css.push('aspect-ratio:' + (c.img_ratio || '16/9'), 'object-fit:' + (c.img_fit || 'cover'));
    if (c.img_height === 'px') css.push('height:' + Math.max(20, Number(c.img_height_value) || 200) + 'px', 'object-fit:' + (c.img_fit || 'cover'));
    var align = { start: 'start', center: 'center', end: 'end' }[c.align] || 'center';
    return wrap(exp, '<div class="ck-image" style="justify-items:' + align + '"><div style="width:' + width + ';max-width:100%"><img src="' + h.esc(src) + '" alt="' + h.esc(c.alt || '') + '" style="' + css.join(';') + '"></div>' +
      (c.caption ? '<p class="ck-muted">' + h.esc(c.caption) + '</p>' : '') + (c.image ? '' : '<p class="ck-note">Sample image. Upload yours in the Content step.</p>') + '</div>');
  }
  function title(text) { return text ? '<p class="ck-title">' + h.esc(text) + '</p>' : ''; }
  function para(text) { return text ? '<p class="ck-text">' + h.esc(text) + '</p>' : ''; }
  function button(text, primary) { return text ? '<span class="ck-btn' + (primary ? ' ck-primary' : '') + '">' + h.esc(text) + '</span>' : ''; }
  function stars(n) { var out = ''; for (var i = 1; i <= 5; i++) out += i <= Math.round(n || 0) ? '★' : '☆'; return '<span class="ck-stars">' + out + '</span>'; }
  function fill(tpl, vars) { return h.esc(tpl || '').replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? '<b>' + h.esc(vars[k]) + '</b>' : m; }); }

  function progress(exp, ctx, kind) {
    var c = exp.content;
    var ms = (SAMPLE_GIFTS.content.milestones).filter(function (m) {
      return kind === 'gift' ? m.reward === 'gift' : (c.milestones === 'all' || m.reward === 'shipping');
    });
    var subtotal = (ctx.cartTotal || 4500) / 100;
    var next = ms.filter(function (m) { return subtotal < m.threshold; })[0];
    var top = ms.length ? ms[ms.length - 1].threshold : 1;
    var pct = Math.min(100, Math.round(subtotal / top * 100));
    var msg = next ? fill(c.progress_message, { remaining: h.money(next.threshold - subtotal, ctx.currency), reward: next.label })
      : fill(c.unlocked_message, { reward: ms.length ? ms[ms.length - 1].label : '' });
    var bar = '<div class="ck-bar"><i style="width:' + pct + '%"></i></div>';
    var steps = exp.style === 'ladder' || exp.style === 'multi'
      ? '<ul class="ck-steps">' + ms.map(function (m) { return '<li class="' + (subtotal >= m.threshold ? 'done' : '') + '">' + h.esc(h.money(m.threshold, ctx.currency)) + ' · ' + h.esc(m.label) + '</li>'; }).join('') + '</ul>' : '';
    var gift = kind === 'gift' && ms[0] && ms[0].products ? '<div class="ck-row">' + h.productImage(ms[0].products[0]) + '<div><p class="ck-text"><b>' + h.esc(ms[0].products[0].title) + '</b></p><p class="ck-muted">Free with your order</p></div>' + button(next ? '' : c.button_text, true) + '</div>' : '';
    return wrap(exp, '<p class="ck-text">' + msg + '</p>' + bar + steps + gift + '<p class="ck-note">Preview with a sample Progressive gifts campaign. Live, it follows yours.</p>');
  }

  var R = {
    'checkout-reviews': function (exp) {
      var c = exp.content;
      var list = (c.reviews && c.reviews.length ? c.reviews : SAMPLE_REVIEWS);
      if (exp.style === 'slider' || exp.style === 'premium') list = list.slice(0, 1);
      return wrap(exp, title(c.headline) + (c.rating ? '<p class="ck-text">' + stars(c.rating) + ' ' + h.esc(c.rating) + (c.review_count ? ' · ' + h.esc(Number(c.review_count).toLocaleString()) + ' reviews' : '') + '</p>' : '') +
        list.map(function (r) { return '<div class="ck-quote">' + stars(r.rating) + '<p class="ck-text">“' + h.esc(r.quote) + '”</p><p class="ck-muted">' + h.esc(r.author) + '</p></div>'; }).join('') +
        (exp.style === 'slider' ? '<p class="ck-muted">‹ 1 / ' + (c.reviews && c.reviews.length || 2) + ' ›</p>' : ''));
    },
    'checkout-countdown': function (exp) {
      var c = exp.content;
      var timed = c.mode === 'hours' || c.mode === 'minutes';
      var length = timed ? (c.mode === 'hours' ? (Number(c.hours) || 1) * 3600e3 : (Number(c.minutes) || 1) * 60e3) : 0;
      var left = timed ? length : c.ends_at ? Math.max(0, Date.parse(c.ends_at) - Date.now()) : 2 * 3600e3 + 14 * 60e3;
      var hh = Math.floor(left / 3600e3), mm = Math.floor(left % 3600e3 / 60e3), ss = Math.floor(left % 60e3 / 1e3);
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      var note = timed ? '<p class="ck-note">Starts when each shopper reaches checkout' + (c.repeat === 'end' ? ', then stops.' : ' and starts again every ' + (c.mode === 'hours' ? (Number(c.hours) || 1) + ' h.' : (Number(c.minutes) || 1) + ' min.')) + '</p>' : '';
      return wrap(exp, '<div class="ck-row ck-between"><p class="ck-text"><b>' + h.esc(c.headline) + '</b></p><p class="ck-timer">' + pad(hh) + ':' + pad(mm) + ':' + pad(ss) + '</p></div>' + note);
    },
    'checkout-shipping': function (exp, ctx) { return progress(exp, ctx, 'shipping'); },
    'checkout-gift': function (exp, ctx) { return progress(exp, ctx, 'gift'); },
    'checkout-promo': function (exp) {
      var c = exp.content;
      return wrap(exp, title(c.headline || 'Your headline') + para(c.message) + (c.code ? '<div class="ck-row"><span class="ck-code">' + h.esc(c.code) + '</span>' + button(c.apply_text, false) + '</div>' : ''));
    },
    'checkout-trust': function (exp) {
      var c = exp.content;
      return wrap(exp, title(c.headline) + '<ul class="ck-badges">' + (c.badges || []).map(function (b) { return '<li>✓ ' + h.esc(b.label) + '</li>'; }).join('') + '</ul>' + para(c.guarantee));
    },
    'ty-cross-sell': function (exp, ctx) {
      var c = exp.content;
      var list = (c.products || []).slice(0, exp.style === 'featured' ? 1 : 4);
      return wrap(exp, title(c.headline) + para(c.message) + '<div class="ck-products">' + list.map(function (p) {
        return '<div class="ck-product">' + h.productImage(p) + '<p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + (p.price != null ? '<p class="ck-muted">' + h.esc(h.money(p.price, ctx.currency)) + '</p>' : '') + button(c.button_text, false) + '</div>';
      }).join('') + '</div>');
    },
    'ty-reorder': function (exp) { var c = exp.content; return wrap(exp, title(c.headline) + para(c.message) + button(c.button_text, true)); },
    'ty-review': function (exp) { var c = exp.content; return wrap(exp, title(c.headline) + para(c.message) + (exp.style === 'products' ? '<ul class="ck-badges"><li>Glow Serum</li><li>Night Cream</li></ul>' : '') + button(c.button_text, true)); },
    'ty-referral': function (exp) { var c = exp.content; return wrap(exp, title(c.headline) + para(c.message) + '<div class="ck-row"><span class="ck-code">' + h.esc(c.code || 'YOURCODE') + '</span>' + button('Share', false) + '</div>'); },
    'ty-survey': function (exp) {
      var c = exp.content;
      return wrap(exp, title(c.question) + '<ul class="ck-choices">' + (c.options || []).map(function (o, i) { return '<li><span class="ck-radio' + (i === 0 ? ' on' : '') + '"></span>' + h.esc(o.label) + '</li>'; }).join('') + '</ul>' + button(c.button_text, true));
    },
    'ty-discount': function (exp) { var c = exp.content; return wrap(exp, title(c.headline) + para(c.message) + '<div class="ck-row"><span class="ck-code">' + h.esc(c.code || 'YOURCODE') + '</span></div>' + (c.expiry_text ? '<p class="ck-muted">' + h.esc(c.expiry_text) + '</p>' : '')); },
    'post-purchase': function (exp, ctx) {
      var c = exp.content;
      var p = (c.offer_product || [])[0] || { title: 'Your offer product', price: 29, image: window.OO_SAMPLE_IMAGE };
      var price = Number(p.price || 29), pct = Number(c.discount_percent || 0), now = price * (1 - pct / 100);
      var head = exp.style === 'premium' ? '<div class="ck-banner ck" style="padding:12px"><p class="ck-title">' + h.esc(c.headline) + '</p>' + para(c.message) + '</div>' : title(c.headline);
      var second = c.downsell ? '<p class="ck-note">If they decline: “' + h.esc(c.downsell_headline) + '” with ' + h.esc(c.downsell_discount || 0) + '% off ' + h.esc(((c.downsell_product || [])[0] || {}).title || 'the second offer') + '.</p>' : '';
      return wrap(exp, head + '<div class="ck-row">' + (exp.style === 'minimal' ? '' : h.productImage(p)) + '<div><p class="ck-text"><b>' + h.esc(p.title) + '</b></p>' + (exp.style === 'premium' ? '' : para(c.message)) +
        '<p class="ck-text">' + (pct ? '<s class="ck-muted">' + h.esc(h.money(price, ctx.currency)) + '</s> ' : '') + '<b>' + h.esc(h.money(now, ctx.currency)) + '</b>' + (pct ? ' · ' + pct + '% off' : '') + '</p></div></div>' +
        button(c.accept_text + ' · ' + h.money(now, ctx.currency), true) + '<span class="ck-muted" style="justify-self:start">' + h.esc(c.decline_text) + '</span>' + second);
    },
    'checkout-image': image,
    'ty-image': image,
    'ty-message': function (exp) { var c = exp.content; return wrap(exp, title(c.headline || 'Your headline') + para(c.message) + button(c.button_text, false)); }
  };

  Object.keys(R).forEach(function (type) { OrderOrbit.define(type, R[type]); });
})();
