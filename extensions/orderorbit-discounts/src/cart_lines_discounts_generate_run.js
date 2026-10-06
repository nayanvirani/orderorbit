import {DiscountClass, OrderDiscountSelectionStrategy, ProductDiscountSelectionStrategy} from '../generated/api';

/**
 * OrderOrbit offers. One automatic discount per store runs this function; its
 * "offers" metafield lists every live quantity break, BOGO, upsell and free
 * gift (written by the app on publish). Bundles are priced by the OrderOrbit
 * cart transform instead. Offers are applied in priority
 * order and each cart line gets at most one OrderOrbit discount.
 *
 * Offer shapes (product ids are numeric strings, money in shop currency):
 *   tiers  { id, p (empty = every product), x (products never included), tiers: [[quantity, percent], ...] }
 *   bogo   { id, p, g (empty = same as p), bq, gq, v, once }
 *   bq     { id, o: [ null | { q, t: "percentage"|"amount"|"fixed_price"|"none", v, g } ] }
 *          one-product bundle offers: lines tagged _oo_bundle = "<id>|<offer index>|<group>";
 *          the group gets the offer price once it holds q items, and up to g gift lines
 *          (_oo_gift) in the group are free. Entries with s = 1 are packs (p = their product ids,
 *          all required) and apply only to groups bought on a subscription: those can't be merged
 *          by the cart transform. mix: { p, tiers: [[count, percent], ...] } prices mix & match
 *          groups ("<id>|m|<group>") bought on a subscription the same way.
 *   pg     { id, by: "value"|"count", m: [{ t, r: "gift"|"choice"|"shipping"|"percent"|"amount", v, q }] }
 *          progressive gifts: gift lines carry _oo_offer = id and _oo_gift = milestone index and are
 *          free (up to q units) once the milestone is reached; the best reached order discount applies
 *          to the rest of the order. Gifts never count toward their own milestones.
 *   upsell { id, v }          lines added by the widget carry _oo_offer = id
 *   gift   { id, th: [amounts] } lines added by the widget carry _oo_offer = id
 *
 * @typedef {import("../generated/api").CartInput} RunInput
 * @typedef {import("../generated/api").CartLinesDiscountsGenerateRunResult} CartLinesDiscountsGenerateRunResult
 */

const numericId = (gid) => String(gid || '').split('/').pop();

function percent(value) {
  return {percentage: {value: Math.min(100, Math.max(0, Number(value) || 0))}};
}

function candidate(message, targets, value) {
  return {message, targets: targets.map(({line, quantity}) => ({cartLine: quantity ? {id: line.id, quantity} : {id: line.id}})), value};
}

// Cheapest units first, so "get" and gift rewards discount the lowest-priced items.
function takeUnits(lines, count) {
  const targets = [];
  for (const line of [...lines].sort((a, b) => a.unit - b.unit)) {
    if (count <= 0) break;
    const quantity = Math.min(line.qty, count);
    targets.push({line, quantity: quantity < line.qty ? quantity : null});
    count -= quantity;
  }
  return targets;
}

const units = (lines) => lines.reduce((sum, line) => sum + line.qty, 0);

