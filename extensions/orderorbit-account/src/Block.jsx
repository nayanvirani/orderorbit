/*
 * OrderOrbit Space blocks for Shopify's customer accounts. The merchant places this block on the
 * Orders, Profile or Order status page in the customer accounts editor and picks a block type;
 * the live configuration and the customer's orders come from the Customer Account API.
 */
import '@shopify/ui-extensions/preact';
import { createContext, render } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { boxStyle, cartLink, choose, fill, link, pageFor, purchasedProducts, reviewLink, rewardTier, shipmentStep, totalSpent, activeOrders } from './logic.js';
import { loadCustomer, loadOrder, loadPayload, loadProducts, track } from './data.js';

export default async () => {
  render(<Extension />, document.body);
};

function money(amount, currency) {
  try {
    return shopify.i18n.formatCurrency(Number(amount), currency ? { currency } : undefined);
  } catch (e) {
    return String(amount);
  }
}

function date(value) {
  try {
    return shopify.i18n.formatDate(new Date(value), { month: 'short', day: 'numeric', year: 'numeric' });
  } catch (e) {
    return String(value).slice(0, 10);
  }
}

const t = (key, vars) => shopify.i18n.translate(key, vars);

function Extension() {
  const page = pageFor(shopify.extension.target);
  const [state, setState] = useState(null);

  useEffect(() => {
    let alive = true;
    (async () => {
      const payload = await loadPayload();
      const exp = choose(payload, shopify.settings && shopify.settings.value, { country: shopify.localization && shopify.localization.country && shopify.localization.country.value && shopify.localization.country.value.isoCode });
      if (!exp) return alive && setState({ exp: null });
      let customer = { firstName: '', orders: [] };
      let order = null;
      try {
        customer = await loadCustomer();
        const current = page === 'order' && shopify.order && shopify.order.value && shopify.order.value.id;
        order = current ? (customer.orders.find((o) => o.id === current) || await loadOrder(current)) : activeOrders(customer.orders)[0] || null;
      } catch (e) {
        /* signed out or the API is unavailable: blocks that need orders hide */
      }
      if (alive) setState({ exp, payload, customer, order });
    })();
    return () => { alive = false; };
  }, []);

  useEffect(() => {
    if (state && state.exp && state.exp.analytics && state.exp.analytics.track_views !== false) track(state.exp, 'experience_viewed', { page_type: 'account_' + page });
  }, [state && state.exp && state.exp.id]);

  if (!state || !state.exp) return null;
  const Render = BLOCKS[state.exp.type];
  const d = state.exp.design || {};
  const text = { color: d.ck_text === 'subdued' ? 'subdued' : undefined, tone: d.ck_tone && d.ck_tone !== 'auto' ? d.ck_tone : undefined };
  return Render ? (
    <TextStyle.Provider value={text}>
      <Render exp={state.exp} c={state.exp.content || {}} shopUrl={state.payload.shop_url} customer={state.customer} order={state.order} page={page} />
    </TextStyle.Provider>
  ) : null;
}

/** The block's text colour and tone (Design step), applied to every text in it. */
const TextStyle = createContext({});

function T({ color, tone, type, children }) {
  const style = useContext(TextStyle);
  return <s-text type={type} color={color || style.color} tone={tone || style.tone}>{children}</s-text>;
}

function Frame({ exp, heading, tone, children }) {
  const box = boxStyle(exp.style, exp.design);
  const body = <s-stack gap="small-300">{heading && box.kind !== 'banner' ? (box.kind === 'plain' ? <T type="strong">{heading}</T> : <s-heading>{heading}</s-heading>) : null}{children}</s-stack>;
  if (box.kind === 'banner') return <Sized size={box.size}><s-banner heading={heading || undefined} tone={tone || 'info'}>{body}</s-banner></Sized>;
  if (box.kind === 'plain') return <Sized size={box.size}>{body}</Sized>;
  return <s-box {...box.props} {...box.size}>{body}</s-box>;
}

function Sized({ size, children }) {
  return size.inlineSize || size.minBlockSize ? <s-box {...size}>{children}</s-box> : children;
}

function orderStatus(order) {
  if (order.cancelledAt) return t('status.CANCELLED');
  const shipment = ((order.fulfillments && order.fulfillments.nodes) || []).map((f) => f.latestShipmentStatus).filter(Boolean)[0];
  return shipment ? t('shipment.' + shipment) : t('status.' + (order.fulfillmentStatus || 'UNFULFILLED'));
}

function Tracking({ order, buttonText }) {
  const fulfillments = (order.fulfillments && order.fulfillments.nodes) || [];
  return fulfillments.flatMap((f) => (f.trackingInformation || []).map((ti) => (
    <s-stack direction="inline" gap="small-200" alignItems="center">
      <T color="subdued">{ti.company && ti.number ? t('carrier', { company: ti.company, number: ti.number }) : ti.number || ti.company || ''}</T>
      {ti.url ? <s-link href={ti.url} target="_blank">{buttonText}</s-link> : null}
    </s-stack>
  )));
}

