/* Growvia · bundles: mix & match slots and product pool (loaded only for mix & match). */
(function () {
  var h = OrderOrbit.h;

  function tierFor(c, count) {
    var best = null;
    c.mix.tiers.forEach(function (t) { if (count >= t.count) best = t; });
    return best;
  }

  function img(p) { return '<span class="oo-mimg">' + h.productImage(p || {}) + '</span>'; }

  function slot(c, pick, k) {
    var p = pick && c.mix.pool[pick.j];
    return p
      ? '<span class="oo-mslot oo-mslot-on">' + img(p) + '<small>' + h.esc(p.title) + (pick.label ? '<em>' + h.esc(pick.label) + '</em>' : '') + '</small><button type="button" class="oo-mx" aria-label="Remove ' + h.esc(p.title) + '" data-oo-unpick="' + k + '">×</button></span>'
      : '<span class="oo-mslot"><i aria-hidden="true">+</i><small>' + h.esc(c.mix.slot_text) + '</small></span>';
  }

  OrderOrbit.bundleMix = {
    html: function (c, ctx, B) {
      var slots = '';
      for (var k = 0; k < c.mix.slots; k++) slots += '<span data-oo-slot="' + k + '">' + slot(c, null, k) + '</span>';
      var pool = c.mix.pool.map(function (p, j) {
        var many = c.settings.show_variants && p.variants && p.variants.length > 1;
        return '<div class="oo-mcard">' + img(p) +
          '<span class="oo-minfo"><span class="oo-mname">' + h.esc(p.title) + '</span><span class="oo-mprice">' + B.money(p.price || 0, ctx) + '</span>' +
          (many ? B.select('m:' + j, p.variants, ctx) : '') + '</span>' +
          '<button type="button" class="oo-madd" data-oo-pick="' + j + '">+ Add</button></div>';
      }).join('');
      return '<div class="oo-mslots" style="--n:' + c.mix.slots + '">' + slots + '</div><p class="oo-bmsg" data-oo-msg></p><div class="oo-mpool">' + pool + '</div>';
    },

    // Redraws the slots and progress; returns the saving for the summary.
    update: function (root, c, ctx, picks, btn, B, extra) {
      root.querySelectorAll('[data-oo-slot]').forEach(function (el, k) {
        el.innerHTML = slot(c, picks[k], k);
      });
      var total = picks.reduce(function (sum, p) { return sum + p.price; }, 0);
      var tier = tierFor(c, picks.length);
      var next = c.mix.tiers.filter(function (t) { return t.count > picks.length; })[0];
      var saving = tier ? total * tier.discount / 100 : 0;
      root.querySelector('[data-oo-msg]').innerHTML = next
        ? 'Add <b>' + (next.count - picks.length) + '</b> more to save <b>' + h.esc(next.discount) + '%</b>'
        : (tier ? 'Bundle complete: you save <b>' + h.esc(tier.discount) + '%</b>' : '');
      var full = picks.length >= c.mix.slots;
      btn.disabled = picks.length < Math.min(2, c.mix.slots);
      root.querySelectorAll('[data-oo-pick]').forEach(function (b) { b.disabled = full; b.textContent = full ? 'Bundle full' : '+ Add'; });
      btn.innerHTML = h.esc(c.settings.button_text) + (total ? ' · ' + B.money(total - saving + extra, ctx) : '');
      // Subscribe & save (oo-bundles-sub.js) prices the box's first delivery.
      if (c.subscription && c.subscription.enabled && OrderOrbit.bundleSub) OrderOrbit.bundleSub.update(root, c, ctx, 'm', total, total - saving, extra, btn, picks.map(function (p) { return { id: p.v, quantity: 1, properties: { _oo_bundle: 1 } }; }));
      return saving;
    }
  };
})();
