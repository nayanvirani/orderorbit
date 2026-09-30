/* OrderOrbit · sticky add to cart. Appears once the theme's own buy button scrolls out of view and uses it when it can. */
(function () {
  var S = OrderOrbit.shop;

  function themeButton() {
    var form = document.querySelector('form[action*="/cart/add"]:not([data-oo-form])');
    return form && form.querySelector('[type="submit"], button[name="add"]');
  }

  OrderOrbit.define('sticky-atc', function (exp, ctx, h) {
    if (!ctx.product && !ctx.preview) return null;
    var c = exp.content;
    return '<div class="oo-body oo-sticky-body">' + (c.show_image ? h.productImage({ image: ctx.productImage, title: ctx.productTitle }) : '') +
      '<div class="oo-card-meta"><span class="oo-name">' + h.esc(ctx.productTitle || 'Product name') + '</span>' + (c.show_price ? '<span class="oo-price">' + h.esc(h.money((ctx.productPrice || 2900) / 100, ctx.currency)) + '</span>' : '') + '</div>' +
      '<button type="button" class="oo-btn" data-oo-click="sticky_atc_clicked" data-oo-add>' + h.esc(c.button_text) + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    setup: function (root, exp, ctx) {
      if (!root || ctx.preview) return;
      var target = themeButton();
      if (target && 'IntersectionObserver' in window) {
        root.classList.add('oo-sticky-away');
        new IntersectionObserver(function (entries) {
          root.classList.toggle('oo-sticky-away', entries[0].isIntersecting || entries[0].boundingClientRect.top > 0);
        }).observe(target);
      }
      var btn = root.querySelector('[data-oo-add]');
      btn.addEventListener('click', function () {
        var theme = themeButton();
        // The theme's button keeps its own cart drawer and variant logic.
        if (theme && !theme.disabled) { theme.click(); return; }
        S.add(exp, ctx, [{ id: S.pageVariant(ctx), quantity: 1 }], btn, root);
      });
    }
  });
})();
