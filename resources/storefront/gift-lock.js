/* OrderOrbit · gift lines in the cart, on every page while the cart holds one (loaded by the core).
   - The reward sets a gift's quantity, so the theme's quantity and remove controls are hidden for it
     in the cart page and cart drawer, with "Free gift · Qty n" instead.
   - A gift whose milestone is no longer reached would be charged, so it's taken out; a gift whose
     quantity was changed is set back to what its reward gives. */
(function () {
  var CARTS = 'cart-drawer,cart-drawer-items,cart-items,cart-notification,form[action$="/cart"],#CartDrawer,#cart,[id*="cart-drawer"],[class*="cart-drawer"],[class*="mini-cart"]';
  var ROW = 'tr,li,.cart-item,[class*="cart-item"],[class*="line-item"],[data-cart-item]';
  var CONTROLS = 'quantity-input,quantity-popover,.quantity,[class*="quantity-selector"],[class*="qty-selector"],input[name="updates[]"],cart-remove-button,[class*="remove"],a[href*="/cart/change"]';
  var root = (window.Shopify && Shopify.routes && Shopify.routes.root) || '/';
  var cart = { total: 0, lines: [] };
  var gifts = [];
  var queued = false;
  var busy = false;
  var tries = {};

  var style = document.createElement('style');
  style.textContent = '.oo-gift-locked{display:none!important}.oo-gift-qty{display:inline-block;font-size:.85em;font-weight:600;padding:4px 0}';
  document.head.appendChild(style);

  function json(selector) {
    try { return JSON.parse(document.querySelector(selector).textContent); } catch (e) { return null; }
  }

  function read(total, items) {
    cart = { total: total || 0, lines: items.map(function (l, i) { l.line = i + 1; return l; }) };
    gifts = cart.lines.filter(function (l) { return l.gift != null; });
    queue();
    guard();
  }

  // ------------------------------------------------------------ keep gifts right
  function campaign(id) {
    var data = json('script[data-oo-data]') || {};
    return (data.experiences || []).filter(function (e) { return e.id === id && e.type === 'progressive-gifts'; })[0];
  }

  // Progress in the campaign's unlock unit (money or items), its own gifts excluded.
  function progress(exp) {
    var own = function (l) { return l.offer === exp.id && l.gift != null; };
    if (exp.content.settings.unlock === 'count') {
      return cart.lines.filter(function (l) { return !own(l); }).reduce(function (n, l) { return n + l.qty; }, 0);
    }
    return Math.max(0, cart.total - cart.lines.filter(own).reduce(function (n, l) { return n + (l.price || 0); }, 0)) / 100;
  }

  // What each reward should hold in the cart: 0 when its milestone isn't reached, else its quantity.
  function wanted(offer, index) {
    var exp = campaign(offer);
    if (!exp) return null;
    var m = exp.content.milestones.filter(function (x) { return String(x.index) === String(index); })[0];
    return !m || progress(exp) < m.threshold ? 0 : (m.quantity || 1);
  }

  // The next change that brings a reward's gift units (across all its lines) to what it gives.
  // A line can be listed more than once (Shopify splits a line that's partly discounted), so
  // quantities are summed per line key first.
  function nextFix() {
    var byKey = {};
    gifts.forEach(function (l) {
      if (byKey[l.key]) byKey[l.key].qty += l.qty; else byKey[l.key] = { key: l.key, qty: l.qty, offer: l.offer, gift: l.gift };
    });
    var groups = {};
    Object.keys(byKey).forEach(function (k) {
      var l = byKey[k], g = l.offer + '|' + l.gift;
      (groups[g] = groups[g] || []).push(l);
    });
    var fix = null;
    Object.keys(groups).some(function (g) {
      var lines = groups[g];
      var left = wanted(lines[0].offer, lines[0].gift);
      if (left == null) return false;
      return lines.some(function (l) {
        var q = Math.min(l.qty, left);
        left -= q;
        if (q === l.qty || (tries[l.key + q] || 0) >= 2) return false;
        fix = { key: l.key, qty: q };
        return true;
      });
    });
    return fix;
  }

  function guard() {
    if (busy) return;
    var fix = nextFix();
    if (!fix) return;
    busy = true;
    tries[fix.key + fix.qty] = (tries[fix.key + fix.qty] || 0) + 1;
    fetch(root + 'cart/change.js', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ id: fix.key, quantity: fix.qty })
    }).then(function (r) { return r.json(); }).then(function (c) {
      busy = false;
      read(c.total_price, items(c));
      redraw();
    }).catch(function () { busy = false; });
  }

  // Show the new cart in the theme (oo-commerce.js knows how) and in OrderOrbit's own widgets.
  function redraw() {
    if (window.OrderOrbit && OrderOrbit.need) OrderOrbit.need('commerce').then(function () { if (OrderOrbit.shop) OrderOrbit.shop.themeCart(); });
    if (window.OrderOrbit && OrderOrbit.refreshCart) OrderOrbit.refreshCart();
  }

  // ------------------------------------------------------------ lock their controls
  // Themes mark a line by its position (Dawn: data-index, CartItem-n), its key or a /cart/change link.
  function markers(l) {
    var n = l.line;
    return ['[id$="-Item-' + n + '"]', '[id="CartItem-' + n + '"]', '[data-index="' + n + '"]', '[data-line="' + n + '"]', '[data-key="' + l.key + '"]',
      '[data-cart-item-key="' + l.key + '"]', '[data-line-item-key="' + l.key + '"]', 'a[href*="line=' + n + '&"]', 'a[href*="id=' + l.key + '"]'].join(',');
  }

  function rowsFor(l, carts) {
    var sel = markers(l);
    var rows = [];
    Array.prototype.forEach.call(carts, function (c) {
      Array.prototype.forEach.call(c.querySelectorAll(sel), function (m) {
        var row = m.closest(ROW);
        if (row && c.contains(row) && rows.indexOf(row) === -1) rows.push(row);
      });
    });
    return rows;
  }

  function apply() {
    queued = false;
    var carts = document.querySelectorAll(CARTS);
    var locked = [];
    gifts.forEach(function (l) {
      rowsFor(l, carts).forEach(function (row) {
        locked.push(row);
        var hidden = Array.prototype.filter.call(row.querySelectorAll(CONTROLS), function (el) { return !el.querySelector('img,a[href*="/products/"]') && !el.closest('.oo-gift-locked'); });
        hidden.forEach(function (el) { el.classList.add('oo-gift-locked'); });
        var label = row.querySelector('.oo-gift-qty');
        var text = 'Free gift · Qty ' + l.qty;
        if (label) { if (label.textContent !== text) label.textContent = text; return; }
        var first = row.querySelector('.oo-gift-locked');
        if (!first) return;
        label = document.createElement('span');
        label.className = 'oo-gift-qty';
        label.textContent = text;
        first.parentNode.insertBefore(label, first);
      });
    });
    // Rows that are no longer gifts (the cart changed, or the theme kept the old row) get their controls back.
    document.querySelectorAll('.oo-gift-locked,.oo-gift-qty').forEach(function (el) {
      if (locked.some(function (row) { return row.contains(el); })) return;
      if (el.classList.contains('oo-gift-qty')) el.remove(); else el.classList.remove('oo-gift-locked');
    });
  }

  function queue() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(apply);
  }

  function items(c) {
    return (c.items || []).map(function (i) { return { key: i.key, qty: i.quantity, price: i.final_line_price, offer: (i.properties || {})._oo_offer || null, gift: (i.properties || {})._oo_gift }; });
  }

  function refetch() {
    fetch(root + 'cart.js', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (c) { read(c.total_price, items(c)); })
      .catch(function () { /* offline */ });
  }

  var ctx = json('script[data-oo-context]');
  // The page's cart for a first paint, then the live cart (a gift may have been added since the page loaded).
  if (ctx && ctx.cartLines) read(ctx.cartTotal, ctx.cartLines.map(function (l) { return { key: l.key, qty: l.qty, price: l.price, offer: l.offer, gift: l.gift }; }));
  refetch();

  // Themes re-render the cart after every change: lock the new rows, and re-read the cart.
  new MutationObserver(queue).observe(document.body, { childList: true, subtree: true });
  if ('PerformanceObserver' in window) {
    try {
      new PerformanceObserver(function (list) {
        if (list.getEntries().some(function (e) { return /\/cart\/(add|change|update|clear)/.test(e.name) && !busy; })) setTimeout(refetch, 200);
      }).observe({ type: 'resource', buffered: false });
    } catch (e) { /* unsupported */ }
  }
})();
