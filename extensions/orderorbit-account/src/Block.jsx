/*
 * Growvia blocks for Shopify's customer accounts. The merchant places this block on the
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

/** The block's frame (same rules as checkout blocks); `as` asks for a banner, plain or subdued frame. */
function Frame({ exp, heading, tone, as, children }) {
  const box = boxStyle(exp.style, exp.design, as);
  const body = <s-stack gap="small-300">{heading && box.kind !== 'banner' ? (box.kind === 'plain' ? <T type="strong">{heading}</T> : <s-heading>{heading}</s-heading>) : null}{children}</s-stack>;
  if (box.kind === 'banner') return <Sized size={box.size}><s-banner heading={heading || undefined} tone={box.tone || tone || 'info'}>{body}</s-banner></Sized>;
  if (box.kind === 'plain') return <Sized size={box.size}>{body}</Sized>;
  return <s-box {...box.props} {...box.size}>{body}</s-box>;
}

function Sized({ size, children }) {
  return size.inlineSize || size.minBlockSize ? <s-box {...size}>{children}</s-box> : children;
}

/** A subdued tile inside a grid. */
function Tile({ center, children }) {
  return (
    <s-box background="subdued" borderRadius="base" padding="base">
      <s-stack gap="small-100" alignItems={center ? 'center' : undefined}>{children}</s-stack>
    </s-box>
  );
}

function Row({ children, align, justify }) {
  return <s-stack direction="inline" gap="base" alignItems={align || 'center'} justifyContent={justify}>{children}</s-stack>;
}

/** Items separated by dividers. */
function Divided({ items }) {
  return <s-stack gap="small-300">{items.map((item, i) => [i ? <s-divider /> : null, item])}</s-stack>;
}

const columns = (n) => Array(Math.max(1, n)).fill('1fr').join(' ');

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

function firstImage(order) {
  const line = ((order && order.lineItems && order.lineItems.nodes) || []).find((l) => l.image && l.image.url);
  return line ? line.image.url : null;
}

// ------------------------------------------------------------------ blocks

function MyOrders({ exp, c, shopUrl, customer, order }) {
  const orders = activeOrders(customer.orders);
  const spent = totalSpent(customer.orders);
  const currency = orders[0] && orders[0].totalPrice && orders[0].totalPrice.currencyCode;
  const reorder = order && cartLink(shopUrl, (order.lineItems && order.lineItems.nodes) || []);
  const headline = fill(c.headline, { first_name: customer.firstName });
  const message = c.message ? <T>{fill(c.message, { count: orders.length, spent: money(spent, currency), first_name: customer.firstName })}</T> : null;
  const again = (link) => (c.show_reorder && reorder ? (link
    ? <s-link href={reorder} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.button_text}</s-link>
    : <s-button href={reorder} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.button_text}</s-button>) : null);
  const latest = c.show_latest && order ? (
    <s-stack gap="small-100">
      <Row justify="space-between">
        <s-stack direction="inline" gap="base" alignItems="center">
          {firstImage(order) ? <s-product-thumbnail src={firstImage(order)} size="small" /> : null}
          <s-stack gap="small-100">
            <T type="strong">{t('latest', { name: order.name, date: date(order.processedAt) })}</T>
            <T color="subdued">{t('orderTotal', { total: money(order.totalPrice.amount, order.totalPrice.currencyCode) })}</T>
          </s-stack>
        </s-stack>
        <s-badge icon="delivery">{orderStatus(order)}</s-badge>
      </Row>
      {c.show_tracking ? <Tracking order={order} buttonText={t('steps.shipped')} /> : null}
    </s-stack>
  ) : null;

  if (exp.style === 'banner') {
    return <Frame exp={exp} heading={headline} tone="success">{message}{again(true)}</Frame>;
  }
  if (exp.style === 'compact') {
    if (!order) return null;
    return <Frame exp={exp} heading={headline}>{latest}{again(false)}</Frame>;
  }
  return (
    <Frame exp={exp} heading={headline}>
      {message}
      {orders.length ? (
        <s-grid gridTemplateColumns="1fr 1fr 1fr" gap="small-300">
          <Tile center><s-heading>{String(orders.length)}</s-heading><T color="subdued">{t('stats.orders')}</T></Tile>
          <Tile center><s-heading>{money(spent, currency)}</s-heading><T color="subdued">{t('stats.spent')}</T></Tile>
          <Tile center><s-heading>{shopify.i18n.formatDate(new Date(orders[0].processedAt), { month: 'short', day: 'numeric' })}</s-heading><T color="subdued">{t('stats.last')}</T></Tile>
        </s-grid>
      ) : <T color="subdued">{t('noOrders')}</T>}
      {latest}
      {again(false)}
    </Frame>
  );
}

