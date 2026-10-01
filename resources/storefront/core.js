/*!
 * OrderOrbit storefront runtime (core). Renders published experiences inside
 * OrderOrbit app blocks that merchants place in the Theme Editor. It never edits
 * theme markup or intercepts the theme's own add-to-cart; bundle, upsell, gift
 * and quantity-break buttons add their own items through the cart API, and the
 * OrderOrbit discount applies the saving at checkout. Each experience type's
 * renderer is a separate small file loaded only when a page shows that type.
 * Also powers the in-app preview.
 */
(function () {
  'use strict';

  if (window.OrderOrbit && window.OrderOrbit.version) return;

  var MOBILE = '(max-width: 749px)';
  var events = [];
  var renderers = {};
  var hooks = {};
  // Types that depend on the cart re-render when it changes; others keep the shopper's selections.
  var CART_TYPES = ['shipping-bar', 'free-gifts', 'cart-upsells', 'progressive-gifts'];
  var loading = {};
  var assetBase = '';
  var assetQuery = '';

  // ---------------------------------------------------------------- helpers (shared with type files)
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

  function icon(paths) {
    return '<svg class="oo-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
  }

  var GIFT = '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>';

  function productImage(p) {
    return p && p.image
      ? '<img class="oo-img" src="' + esc(p.image) + '" alt="' + esc(p.title) + '" loading="lazy" width="120" height="120">'
      : '<span class="oo-img oo-img-empty" aria-hidden="true"></span>';
  }

  var h = { esc: esc, money: money, fill: fill, numericId: numericId, icon: icon, GIFT: GIFT, productImage: productImage, };

  function track(name, exp, extra) {
    var a = exp.analytics || {};
    if (name === 'experience_viewed' ? a.track_views === false : a.track_clicks === false) return;
    var detail = Object.assign({ event: 'orderorbit:' + name, experience_id: exp.id, experience_type: exp.type, template_id: exp.template, version: exp.version, timestamp: new Date().toISOString() }, extra || {});
    events.push(detail);
    try { document.dispatchEvent(new CustomEvent('orderorbit:event', { detail: detail })); } catch (e) { /* old browsers */ }
    // Shopify's analytics bus: the OrderOrbit Space pixel records it (with the shopper's consent).
    try { if (window.Shopify && Shopify.analytics && Shopify.analytics.publish) Shopify.analytics.publish('orderorbit_event', detail); } catch (e) { /* not a storefront */ }
  }

  // ---------------------------------------------------------------- targeting
  function utm(key) {
    var value = new URLSearchParams(location.search).get(key);
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
    if (t.exclude && t.exclude.some(function (p) { return numericId(p.id) === String(ctx.product); })) return false;
    if (t.products && t.products.length) {
      var ids = t.products.map(function (p) { return numericId(p.id); });
      if (!ctx.product || ids.indexOf(String(ctx.product)) === -1) return false;
    }
    if (t.collections && t.collections.length) {
      var have = (ctx.collections || []).map(String);
      if (!t.collections.some(function (c) { return have.indexOf(numericId(c.id)) !== -1; })) return false;
    }
    var cart = (ctx.cartTotal || 0) / 100;
    if (t.cart_min != null && t.cart_min !== '' && cart < Number(t.cart_min)) return false;
    if (t.cart_max != null && t.cart_max !== '' && cart > Number(t.cart_max)) return false;
    var mobile = window.matchMedia && window.matchMedia(MOBILE).matches;
    if ((t.device === 'mobile' && !mobile) || (t.device === 'desktop' && mobile)) return false;
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
      return (pinnedId ? e.id === pinnedId : e.type === type) && matches(e, ctx);
    });
    list.sort(function (a, b) { return (b.priority || 0) - (a.priority || 0); });
    return list[0] || null;
  }

  // ---------------------------------------------------------------- type loading
  function setAssets(url) {
    var m = String(url || '').match(/^(.*\/)orderorbit\.js(\?.*)?$/);
    if (m) { assetBase = m[1]; assetQuery = m[2] || ''; }
  }

  // Shared helpers loaded first: oo-commerce.js (cart, products) for types that add to the cart,
  // oo-timer.js for countdowns.
  function needs(type) {
    return (/^(shipping-bar|countdown|trust|sales-pop|preorder)$/.test(type) ? [] : ['commerce']).concat(/^(bundles|countdown)$/.test(type) ? ['timer'] : /^(shipping-bar|free-gifts)$/.test(type) ? ['thresholds'] : []);
  }

  function script(name) {
    if (!loading[name]) {
      loading[name] = new Promise(function (resolve) {
        var s = document.createElement('script');
        s.src = assetBase + 'oo-' + name + '.js' + assetQuery;
        s.async = true;
        s.onload = s.onerror = resolve;
        document.head.appendChild(s);
      });
    }
    return loading[name];
  }

  function load(type) {
    if (renderers[type]) return Promise.resolve(renderers[type]);
    return Promise.all(needs(type).map(script))
      .then(function () { return script(type); })
      .then(function () { return renderers[type] || null; });
  }

  /** hook: { prepare(exp, ctx) -> Promise (e.g. load live products), setup(root, exp, ctx) (bind buttons) } */
  function define(type, fn, hook) { renderers[type] = fn; hooks[type] = hook || {}; }

  // ---------------------------------------------------------------- rendering
  function styleVars(d) {
    return '--oo-primary:' + (d.primary_color || '#303030') + ';--oo-accent:' + (d.accent_color || '#5b4bff') + ';--oo-text:' + (d.text_color || '#1d1b33') +
      ';--oo-bg:' + (d.background_color || '#ffffff') + ';--oo-radius:' + (d.radius != null ? d.radius : 12) + 'px';
  }

  // Merchant CSS, scoped to this experience only.
  function scopedCss(exp) {
    var css = exp.design && exp.design.custom_css;
    if (!css) return '';
    var scope = '[data-oo-exp="' + exp.id + '"] ';
    return '<style>' + String(css).replace(/<\/?style[^>]*>/gi, '').replace(/@import[^;]*;?/gi, '').replace(/(^|})\s*([^{}@]+)\{/g, function (m, close, sel) {
      return close + sel.split(',').map(function (s) { return scope + s.trim(); }).join(', ') + '{';
    }) + '</style>';
  }

  function paint(el, exp, ctx, renderer) {
    var inner = renderer ? renderer(exp, ctx, h) : null;
    if (!inner) { el.innerHTML = ''; el.hidden = true; return false; }

    var d = exp.design || {};
    var b = exp.behavior || {};
    var cls = ['oo-exp', 'oo-type-' + exp.type, 'oo-style-' + (exp.style || 'card'), 'oo-space-' + (d.spacing || 'comfortable'), 'oo-btn-' + (d.button_style || 'filled')];
    if (d.border) cls.push('oo-bordered');
    if (d.font === 'system') cls.push('oo-font-system');
    if (d.hide_on_mobile) cls.push('oo-hide-mobile');
    if (d.hide_on_desktop) cls.push('oo-hide-desktop');
    if (b.animation && b.animation !== 'none' && !ctx.preview) cls.push('oo-anim-' + b.animation);
    if (exp.type === 'sticky-atc') cls.push('oo-sticky-' + ((exp.content && exp.content.position) || 'bottom'));

    el.hidden = false;
    el.innerHTML = scopedCss(exp) + '<div class="' + cls.join(' ') + '" data-oo-exp="' + esc(exp.id) + '" style="' + styleVars(d) + '">' +
      (b.dismissible ? '<button type="button" class="oo-close" aria-label="Dismiss" data-oo-dismiss>×</button>' : '') + inner + '</div>';

    if (OrderOrbit.timers) OrderOrbit.timers(el);
    el.__ooExp = exp.id;
    var hook = hooks[exp.type] || {};
    if (hook.setup) hook.setup(el.querySelector('.oo-exp'), exp, ctx);
    if (!ctx.preview && !el.__ooBound) {
      el.__ooBound = true;
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

  /** Renders one experience into el. Returns a Promise<boolean> (false when nothing shows). */
  function render(el, exp, ctx) {
    ctx = ctx || {};
    return load(exp.type).then(function (renderer) {
      var hook = hooks[exp.type] || {};
      return Promise.resolve(hook.prepare && (!ctx.preview || hook.always) ? hook.prepare(exp, ctx) : null)
        .catch(function () { /* live data unavailable: render with saved data */ })
        .then(function () { return paint(el, exp, ctx, renderer); });
    });
  }

  // ---------------------------------------------------------------- storefront mounting
  function readJson(selector) {
    var node = document.querySelector(selector);
    try { return node ? JSON.parse(node.textContent) : null; } catch (e) { return null; }
  }

  var state = { data: null, ctx: null };

  // Experiences set to sit above or below the theme's add-to-cart are placed there by the
  // app embed, unless the merchant placed a block for them in the Theme Editor.
  function inject() {
    var form = state.ctx.page === 'product' && document.querySelector('form[action*="/cart/add"]');
    if (!form) return;
    state.data.experiences.forEach(function (e) {
      var pos = e.behavior && e.behavior.position;
      if (!pos || pos === 'block' || document.querySelector('[data-oo-id="' + e.id + '"],[data-oo-block][data-oo-type="' + e.type + '"]:not([data-oo-auto])')) return;
      var el = document.createElement('div');
      el.className = 'oo-root oo-auto';
      el.setAttribute('data-oo-block', '');
      el.setAttribute('data-oo-type', e.type);
      el.setAttribute('data-oo-id', e.id);
      el.setAttribute('data-oo-auto', '');
      var btn = form.querySelector('[type="submit"],[name="add"]');
      var anchor = (btn && (btn.closest('.product-form__buttons') || btn)) || form;
      anchor.parentNode.insertBefore(el, pos === 'below_atc' ? anchor.nextSibling : anchor);
    });
  }

  function mountAll(reason) {
    state.data = state.data || readJson('script[data-oo-data]') || { experiences: [] };
    state.ctx = state.ctx || readJson('script[data-oo-context]') || {};
    setAssets(state.ctx.assets);
    // Global types (Sales pop) get one root on the page; they never need a theme block.
    if (!document.querySelector('[data-oo-type="sales-pop"]') && state.data.experiences.some(function (e) { return e.type === 'sales-pop'; })) {
      var g = document.createElement('div');
      g.className = 'oo-root';
      g.setAttribute('data-oo-block', '');
      g.setAttribute('data-oo-type', 'sales-pop');
      document.body.appendChild(g);
    }
    if (reason !== 'cart') inject();
    document.querySelectorAll('[data-oo-block]').forEach(function (el) {
      var exp = choose(state.data.experiences, el.getAttribute('data-oo-type'), (el.getAttribute('data-oo-id') || '').trim(), state.ctx);
      if (exp && reason === 'cart' && el.__ooExp === exp.id && CART_TYPES.indexOf(exp.type) === -1) {
        return;
      } else if (exp) {
        render(el, exp, Object.assign({ currency: state.data.currency }, state.ctx));
      } else if (state.ctx.designMode) {
        // Only merchants in the Theme Editor see this; shoppers see nothing.
        el.hidden = false;
        el.innerHTML = '<div class="oo-editor-note">OrderOrbit Space: nothing published matches this block here yet.</div>';
      } else {
        el.hidden = true;
      }
    });
  }

  // Re-render cart-aware experiences when the cart changes. We watch for completed
  // cart requests; we never wrap or block the theme's own calls.
  function refreshCart() {
    return fetch(((window.Shopify && Shopify.routes && Shopify.routes.root) || '/') + 'cart.js', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (cart) {
        state.ctx = Object.assign({}, state.ctx || {}, {
          cartTotal: cart.total_price,
          cartLines: (cart.items || []).map(function (i) { return { key: i.key, variant: i.variant_id, product: i.product_id, qty: i.quantity, price: i.final_line_price, offer: (i.properties || {})._oo_offer || null, gift: (i.properties || {})._oo_gift }; })
        });
        mountAll('cart');
      })
      .catch(function () { /* offline or blocked */ });
  }

  function watchCart() {
    if (!('PerformanceObserver' in window)) return;
    var pending = null;
    try {
      new PerformanceObserver(function (list) {
        if (list.getEntries().some(function (e) { return /\/cart\/(add|change|update|clear)/.test(e.name); })) {
          clearTimeout(pending);
          pending = setTimeout(refreshCart, 150);
        }
      }).observe({ type: 'resource', buffered: false });
    } catch (e) { /* unsupported */ }
  }

  window.OrderOrbit = { version: '1.4.0', need: script, render: render, define: define, setAssets: setAssets, mountAll: mountAll, refreshCart: refreshCart, matches: matches, choose: choose, track: track, events: events, h: h };

  var self = document.currentScript;
  if (self) setAssets(self.src);

  if (document.querySelector('script[data-oo-data]')) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll); else mountAll();
    watchCart();
    document.addEventListener('shopify:section:load', function () { state.data = null; state.ctx = null; mountAll(); });
  }
})();
