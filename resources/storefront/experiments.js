/*
 * A/B tests (oo-experiments.js), loaded only on pages with an experience under test.
 * Assignment is deterministic: a hash of the experiment id and a visitor id kept in
 * localStorage puts each visitor in the same variant on every visit. Visitors outside the
 * test's audience see the experience as published and aren't counted. Each variant's first
 * view in a session is recorded as an exposure; the visitor's later events carry the variant.
 */
(function () {
  if (!window.OrderOrbit) return;

  function visitor() {
    try {
      var id = localStorage.getItem('oo_vid');
      if (!id) {
        id = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        localStorage.setItem('oo_vid', id);
      }
      return id;
    } catch (e) {
      return null; // storage blocked: not in the test
    }
  }

  // FNV-1a: a stable bucket from 0 to 99.
  function bucket(text) {
    var h = 2166136261;
    for (var i = 0; i < text.length; i++) {
      h ^= text.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return (h >>> 0) % 100;
  }

  // Priced tests (G39): the shopper's versions go to the cart attribute _oo_x ("<id>:B;<id>:H"), so
  // checkout applies the rewards of the version they saw (H = hidden: none).
  function remember(id, key) {
    try {
      var map = JSON.parse(localStorage.getItem('oo_pv') || '{}');
      if (map[id] === key) return;
      map[id] = key;
      localStorage.setItem('oo_pv', JSON.stringify(map));
      var value = Object.keys(map).filter(function (k) { return map[k] !== 'A'; }).map(function (k) { return k + ':' + map[k]; }).join(';');
      fetch(((window.Shopify && Shopify.routes && Shopify.routes.root) || '/') + 'cart/update.js', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ attributes: { _oo_x: value } })
      }).catch(function () {});
    } catch (e) { /* storage blocked */ }
  }

  OrderOrbit.assign = function (exp, ctx) {
    var x = exp.x;
    var id = visitor();
    if (!id || !x.variants || !OrderOrbit.matches({ targeting: x.audience || {} }, ctx)) return exp;
    // Segment audience (Audiences & Personalization).
    var seg = (x.audience && x.audience.segments) || [];
    if (seg.length) {
      var m = window.OrderOrbitSeg ? window.OrderOrbitSeg(ctx) : {};
      if (!seg.some(function (s) { return m[s]; })) return exp;
    }

    var b = bucket(x.id + ':' + id);
    var sum = 0;
    var v = x.variants.filter(function (variant) { sum += variant.alloc; return b < sum; })[0] || x.variants[0];
    var shown = Object.assign({}, exp, {
      template: v.template || exp.template,
      style: v.style || exp.style,
      content: Object.assign({}, exp.content, v.content || {}),
      design: Object.assign({}, exp.design, v.design || {}),
      xv: v.key,
      // A priced version (bundles, G39): its cart lines carry _oo_v so checkout charges its prices.
      pv: v.pv
    });

    if (x.priced && !ctx.preview) remember(exp.id, v.hidden ? 'H' : v.key);

    try {
      if (sessionStorage.getItem('oo_xp_' + x.id) !== v.key) {
        sessionStorage.setItem('oo_xp_' + x.id, v.key);
        OrderOrbit.track('experiment_exposed', shown, { holdout: !!v.hidden });
      }
    } catch (e) {
      OrderOrbit.track('experiment_exposed', shown, { holdout: !!v.hidden });
    }
    return v.hidden ? null : shown;
  };
})();
