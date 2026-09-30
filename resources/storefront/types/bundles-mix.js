/* OrderOrbit · bundles: mix & match slots (loaded only for mix & match bundles). */
(function () {
  var h = OrderOrbit.h;

  function tierFor(c, count) {
    var best = null;
    c.mix.tiers.forEach(function (t) { if (count >= t.count) best = t; });
    return best;
  }

  function empty(c) { return '<i>+</i><small>' + h.esc(c.mix.slot_text) + '</small>'; }

  OrderOrbit.bundleMix = {
    html: function (c, ctx, B) {
      var slots = '';
      for (var k = 0; k < c.mix.slots; k++) slots += '<span class="oo-bslot" data-oo-slot="' + k + '">' + empty(c) + '</span>';
      var pool = c.mix.pool.map(function (p, j) {
        return '<div class="oo-bitem oo-bpool">' + h.productImage(p) + '<span class="oo-bitem-name">' + h.esc(p.title) + '</span><span class="oo-bitem-price">' + B.money(p.price || 0, ctx) + '</span>' +
          (c.settings.show_variants && p.variants && p.variants.length > 1 ? B.select('m:' + j, p.variants, ctx) : '') +
          '<button type="button" class="oo-btn oo-btn-sm" data-oo-pick="' + j + '">Add</button></div>';
      }).join('');
      return '<div class="oo-bslots">' + slots + '</div><p class="oo-bmsg" data-oo-msg></p><div class="oo-bpoollist">' + pool + '</div>';
    },

    // Redraws the slots and progress; returns the saving for the summary.
    update: function (root, c, ctx, picks, btn, B, extra) {
      root.querySelectorAll('[data-oo-slot]').forEach(function (el, k) {
        var p = picks[k] && c.mix.pool[picks[k].j];
        el.classList.toggle('oo-bfilled', !!p);
        el.innerHTML = p ? h.productImage(p) + '<small>' + h.esc(p.title) + '</small><button type="button" aria-label="Remove" data-oo-unpick="' + k + '">×</button>' : empty(c);
      });
      var total = picks.reduce(function (sum, p) { return sum + Number(c.mix.pool[p.j].price || 0); }, 0);
      var tier = tierFor(c, picks.length);
      var next = c.mix.tiers.filter(function (t) { return t.count > picks.length; })[0];
      var saving = tier ? total * tier.discount / 100 : 0;
      root.querySelector('[data-oo-msg]').innerHTML = next
        ? 'Add ' + (next.count - picks.length) + ' more to save ' + h.esc(next.discount) + '%'
        : (tier ? 'Bundle complete: you save ' + h.esc(tier.discount) + '%' : '');
      btn.disabled = picks.length < Math.min(2, c.mix.slots);
      root.querySelectorAll('[data-oo-pick]').forEach(function (b) { b.disabled = picks.length >= c.mix.slots; });
      btn.innerHTML = h.esc(c.settings.button_text) + (total ? ' · ' + B.money(total - saving + extra, ctx) : '');
      return saving;
    }
  };
})();