function TrackOrder({ exp, c, shopUrl, order }) {
  if (!order) return null;
  const step = shipmentStep(order);
  const eta = ((order.fulfillments && order.fulfillments.nodes) || []).map((f) => f.estimatedDeliveryAt).filter(Boolean)[0];
  const help = link(shopUrl, c.help_url);
  const labels = [t('steps.ordered'), t('steps.shipped'), t('steps.out'), t('steps.delivered')];
  const helpLink = help && c.help_text ? <s-link href={help} onClick={() => track(exp, 'experience_clicked', { action: 'help' })}>{c.help_text}</s-link> : null;
  const note = step === 0 ? <T color="subdued">{c.pending_message}</T> : step === 3 ? <T>{c.delivered_message}</T> : null;
  const tracking = (((order.fulfillments && order.fulfillments.nodes) || []).flatMap((f) => f.trackingInformation || []).find((ti) => ti.url) || {}).url;

  if (exp.style === 'compact') {
    return (
      <Frame exp={exp}>
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            <s-icon type="delivery" tone="info" size="large" />
            <s-stack gap="small-100">
              <T type="strong">{order.name} · {orderStatus(order)}</T>
              {eta && step < 3 ? <T color="subdued">{t('estimated', { date: date(eta) })}</T> : note}
            </s-stack>
          </s-stack>
          {tracking ? <s-button variant="secondary" href={tracking} target="_blank">{c.button_text}</s-button> : null}
        </Row>
      </Frame>
    );
  }
  if (exp.style === 'timeline') {
    return (
      <Frame exp={exp} heading={c.headline}>
        <T type="strong">{order.name}{eta && step < 3 ? ' · ' + t('estimated', { date: date(eta) }) : ''}</T>
        <s-stack gap="small-200">
          {labels.map((label, i) => (
            <s-stack direction="inline" gap="small-200" alignItems="center">
              <s-icon type={i <= step ? 'check-circle-filled' : 'circle-dashed'} tone={i <= step ? 'success' : undefined} />
              <T color={i <= step ? undefined : 'subdued'} type={i === step ? 'strong' : undefined}>{label}</T>
            </s-stack>
          ))}
        </s-stack>
        {note}
        <Tracking order={order} buttonText={c.button_text} />
        {helpLink}
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      <Row justify="space-between">
        <T type="strong">{order.name}</T>
        <s-badge icon="delivery">{orderStatus(order)}</s-badge>
      </Row>
      <s-progress value={Math.round((step / 3) * 100)} max={100} />
      <s-grid gridTemplateColumns="1fr 1fr 1fr 1fr" gap="small-200">
        {labels.map((label, i) => <T color={i <= step ? undefined : 'subdued'} type={i <= step ? 'strong' : 'small'}>{label}</T>)}
      </s-grid>
      {note}
      {eta && step < 3 ? <T>{t('estimated', { date: date(eta) })}</T> : null}
      <Tracking order={order} buttonText={c.button_text} />
      {helpLink}
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
  if (exp.style === 'button') {
    return (
      <s-stack direction="inline" gap="base" alignItems="center">
        {button}
        <T color="subdued">{t('reorderItems', { count: lines.length })}</T>
      </s-stack>
    );
  }
  const items = c.behavior === 'pick' ? (
    <s-stack gap="small-200">
      <T color="subdued">{t('chooseItems')}</T>
      {lines.map((l, i) => (
        <s-checkbox checked={picked[i]} label={l.quantity + ' × ' + l.title} onChange={() => setPicked(picked.map((p, j) => (j === i ? !p : p)))} />
      ))}
    </s-stack>
  ) : (
    <Row>
      {lines.filter((l) => l.image && l.image.url).slice(0, 4).map((l) => <s-product-thumbnail src={l.image.url} alt={l.title} />)}
      <T color="subdued">{t('reorderItems', { count: lines.length })}</T>
    </Row>
  );
  if (exp.style === 'banner') {
    return <Frame exp={exp} heading={c.headline} tone="warning">{c.message ? <T>{c.message}</T> : null}{c.behavior === 'pick' ? items : null}{button}</Frame>;
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      {items}
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
  const message = <T>{r.next ? fill(c.progress_message, vars) : fill(c.top_message, vars)}</T>;
  const perks = page ? <s-link href={page}>{t('viewRewards')}</s-link> : null;

  if (exp.style === 'banner') {
    return <Frame exp={exp} heading={c.headline} tone="success">{message}<s-progress value={r.percent} max={100} />{perks}</Frame>;
  }
  if (exp.style === 'premium') {
    return (
      <Frame exp={exp}>
        <s-stack gap="small-200" alignItems="center">
          <s-icon type="star" tone="warning" size="large" />
          <T color="subdued">{c.headline}</T>
          <s-heading>{r.tier ? r.tier.name : t('member')}</s-heading>
          {r.tier && r.tier.perks ? <T color="subdued">{r.tier.perks}</T> : null}
        </s-stack>
        <s-progress value={r.percent} max={100} />
        {message}
        {perks}
      </Frame>
    );
  }
  const sorted = tiers.slice().sort((a, b) => (a.threshold || 0) - (b.threshold || 0)).slice(0, 3);
  return (
    <Frame exp={exp} heading={c.headline}>
      <Row justify="space-between">
        <s-badge icon="star">{r.tier ? r.tier.name : t('member')}</s-badge>
        {r.next ? <T color="subdued">{t('nextTier', { tier: r.next.name })}</T> : null}
      </Row>
      <s-progress value={r.percent} max={100} />
      {message}
      <s-grid gridTemplateColumns={columns(sorted.length)} gap="small-300">
        {sorted.map((tier) => <Tile center><T type="strong">{tier.name}</T>{tier.perks ? <T color="subdued">{tier.perks}</T> : null}</Tile>)}
      </s-grid>
      {perks}
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
  const href = (p) => reviewLink(c.review_url, Object.assign({ productId: p.productId }, details[p.productId] || {}), shopUrl);
  const clicked = () => track(exp, 'experience_clicked', { action: 'review' });
  const image = (p) => p.image || (details[p.productId] || {}).image;
  const name = (p) => (details[p.productId] || {}).title || p.title;

  if (exp.style === 'compact') {
    const p = products[0];
    return (
      <Frame exp={exp}>
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            {image(p) ? <s-product-thumbnail src={image(p)} alt={name(p)} /> : null}
            <s-stack gap="small-100">
              <T type="strong">{c.headline}</T>
              <T color="subdued">{name(p)}</T>
              <T tone="warning">☆☆☆☆☆</T>
            </s-stack>
          </s-stack>
          <s-button variant="primary" href={href(p)} onClick={clicked}>{c.button_text}</s-button>
        </Row>
      </Frame>
    );
  }
  if (exp.style === 'grid') {
    return (
      <Frame exp={exp} heading={c.headline}>
        {c.message ? <T>{c.message}</T> : null}
        <s-grid gridTemplateColumns="1fr 1fr" gap="base">
          {products.map((p) => (
            <Tile center>
              {image(p) ? <s-product-thumbnail src={image(p)} alt={name(p)} /> : null}
              <T type="strong">{name(p)}</T>
              <T tone="warning">☆☆☆☆☆</T>
              <s-link href={href(p)} onClick={clicked}>{c.button_text}</s-link>
            </Tile>
          ))}
        </s-grid>
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      <Divided items={products.map((p) => (
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            {image(p) ? <s-product-thumbnail src={image(p)} alt={name(p)} size="small" /> : null}
            <T type="strong">{name(p)}</T>
          </s-stack>
          <s-link href={href(p)} onClick={clicked}>{c.button_text}</s-link>
        </Row>
      ))} />
    </Frame>
  );
}

function MyProducts({ exp, c, shopUrl, customer }) {
  const products = purchasedProducts(customer.orders, c.sort).slice(0, Number(c.max_products) || 6);
  const details = useProductDetails(products);
  if (!products.length) return null;
  const info = (p) => details[p.productId] || {};
  const url = (p) => info(p).url || (info(p).handle ? shopUrl + '/products/' + info(p).handle : null);
  const again = (p) => cartLink(shopUrl, [{ variantId: p.variantId, quantity: 1 }]);
  const buy = (p, variant) => (again(p) ? <s-button variant={variant || 'secondary'} href={again(p)} onClick={() => track(exp, 'experience_clicked', { action: 'buy_again' })}>{c.button_text}</s-button> : null);
  const image = (p) => p.image || info(p).image;
  const name = (p) => info(p).title || p.title;

  if (exp.style === 'compact') {
    return (
      <Frame exp={exp}>
        <s-stack direction="inline" gap="small-200" alignItems="center"><s-icon type="heart" tone="critical" /><T type="strong">{c.headline}</T></s-stack>
        <s-grid gridTemplateColumns={columns(Math.min(4, products.length))} gap="base">
          {products.slice(0, 4).map((p) => (
            <s-clickable href={url(p) || again(p) || undefined}>
              <s-stack gap="small-100" alignItems="center">
                {image(p) ? <s-product-thumbnail src={image(p)} alt={name(p)} /> : null}
                <T color="subdued">{name(p)}</T>
              </s-stack>
            </s-clickable>
          ))}
        </s-grid>
      </Frame>
    );
  }
  if (exp.style === 'grid') {
    return (
      <Frame exp={exp} heading={c.headline}>
        {c.message ? <T>{c.message}</T> : null}
        <s-grid gridTemplateColumns={columns(Math.min(3, products.length))} gap="base">
          {products.map((p) => (
            <s-stack gap="small-100">
              {image(p) ? <s-image src={image(p)} alt={name(p)} aspectRatio="1" objectFit="cover" borderRadius="base" /> : null}
              <T type="strong">{name(p)}</T>
              {p.count > 1 ? <T color="subdued">{t('boughtTimes', { count: p.count })}</T> : null}
              {buy(p)}
            </s-stack>
          ))}
        </s-grid>
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      <Divided items={products.map((p) => (
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            {image(p) ? <s-product-thumbnail src={image(p)} alt={name(p)} size="small" /> : null}
            <s-stack gap="small-100">
              <T type="strong">{name(p)}</T>
              <s-stack direction="inline" gap="small-200">
                {p.count > 1 ? <T color="subdued">{t('boughtTimes', { count: p.count })}</T> : null}
                {url(p) && c.view_text ? <s-link href={url(p)}>{c.view_text}</s-link> : null}
              </s-stack>
            </s-stack>
          </s-stack>
          {buy(p)}
        </Row>
      ))} />
    </Frame>
  );
}

function Support({ exp, c, shopUrl }) {
  const help = link(shopUrl, c.help_url);
  const returns = link(shopUrl, c.returns_url);
  const email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(c.email || '').trim()) ? String(c.email).trim() : null;
  const phone = String(c.phone || '').replace(/[^\d+]/g, '');
  const clicked = (action) => () => track(exp, 'experience_clicked', { action });
  const contacts = [
    email ? { icon: 'email', href: 'mailto:' + email, label: t('email'), action: 'email' } : null,
    phone ? { icon: 'phone', href: 'tel:' + phone, label: t('call') + ' · ' + c.phone, short: t('call'), action: 'call' } : null,
    help ? { icon: 'question-circle', href: help, label: t('helpCenter'), action: 'help' } : null,
    returns ? { icon: 'return', href: returns, label: t('returns'), action: 'returns' } : null,
  ].filter(Boolean);
  const faqs = (c.faqs || []).filter((f) => f.question && f.answer);

  if (exp.style === 'compact') {
    return (
      <Row justify="space-between">
        <s-stack direction="inline" gap="small-200" alignItems="center"><s-icon type="question-circle" size="large" /><T type="strong">{c.headline}</T></s-stack>
        <s-stack direction="inline" gap="base">{contacts.map((l) => <s-link href={l.href} onClick={clicked(l.action)}>{l.short || l.label}</s-link>)}</s-stack>
      </Row>
    );
  }
  if (exp.style === 'faq') {
    return (
      <Frame exp={exp} heading={c.headline}>
        {c.message ? <T color="subdued">{c.message}</T> : null}
        {faqs.length ? (
          <Divided items={faqs.map((f) => (
            <s-details>
              <s-summary>{f.question}</s-summary>
              <T color="subdued">{f.answer}</T>
            </s-details>
          ))} />
        ) : null}
        {contacts.length ? <s-stack direction="inline" gap="base">{contacts.map((l) => <s-link href={l.href} onClick={clicked(l.action)}>{l.label}</s-link>)}</s-stack> : null}
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T color="subdued">{c.message}</T> : null}
      {contacts.length ? (
        <s-grid gridTemplateColumns={columns(Math.min(3, contacts.length))} gap="small-300">
          {contacts.map((l) => (
            <s-clickable href={l.href} onClick={clicked(l.action)}>
              <Tile><s-stack direction="inline" gap="small-200" alignItems="center"><s-icon type={l.icon} tone="info" /><T type="strong">{l.label}</T></s-stack></Tile>
            </s-clickable>
          ))}
        </s-grid>
      ) : null}
      {faqs.length ? (
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