const RULES = {
  tiers(offer, lines) {
    const ids = offer.p || [];
    const excluded = offer.x || [];
    const tiers = (offer.tiers || []).filter(([, pct]) => Number(pct) > 0).sort((a, b) => b[0] - a[0]);
    const byProduct = {};
    lines.filter((line) => !excluded.includes(line.product) && (!ids.length || ids.includes(line.product))).forEach((line) => {
      (byProduct[line.product] = byProduct[line.product] || []).push(line);
    });
    return Object.values(byProduct).map((group) => {
      const tier = tiers.find(([quantity]) => units(group) >= Number(quantity));
      return tier ? candidate(offer.m || `Buy ${tier[0]}, save ${tier[1]}%`, group.map((line) => ({line})), percent(tier[1])) : null;
    }).filter(Boolean);
  },

  bogo(offer, lines) {
    const buyIds = offer.p || [];
    const getIds = offer.g && offer.g.length ? offer.g : buyIds;
    const bq = Math.max(1, Number(offer.bq) || 1);
    const gq = Math.max(1, Number(offer.gq) || 1);
    const same = getIds.length === buyIds.length && getIds.every((id) => buyIds.includes(id));
    let free;
    let pool;
    if (same) {
      pool = lines.filter((line) => buyIds.includes(line.product));
      free = Math.floor(units(pool) / (bq + gq)) * gq;
    } else {
      const buy = lines.filter((line) => buyIds.includes(line.product) && !getIds.includes(line.product));
      pool = lines.filter((line) => getIds.includes(line.product));
      free = Math.min(Math.floor(units(buy) / bq) * gq, units(pool));
    }
    if (offer.once) free = Math.min(free, gq);
    if (free <= 0 || !pool.length) return null;
    return [candidate(offer.m || 'Buy more, get more', takeUnits(pool, free), percent(offer.v || 100))];
  },

  bq(offer, lines, rate) {
    const groups = {};
    lines.forEach((line) => {
      if (line.bundle && line.bundle.indexOf(offer.id + '|') === 0) (groups[line.bundle] = groups[line.bundle] || []).push(line);
    });
    const found = [];
    for (const [tag, group] of Object.entries(groups)) {
      const index = tag.split('|')[1];
      const paid = group.filter((line) => !line.gift);
      const subscribed = group.some((line) => line.sub);
      if (index === 'm') {
        // Mix & match on a subscription: the best tier reached, for products from the pool.
        const mix = offer.mix;
        if (!mix || !subscribed || !paid.every((line) => (mix.p || []).includes(line.product))) continue;
        const tier = [...(mix.tiers || [])].sort((a, b) => b[0] - a[0]).find(([need]) => units(paid) >= Number(need));
        if (tier && Number(tier[1]) > 0) found.push(candidate(offer.m || 'Bundle discount', paid.map((line) => ({line})), percent(tier[1])));
        continue;
      }
      const o = (offer.o || [])[Number(index)];
      if (!o) continue;
      // Packs: only on a subscription (otherwise the cart transform prices them), and complete.
      if (o.s && (!subscribed || !(o.p || []).every((id) => paid.some((line) => line.product === id)) || !paid.every((line) => (o.p || []).includes(line.product)))) continue;
      if (units(paid) < (Number(o.q) || 1)) continue;
      const total = paid.reduce((sum, line) => sum + line.unit * line.qty, 0);
      const v = Number(o.v) || 0;
      const pct = o.t === 'percentage' ? v
        : o.t === 'amount' && total > 0 ? (v * rate / total) * 100
          : o.t === 'fixed_price' && total > 0 ? Math.max(0, 1 - (v * rate) / total) * 100
            : 0;
      if (pct > 0) found.push(candidate(offer.m || 'Bundle discount', paid.map((line) => ({line})), percent(Math.round(pct * 100) / 100)));
      const gifts = group.filter((line) => line.gift);
      if (o.g && gifts.length) found.push(candidate('Free gift', takeUnits(gifts, Number(o.g)), percent(100)));
    }
    return found;
  },

  pg(offer, lines, rate, all, out) {
    const isGift = (line) => line.offer === offer.id && line.giftIndex !== null;
    const paid = all.filter((line) => !isGift(line));
    const progress = offer.by === 'count' ? units(paid) : paid.reduce((sum, line) => sum + line.unit * line.qty, 0);
    const reached = (m) => progress >= Number(m.t) * (offer.by === 'count' ? 1 : rate);
    const found = [];
    (offer.m || []).forEach((m, i) => {
      if ((m.r !== 'gift' && m.r !== 'choice') || !reached(m)) return;
      const gifts = lines.filter((line) => isGift(line) && Number(line.giftIndex) === i);
      if (gifts.length) found.push(candidate(offer.n || 'Free gift', takeUnits(gifts, Number(m.q) || 1), percent(100)));
    });
    // The highest order discount reached.
    const order = (offer.m || []).filter((m) => (m.r === 'percent' || m.r === 'amount') && reached(m)).pop();
    if (order && out) {
      out.order.push({
        message: offer.n || 'Reward unlocked',
        targets: [{orderSubtotal: {excludedCartLineIds: all.filter(isGift).map((line) => line.id)}}],
        value: order.r === 'percent' ? percent(order.v) : {fixedAmount: {amount: (Number(order.v) * rate).toFixed(2)}},
      });
    }
    return found;
  },

  upsell(offer, lines) {
    const tagged = lines.filter((line) => line.offer === offer.id);
    return tagged.length && Number(offer.v) ? [candidate(offer.m || 'Special offer', tagged.map((line) => ({line})), percent(offer.v))] : null;
  },

  gift(offer, lines, rate, all) {
    const gifts = lines.filter((line) => line.offer === offer.id);
    if (!gifts.length) return null;
    // Gifts don't count toward their own threshold.
    const spend = all.filter((line) => line.offer !== offer.id).reduce((sum, line) => sum + line.unit * line.qty, 0);
    const reached = (offer.th || []).filter((amount) => spend >= Number(amount) * rate).length;
    return reached ? [candidate(offer.m || 'Free gift', takeUnits(gifts, reached), percent(100))] : null;
  },
};

/**
 * @param {RunInput} input
 * @returns {CartLinesDiscountsGenerateRunResult}
 */
export function cartLinesDiscountsGenerateRun(input) {
  const offers = input.discount.metafield?.jsonValue?.offers || [];
  const classes = input.discount.discountClasses;
  if (!offers.length || (!classes.includes(DiscountClass.Product) && !classes.includes(DiscountClass.Order))) {
    return {operations: []};
  }

  const rate = Number(input.presentmentCurrencyRate) || 1;
  const all = input.cart.lines
    .filter((line) => line.merchandise.__typename === 'ProductVariant')
    .map((line) => ({
      id: line.id,
      qty: line.quantity,
      product: numericId(line.merchandise.product.id),
      offer: line.offer?.value || null,
      bundle: line.bundle?.value || null,
      gift: !!line.gift?.value,
      giftIndex: line.gift?.value ?? null,
      sub: !!line.sellingPlanAllocation,
      unit: Number(line.cost.amountPerQuantity.amount),
    }));

  const used = new Set();
  const candidates = [];
  const out = {order: []};
  for (const offer of offers) {
    const rule = RULES[offer.k];
    if (!rule) continue;
    const open = all.filter((line) => !used.has(line.id));
    for (const found of rule(offer, open, rate, all, out) || []) {
      found.targets.forEach((target) => used.add(target.cartLine.id));
      candidates.push(found);
    }
  }

  const operations = [];
  if (candidates.length && classes.includes(DiscountClass.Product)) {
    operations.push({productDiscountsAdd: {candidates, selectionStrategy: ProductDiscountSelectionStrategy.All}});
  }
  if (out.order.length && classes.includes(DiscountClass.Order)) {
    operations.push({orderDiscountsAdd: {candidates: out.order, selectionStrategy: OrderDiscountSelectionStrategy.First}});
  }
  return {operations};
}
