import {register} from '@shopify/web-pixels-extension';

/**
 * OrderOrbit Space analytics. Sends, with the shopper's analytics consent:
 *  - Shopify's standard storefront events (pages, products, collections, search, cart, checkout)
 *  - experience views, clicks and adds to cart (published by the storefront widgets and the
 *    checkout blocks), and post-purchase survey answers
 *  - completed checkouts, with each line's OrderOrbit offer tag for revenue attribution, and the
 *    product (title, link, image) and market country for Sales pop
 * Every event carries Shopify's anonymous visitor id, a session id, the device type and the
 * session's traffic source (UTM tags or the referring site), and the customer's numeric id when
 * they're signed in. No names, emails or addresses are sent.
 */
const ENDPOINT = 'https://orderorbit.space/api/pixel';
const SESSION_MINUTES = 30;

register(({analytics, browser, init, settings}) => {
  const shop = init.data.shop.myshopifyDomain;
  const customer = init.data.customer && init.data.customer.id ? String(init.data.customer.id) : undefined;
  const ua = (init.context && init.context.navigator && init.context.navigator.userAgent) || '';
  const device = /iPad|Tablet/i.test(ua) ? 'tablet' : /Mobi|Android|iPhone/i.test(ua) ? 'mobile' : 'desktop';

  const post = (payload) => fetch(ENDPOINT, {
    method: 'POST',
    keepalive: true,
    // text/plain keeps this a simple request (no CORS preflight).
    headers: {'Content-Type': 'text/plain'},
    body: JSON.stringify(Object.assign({t: settings.token, s: shop}, payload)),
  }).catch(() => {});

  /** The traffic source of a landing page: UTM tags, else the referring site, else direct. */
  function sourceOf(location, referrer) {
    let params;
    try {
      params = new URL(location.href).searchParams;
    } catch (e) {
      params = new URLSearchParams('');
    }
    if (params.get('utm_source')) {
      return {src: params.get('utm_source'), med: params.get('utm_medium') || undefined, cmp: params.get('utm_campaign') || undefined};
    }
    if (params.get('gclid')) return {src: 'google', med: 'cpc'};
    if (params.get('fbclid')) return {src: 'facebook', med: 'social'};
    let host = '';
    try {
      host = referrer ? new URL(referrer).hostname.replace(/^www\./, '') : '';
    } catch (e) {
      host = '';
    }
    if (!host || host === location.hostname || host.endsWith('shopify.com') || host === shop) return {src: 'direct'};
    return {src: host, med: 'referral'};
  }

  // A session ends after 30 minutes without activity; each new session records its source.
  let current = null;
  let queue = Promise.resolve();
  // Events at page load arrive together; look sessions up one at a time so they share one.
  function session(event) {
    const next = queue.then(() => lookup(event));
    queue = next.catch(() => {});
    return next;
  }
  async function lookup(event) {
    const now = Date.now();
    if (!current) {
      try {
        current = JSON.parse((await browser.sessionStorage.getItem('oo_s')) || 'null');
      } catch (e) {
        current = null;
      }
    }
    let started = false;
    if (!current || now - current.at > SESSION_MINUTES * 6e4) {
      const doc = (event && event.context && event.context.document) || (init.context && init.context.document) || {};
      current = Object.assign({id: now.toString(36) + Math.random().toString(36).slice(2, 10)}, sourceOf(doc.location || {}, doc.referrer));
      started = true;
    }
    current.at = now;
    try {
      await browser.sessionStorage.setItem('oo_s', JSON.stringify(current));
    } catch (e) {
      /* storage unavailable: the session lasts as long as the page */
    }
    return {started, ctx: {sid: current.id, src: current.src, med: current.med, cmp: current.cmp}};
  }

  async function send(event, payload) {
    const {started, ctx} = await session(event);
    const doc = (event.context && event.context.document) || {};
    const base = Object.assign({vid: event.clientId, cust: customer, dev: device, path: doc.location ? doc.location.pathname : undefined}, ctx);
    // The old "session" message keeps conversion rate working for reports built on it.
    if (started) post(Object.assign({k: 's'}, base));
    post(Object.assign(base, payload));
  }

  const money = (m) => (m && m.amount != null ? Number(m.amount) : undefined);
  const productOf = (variant) => {
    const v = variant || {};
    const p = v.product || {};
    return {p: p.id || undefined, va: v.id || undefined, lb: p.title || v.title || undefined};
  };

  analytics.subscribe('orderorbit_event', (event) => {
    const d = event.customData || {};
    send(event, {k: 'e', e: String(d.event || '').replace('orderorbit:', ''), x: d.experience_id, ty: d.experience_type, tp: d.template_id, q: d.quantity || 1, a: d.answer || undefined});
  });

  analytics.subscribe('page_viewed', (event) => send(event, {k: 'v', n: 'page_viewed'}));

  analytics.subscribe('product_viewed', (event) => {
    const variant = event.data.productVariant;
    send(event, Object.assign({k: 'v', n: 'product_viewed', v: money(variant && variant.price)}, productOf(variant)));
  });

  analytics.subscribe('collection_viewed', (event) => {
    const c = event.data.collection || {};
    send(event, {k: 'v', n: 'collection_viewed', lb: c.title || undefined, cid: c.id || undefined});
  });

  analytics.subscribe('search_submitted', (event) => {
    const r = event.data.searchResult || {};
    send(event, {k: 'v', n: 'search_submitted', lb: r.query || undefined});
  });

  const cartLine = (name) => (event) => {
    const line = event.data.cartLine || {};
    send(event, Object.assign({k: 'v', n: name, q: line.quantity || 1, v: money(line.cost && line.cost.totalAmount)}, productOf(line.merchandise)));
  };
  analytics.subscribe('product_added_to_cart', cartLine('product_added_to_cart'));
  analytics.subscribe('product_removed_from_cart', cartLine('product_removed_from_cart'));

  analytics.subscribe('cart_viewed', (event) => {
    const cart = event.data.cart || {};
    send(event, {k: 'v', n: 'cart_viewed', v: money(cart.cost && cart.cost.totalAmount), q: cart.totalQuantity || undefined});
  });

  analytics.subscribe('checkout_started', (event) => {
    const c = event.data.checkout || {};
    send(event, {k: 'v', n: 'checkout_started', v: money(c.totalPrice)});
  });

  analytics.subscribe('payment_info_submitted', (event) => {
    const c = event.data.checkout || {};
    send(event, {k: 'v', n: 'payment_info_submitted', v: money(c.totalPrice)});
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
    const order = checkout.order || {};
    send(event, {
      k: 'o',
      id: order.id || checkout.token,
      v: checkout.totalPrice ? Number(checkout.totalPrice.amount) : 0,
      sub: checkout.subtotalPrice ? Number(checkout.subtotalPrice.amount) : 0,
      c: checkout.currencyCode,
      // The market country only (for "Someone in Canada"); never the address.
      cc: localization.country ? localization.country.isoCode : null,
      // The buyer's numeric customer id, for their journey and repeat purchases.
      oc: order.customer && order.customer.id ? String(order.customer.id) : undefined,
      l: lines,
    });
  });
});
