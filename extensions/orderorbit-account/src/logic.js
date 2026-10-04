/*
 * Pure helpers for the OrderOrbit Space account blocks (no Shopify APIs, so they can be tested in
 * Node): which block to show, reward tiers, purchased products, reorder links and box styles.
 */

/** The page a target renders on: 'orders', 'profile' or 'order'. */
export function pageFor(target) {
  if (String(target).indexOf('order-status') !== -1) return 'order';
  if (String(target).indexOf('profile') !== -1) return 'profile';
  return 'orders';
}

/** The highest-priority published block for this placement. */
export function choose(payload, settings, ctx) {
  const list = (payload && payload.experiences) || [];
  const pinned = String((settings && settings.experience_id) || '').trim();
  const type = String((settings && settings.block) || '').trim();
  const now = ctx.now || Date.now();
  return list
    .filter((e) => (pinned ? e.id === pinned : e.type === type))
    .filter((e) => {
      if (e.starts_at && Date.parse(e.starts_at) > now) return false;
      if (e.ends_at && Date.parse(e.ends_at) <= now) return false;
      const codes = String((e.targeting && e.targeting.countries) || '').toUpperCase().split(',').map((s) => s.trim()).filter(Boolean);
      return !codes.length || codes.indexOf(String(ctx.country || '').toUpperCase()) !== -1;
    })
    .sort((a, b) => (b.priority || 0) - (a.priority || 0))[0] || null;
}

/** "gid://shopify/ProductVariant/123" → "123". */
export function numericId(gid) {
  const m = String(gid || '').match(/(\d+)$/);
  return m ? m[1] : '';
}

/** Replaces {name} placeholders. */
export function fill(template, vars) {
  return String(template || '').replace(/\{(\w+)\}/g, (m, key) => (key in vars ? String(vars[key]) : m));
}

/** A cart permalink that adds these lines: https://shop/cart/123:1,456:2 */
export function cartLink(shopUrl, lines) {
  const parts = (lines || [])
    .filter((l) => numericId(l.variantId) && (l.quantity || 0) > 0)
    .map((l) => numericId(l.variantId) + ':' + l.quantity);
  return parts.length ? String(shopUrl || '').replace(/\/$/, '') + '/cart/' + parts.join(',') : null;
}

/** A store path (/pages/contact) or https link, made absolute; anything else is dropped. */
export function link(shopUrl, value) {
  const v = String(value || '').trim();
  if (/^https:\/\//i.test(v)) return v;
  if (v.charAt(0) === '/') return String(shopUrl || '').replace(/\/$/, '') + v;
  return null;
}

/** Orders that count (not cancelled). */
export function activeOrders(orders) {
  return (orders || []).filter((o) => !o.cancelledAt);
}

/** Total spend across the given orders. */
export function totalSpent(orders) {
  return Math.round(activeOrders(orders).reduce((n, o) => n + Number((o.totalPrice && o.totalPrice.amount) || 0), 0) * 100) / 100;
}

/**
 * The customer's reward tier from total spend.
 * @returns {{ tier, next, remaining, percent }}
 */
export function rewardTier(tiers, spent) {
  const sorted = (tiers || []).filter((t) => t && t.name).slice().sort((a, b) => Number(a.threshold || 0) - Number(b.threshold || 0));
  let tier = null;
  sorted.forEach((t) => { if (spent >= Number(t.threshold || 0)) tier = t; });
  const next = sorted.find((t) => spent < Number(t.threshold || 0)) || null;
  const from = tier ? Number(tier.threshold || 0) : 0;
  const to = next ? Number(next.threshold) : from;
  return {
    tier,
    next,
    remaining: next ? Math.round((to - spent) * 100) / 100 : 0,
    percent: next ? Math.max(0, Math.min(100, Math.round(((spent - from) / Math.max(1, to - from)) * 100))) : 100,
  };
}

/**
 * Products bought across orders, one entry per product with its latest variant.
 * @param sort 'recent' (latest purchase first) or 'frequent' (most bought first)
 */
export function purchasedProducts(orders, sort) {
  const map = {};
  let order = 0;
  activeOrders(orders).forEach((o) => {
    ((o.lineItems && o.lineItems.nodes) || []).forEach((l) => {
      const id = numericId(l.productId);
      if (!id) return;
      if (!map[id]) {
        map[id] = { productId: l.productId, variantId: l.variantId, title: l.title, image: l.image && l.image.url, count: 0, rank: order++ };
      }
      map[id].count += l.quantity || 1;
    });
  });
  const list = Object.keys(map).map((k) => map[k]);
  return sort === 'frequent' ? list.sort((a, b) => b.count - a.count || a.rank - b.rank) : list.sort((a, b) => a.rank - b.rank);
}

/** The step a shipment has reached: 0 ordered, 1 shipped, 2 out for delivery, 3 delivered. */
export function shipmentStep(order) {
  const fulfillments = (order && order.fulfillments && order.fulfillments.nodes) || [];
  let step = 0;
  fulfillments.forEach((f) => {
    const s = f.latestShipmentStatus;
    const n = s === 'DELIVERED' || s === 'PICKED_UP' ? 3 : s === 'OUT_FOR_DELIVERY' ? 2 : f.status === 'SUCCESS' || s ? 1 : 0;
    if (n > step) step = n;
  });
  return step;
}

/** A review link from the merchant's pattern. */
export function reviewLink(pattern, product, shopUrl) {
  const productUrl = product.url || (product.handle ? String(shopUrl || '').replace(/\/$/, '') + '/products/' + product.handle : String(shopUrl || ''));
  return link(shopUrl, fill(pattern || '{product_url}#reviews', { product_url: productUrl, handle: product.handle || '', product_id: numericId(product.productId) })) || productUrl;
}

const BANNER = ['banner'];
const PLAIN = ['compact', 'button', 'faq'];
const BORDERS = ['base', 'large', 'large-100', 'large-200'];

function size(kind, value) {
  const n = Math.round(Number(value) || 0);
  if (kind === 'px' && n > 0) return n + 'px';
  if (kind === 'percent' && n > 0) return Math.min(100, n) + '%';
  return undefined;
}

/** How a block's frame is drawn, from its layout and Design step (same rules as checkout blocks). */
export function boxStyle(style, design) {
  const d = design || {};
  const set = (key) => d[key] && d[key] !== 'auto';
  const custom = set('ck_background') || set('ck_border') || set('ck_radius') || set('ck_padding');
  const sized = {
    inlineSize: d.ck_width && d.ck_width !== 'full' ? size(d.ck_width, d.ck_width_value) : undefined,
    minBlockSize: d.ck_height === 'px' ? size('px', d.ck_height_value) : undefined,
  };
  if (!custom && BANNER.indexOf(style) !== -1) return { kind: 'banner', props: {}, size: sized };
  if (!custom && PLAIN.indexOf(style) !== -1) return { kind: 'plain', props: {}, size: sized };
  const plain = PLAIN.indexOf(style) !== -1;
  const border = set('ck_border') ? d.ck_border : plain ? 'none' : 'base';
  return {
    kind: 'box',
    props: {
      background: set('ck_background') ? d.ck_background : style === 'premium' || BANNER.indexOf(style) !== -1 ? 'subdued' : undefined,
      border: BORDERS.indexOf(border) !== -1 ? border + ' base ' + (d.ck_border_style || 'solid') : 'none',
      borderRadius: set('ck_radius') ? d.ck_radius : plain ? 'none' : 'base',
      padding: set('ck_padding') ? d.ck_padding : plain ? 'none' : 'base',
    },
    size: sized,
  };
}
