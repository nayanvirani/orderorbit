// @ts-check

/**
 * OrderOrbit bundles. Items added from a bundle widget carry the line property
 * _oo_bundle = "<bundle id>|<offer index or m>|<group>", one group per "add to
 * cart" click; free gifts in the group also carry _oo_gift. When a group still
 * qualifies, its lines (gifts included) merge into one bundle line priced so the
 * shopper pays the offer price for the paid items and nothing for the gifts.
 * The bundle line uses the main product's own variant (the pack's first product,
 * or the first item picked); no extra product is created. Orders keep the
 * component lines, so Shopify deducts each product's inventory.
 *
 * Groups holding a subscription line (a selling plan) are left alone: Shopify doesn't allow
 * transforms on those lines, so the OrderOrbit discount prices them instead.
 *
 * Config (cart transform metafield $app:bundles), money in shop currency:
 *   { bundles: [{ id, title, image,
 *       o: [ null | { p: [productIds], t: "percentage"|"amount"|"fixed_price"|"none", v, n, gp, gq } ],
 *       mix: { p: [productIds] | c: [collectionIds], tiers: [[count, percent], ...],
 *              min, max, pp (limit per product), t: "fixed", v (box price), gp, gq, gm } }] }
 * Build-your-own boxes only merge when they respect their size and limits. Gifts (gp = gift product
 * ids, gq = units, gm = items that unlock them) are free only within those; otherwise the gift lines
 * stay out of the bundle line and are paid at full price.
 *
 * @typedef {import("../generated/api").CartTransformRunInput} CartTransformRunInput
 * @typedef {import("../generated/api").CartTransformRunResult} CartTransformRunResult
 */

const numericId = (gid) => String(gid || '').split('/').pop();
const units = (lines) => lines.reduce((sum, line) => sum + line.qty, 0);
const total = (lines) => lines.reduce((sum, line) => sum + line.unit * line.qty, 0);

/**
 * Mix & match and build-your-own box: every item is one of the box's products (p) or in one of its
 * collections (c), and the box respects its size (min, max) and limit per product (pp).
 */
export function mixFits(mix, paid) {
  const allowed = (line) => (mix.c ? (line.collections || []).some((c) => mix.c.includes(c)) : (mix.p || []).includes(line.product));
  if (!paid.length || !paid.every(allowed)) return false;
  const count = units(paid);
  if ((mix.min && count < mix.min) || (mix.max && count > mix.max)) return false;
  if (mix.pp) {
    const per = {};
    for (const line of paid) {
      per[line.product] = (per[line.product] || 0) + line.qty;
      if (per[line.product] > mix.pp) return false;
    }
  }
  return true;
}

/** The group's gift lines are the entry's gift products, within its gift units, unlocked by count items. */
export function giftsFit(entry, gifts, count) {
  if (!gifts.length) return true;
  const ids = entry.gp || [];
  return gifts.every((line) => ids.includes(line.product)) && units(gifts) <= (Number(entry.gq) || 0) && count >= (Number(entry.gm) || 0);
}

// What the paid items should cost after the offer.
function offerPrice(paid, type, value, rate) {
  if (type === 'percentage') return paid * (1 - Math.min(100, value) / 100);
  if (type === 'amount') return Math.max(0, paid - value * rate);
  if (type === 'fixed_price') return Math.min(paid, value * rate);
  return paid;
}

/**
 * @param {CartTransformRunInput} input
 * @returns {CartTransformRunResult}
 */
export function cartTransformRun(input) {
  const bundles = input.cartTransform.metafield?.jsonValue?.bundles || [];
  if (!bundles.length) return {operations: []};

  const rate = Number(input.presentmentCurrencyRate) || 1;
  const groups = {};
  for (const line of input.cart.lines) {
    const tag = line.bundle?.value;
    if (!tag || line.merchandise.__typename !== 'ProductVariant') continue;
    (groups[tag] = groups[tag] || []).push({
      id: line.id,
      qty: line.quantity,
      gift: !!line.gift?.value,
      sub: !!line.sellingPlanAllocation,
      variant: line.merchandise.id,
      product: numericId(line.merchandise.product.id),
      collections: (line.merchandise.product.inCollections || []).filter((c) => c.isMember).map((c) => c.collectionId),
      unit: Number(line.cost.amountPerQuantity.amount),
    });
  }

  const operations = [];
  for (let [tag, lines] of Object.entries(groups)) {
    const [bundleId, index] = tag.split('|');
    const bundle = bundles.find((b) => b.id === bundleId);
    if (!bundle) continue;

    if (lines.some((line) => line.sub)) continue;
    const paid = lines.filter((line) => !line.gift);
    if (!paid.length || lines.length < 2 && units(lines) < 2) continue;

    let target;
    let title = bundle.title;
    let main = paid[0];
    if (index === 'm') {
      const mix = bundle.mix;
      if (!mix || !mixFits(mix, paid)) continue;
      const count = units(paid);
      if (!giftsFit(mix, lines.filter((line) => line.gift), count)) lines = paid;
      const tier = [...(mix.tiers || [])].sort((a, b) => b[0] - a[0]).find(([need]) => count >= need);
      // A box can have one price (an exact size), or a discount by number of items.
      target = mix.t === 'fixed' ? offerPrice(total(paid), 'fixed_price', Number(mix.v) || 0, rate) : offerPrice(total(paid), 'percentage', tier ? Number(tier[1]) : 0, rate);
    } else {
      const offer = (bundle.o || [])[Number(index)];
      if (!offer) continue;
      const ids = offer.p || [];
      // The pack is complete and holds nothing else.
      if (!paid.every((line) => ids.includes(line.product)) || !ids.every((id) => paid.some((line) => line.product === id))) continue;
      if (!giftsFit(offer, lines.filter((line) => line.gift), units(paid))) lines = paid;
      target = offerPrice(total(paid), offer.t, Number(offer.v) || 0, rate);
      title = offer.n || title;
      main = paid.find((line) => line.product === ids[0]) || main;
    }

    const all = total(lines);
    const percent = all > 0 ? Math.max(0, Math.min(100, (1 - target / all) * 100)) : 0;
    operations.push({
      linesMerge: {
        cartLines: lines.map((line) => ({cartLineId: line.id, quantity: line.qty})),
        parentVariantId: main.variant,
        // Keeps the bundle's tag on the merged line so orders are attributed to it.
        attributes: [{key: '_oo_offer', value: bundle.id}],
        ...(title ? {title} : {}),
        ...(bundle.image ? {image: {url: bundle.image}} : {}),
        ...(percent > 0 ? {price: {percentageDecrease: {value: Math.round(percent * 100) / 100}}} : {}),
      },
    });
  }

  return {operations};
}