// ------------------------------------------------------------------ blocks

function MyOrders({ exp, c, shopUrl, customer, order }) {
  const orders = activeOrders(customer.orders);
  const spent = totalSpent(customer.orders);
  const currency = orders[0] && orders[0].totalPrice && orders[0].totalPrice.currencyCode;
  const reorder = order && cartLink(shopUrl, (order.lineItems && order.lineItems.nodes) || []);
  return (
    <Frame exp={exp} heading={fill(c.headline, { first_name: customer.firstName })} tone="success">
      {c.message ? <T>{fill(c.message, { count: orders.length, spent: money(spent, currency), first_name: customer.firstName })}</T> : null}
      {c.show_latest && order ? (
        <s-stack gap="small-100">
          <T type="strong">{t('latest', { name: order.name, date: date(order.processedAt) })}</T>
          <s-stack direction="inline" gap="small-200" alignItems="center">
            <s-badge>{orderStatus(order)}</s-badge>
            <T color="subdued">{t('orderTotal', { total: money(order.totalPrice.amount, order.totalPrice.currencyCode) })}</T>
          </s-stack>
          {c.show_tracking ? <Tracking order={order} buttonText={t('steps.shipped')} /> : null}
        </s-stack>
      ) : !orders.length ? <T color="subdued">{t('noOrders')}</T> : null}
      {c.show_reorder && reorder ? <s-button href={reorder} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.button_text}</s-button> : null}
    </Frame>
  );
}

function TrackOrder({ exp, c, shopUrl, order }) {
  if (!order) return null;
  const step = shipmentStep(order);
  const eta = ((order.fulfillments && order.fulfillments.nodes) || []).map((f) => f.estimatedDeliveryAt).filter(Boolean)[0];
  const help = link(shopUrl, c.help_url);
  const labels = [t('steps.ordered'), t('steps.shipped'), t('steps.out'), t('steps.delivered')];
  return (
    <Frame exp={exp} heading={c.headline} tone={step === 3 ? 'success' : 'info'}>
      <T type="strong">{order.name} · {orderStatus(order)}</T>
      {exp.style === 'timeline' ? (
        <s-stack gap="small-100">
          {labels.map((label, i) => <T color={i <= step ? undefined : 'subdued'} type={i === step ? 'strong' : undefined}>{(i <= step ? '● ' : '○ ') + label}</T>)}
        </s-stack>
      ) : <s-progress value={Math.round((step / 3) * 100)} max={100} />}
      {step === 0 ? <T color="subdued">{c.pending_message}</T> : step === 3 ? <T>{c.delivered_message}</T> : null}
      {eta && step < 3 ? <T>{t('estimated', { date: date(eta) })}</T> : null}
      <Tracking order={order} buttonText={c.button_text} />
      {help && c.help_text ? <s-link href={help} onClick={() => track(exp, 'experience_clicked', { action: 'help' })}>{c.help_text}</s-link> : null}
    </Frame>
  );
}

function Reorder({ exp, c, shopUrl, order }) {
  const lines = ((order && order.lineItems && order.lineItems.nodes) || []).filter((l) => l.variantId);
  const [picked, setPicked] = useState(lines.map(() => true));
  if (!lines.length) return null;
  const chosen = c.behavior === 'pick' ? lines.filter((l, i) => picked[i]) : lines;
  const href = cartLink(shopUrl, chosen);
  const button = <s-button variant="primary" href={href || undefined} disabled={!href} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.behavior === 'pick' ? t('addItems', { count: chosen.length }) : c.button_text}</s-button>;
  if (exp.style === 'button') return button;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      {c.behavior === 'pick' ? (
        <s-stack gap="small-200">
          <T color="subdued">{t('chooseItems')}</T>
          {lines.map((l, i) => (
            <s-checkbox checked={picked[i]} label={l.quantity + ' × ' + l.title} onChange={() => setPicked(picked.map((p, j) => (j === i ? !p : p)))} />
          ))}
        </s-stack>
      ) : null}
      {button}
    </Frame>
  );
}

function Rewards({ exp, c, shopUrl, customer }) {
  const spent = totalSpent(customer.orders);
  const tiers = c.tiers || [];
  if (!tiers.length) return null;
  const currency = (activeOrders(customer.orders)[0] || { totalPrice: {} }).totalPrice.currencyCode;
  const r = rewardTier(tiers, spent);
  const page = link(shopUrl, c.perks_url);
  const vars = { tier: r.tier ? r.tier.name : '', next_tier: r.next ? r.next.name : '', remaining: money(r.remaining, currency), spent: money(spent, currency) };
  return (
    <Frame exp={exp} heading={c.headline} tone="success">
      {r.tier ? <T type="strong">{r.tier.name}{r.tier.perks ? ' · ' + r.tier.perks : ''}</T> : null}
      <s-progress value={r.percent} max={100} />
      <T>{r.next ? fill(c.progress_message, vars) : fill(c.top_message, vars)}</T>
      {r.next && r.next.perks ? <T color="subdued">{r.next.name}: {r.next.perks}</T> : null}
      {page ? <s-link href={page}>{t('viewRewards')}</s-link> : null}
    </Frame>
  );
}

