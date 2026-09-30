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
 * Config (cart transform metafield $app:bundles), money in shop currency:
 *   { bundles: [{ id, title, image,
 *       o: [ null | { p: [productIds], t: "percentage"|"amount"|"fixed_price"|"none", v, n } ],
 *       mix: { p: [productIds], tiers: [[count, percent], ...] } }] }
 *
 * @typedef {import("../generated/api").CartTransformRunInput} CartTransformRunInput
 * @typedef {import("../generated/api").CartTransformRunResult} CartTransformRunResult
 */

const numericId = (gid) => String(gid || '').split('/').pop();
const units = (lines) => lines.reduce((sum, line) => sum + line.qty, 0);
const total = (lines) => lines.reduce((sum, line) => sum + line.unit * line.qty, 0);

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
      variant: line.merchandise.id,
      product: numericId(line.merchandise.product.id),
      unit: Number(line.cost.amountPerQuantity.amount),
    });
  }

  const operations = [];
  for (const [tag, lines] of Object.entries(groups)) {
    const [bundleId, index] = tag.split('|');
    const bundle = bundles.find((b) => b.id === bundleId);
    if (!bundle) continue;

    const paid = lines.filter((line) => !line.gift);
    if (!paid.length || lines.length < 2 && units(lines) < 2) continue;

    let target;
    let title = bundle.title;
    let main = paid[0];
    if (index === 'm') {
      const mix = bundle.mix;
      if (!mix || !paid.every((line) => mix.p.includes(line.product))) continue;
      const count = units(paid);
      const tier = [...(mix.tiers || [])].sort((a, b) => b[0] - a[0]).find(([need]) => count >= need);
      target = offerPrice(total(paid), 'percentage', tier ? Number(tier[1]) : 0, rate);
    } else {
      const offer = (bundle.o || [])[Number(index)];
      if (!offer) continue;
      const ids = offer.p || [];
      // The pack is complete and holds nothing else.
      if (!paid.every((line) => ids.includes(line.product)) || !ids.every((id) => paid.some((line) => line.product === id))) continue;
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
        ...(title ? {title} : {}),
        ...(bundle.image ? {image: {url: bundle.image}} : {}),
        ...(percent > 0 ? {price: {percentageDecrease: {value: Math.round(percent * 100) / 100}}} : {}),
      },
    });
  }

  return {operations};
}
