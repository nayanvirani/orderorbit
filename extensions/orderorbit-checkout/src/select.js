/*
 * Pure helpers for the OrderOrbit Space checkout blocks (no Shopify APIs, so they can be tested
 * in Node): which published block to show, and progress toward a Progressive gifts milestone.
 */

/** Which page we are on: 'checkout', 'thank_you' or 'order_status'. */
export function pageFor(target) {
  if (target.startsWith('purchase.thank-you')) return 'thank_you';
  if (target.startsWith('customer-account.order-status')) return 'order_status';
  return 'checkout';
}

/**
 * The highest-priority published block for this placement.
 * @param payload  the $app:checkout metafield (parsed)
 * @param settings the block's settings in the checkout editor ({ block, experience_id })
 * @param ctx      { page, subtotal, country, now }
 */
export function choose(payload, settings, ctx) {
  const list = (payload && payload.experiences) || [];
  const pinned = String((settings && settings.experience_id) || '').trim();
  const type = String((settings && settings.block) || '').trim();
  const now = ctx.now || Date.now();

  return list
    .filter((e) => (pinned ? e.id === pinned : e.type === type))
    .filter((e) => {
      const isCheckout = e.type.startsWith('checkout-');
      if (isCheckout !== (ctx.page === 'checkout')) return false;
      const pages = (e.content && e.content.pages) || 'both';
      if (!isCheckout && pages !== 'both' && pages !== ctx.page) return false;
      if (e.starts_at && Date.parse(e.starts_at) > now) return false;
      if (e.ends_at && Date.parse(e.ends_at) <= now) return false;
      const t = e.targeting || {};
      if (t.cart_min != null && t.cart_min !== '' && ctx.subtotal < Number(t.cart_min)) return false;
      if (t.cart_max != null && t.cart_max !== '' && ctx.subtotal > Number(t.cart_max)) return false;
      if (t.countries) {
        const codes = String(t.countries).toUpperCase().split(',').map((s) => s.trim()).filter(Boolean);
        if (codes.length && codes.indexOf(String(ctx.country || '').toUpperCase()) === -1) return false;
      }
      return true;
    })
    .sort((a, b) => (b.priority || 0) - (a.priority || 0))[0] || null;
}

/**
 * Progress toward the campaign's milestones.
 * @param gifts   payload.gifts ({ id, content: { settings, milestones } })
 * @param lines   [{ quantity, amount, attributes: [{ key, value }] }]
 * @param filter  milestone => boolean (e.g. only shipping rewards)
 */
export function progress(gifts, lines, filter) {
  const content = (gifts && gifts.content) || {};
  const milestones = (content.milestones || []).filter(filter || (() => true));
  const isGift = (l) => (l.attributes || []).some((a) => a.key === '_oo_gift') && (l.attributes || []).some((a) => a.key === '_oo_offer' && a.value === gifts.id);
  const paid = (lines || []).filter((l) => !isGift(l));
  const byCount = content.settings && content.settings.unlock === 'count';
  const value = byCount ? paid.reduce((n, l) => n + (l.quantity || 0), 0) : paid.reduce((n, l) => n + (l.amount || 0), 0);
  const next = milestones.find((m) => value < m.threshold) || null;
  const top = milestones.length ? milestones[milestones.length - 1].threshold : 0;
  return {
    value,
    byCount,
    milestones,
    next,
    remaining: next ? Math.round((next.threshold - value) * 100) / 100 : 0,
    percent: top ? Math.min(100, Math.round((value / top) * 100)) : 0,
    reached: milestones.filter((m) => value >= m.threshold),
    claimed: (lines || []).filter(isGift).map((l) => (l.attributes.find((a) => a.key === '_oo_gift') || {}).value),
  };
}

/** Replaces {name} placeholders. */
export function fill(template, vars) {
  return String(template || '').replace(/\{(\w+)\}/g, (m, key) => (key in vars ? String(vars[key]) : m));
}

/** "gid://shopify/ProductVariant/123" → "123". */
export function numericId(gid) {
  const m = String(gid || '').match(/(\d+)$/);
  return m ? m[1] : '';
}

/**
 * Milliseconds left on a checkout countdown, or null while a timer's start is still loading.
 *  - date:            to the campaign's end date, the same for everyone
 *  - hours / minutes: from `started` (when this shopper reached checkout); with repeat "restart"
 *                     it starts a new round each time it reaches zero, otherwise it ends
 */
export function deadlineLeft(content, now, started) {
  const c = content || {};
  if (c.mode !== 'hours' && c.mode !== 'minutes') {
    return c.ends_at ? Date.parse(c.ends_at) - now : 0;
  }
  if (started == null) return null;
  const length = (c.mode === 'hours' ? Number(c.hours) || 1 : Number(c.minutes) || 1) * (c.mode === 'hours' ? 36e5 : 6e4);
  const elapsed = Math.max(0, now - started);
  if (elapsed < length) return length - elapsed;
  return c.repeat === 'end' ? 0 : length - (elapsed % length);
}
