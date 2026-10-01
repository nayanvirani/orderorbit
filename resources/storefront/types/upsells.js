/* OrderOrbit · product and cart upsells. Items added here carry the offer, so its incentive applies
   at checkout. A cart upsell can also live inside the theme's cart drawer: the app embed adds a
   root for it (no theme block), and this file moves it into the drawer, keeps it there when the
   theme redraws the drawer, and refreshes the drawer after an add. Themes whose drawer can't be
   refreshed fall back to the cart page. */
(function () {
  var S = OrderOrbit.shop;
  var DRAWER = 'cart-drawer,#CartDrawer,#cart-drawer,.cart-drawer,[data-cart-drawer],#Cart-Drawer,#mini-cart,.mini-cart,#sidebar-cart,.drawer--cart,#slideout-ajax-cart,.ajaxcart__inner';
  var FOOT = '.drawer__footer,.cart-drawer__footer,[class*="drawer__footer"],[class*="drawer-footer"],.cart__footer,.mini-cart__footer,.ajaxcart__footer';
  var INNER = '.drawer__inner,.cart-drawer__inner,[class*="drawer__inner"],[class*="drawer__content"],.mini-cart__inner';

  function visible(exp, ctx, limit) {
    return (exp.content.products || []).filter(function (p) {
      if (ctx.preview) return true;
      if (p.available === false) return false;
      if (ctx.product && OrderOrbit.h.numericId(p.id) === String(ctx.product)) return false;
      return !S.inCart(ctx, p);
    }).slice(0, limit);
  }

  function cards(h, list, c, ctx) {
    return list.map(function (p, i) {
      var price = p.price != null ? Number(p.price) : null;
      var pct = Number(c.discount_percent || 0);
      var offer = pct && price != null ? price * (1 - pct / 100) : price;
      return '<div class="oo-card">' + h.productImage(p) + '<div class="oo-card-meta"><span class="oo-name">' + h.esc(p.title) + '</span>' +
        (price != null ? '<span class="oo-price">' + (pct ? '<s>' + h.esc(h.money(price, ctx.currency)) + '</s> ' : '') + h.esc(h.money(offer, ctx.currency)) + '</span>' : '') +
        S.variantSelect(p, i, ctx) + '</div><button type="button" class="oo-btn oo-btn-sm" data-oo-click="upsell_accepted" data-oo-add="' + i + '">' + h.esc(c.cta_text || 'Add') + '</button></div>';
    }).join('');
  }

  function render(limitKey, subKey) {
    return function (exp, ctx, h) {
      var c = exp.content;
      var list = visible(exp, ctx, limitKey ? Number(c[limitKey] || 3) : 4);
      if (!list.length) return ctx.preview ? '<div class="oo-body"><p class="oo-empty">Choose products to recommend.</p></div>' : null;
      return '<div class="oo-body"><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c[subKey] ? '<p class="oo-sub">' + h.esc(c[subKey]) + '</p>' : '') +
        '<div class="oo-cards">' + cards(h, list, c, ctx) + '</div><p class="oo-status" data-oo-status role="status"></p></div>';
    };
  }

  // Puts the block inside the theme's cart drawer, above its footer. Returns the drawer, or null.
  function place(block) {
    var d = document.querySelector(DRAWER);
    if (!d) return null;
    if (!d.contains(block)) {
      var foot = d.querySelector(FOOT);
      if (foot && foot.parentNode) foot.parentNode.insertBefore(block, foot); else (d.querySelector(INNER) || d).appendChild(block);
    }
    block.classList.add('oo-in-drawer');
    return d;
  }

  // After an add from the drawer: let the theme redraw it, or go to the cart page if it can't.
  function refreshDrawer(d, block, body) {
    try { if (d.renderContents && body.sections) { d.renderContents(body); return; } } catch (e) { /* not a Dawn-style drawer */ }
    var changed = false;
    var watch = new MutationObserver(function (list) {
      changed = changed || list.some(function (m) { return !block.contains(m.target); });
    });
    watch.observe(d, { childList: true, subtree: true });
    ['cart:refresh', 'cart:updated', 'cart:update', 'cart:change'].forEach(function (name) {
      document.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { cart: body } }));
    });
    setTimeout(function () {
      watch.disconnect();
      if (!changed) location.href = ((window.Shopify && Shopify.routes && Shopify.routes.root) || '/') + 'cart';
    }, 1500);
  }

  function hook(limitKey) {
    return {
      // Live product data isn't needed where nothing will show: the drawer upsell with an empty cart.
      prepare: function (exp, ctx) { return limitKey && ctx.page !== 'cart' && !(ctx.cartLines || []).length ? null : S.hydrate(exp, ['products']); },
      setup: function (root, exp, ctx) {
        if (!root) return;
        var c = exp.content;
        var list = visible(exp, ctx, limitKey ? Number(c[limitKey] || 3) : 4);
        var block = root.closest('.oo-root');
        var drawer = null;

        if (block && block.hasAttribute('data-oo-g') && !ctx.preview) {
          drawer = place(block);
          // No drawer in this theme, or nothing in the cart yet: stay out of the way.
          if (!drawer || !(ctx.cartLines || []).length) { block.hidden = true; return; }
          [].slice.call(root.querySelectorAll('.oo-card'), Math.max(1, Number(c.drawer_max) || 2)).forEach(function (card) { card.remove(); });
          if (!block.__ooDrawer && 'MutationObserver' in window) {
            // Themes replace the drawer's contents when the cart changes: put the block back.
            block.__ooDrawer = new MutationObserver(function () { if (drawer.isConnected && !drawer.contains(block)) place(block); });
            block.__ooDrawer.observe(drawer, { childList: true, subtree: true });
          }
        }

        root.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-oo-add]');
          if (!btn) return;
          var i = Number(btn.getAttribute('data-oo-add'));
          OrderOrbit.track('upsell_accepted', exp);
          S.add(exp, ctx, [{ id: S.chosenVariant(root, list[i], i), quantity: 1 }], btn, root, drawer && {
            sections: drawer.getSectionsToRender ? drawer.getSectionsToRender().map(function (s) { return s.id; }) : null,
            done: function (body) { refreshDrawer(drawer, block, body); }
          });
        });
      }
    };
  }

  OrderOrbit.define('product-upsells', render(null, 'offer_message'), hook(null));
  OrderOrbit.define('cart-upsells', render('max_shown', 'incentive'), hook('max_shown'));
})();
