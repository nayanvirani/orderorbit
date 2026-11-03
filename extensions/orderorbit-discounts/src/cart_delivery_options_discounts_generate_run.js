import {DeliveryDiscountSelectionStrategy, DiscountClass} from '../generated/api';

/**
 * Free shipping from a Shipping Bar experience ({ k: "ship", min, m }), a
 * Progressive gifts shipping milestone ({ k: "pg", by, m: [{ t, r: "shipping" }] }) or a
 * bundle tier ({ k: "bship", id, t: { "<offer index>": units } }): the cart holds that tier,
 * either as lines tagged _oo_bundle = "<id>|<index>|<group>" with enough paid units, or as a
 * pack the cart transform merged, tagged _oo_tier = "<id>|<index>".
 *
 * @typedef {import("../generated/api").DeliveryInput} RunInput
 * @typedef {import("../generated/api").CartDeliveryOptionsDiscountsGenerateRunResult} CartDeliveryOptionsDiscountsGenerateRunResult
 */

/**
 * @param {RunInput} input
 * @returns {CartDeliveryOptionsDiscountsGenerateRunResult}
 */
export function cartDeliveryOptionsDiscountsGenerateRun(input) {
  const all = input.discount.metafield?.jsonValue?.offers || [];
  const groups = input.cart.deliveryGroups;
  if (!all.length || !groups.length || !input.discount.discountClasses.includes(DiscountClass.Shipping)) {
    return {operations: []};
  }

  const rate = Number(input.presentmentCurrencyRate) || 1;
  const subtotal = Number(input.cart.cost.subtotalAmount.amount);
  // Progressive gifts count the cart without their own gift lines.
  const progress = (offer) => {
    const paid = (input.cart.lines || []).filter((line) => !(line.offer?.value === offer.id && line.gift?.value != null));
    return offer.by === 'count'
      ? paid.reduce((sum, line) => sum + line.quantity, 0)
      : paid.reduce((sum, line) => sum + Number(line.cost.amountPerQuantity.amount) * line.quantity, 0);
  };
  // Only lines of the offer's A/B test version (_oo_v; none = A).
  const ofVersion = (offer) => (input.cart.lines || []).filter((line) => (line.version?.value || 'A') === (offer.v || 'A'));
  const tierBought = (offer) => ofVersion(offer).some((line) => {
    const tier = line.tier?.value;
    if (tier && tier.indexOf(offer.id + '|') === 0) return Object.prototype.hasOwnProperty.call(offer.t || {}, tier.split('|')[1]);
    return false;
  }) || Object.entries(ofVersion(offer).reduce((groups, line) => {
    const tag = line.bundle?.value;
    if (tag && tag.indexOf(offer.id + '|') === 0 && line.gift?.value == null) groups[tag] = (groups[tag] || 0) + line.quantity;
    return groups;
  }, {})).some(([tag, count]) => {
    const need = (offer.t || {})[tag.split('|')[1]];
    return need != null && count >= Number(need);
  });
  const offer = all.find((o) => (o.k === 'ship' && subtotal >= Number(o.min) * rate)
    || (o.k === 'pg' && (o.m || []).some((m) => m.r === 'shipping' && progress(o) >= Number(m.t) * (o.by === 'count' ? 1 : rate)))
    || (o.k === 'bship' && tierBought(o)));
  if (!offer) {
    return {operations: []};
  }

  return {
    operations: [{
      deliveryDiscountsAdd: {
        candidates: [{
          message: (offer.k === 'pg' ? offer.n : offer.m) || 'Free shipping',
          targets: groups.map((group) => ({deliveryGroup: {id: group.id}})),
          value: {percentage: {value: 100}},
        }],
        selectionStrategy: DeliveryDiscountSelectionStrategy.All,
      },
    }],
  };
}