function useProductDetails(products) {
  const [details, setDetails] = useState({});
  useEffect(() => {
    loadProducts(products.map((p) => p.productId)).then(setDetails);
  }, [products.map((p) => p.productId).join(',')]);
  return details;
}

function Reviews({ exp, c, shopUrl, customer }) {
  const products = purchasedProducts(customer.orders, 'recent').slice(0, Number(c.max_products) || 4);
  const details = useProductDetails(products);
  if (!products.length) return null;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      <s-grid gridTemplateColumns={exp.style === 'grid' ? '1fr 1fr' : '1fr'} gap="base">
        {products.map((p) => {
          const info = details[p.productId] || {};
          return (
            <s-stack direction="inline" gap="base" alignItems="center">
              {p.image || info.image ? <s-product-thumbnail src={p.image || info.image} alt={p.title} /> : null}
              <s-stack gap="small-100">
                <T type="strong">{info.title || p.title}</T>
                <s-link href={reviewLink(c.review_url, Object.assign({ productId: p.productId }, info), shopUrl)} onClick={() => track(exp, 'experience_clicked', { action: 'review' })}>{c.button_text}</s-link>
              </s-stack>
            </s-stack>
          );
        })}
      </s-grid>
    </Frame>
  );
}

function MyProducts({ exp, c, shopUrl, customer }) {
  const products = purchasedProducts(customer.orders, c.sort).slice(0, Number(c.max_products) || 6);
  const details = useProductDetails(products);
  if (!products.length) return null;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      <s-grid gridTemplateColumns={exp.style === 'grid' ? '1fr 1fr' : '1fr'} gap="base">
        {products.map((p) => {
          const info = details[p.productId] || {};
          const url = info.url || (info.handle ? shopUrl + '/products/' + info.handle : null);
          const again = cartLink(shopUrl, [{ variantId: p.variantId, quantity: 1 }]);
          return (
            <s-stack direction={exp.style === 'grid' ? 'block' : 'inline'} gap="small-200" alignItems={exp.style === 'grid' ? 'start' : 'center'}>
              {p.image || info.image ? <s-product-thumbnail src={p.image || info.image} alt={p.title} /> : null}
              <s-stack gap="small-100">
                <T type="strong">{info.title || p.title}</T>
                {p.count > 1 ? <T color="subdued">{t('boughtTimes', { count: p.count })}</T> : null}
                <s-stack direction="inline" gap="small-200">
                  {again ? <s-link href={again} onClick={() => track(exp, 'experience_clicked', { action: 'buy_again' })}>{c.button_text}</s-link> : null}
                  {url && c.view_text ? <s-link href={url}>{c.view_text}</s-link> : null}
                </s-stack>
              </s-stack>
            </s-stack>
          );
        })}
      </s-grid>
    </Frame>
  );
}

function Support({ exp, c, shopUrl }) {
  const help = link(shopUrl, c.help_url);
  const returns = link(shopUrl, c.returns_url);
  const email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(c.email || '').trim()) ? String(c.email).trim() : null;
  const phone = String(c.phone || '').replace(/[^\d+]/g, '');
  const clicked = (action) => () => track(exp, 'experience_clicked', { action });
  const links = [
    email ? <s-link href={'mailto:' + email} onClick={clicked('email')}>{t('email')}</s-link> : null,
    phone ? <s-link href={'tel:' + phone} onClick={clicked('call')}>{t('call')} · {c.phone}</s-link> : null,
    help ? <s-link href={help} onClick={clicked('help')}>{t('helpCenter')}</s-link> : null,
    returns ? <s-link href={returns} onClick={clicked('returns')}>{t('returns')}</s-link> : null,
  ].filter(Boolean);
  const faqs = (c.faqs || []).filter((f) => f.question && f.answer);
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      {links.length ? <s-stack direction={exp.style === 'compact' ? 'inline' : 'block'} gap="small-300">{links}</s-stack> : null}
      {faqs.length && exp.style !== 'compact' ? (
        <s-stack gap="small-300">
          {faqs.map((f) => (
            <s-stack gap="small-100">
              <T type="strong">{f.question}</T>
              <T color="subdued">{f.answer}</T>
            </s-stack>
          ))}
        </s-stack>
      ) : null}
    </Frame>
  );
}

const BLOCKS = {
  'account-orders': MyOrders,
  'account-tracking': TrackOrder,
  'account-reorder': Reorder,
  'account-rewards': Rewards,
  'account-reviews': Reviews,
  'account-products': MyProducts,
  'account-support': Support,
};
