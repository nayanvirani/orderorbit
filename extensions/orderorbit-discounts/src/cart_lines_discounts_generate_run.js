import {DiscountClass, ProductDiscountSelectionStrategy} from '../generated/api';

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
  if (!offers.length || !input.discount.discountClasses.includes(DiscountClass.Product)) {
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
      unit: Number(line.cost.amountPerQuantity.amount),
    }));

  const used = new Set();
  const candidates = [];
  for (const offer of offers) {
    const rule = RULES[offer.k];
    if (!rule) continue;
    const open = all.filter((line) => !used.has(line.id));
    for (const found of rule(offer, open, rate, all) || []) {
      found.targets.forEach((target) => used.add(target.cartLine.id));
      candidates.push(found);
    }
  }

  return candidates.length
    ? {operations: [{productDiscountsAdd: {candidates, selectionStrategy: ProductDiscountSelectionStrategy.All}}]}
    : {operations: []};
}
