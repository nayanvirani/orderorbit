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

  function wrap(exp, body) {
    return '<div class="ck ck-' + h.esc(exp.style || 'card') + '">' + body + '</div>';
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
      var left = c.ends_at ? Math.max(0, Date.parse(c.ends_at) - Date.now()) : 2 * 3600e3 + 14 * 60e3;
      var hh = Math.floor(left / 3600e3), mm = Math.floor(left % 3600e3 / 60e3), ss = Math.floor(left % 60e3 / 1e3);
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      return wrap(exp, '<div class="ck-row ck-between"><p class="ck-text"><b>' + h.esc(c.headline) + '</b></p><p class="ck-timer">' + pad(hh) + ':' + pad(mm) + ':' + pad(ss) + '</p></div>');
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
    'ty-message': function (exp) { var c = exp.content; return wrap(exp, title(c.headline || 'Your headline') + para(c.message) + button(c.button_text, false)); }
  };

  Object.keys(R).forEach(function (type) { OrderOrbit.define(type, R[type]); });
})();
