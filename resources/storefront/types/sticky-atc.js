/* OrderOrbit Space · sticky add to cart. Appears once the theme's own buy button scrolls out of
   view. A tap scrolls back to the product form (so shoppers can pick options) or uses the theme's
   own button; the theme keeps its variant, quantity, selling plan and cart drawer logic. */
(function () {
  var S = OrderOrbit.shop;
  var h = OrderOrbit.h;

  function themeForm() { return document.querySelector('form[action*="/cart/add"]:not([data-oo-form])'); }
  function themeButton() {
    var form = themeForm();
    return form && form.querySelector('[type="submit"], button[name="add"]');
  }

  OrderOrbit.define('sticky-atc', function (exp, ctx) {
    if (!ctx.product && !ctx.preview) return null;
    var c = exp.content;
    var price = (ctx.productPrice != null ? ctx.productPrice : 2900) / 100;
    var compare = ctx.productCompareAt ? ctx.productCompareAt / 100 : (ctx.preview && c.show_compare ? price * 1.25 : 0);
    var info = (c.show_title !== false ? '<span class="oo-name">' + h.esc(ctx.productTitle || 'Product name') + '</span>' : '') +
      (c.show_price ? '<span class="oo-price">' + (c.show_compare && compare > price ? '<s>' + h.esc(h.money(compare, ctx.currency)) + '</s> ' : '') + '<b>' + h.esc(h.money(price, ctx.currency)) + '</b></span>' : '');
    return '<div class="oo-body oo-sticky-body oo-sa-' + (exp.style || 'bar') + '">' +
      (c.show_image && exp.style !== 'pill' ? '<span class="oo-sa-img">' + h.productImage({ image: ctx.productImage || (ctx.preview ? window.OO_SAMPLE_IMAGE : null), title: ctx.productTitle }) + '</span>' : '') +
      (info ? '<div class="oo-card-meta">' + info + '</div>' : '') +
      '<button type="button" class="oo-btn" data-oo-click="sticky_atc_clicked" data-oo-add>' + h.esc(c.button_text) + '</button><p class="oo-status" data-oo-status role="status"></p></div>';
  }, {
    setup: function (root, exp, ctx) {
      if (!root) return;
      var c = exp.content;
      root.style.setProperty('--sa-offset', (c.offset != null ? c.offset : 12) + 'px');
      root.classList.add('oo-sa-dev-' + (c.devices || 'all'));
      if (ctx.preview) return;
      // Themes put the product column in its own stacking context (Dawn's sticky info column),
      // which traps a fixed bar under later sections like the footer. Move the bar's mount to
      // <body>; a section re-render (variant change) replaces the copy moved before.
      var mount = root.parentNode;
      if (mount && mount.parentNode !== document.body) {
        [].forEach.call(document.querySelectorAll('body > [data-oo-sa-portal]'), function (old) { if (old !== mount) old.remove(); });
        mount.setAttribute('data-oo-sa-portal', '');
        document.body.appendChild(mount);
      }
      var target = themeButton();
      if (target && 'IntersectionObserver' in window) {
        root.classList.add('oo-sticky-away');
        new IntersectionObserver(function (entries) {
          root.classList.toggle('oo-sticky-away', entries[0].isIntersecting || entries[0].boundingClientRect.top > 0);
        }).observe(target);
      } else if (!target) {
        // No theme buy button found: nothing to follow, so stay hidden.
        root.classList.add('oo-sticky-away');
        return;
      }
      var btn = root.querySelector('[data-oo-add]');
      btn.addEventListener('click', function () {
        var theme = themeButton();
        if (c.on_click === 'add' && theme && !theme.disabled) { theme.click(); return; }
        var form = themeForm();
        if (form) {
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }
        S.add(exp, ctx, [{ id: S.pageVariant(ctx), quantity: 1 }], btn, root);
      });
    }
  });
})();
