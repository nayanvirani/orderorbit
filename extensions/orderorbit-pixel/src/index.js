import {register} from '@shopify/web-pixels-extension';

/**
 * OrderOrbit Space analytics. Sends, with the shopper's analytics consent:
 *  - experience views, clicks and adds to cart (published by the storefront widgets and the
 *    checkout blocks), and post-purchase survey answers
 *  - one "session" per browser session (for conversion rate)
 *  - completed checkouts, with each line's OrderOrbit offer tag for revenue attribution, and the
 *    product (title, link, image) and market country for Sales pop
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
    send({k: 'e', e: String(d.event || '').replace('orderorbit:', ''), x: d.experience_id, ty: d.experience_type, q: d.quantity || 1, a: d.answer || undefined});
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
      const variant = line.variant || {};
      const product = variant.product || {};
      return {
        q: line.quantity,
        v: line.finalLinePrice ? Number(line.finalLinePrice.amount) : 0,
        o: props._oo_offer || null,
        b: props._oo_bundle ? String(props._oo_bundle).split('|')[0] : null,
        // Product details for Sales pop (public catalogue data only).
        p: product.id || null,
        t: product.title || line.title || null,
        u: product.url || null,
        i: variant.image ? variant.image.src : null,
      };
    });
    const localization = checkout.localization || {};
    send({
      k: 'o',
      id: checkout.order ? checkout.order.id : checkout.token,
      v: checkout.totalPrice ? Number(checkout.totalPrice.amount) : 0,
      sub: checkout.subtotalPrice ? Number(checkout.subtotalPrice.amount) : 0,
      c: checkout.currencyCode,
      // The market country only (for "Someone in Canada"); never the address.
      cc: localization.country ? localization.country.isoCode : null,
      l: lines,
    });
  });
});
