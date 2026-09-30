import {register} from '@shopify/web-pixels-extension';

/**
 * OrderOrbit Space analytics. Sends, with the shopper's analytics consent:
 *  - experience views, clicks and adds to cart (published by the storefront widgets)
 *  - one "session" per browser session (for conversion rate)
 *  - completed checkouts, with each line's OrderOrbit offer tag for revenue attribution
 * No names, emails or addresses are sent.
 */
const ENDPOINT = 'https://orderorbit.space/api/pixel';

register(({analytics, browser, init, settings}) => {
  const shop = init.data.shop.myshopifyDomain;

  const send = (payload) => fetch(ENDPOINT, {
    method: 'POST',
    keepalive: true,
    // text/plain keeps this a simple request (no CORS preflight).
    headers: {'Content-Type': 'text/plain'},
    body: JSON.stringify(Object.assign({t: settings.token, s: shop}, payload)),
  }).catch(() => {});

  analytics.subscribe('orderorbit_event', (event) => {
    const d = event.customData || {};
    send({k: 'e', e: String(d.event || '').replace('orderorbit:', ''), x: d.experience_id, ty: d.experience_type, q: d.quantity || 1});
  });

  analytics.subscribe('page_viewed', async () => {
    try {
      if (await browser.sessionStorage.getItem('oo_session')) return;
      await browser.sessionStorage.setItem('oo_session', '1');
    } catch (e) {
      return;
    }
    send({k: 's'});
  });

  analytics.subscribe('checkout_completed', (event) => {
    const checkout = event.data.checkout;
    const lines = (checkout.lineItems || []).map((line) => {
      const props = {};
      (line.properties || []).forEach((p) => { props[p.key] = p.value; });
      return {
        q: line.quantity,
        v: line.finalLinePrice ? Number(line.finalLinePrice.amount) : 0,
        o: props._oo_offer || null,
        b: props._oo_bundle ? String(props._oo_bundle).split('|')[0] : null,
      };
    });
    send({
      k: 'o',
      id: checkout.order ? checkout.order.id : checkout.token,
      v: checkout.totalPrice ? Number(checkout.totalPrice.amount) : 0,
      sub: checkout.subtotalPrice ? Number(checkout.subtotalPrice.amount) : 0,
      c: checkout.currencyCode,
      l: lines,
    });
  });
});
