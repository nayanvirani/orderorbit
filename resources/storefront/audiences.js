/*
 * Audiences & Personalization (oo-audiences.js), loaded before the first render when the
 * store has published segments or rules. Segments are evaluated in the shopper's browser from
 * the page context (the signed-in customer's orders, spend, tags and purchases; device; market)
 * and the experiences they interacted with on this device. Rules then show, swap the template
 * of, or hide experiences, highest priority first. Nothing is sent anywhere.
 */
(function () {
  var MOBILE = '(max-width: 749px)';
  var p = null;
  try {
    var node = document.querySelector('script[data-oo-data]');
    p = node ? (JSON.parse(node.textContent) || {}).p : null;
  } catch (e) { p = null; }
  if (!p) return;

  // Experience interactions on this device: { handle: { v: viewed, c: clicked, a: added } }.
  function interactions() {
    try { return JSON.parse(localStorage.getItem('oo_ix') || '{}'); } catch (e) { return {}; }
  }
  document.addEventListener('orderorbit:event', function (e) {
    var d = e.detail || {};
    var kind = { 'orderorbit:experience_viewed': 'v', 'orderorbit:experience_clicked': 'c', 'orderorbit:added_to_cart': 'a' }[d.event];
    if (!kind || !d.experience_id) return;
    var all = interactions();
    (all[d.experience_id] = all[d.experience_id] || {})[kind] = Date.now();
    try { localStorage.setItem('oo_ix', JSON.stringify(all)); } catch (err) { /* private mode */ }
  });

  function utm(key) {
    var value = new URLSearchParams(location.search).get(key);
    try {
      if (value) sessionStorage.setItem('oo_' + key, value);
      return value || sessionStorage.getItem('oo_' + key) || '';
    } catch (e) { return value || ''; }
  }

  function num(op, actual, value) {
    if (actual == null) return false;
    value = Number(value);
    return op === 'lte' ? actual <= value : op === 'eq' ? actual === value : actual >= value;
  }

  function rule(r, ctx) {
    var c = ctx.customer || {};
    var orders = ctx.ordersCount || 0;
    var spent = (c.spent || 0) / 100;
    var mobile = window.matchMedia && window.matchMedia(MOBILE).matches;
    var list = function (v) { return String(v || '').toUpperCase().split(',').map(function (s) { return s.trim(); }).filter(Boolean); };
    switch (r.field) {
      case 'orders_count': return num(r.op, orders, r.value);
      case 'ltv': return num(r.op, spent, r.value);
      case 'aov': return orders ? num(r.op, spent / orders, r.value) : false;
      case 'days_since_order': return c.lastOrderAt ? num(r.op, Math.floor((Date.now() / 1000 - c.lastOrderAt) / 86400), r.value) : false;
      case 'customer': return r.value === 'signed_in' ? !!ctx.loggedIn : !ctx.loggedIn;
      case 'customer_tag':
        var has = (c.tags || []).map(function (t) { return String(t).toUpperCase(); }).indexOf(String(r.value).toUpperCase()) !== -1;
        return r.op === 'not' ? !has : has;
      case 'product_purchased':
        var bought = (c.products || []).map(String);
        var found = (r.value || []).some(function (id) { return bought.indexOf(String(id)) !== -1; });
        return r.op === 'not' ? !found : found;
      case 'country':
        var inList = list(r.value).indexOf(String(ctx.country || '').toUpperCase()) !== -1;
        return r.op === 'not' ? !inList : inList;
      case 'device': return r.value === 'mobile' ? mobile : !mobile;
      case 'experience':
        var seen = interactions()[r.value] || {};
        return !!seen[r.op === 'clicked' ? 'c' : r.op === 'added' ? 'a' : 'v'];
    }
    return false;
  }

  // Segment ids this shopper belongs to.
  function members(ctx) {
    var out = {};
    Object.keys(p.segments || {}).forEach(function (id) {
      var s = p.segments[id];
      var results = (s.rules || []).map(function (r) { return rule(r, ctx); });
      out[id] = results.length > 0 && (s.match === 'any' ? results.indexOf(true) !== -1 : results.indexOf(false) === -1);
    });
    return out;
  }

  function inAny(ids, m) {
    return !ids || !ids.length || ids.some(function (id) { return m[id]; });
  }

  function live(c, ctx) {
    c = c || {};
    var cart = (ctx.cartTotal || 0) / 100;
    var mobile = window.matchMedia && window.matchMedia(MOBILE).matches;
    if ((c.device === 'mobile' && !mobile) || (c.device === 'desktop' && mobile)) return false;
    if (c.cart_min != null && c.cart_min !== '' && cart < Number(c.cart_min)) return false;
    if (c.cart_max != null && c.cart_max !== '' && cart > Number(c.cart_max)) return false;
    if (c.utm_source && utm('utm_source') !== c.utm_source) return false;
    if (c.utm_campaign && utm('utm_campaign') !== c.utm_campaign) return false;
    return true;
  }

  // For A/B tests with a segment audience.
  window.OrderOrbitSeg = members;

  window.OrderOrbitPz = function (experiences, ctx) {
    var m = members(ctx);
    var rules = p.rules || [];
    return experiences.reduce(function (out, e) {
      var t = e.targeting || {};
      if (!ctx.preview && !inAny(t.segments, m)) return out;
      var mine = rules.filter(function (r) { return r.experience === e.id; });
      var hit = mine.filter(function (r) { return inAny(r.segments, m) && live(r.when, ctx); })[0];
      if (hit && hit.outcome === 'hide') return out;
      if (hit && hit.outcome === 'swap') {
        out.push(Object.assign({}, e, { template: hit.template, style: hit.style }));
        return out;
      }
      // An experience with "show" rules is only for the shoppers those rules match.
      if (!hit && mine.some(function (r) { return r.outcome === 'show'; })) return out;
      out.push(e);
      return out;
    }, []);
  };
})();
