import {DeliveryDiscountSelectionStrategy, DiscountClass} from '../generated/api';

/**
 * Free shipping from a Shipping Bar experience: { k: "ship", min, m }.
 *
 * @typedef {import("../generated/api").DeliveryInput} RunInput
 * @typedef {import("../generated/api").CartDeliveryOptionsDiscountsGenerateRunResult} CartDeliveryOptionsDiscountsGenerateRunResult
 */

/**
 * @param {RunInput} input
 * @returns {CartDeliveryOptionsDiscountsGenerateRunResult}
 */
export function cartDeliveryOptionsDiscountsGenerateRun(input) {
  const offers = (input.discount.metafield?.jsonValue?.offers || []).filter((offer) => offer.k === 'ship');
  const groups = input.cart.deliveryGroups;
  if (!offers.length || !groups.length || !input.discount.discountClasses.includes(DiscountClass.Shipping)) {
    return {operations: []};
  }

  const rate = Number(input.presentmentCurrencyRate) || 1;
  const subtotal = Number(input.cart.cost.subtotalAmount.amount);
  const offer = offers.find((o) => subtotal >= Number(o.min) * rate);
  if (!offer) {
    return {operations: []};
  }

  return {
    operations: [{
      deliveryDiscountsAdd: {
        candidates: [{
          message: offer.m || 'Free shipping',
          targets: groups.map((group) => ({deliveryGroup: {id: group.id}})),
          value: {percentage: {value: 100}},
        }],
        selectionStrategy: DeliveryDiscountSelectionStrategy.All,
      },
    }],
  };
}
