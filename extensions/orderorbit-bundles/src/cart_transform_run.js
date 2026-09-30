// @ts-check

/**
 * OrderOrbit bundles. Items added from a bundle widget carry the line property
 * _oo_bundle = "<bundle id>|<group>", one group per "add bundle" click. When a
 * group still qualifies (every product for a fixed bundle, enough items for mix
 * & match), its lines merge into one bundle line priced with the bundle saving.
 * Orders keep the component lines, so Shopify deducts each product's inventory.
 *
 * Config (cart transform metafield $app:bundles), money in shop currency:
 *   { bundles: [{ id, parent, title, image, mode: "mix"|"fixed", min, p: [productIds], t: "percentage"|"amount", v }] }
 *
 * @typedef {import("../generated/api").CartTransformRunInput} CartTransformRunInput
 * @typedef {import("../generated/api").CartTransformRunResult} CartTransformRunResult
 */

const numericId = (gid) => String(gid || '').split('/').pop();

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
      product: numericId(line.merchandise.product.id),
      unit: Number(line.cost.amountPerQuantity.amount),
    });
  }

  const operations = [];
  for (const [tag, lines] of Object.entries(groups)) {
    const bundle = bundles.find((b) => b.id === tag.split('|')[0]);
    if (!bundle || !bundle.parent) continue;

    const ids = bundle.p || [];
    if (!lines.every((line) => ids.includes(line.product))) continue;
    const items = lines.reduce((sum, line) => sum + line.qty, 0);
    const qualifies = bundle.mode === 'fixed'
      ? ids.every((id) => lines.some((line) => line.product === id))
      : items >= Math.max(1, Number(bundle.min) || 1);
    // A merge needs at least two lines; a single line is just a product.
    if (!qualifies || lines.length < 2 && items < 2) continue;

    const total = lines.reduce((sum, line) => sum + line.unit * line.qty, 0);
    const value = Number(bundle.v) || 0;
    const percent = bundle.t === 'amount'
      ? (total > 0 ? Math.min(100, (value * rate / total) * 100) : 0)
      : Math.min(100, value);

    operations.push({
      linesMerge: {
        cartLines: lines.map((line) => ({cartLineId: line.id, quantity: line.qty})),
        parentVariantId: bundle.parent,
        ...(bundle.title ? {title: bundle.title} : {}),
        ...(bundle.image ? {image: {url: bundle.image}} : {}),
        ...(percent > 0 ? {price: {percentageDecrease: {value: Math.round(percent * 100) / 100}}} : {}),
      },
    });
  }

  return {operations};
}
