/* OrderOrbit · commerce helpers, shared by every type that adds to the cart.
   The build prepends this to those type files; the first one loaded defines it. */
(function () {
  if (OrderOrbit.shop) return;
  var h = OrderOrbit.h;
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
        variants: variants.length > 1 ? variants : null
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
   * Adds items ({ id: variant, quantity }) tagged with the experience, then follows the "after add"
   * setting. With opts ({ sections, done }) it stays on the page instead: the theme's section HTML is
   * requested with the add and handed to done(body), e.g. to refresh a cart drawer.
   */
  function add(exp, ctx, items, btn, rootEl, opts) {
    items = items.filter(function (i) { return i && i.id && i.quantity > 0; });
    if (!items.length) { status(rootEl, 'This item is unavailable right now.', true); return Promise.resolve(false); }
    if (ctx.preview) { status(rootEl, 'Preview: adds ' + items.reduce(function (n, i) { return n + i.quantity; }, 0) + ' item(s) to the cart.'); return Promise.resolve(false); }

    var label = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = '…'; }
    return fetch(root() + 'cart/add.js', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(Object.assign({ items: items.map(function (i) { return { id: Number(h.numericId(i.id)), quantity: i.quantity, properties: Object.assign({ _oo_offer: exp.id }, i.properties || {}) }; }) },
        opts && opts.sections ? { sections: opts.sections, sections_url: location.pathname } : {}))
    }).then(function (r) {
      return r.json().then(function (body) { if (!r.ok) throw new Error(body.description || body.message || 'Could not add to cart'); return body; });
    }).then(function (body) {
      OrderOrbit.track('added_to_cart', exp, { quantity: items.reduce(function (n, i) { return n + i.quantity; }, 0) });
      var after = opts ? 'stay' : (exp.behavior && exp.behavior.after_add) || 'cart';
      if (after === 'checkout') { location.href = root() + 'checkout'; return true; }
      if (after === 'cart') { location.href = root() + 'cart'; return true; }
      status(rootEl, 'Added to your cart.');
      document.dispatchEvent(new CustomEvent('orderorbit:cart-updated', { detail: { experience_id: exp.id } }));
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = label; }
      if (opts && opts.done) opts.done(body);
      return OrderOrbit.refreshCart().then(function () { return true; });
    }).catch(function (err) {
      status(rootEl, err.message, true);
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = label; }
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

  OrderOrbit.shop = { load: load, hydrate: hydrate, variantSelect: variantSelect, chosenVariant: chosenVariant, pageVariant: pageVariant, add: add, change: change, status: status, linesFor: linesFor, inCart: inCart, saving: saving };
})();
