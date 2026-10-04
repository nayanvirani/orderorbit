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

const BANNER = ['banner', 'announcement', 'unlocked'];
const PLAIN = ['compact', 'row', 'button', 'simple', 'plain'];
const BORDERS = ['base', 'large', 'large-100', 'large-200'];

function size(kind, value) {
  const n = Math.round(Number(value) || 0);
  if (kind === 'px' && n > 0) return n + 'px';
  if (kind === 'percent' && n > 0) return Math.min(100, n) + '%';
  return undefined;
}

/**
 * How a block's frame is drawn, from its layout and Design step (ck_* fields; missing on blocks
 * published before box styles existed, which then look as before).
 * @returns { kind: 'banner'|'plain'|'box', props: s-box props, size: { inlineSize, minBlockSize } }
 */
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

/** Sizing for an image block's picture: width, aspect ratio or a fixed, clipped height. */
export function imageStyle(c) {
  const width = c.img_width === 'px' || c.img_width === 'percent' ? size(c.img_width, c.img_width_value) : '100%';
  const border = BORDERS.indexOf(c.img_border) !== -1 ? c.img_border + ' base ' + (c.img_border_style || 'solid') : undefined;
  const radius = c.img_radius || 'base';
  const image = { inlineSize: 'fill', borderRadius: radius, border };
  let clip = null;
  if (c.img_height === 'ratio' && c.img_ratio) {
    image.aspectRatio = c.img_ratio;
    image.objectFit = c.img_fit || 'cover';
  } else if (c.img_height === 'px') {
    const h = Math.max(20, Math.round(Number(c.img_height_value) || 200));
    image.objectFit = c.img_fit || 'cover';
    if (c.img_width === 'px') {
      // Both sides in pixels: an exact shape.
      image.aspectRatio = Math.round(Number(c.img_width_value) || 240) + '/' + h;
    } else {
      // Width follows the column: keep the height by clipping the picture.
      clip = { blockSize: h + 'px', overflow: 'hidden', borderRadius: radius };
    }
  }
  return { width, image, clip };
}
