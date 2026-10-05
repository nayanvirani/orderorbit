/* OrderOrbit · gift lines in the cart. The reward sets their quantity, so the theme's quantity and
   remove controls are hidden for them in the cart page and cart drawer, with "Free gift · Qty n" instead.
   Loaded by the core only while the cart holds a gift. */
(function () {
  var CARTS = 'cart-drawer,cart-drawer-items,cart-items,cart-notification,form[action$="/cart"],#CartDrawer,#cart,[id*="cart-drawer"],[class*="cart-drawer"],[class*="mini-cart"]';
  var ROW = 'tr,li,.cart-item,[class*="cart-item"],[class*="line-item"],[data-cart-item]';
  var CONTROLS = 'quantity-input,quantity-popover,.quantity,[class*="quantity-selector"],[class*="qty-selector"],input[name="updates[]"],cart-remove-button,[class*="remove"],a[href*="/cart/change"]';
  var lines = [];
  var queued = false;

  var style = document.createElement('style');
  style.textContent = '.oo-gift-locked{display:none!important}.oo-gift-qty{display:inline-block;font-size:.85em;font-weight:600;padding:4px 0}';
  document.head.appendChild(style);

  function read(items) {
    lines = items.map(function (l, i) { return { line: i + 1, key: l.key, qty: l.qty, gift: l.gift }; }).filter(function (l) { return l.gift != null; });
    queue();
  }

  // Themes mark a line by its position (Dawn: data-index, CartItem-n), its key or a /cart/change link.
  function markers(l) {
    var n = l.line;
    return ['[id$="-Item-' + n + '"]', '[id="CartItem-' + n + '"]', '[data-index="' + n + '"]', '[data-line="' + n + '"]', '[data-key="' + l.key + '"]',
      '[data-cart-item-key="' + l.key + '"]', '[data-line-item-key="' + l.key + '"]', 'a[href*="line=' + n + '&"]', 'a[href*="id=' + l.key + '"]'].join(',');
  }

  function apply() {
    queued = false;
    if (!lines.length) return;
    var carts = document.querySelectorAll(CARTS);
    lines.forEach(function (l) {
      var sel = markers(l);
      var rows = [];
      Array.prototype.forEach.call(carts, function (cart) {
        Array.prototype.forEach.call(cart.querySelectorAll(sel), function (m) {
          var row = m.closest(ROW);
          if (row && cart.contains(row) && rows.indexOf(row) === -1) rows.push(row);
        });
      });
      rows.forEach(function (row) {
        var hidden = Array.prototype.filter.call(row.querySelectorAll(CONTROLS), function (el) { return !el.querySelector('img,a[href*="/products/"]') && !el.closest('.oo-gift-locked'); });
        hidden.forEach(function (el) { el.classList.add('oo-gift-locked'); });
        var label = row.querySelector('.oo-gift-qty');
        var text = 'Free gift · Qty ' + l.qty;
        if (label) { if (label.textContent !== text) label.textContent = text; return; }
        if (!hidden.length) return;
        label = document.createElement('span');
        label.className = 'oo-gift-qty';
        label.textContent = text;
        hidden[0].parentNode.insertBefore(label, hidden[0]);
      });
    });
  }

  function queue() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(apply);
  }

  function refetch() {
    fetch(((window.Shopify && Shopify.routes && Shopify.routes.root) || '/') + 'cart.js', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (cart) {
        read((cart.items || []).map(function (i) { return { key: i.key, qty: i.quantity, gift: (i.properties || {})._oo_gift }; }));
      })
      .catch(function () { /* offline */ });
  }

  try { read((JSON.parse(document.querySelector('script[data-oo-context]').textContent).cartLines) || []); } catch (e) { refetch(); }

  // Themes re-render the cart after every change: lock the new rows, and re-read the cart.
  new MutationObserver(queue).observe(document.body, { childList: true, subtree: true });
  if ('PerformanceObserver' in window) {
    try {
      new PerformanceObserver(function (list) {
        if (list.getEntries().some(function (e) { return /\/cart\/(add|change|update|clear)/.test(e.name); })) setTimeout(refetch, 200);
      }).observe({ type: 'resource', buffered: false });
    } catch (e) { /* unsupported */ }
  }
})();
