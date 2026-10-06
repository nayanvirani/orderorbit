/* OrderOrbit · commerce helpers, shared by every type that adds to the cart.
   The build prepends this to those type files; the first one loaded defines it. */
(function () {
  if (OrderOrbit.shop) return;
  var h = OrderOrbit.h;
  // Gift icon for the gift and threshold widgets (kept out of the core to keep it small).
  h.GIFT = h.GIFT || '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>';
  var products = {};

  function root() { return (window.Shopify && Shopify.routes && Shopify.routes.root) || '/'; }

  // Live product data in the shopper's currency: variants, availability, prices.
  function load(p) {
    if (!p || !p.handle) return Promise.resolve(p);
    products[p.handle] = products[p.handle] || fetch(root() + 'products/' + p.handle + '.js', { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    // Variants the merchant mapped in the app (kept across re-renders as p.mapped).
    if (!p.mapped) p.mapped = (p.variants || []).map(function (v) { return h.numericId(v.id); });
    return products[p.handle].then(function (live) {
      var variants = (live.variants || []).filter(function (v) {
        return !p.mapped.length || p.mapped.indexOf(String(v.id)) !== -1;
      }).map(function (v) {
        return { id: v.id, title: v.public_title || v.title || v.option1, price: v.price / 100, compare_at: v.compare_at_price ? v.compare_at_price / 100 : null, available: v.available };
      });
      var first = variants.filter(function (v) { return v.available; })[0] || variants[0] || {};
      var img = live.featured_image || (live.images || [])[0];
      return Object.assign(p, {
        title: live.title, image: img ? (img.indexOf('//') === 0 ? 'https:' + img : img) : p.image,
        price: first.price, compare_at: first.compare_at, variant_id: first.id, available: variants.some(function (v) { return v.available; }),
        variants: variants.length > 1 ? variants : null,
        // Subscription plans (selling plans) and each variant's plan prices, for subscription bundles.
        plans: (live.selling_plan_groups || []).length ? { groups: live.selling_plan_groups, variants: live.variants } : null
      });
    }).catch(function () { return p; });
  }

  function hydrate(exp, keys) {
    return Promise.all(keys.reduce(function (all, key) { return all.concat((exp.content[key] || []).map(load)); }, []));
  }

  // A variant picker for multi-variant products; the chosen id is read at add time.
  function variantSelect(p, i, ctx) {
    if (!p.variants) return '';
    return '<select class="oo-variant" data-oo-variant="' + i + '" aria-label="' + h.esc(p.title) + ' option">' + p.variants.map(function (v) {
      return '<option value="' + v.id + '" data-price="' + v.price + '"' + (v.id === p.variant_id ? ' selected' : '') + (v.available ? '' : ' disabled') + '>' + h.esc(v.title) + ' · ' + h.esc(h.money(v.price, ctx.currency)) + '</option>';
    }).join('') + '</select>';
  }

  function chosenVariant(root, p, i) {
    var sel = root.querySelector('[data-oo-variant="' + i + '"]');
    return sel ? sel.value : p.variant_id;
  }

  // The variant selected in the theme's own product form, if the page has one.
  function pageVariant(ctx) {
    var input = document.querySelector('form[action*="/cart/add"] [name="id"]');
    return (input && input.value) || ctx.variant || null;
  }

  function status(rootEl, text, bad) {
    var node = rootEl && rootEl.querySelector('[data-oo-status]');
    if (node) { node.textContent = text || ''; node.classList.toggle('oo-status-error', !!bad); }
  }

  /**
   * Theme callbacks. A theme can define functions on window.OrderOrbitHooks and/or listen for the
   * matching document event. Returning false from the function, or calling preventDefault() on
   * the event, changes what OrderOrbit does next. Errors in theme code never break the widget.
   *   beforeAddToCart  / orderorbit:before-add      → false cancels the add
   *   afterAddToCart   / orderorbit:added-to-cart   → false keeps the shopper on the page
   *   addToCartFailed  / orderorbit:add-failed
   * Resolves to false when the theme asked for the default to be skipped.
   */
  function hook(name, event, detail) {
    var go = true;
    try {
      if (!document.dispatchEvent(new CustomEvent('orderorbit:' + event, { detail: detail, cancelable: true }))) go = false;
    } catch (e) { /* old browsers */ }
    var fn = window.OrderOrbitHooks && window.OrderOrbitHooks[name];
    if (typeof fn !== 'function') return Promise.resolve(go);
    return Promise.resolve().then(function () { return fn(detail); }).then(function (result) {
      return go && result !== false;
    }).catch(function (err) {
      if (window.console) console.error('[OrderOrbit] ' + name + ' callback failed', err);
      return go;
    });
  }

  /** The live cart (Shopify's /cart.js), for theme callbacks. */
  function cart() {
    return fetch(root() + 'cart.js', { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  /**
   * Shows the current cart in the theme after the app changed it (adds, gift fixes): Dawn-style
   * themes re-render their cart drawer and cart count from Shopify's section rendering, the cart
   * page list redraws itself, and other themes get the cart events they listen to.
   */
  function themeCart() {
    var drawer = document.querySelector('cart-drawer');
    var ids = ['cart-icon-bubble'].concat(document.getElementById('CartDrawer') ? ['cart-drawer'] : []);
    var inner = function (html, sel) {
      var el = new DOMParser().parseFromString(html || '', 'text/html').querySelector(sel);
      return el ? el.innerHTML : null;
    };
    document.querySelectorAll('cart-items:not(cart-drawer-items)').forEach(function (el) { if (el.onCartUpdate) el.onCartUpdate(); });
    ['cart:refresh', 'cart:build', 'cart:updated'].forEach(function (name) { document.dispatchEvent(new CustomEvent(name, { bubbles: true })); });
    return Promise.all([
      fetch(root() + '?sections=' + ids.join(','), { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
      cart()
    ]).then(function (res) {
      var sections = res[0] || {};
      var html = inner(sections['cart-icon-bubble'], '.shopify-section');
      var bubble = document.getElementById('cart-icon-bubble');
      if (bubble && html != null) bubble.innerHTML = html;
      html = inner(sections['cart-drawer'], '#CartDrawer');
      var box = document.getElementById('CartDrawer');
      if (box && html != null) {
        box.innerHTML = html;
        if (drawer) {
          drawer.classList.toggle('is-empty', !res[1].item_count);
          var overlay = drawer.querySelector('#CartDrawer-Overlay');
          if (overlay && drawer.close) overlay.addEventListener('click', drawer.close.bind(drawer));
        }
      }
    }).catch(function () { /* the theme shows the new cart on its next load */ });
  }

  /**
   * Adds items ({ id: variant, quantity }) tagged with the experience, then follows the "after add"
   * setting, unless the theme's afterAddToCart callback takes over.
   */
  function add(exp, ctx, items, btn, rootEl) {
    items = items.filter(function (i) { return i && i.id && i.quantity > 0; });
    if (!items.length) { status(rootEl, 'This item is unavailable right now.', true); return Promise.resolve(false); }
    if (ctx.preview) { status(rootEl, 'Preview: adds ' + items.reduce(function (n, i) { return n + i.quantity; }, 0) + ' item(s) to the cart.'); return Promise.resolve(false); }

    var label = btn ? btn.innerHTML : '';
    var reset = function () { if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = label; } };
    var detail = {
      experience: { id: exp.id, type: exp.type, template: exp.template },
      // Theme code may change these before they are sent (e.g. add a line property).
      items: items.map(function (i) { return Object.assign({ id: Number(h.numericId(i.id)), quantity: i.quantity, properties: Object.assign({ _oo_offer: exp.id }, i.properties || {}) }, i.selling_plan ? { selling_plan: i.selling_plan } : {}); }),
      after: (exp.behavior && exp.behavior.after_add) || 'cart',
      element: rootEl || null,
      getCart: cart
    };
    if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = '…'; }

    return hook('beforeAddToCart', 'before-add', detail).then(function (go) {
      if (!go) { reset(); return false; }
      return fetch(root() + 'cart/add.js', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ items: detail.items })
      }).then(function (r) {
        return r.json().then(function (body) { if (!r.ok) throw new Error(body.description || body.message || 'Could not add to cart'); return body; });
      }).then(function (body) {
        OrderOrbit.track('added_to_cart', exp, { quantity: detail.items.reduce(function (n, i) { return n + i.quantity; }, 0) });
        detail.response = body;
        return hook('afterAddToCart', 'added-to-cart', detail);
      }).then(function (go) {
        // The theme handled it (e.g. opened its cart drawer): stay on the page.
        var after = go ? detail.after : 'stay';
        if (after === 'checkout') { location.href = root() + 'checkout'; return true; }
        if (after === 'cart') { location.href = root() + 'cart'; return true; }
        status(rootEl, 'Added to your cart.');
        document.dispatchEvent(new CustomEvent('orderorbit:cart-updated', { detail: { experience_id: exp.id } }));
        reset();
        themeCart();
        return OrderOrbit.refreshCart().then(function () { return true; });
      });
    }).catch(function (err) {
      status(rootEl, err.message, true);
      reset();
      detail.message = err.message;
      hook('addToCartFailed', 'add-failed', detail);
      return false;
    });
  }

  function change(key, quantity) {
    return fetch(root() + 'cart/change.js', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ id: key, quantity: quantity })
    });
  }

  function linesFor(ctx, exp) {
    return (ctx.cartLines || []).filter(function (l) { return l.offer === exp.id; });
  }

  function inCart(ctx, p) {
    var id = h.numericId(p.id);
    return (ctx.cartLines || []).some(function (l) { return String(l.product) === id; });
  }

  // Price after the experience's saving, for display (the discount function applies the real one).
  function saving(total, type, value) {
    // Amounts are set in the shop's currency; convert for shoppers browsing in another.
    if (type === 'amount') return Math.max(0, total - Number(value || 0) * ((window.Shopify && Shopify.currency && Number(Shopify.currency.rate)) || 1));
    if (type === 'percentage') return total * (1 - Number(value || 0) / 100);
    return total;
  }

  OrderOrbit.shop = { cart: cart, load: load, hydrate: hydrate, variantSelect: variantSelect, chosenVariant: chosenVariant, pageVariant: pageVariant, add: add, change: change, themeCart: themeCart, status: status, linesFor: linesFor, inCart: inCart, saving: saving };
})();
