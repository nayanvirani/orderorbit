import {DeliveryDiscountSelectionStrategy, DiscountClass} from '../generated/api';

/**
 * Free shipping from a Shipping Bar experience ({ k: "ship", min, m }) or a
 * Progressive gifts shipping milestone ({ k: "pg", by, m: [{ t, r: "shipping" }] }).
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
  const offer = all.find((o) => (o.k === 'ship' && subtotal >= Number(o.min) * rate)
    || (o.k === 'pg' && (o.m || []).some((m) => m.r === 'shipping' && progress(o) >= Number(m.t) * (o.by === 'count' ? 1 : rate))));
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
