/*
 * Data for the account blocks: the published blocks (a shop metafield) and the signed-in
 * customer's orders, both from the Customer Account API; product links from the Storefront API.
 */

// The app's own namespace on the shop ($app:account, written by Growvia).
const NAMESPACE = 'app--429536804865';
const API = 'shopify://customer-account/api/2026-07/graphql.json';

const ORDER_FIELDS = `
  id name processedAt cancelledAt fulfillmentStatus statusPageUrl
  totalPrice { amount currencyCode }
  lineItems(first: 25) { nodes { title quantity variantId productId image { url } } }
  fulfillments(first: 5) { nodes { status latestShipmentStatus estimatedDeliveryAt trackingInformation { company number url } } }
`;

async function customerApi(query, variables) {
  const response = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query, variables: variables || {} }),
  });
  const json = await response.json();
  if (json.errors && json.errors.length && !json.data) throw new Error(json.errors[0].message);
  return json.data || {};
}

let payloadPromise = null;

/** The published account blocks ({ experiences, shop_url, currency }) or null. */
export function loadPayload() {
  if (!payloadPromise) {
    payloadPromise = customerApi(`query { shop { metafield(namespace: "${NAMESPACE}", key: "account") { value } } }`)
      .then((data) => {
        const value = data.shop && data.shop.metafield && data.shop.metafield.value;
        return value ? JSON.parse(value) : null;
      })
      .catch(() => null);
  }
  return payloadPromise;
}

/** The customer's first name and latest orders (newest first). */
export async function loadCustomer() {
  const data = await customerApi(`query { customer { firstName orders(first: 50, sortKey: PROCESSED_AT, reverse: true) { nodes { ${ORDER_FIELDS} } } } }`);
  const customer = data.customer || {};
  return { firstName: customer.firstName || '', orders: (customer.orders && customer.orders.nodes) || [] };
}

/** One order by id. */
export async function loadOrder(id) {
  const data = await customerApi(`query ($id: ID!) { order(id: $id) { ${ORDER_FIELDS} } }`, { id });
  return data.order || null;
}

/** Product handles, links and images for product ids (Storefront API). */
export async function loadProducts(ids) {
  const unique = Array.from(new Set((ids || []).filter(Boolean))).slice(0, 20);
  if (!unique.length || !shopify.query) return {};
  try {
    const result = await shopify.query(
      'query ($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { id handle title onlineStoreUrl featuredImage { url } } } }',
      { variables: { ids: unique } },
    );
    const map = {};
    ((result.data && result.data.nodes) || []).forEach((n) => {
      if (n && n.id) map[n.id] = { handle: n.handle, url: n.onlineStoreUrl, title: n.title, image: n.featuredImage && n.featuredImage.url };
    });
    return map;
  } catch (e) {
    return {};
  }
}

export function track(exp, event, extra) {
  try {
    shopify.analytics.publish('growvia_event', Object.assign({
      event: 'growvia:' + event, experience_id: exp.id, experience_type: exp.type, template_id: exp.template, version: exp.version,
    }, extra || {}));
  } catch (e) {
    /* analytics not available here */
  }
}
