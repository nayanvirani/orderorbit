/*!
 * OrderOrbit storefront runtime.
 * Renders published experiences inside OrderOrbit app blocks that merchants place
 * in the Theme Editor. It never edits theme markup, opens cart drawers or intercepts
 * add-to-cart; it only reads the cart to show progress. Also powers the in-app preview.
 */
(function () {
  'use strict';

  if (window.OrderOrbit && window.OrderOrbit.version) return;

  var MOBILE = '(max-width: 749px)';
  var events = [];

  // ---------------------------------------------------------------- helpers
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function money(amount, currency) {
    var n = Number(amount || 0);
    try {
      return new Intl.NumberFormat(document.documentElement.lang || undefined, { style: 'currency', currency: currency || 'USD' }).format(n);
    } catch (e) {
      return (currency || '$') + ' ' + n.toFixed(2);
    }
  }

  function fill(template, vars) {
    return esc(template || '').replace(/\{(\w+)\}/g, function (m, key) {
      return key in vars ? '<strong>' + esc(vars[key]) + '</strong>' : m;
    });
  }

  function numericId(gid) {
    var m = String(gid || '').match(/(\d+)$/);
    return m ? m[1] : String(gid || '');
  }

  function stars(rating) {
    var full = Math.round(Number(rating) || 0);
    var out = '';
    for (var i = 1; i <= 5; i++) out += '<span class="oo-star' + (i <= full ? ' oo-on' : '') + '">★</span>';
    return '<span class="oo-stars" aria-label="' + esc(rating) + ' out of 5">' + out + '</span>';
  }

  var ICONS = {
    shipping: '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
    returns: '<path d="M4 12a8 8 0 1 0 3-6.2"/><path d="M4 4v4h4"/>',
    secure: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    guarantee: '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/><path d="m9 12 2 2 4-4"/>',
    support: '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>'
  };
  function icon(name) {
    return '<svg class="oo-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[name] || ICONS.guarantee) + '</svg>';
  }

  function productImage(p) {
    return p && p.image
      ? '<img class="oo-img" src="' + esc(p.image) + '" alt="' + esc(p.title) + '" loading="lazy" width="120" height="120">'
      : '<span class="oo-img oo-img-empty" aria-hidden="true"></span>';
  }

  function track(name, exp, extra) {
    if (exp.analytics && exp.analytics.track_views === false && name === 'experience_viewed') return;
    if (exp.analytics && exp.analytics.track_clicks === false && name !== 'experience_viewed') return;
    var detail = Object.assign({ event: 'orderorbit:' + name, experience_id: exp.id, experience_type: exp.type, template_id: exp.template, version: exp.version, timestamp: new Date().toISOString() }, extra || {});
    events.push(detail);
    try { document.dispatchEvent(new CustomEvent('orderorbit:event', { detail: detail })); } catch (e) { /* old browsers */ }
  }

  // ---------------------------------------------------------------- targeting
  function utm(key) {
    var params = new URLSearchParams(location.search);
    var value = params.get(key);
    try {
      if (value) sessionStorage.setItem('oo_' + key, value);
      return value || sessionStorage.getItem('oo_' + key) || '';
    } catch (e) {
      return value || '';
    }
  }

  function dismissed(exp) {
    try { return exp.behavior && exp.behavior.dismissible && localStorage.getItem('oo_dismiss_' + exp.id) === String(exp.version); } catch (e) { return false; }
  }

  function matches(exp, ctx) {
    var t = exp.targeting || {};
    var now = ctx.now || Date.now();
    if (exp.starts_at && Date.parse(exp.starts_at) > now) return false;
    if (exp.ends_at && Date.parse(exp.ends_at) <= now) return false;
    if (ctx.preview) return true;
    if (dismissed(exp)) return false;

    if (t.page_types && t.page_types.length && t.page_types.indexOf(ctx.page) === -1) return false;
    if (t.products && t.products.length) {
      var ids = t.products.map(function (p) { return numericId(p.id); });
      if (!ctx.product || ids.indexOf(String(ctx.product)) === -1) return false;
    }
    if (t.collections && t.collections.length) {
      var wanted = t.collections.map(function (c) { return numericId(c.id); });
      var has = (ctx.collections || []).map(String);
      if (!wanted.some(function (id) { return has.indexOf(id) !== -1; })) return false;
    }
    var cart = (ctx.cartTotal || 0) / 100;
    if (t.cart_min != null && t.cart_min !== '' && cart < Number(t.cart_min)) return false;
    if (t.cart_max != null && t.cart_max !== '' && cart > Number(t.cart_max)) return false;
    var mobile = window.matchMedia && window.matchMedia(MOBILE).matches;
    if (t.device === 'mobile' && !mobile) return false;
    if (t.device === 'desktop' && mobile) return false;
    if (t.customer === 'returning' && !(ctx.ordersCount > 0)) return false;
    if (t.customer === 'new' && ctx.ordersCount > 0) return false;
    if (t.countries) {
      var codes = String(t.countries).toUpperCase().split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      if (codes.length && codes.indexOf(String(ctx.country || '').toUpperCase()) === -1) return false;
    }
    if (t.utm_source && utm('utm_source') !== t.utm_source) return false;
    if (t.utm_campaign && utm('utm_campaign') !== t.utm_campaign) return false;
    return true;
  }

  function choose(experiences, type, pinnedId, ctx) {
    var list = (experiences || []).filter(function (e) {
      return pinnedId ? e.id === pinnedId : e.type === type;
    }).filter(function (e) { return matches(e, ctx); });
    list.sort(function (a, b) { return (b.priority || 0) - (a.priority || 0); });
    return list[0] || null;
  }

  // ---------------------------------------------------------------- renderers
  function thresholdState(thresholds, total) {
    var sorted = (thresholds || []).filter(function (t) { return t && t.amount != null; }).sort(function (a, b) { return a.amount - b.amount; });
    var next = null;
    var reached = [];
    sorted.forEach(function (t) { if (total >= Number(t.amount)) reached.push(t); else if (!next) next = t; });
    var top = sorted.length ? Number(sorted[sorted.length - 1].amount) : 0;
    return { sorted: sorted, next: next, reached: reached, last: reached[reached.length - 1] || null, pct: top ? Math.min(100, (total / top) * 100) : 0 };
  }

  function ladder(state, currency) {
    if (state.sorted.length < 2) return '';
    var top = Number(state.sorted[state.sorted.length - 1].amount);
    return '<div class="oo-ladder">' + state.sorted.map(function (t) {
      var left = top ? (Number(t.amount) / top) * 100 : 0;
      var done = state.reached.indexOf(t) !== -1;
      return '<span class="oo-milestone' + (done ? ' oo-done' : '') + '" style="left:' + left + '%" title="' + esc(t.reward) + '">' + icon('gift') + '<small>' + esc(money(t.amount, currency)) + '</small></span>';
    }).join('') + '</div>';
  }

  var R = {};

  R['shipping-bar'] = function (exp, ctx) {
    var c = exp.content;
    var total = (ctx.cartTotal || 0) / 100;
    var s = thresholdState(c.thresholds, total);
    var msg;
    if (!total && c.empty_message && s.sorted.length) {
      msg = fill(c.empty_message, { threshold: money(s.sorted[0].amount, ctx.currency), reward: s.sorted[0].reward });
    } else if (s.next) {
      msg = fill(c.progress_message, { remaining: money(Number(s.next.amount) - total, ctx.currency), reward: s.next.reward, threshold: money(s.next.amount, ctx.currency) });
    } else {
      msg = fill(c.unlocked_message, { reward: s.last ? s.last.reward : '' });
    }
    return '<div class="oo-body"><p class="oo-message" role="status">' + msg + '</p>' +
      '<div class="oo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(s.pct) + '"><i style="width:' + s.pct + '%"></i></div>' +
      ladder(s, ctx.currency) + '</div>';
  };

  R['free-gifts'] = function (exp, ctx) {
    var c = exp.content;
    var total = (ctx.cartTotal || 0) / 100;
    var s = thresholdState(c.thresholds, total);
    var msg = s.next
      ? fill(c.progress_message, { remaining: money(Number(s.next.amount) - total, ctx.currency), reward: s.next.reward })
      : fill(c.unlocked_message, { reward: s.last ? s.last.reward : '' });
    var gifts = (c.gift_products || []).slice(0, 2).map(function (p) {
      return '<div class="oo-gift' + (s.reached.length ? ' oo-unlocked' : '') + '">' + productImage(p) + '<span>' + esc(p.title) + '</span>' +
        (s.reached.length ? '<button type="button" class="oo-btn oo-btn-sm" data-oo-click="gift_claimed">' + (c.claim_mode === 'auto' ? 'Added' : 'Claim') + '</button>' : '<em>Locked</em>') + '</div>';
    }).join('');
    return '<div class="oo-body"><p class="oo-message" role="status">' + icon('gift') + ' ' + msg + '</p>' +
      '<div class="oo-progress"><i style="width:' + s.pct + '%"></i></div>' + ladder(s, ctx.currency) + (gifts ? '<div class="oo-gifts">' + gifts + '</div>' : '') + '</div>';
  };

  R.countdown = function (exp, ctx) {
    var c = exp.content;
    var end = Date.parse(c.ends_at || exp.ends_at || '');
    // The builder previews a sample deadline until one is set.
    if (!end && ctx.preview) end = Date.now() + ((2 * 24 + 14) * 3600 + 9 * 60) * 1000;
    if (!end || end <= (ctx.now || Date.now())) {
      return c.ended === 'message' ? '<div class="oo-body"><p class="oo-message">' + esc(c.ended_message) + '</p></div>' : null;
    }
    return '<div class="oo-body oo-countdown-body"><div><p class="oo-title">' + esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + esc(c.subheadline) + '</p>' : '') + '</div>' +
      '<div class="oo-timer" data-oo-end="' + end + '" role="timer" aria-live="off">' +
      ['Days', 'Hours', 'Min', 'Sec'].map(function (l) { return '<span><b>--</b><small>' + l + '</small></span>'; }).join('') + '</div></div>';
  };

  R.trust = function (exp) {
    var c = exp.content;
    var style = exp.style;
    var rating = c.rating ? '<div class="oo-rating">' + stars(c.rating) + ' <span>' + esc(c.rating) + (c.review_count ? ' · ' + esc(Number(c.review_count).toLocaleString()) + ' reviews' : '') + '</span></div>' : '';
    if (style === 'compact') return '<div class="oo-body oo-inline">' + rating + '</div>';
    var badges = (c.badges || []).length ? '<ul class="oo-badges">' + c.badges.map(function (b) { return '<li>' + icon(b.icon) + '<span>' + esc(b.label) + '</span></li>'; }).join('') + '</ul>' : '';
    if (style === 'row') return '<div class="oo-body">' + badges + '</div>';
    var reviews = (c.reviews || []).map(function (r) {
      return '<figure class="oo-review">' + stars(r.rating) + '<blockquote>' + esc(r.quote) + '</blockquote><figcaption><span class="oo-avatar" aria-hidden="true">' + esc(String(r.author || '?').charAt(0)) + '</span>' + esc(r.author) + '</figcaption></figure>';
    });
    if (style === 'premium') reviews = reviews.slice(0, 1);
    var guarantee = c.guarantee ? '<div class="oo-guarantee">' + icon('guarantee') + '<p>' + esc(c.guarantee) + '</p></div>' : '';
    return '<div class="oo-body">' + (c.headline ? '<p class="oo-title">' + esc(c.headline) + '</p>' : '') + rating +
      (style === 'banner' ? guarantee || badges : (reviews.length ? '<div class="oo-reviews">' + reviews.join('') + '</div>' : '') + badges + guarantee) + '</div>';
  };

  R.bundles = function (exp, ctx) {
    var c = exp.content;
    var products = c.products || [];
    var min = Number(c.min_items || 1);
    var selected = products.slice(0, Math.min(min, products.length)).length;
    var remaining = Math.max(0, min - selected);
    var tiles = products.map(function (p, i) {
      return '<label class="oo-tile"><input type="checkbox" ' + (i < min ? 'checked' : '') + ' data-oo-select>' + productImage(p) + '<span class="oo-name">' + esc(p.title) + '</span>' +
        (p.price != null ? '<span class="oo-price">' + (c.show_compare_at && p.compare_at ? '<s>' + esc(money(p.compare_at, ctx.currency)) + '</s> ' : '') + esc(money(p.price, ctx.currency)) + '</span>' : '') + '</label>';
    }).join('') || '<p class="oo-empty">Choose products for this bundle.</p>';
    return '<div class="oo-body">' + (c.badge ? '<span class="oo-badge">' + esc(c.badge) + '</span>' : '') +
      '<p class="oo-title">' + esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + esc(c.subheadline) + '</p>' : '') +
      '<div class="oo-tiles">' + tiles + '</div>' +
      '<p class="oo-message">' + (remaining ? fill(c.progress_message, { remaining: remaining }) : 'Saving unlocked') + '</p>' +
      '<div class="oo-progress"><i style="width:' + (min ? Math.min(100, (selected / min) * 100) : 100) + '%"></i></div>' +
      '<button type="button" class="oo-btn" data-oo-click="bundle_add"' + (remaining ? ' disabled' : '') + '>' + esc(c.cta_text) + '</button></div>';
  };

  R['quantity-breaks'] = function (exp, ctx) {
    var c = exp.content;
    var base = ctx.productPrice != null ? ctx.productPrice / 100 : 29;
    var tiers = (c.tiers || []).map(function (t, i) {
      var qty = Number(t.quantity || 1);
      var unit = base * (1 - Number(t.discount || 0) / 100);
      var shown = c.price_display === 'total' ? unit * qty : unit;
      return '<label class="oo-tier' + (i + 1 === Number(c.default_tier) ? ' oo-selected' : '') + '">' + (t.badge ? '<span class="oo-flag">' + esc(t.badge) + '</span>' : '') +
        '<input type="radio" name="oo-tier-' + esc(exp.id) + '" ' + (i + 1 === Number(c.default_tier) ? 'checked' : '') + '><span class="oo-tier-name">Buy ' + qty + '</span>' +
        '<span class="oo-tier-price">' + esc(money(shown, ctx.currency)) + (c.price_display === 'total' ? '' : ' <small>each</small>') + (Number(t.discount) ? '<em>Save ' + esc(t.discount) + '%</em>' : '') + '</span></label>';
    }).join('');
    return '<div class="oo-body"><p class="oo-title">' + esc(c.headline) + '</p><div class="oo-tiers">' + tiers + '</div></div>';
  };

  function upsellCards(products, c, ctx, limit) {
    return (products || []).slice(0, limit || 4).map(function (p) {
      var price = p.price != null ? Number(p.price) : null;
      var offer = c.discount_percent ? price * (1 - c.discount_percent / 100) : price;
      return '<div class="oo-card">' + productImage(p) + '<div class="oo-card-meta"><span class="oo-name">' + esc(p.title) + '</span>' +
        (price != null ? '<span class="oo-price">' + (c.discount_percent ? '<s>' + esc(money(price, ctx.currency)) + '</s> ' : '') + esc(money(offer, ctx.currency)) + '</span>' : '') +
        '</div><button type="button" class="oo-btn oo-btn-sm" data-oo-click="upsell_accepted">' + esc(c.cta_text || 'Add') + '</button></div>';
    }).join('') || '<p class="oo-empty">Choose products to recommend.</p>';
  }

  R['product-upsells'] = function (exp, ctx) {
    var c = exp.content;
    return '<div class="oo-body"><p class="oo-title">' + esc(c.headline) + '</p>' + (c.offer_message ? '<p class="oo-sub">' + esc(c.offer_message) + '</p>' : '') +
      '<div class="oo-cards">' + upsellCards(c.products, c, ctx, 4) + '</div></div>';
  };

  R['cart-upsells'] = function (exp, ctx) {
    var c = exp.content;
    return '<div class="oo-body"><p class="oo-title">' + esc(c.headline) + '</p>' + (c.incentive ? '<p class="oo-sub">' + esc(c.incentive) + '</p>' : '') +
      '<div class="oo-cards">' + upsellCards(c.products, c, ctx, c.max_shown) + '</div></div>';
  };

  R['sticky-atc'] = function (exp, ctx) {
    var c = exp.content;
    return '<div class="oo-body oo-sticky-body">' + (c.show_image ? '<span class="oo-img oo-img-empty" aria-hidden="true"></span>' : '') +
      '<div class="oo-card-meta"><span class="oo-name">' + esc(ctx.productTitle || 'Product name') + '</span>' + (c.show_price ? '<span class="oo-price">' + esc(money((ctx.productPrice || 2900) / 100, ctx.currency)) + '</span>' : '') + '</div>' +
      '<button type="button" class="oo-btn" data-oo-click="sticky_atc_clicked">' + esc(c.button_text) + '</button></div>';
  };

  // ---------------------------------------------------------------- mounting
  function styleVars(d) {
    d = d || {};
    return '--oo-primary:' + (d.primary_color || '#303030') + ';--oo-accent:' + (d.accent_color || '#5b4bff') + ';--oo-text:' + (d.text_color || '#1d1b33') +
      ';--oo-bg:' + (d.background_color || '#ffffff') + ';--oo-radius:' + (d.radius != null ? d.radius : 12) + 'px';
  }

  function scopedCss(exp) {
    var css = exp.design && exp.design.custom_css;
    if (!css) return '';
    // Scope every rule to this experience; drop anything that could break out.
    var clean = String(css).replace(/<\/?style[^>]*>/gi, '').replace(/@import[^;]*;?/gi, '');
    var scope = '[data-oo-exp="' + exp.id + '"] ';
    return '<style>' + clean.replace(/(^|})\s*([^{}@]+)\{/g, function (m, close, selectors) {
      return close + selectors.split(',').map(function (s) { return scope + s.trim(); }).join(', ') + '{';
    }) + '</style>';
  }

  function render(el, exp, ctx) {
    ctx = ctx || {};
    var renderer = R[exp.type];
    var inner = renderer ? renderer(exp, ctx) : null;
    if (!inner) { el.innerHTML = ''; el.hidden = true; return false; }

    var d = exp.design || {};
    var classes = ['oo-exp', 'oo-type-' + exp.type, 'oo-style-' + (exp.style || 'card'), 'oo-space-' + (d.spacing || 'comfortable'), 'oo-btn-' + (d.button_style || 'filled')];
    if (d.border) classes.push('oo-bordered');
    if (d.font === 'system') classes.push('oo-font-system');
    if (d.hide_on_mobile) classes.push('oo-hide-mobile');
    if (d.hide_on_desktop) classes.push('oo-hide-desktop');
    if (exp.behavior && exp.behavior.animation && exp.behavior.animation !== 'none' && !ctx.preview) classes.push('oo-anim-' + exp.behavior.animation);
    if (exp.type === 'sticky-atc') classes.push('oo-sticky-' + ((exp.content && exp.content.position) || 'bottom'));

    var close = exp.behavior && exp.behavior.dismissible ? '<button type="button" class="oo-close" aria-label="Dismiss" data-oo-dismiss>×</button>' : '';
    el.hidden = false;
    el.innerHTML = scopedCss(exp) + '<div class="' + classes.join(' ') + '" data-oo-exp="' + esc(exp.id) + '" style="' + styleVars(d) + '">' + close + inner + '</div>';

    startTimers(el);
    if (!ctx.preview) {
      track('experience_viewed', exp, { page_type: ctx.page });
      el.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-oo-click]');
        if (btn) track('experience_clicked', exp, { action: btn.getAttribute('data-oo-click') });
        if (e.target.closest('[data-oo-dismiss]')) {
          try { localStorage.setItem('oo_dismiss_' + exp.id, String(exp.version)); } catch (err) { /* private mode */ }
          el.hidden = true;
          track('experience_closed', exp);
        }
      });
    }
    return true;
  }

  var timerHandle = null;
  function startTimers(root) {
    function tick() {
      var nodes = document.querySelectorAll('[data-oo-end]');
      if (!nodes.length) { clearInterval(timerHandle); timerHandle = null; return; }
      nodes.forEach(function (node) {
        var left = Math.max(0, Math.floor((Number(node.getAttribute('data-oo-end')) - Date.now()) / 1000));
        var parts = [Math.floor(left / 86400), Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60];
        node.querySelectorAll('b').forEach(function (b, i) { b.textContent = String(parts[i]).padStart(2, '0'); });
      });
    }
    if (root.querySelector('[data-oo-end]')) {
      tick();
      if (!timerHandle) timerHandle = setInterval(tick, 1000);
    }
  }

  function readJson(selector) {
    var node = document.querySelector(selector);
    if (!node) return null;
    try { return JSON.parse(node.textContent); } catch (e) { return null; }
  }

  var state = { data: null, ctx: null };

  function mountAll() {
    state.data = state.data || readJson('script[data-oo-data]') || { experiences: [] };
    state.ctx = state.ctx || readJson('script[data-oo-context]') || {};
    document.querySelectorAll('[data-oo-block]').forEach(function (el) {
      var exp = choose(state.data.experiences, el.getAttribute('data-oo-type'), (el.getAttribute('data-oo-id') || '').trim(), state.ctx);
      if (exp) {
        render(el, exp, Object.assign({ currency: state.data.currency }, state.ctx));
      } else if (state.ctx.designMode) {
        // Only merchants in the Theme Editor see this; shoppers see nothing.
        el.hidden = false;
        el.innerHTML = '<div class="oo-editor-note">OrderOrbit: no published ' + esc(el.getAttribute('data-oo-type') || '') + ' experience matches this page yet. Publish one in the OrderOrbit app, or check its targeting.</div>';
      } else {
        el.hidden = true;
      }
    });
  }

  // Re-render cart-aware experiences when the cart changes. Read-only: we watch
  // for completed cart requests; we never wrap or block the theme's own calls.
  function refreshCart() {
    fetch((window.Shopify && Shopify.routes && Shopify.routes.root || '/') + 'cart.js', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (cart) { state.ctx = Object.assign({}, state.ctx, { cartTotal: cart.total_price }); mountAll(); })
      .catch(function () { /* offline or blocked */ });
  }

  function watchCart() {
    if (!('PerformanceObserver' in window)) return;
    var pending = null;
    try {
      new PerformanceObserver(function (list) {
        var hit = list.getEntries().some(function (e) { return /\/cart\/(add|change|update|clear)/.test(e.name); });
        if (hit) { clearTimeout(pending); pending = setTimeout(refreshCart, 150); }
      }).observe({ type: 'resource', buffered: false });
    } catch (e) { /* unsupported */ }
  }

  window.OrderOrbit = { version: '1.0.0', render: render, mountAll: mountAll, matches: matches, choose: choose, events: events, renderers: R };

  if (document.querySelector('[data-oo-block]')) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll); else mountAll();
    watchCart();
    document.addEventListener('shopify:section:load', function () { state.data = null; state.ctx = null; mountAll(); });
  }
})();
