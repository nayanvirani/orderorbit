/*
 * OrderOrbit Space blocks for checkout, Thank You and Order Status. The merchant places this block
 * in Shopify's checkout editor and picks a block type; the live configuration comes from the
 * $app:checkout shop metafield that the app publishes. Everything is drawn with Shopify's own
 * components, so it follows the store's checkout branding.
 */
import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { createContext } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { assign, boxStyle, choose, deadlineLeft, fill, imageStyle, numericId, pageFor, progress } from './select.js';

export default async () => {
  render(<Extension />, document.body);
};

const ICON = { shipping: 'delivery', returns: 'return', secure: 'lock', guarantee: 'check-circle', support: 'question-circle' };

function readPayload() {
  const entry = (shopify.appMetafields?.value || []).find(
    (m) => m.metafield.key === 'checkout',
  );
  try {
    return entry ? JSON.parse(entry.metafield.value) : null;
  } catch (e) {
    return null;
  }
}

function money(amount) {
  try {
    return shopify.i18n.formatCurrency(amount);
  } catch (e) {
    return String(amount);
  }
}

function track(exp, event, extra) {
  try {
    shopify.analytics?.publish('orderorbit_event', Object.assign({
      event: 'orderorbit:' + event, experience_id: exp.id, experience_type: exp.type, template_id: exp.template, version: exp.version,
    }, exp.xv ? { experiment_id: exp.x.id, variant: exp.xv } : {}, extra || {}));
  } catch (e) {
    /* analytics not available on this page */
  }
}

function Extension() {
  const page = pageFor(shopify.extension.target);
  const payload = readPayload();
  const subtotal = Number(shopify.cost?.subtotalAmount?.value?.amount || 0);
  const country = shopify.localization?.country?.value?.isoCode;
  const published = choose(payload, shopify.settings?.value, { page, subtotal, country });
  const visitor = useVisitor(published && published.x);
  // In an A/B test, wait for the visitor id so nobody sees one variant and then another.
  const test = published && published.x ? (visitor === undefined ? null : assign(published, visitor, { subtotal, country })) : { exp: published, variant: null };
  const exp = test && test.exp;

  useEffect(() => {
    if (!test || !test.variant) return;
    // One exposure per buyer and variant.
    const key = 'oo_xp_' + published.x.id;
    Promise.resolve(shopify.storage?.read(key)).then((seen) => {
      if (seen === test.variant) return;
      shopify.storage?.write(key, test.variant);
      track(test.tracked, 'experiment_exposed', { holdout: !test.exp });
    }).catch(() => {});
  }, [test && test.variant]);

  useEffect(() => {
    if (exp && exp.analytics?.track_views !== false) track(exp, 'experience_viewed', { page_type: page });
  }, [exp && exp.id]);

  if (!exp) return null;
  const Render = BLOCKS[exp.type];
  const d = exp.design || {};
  const text = { color: d.ck_text === 'subdued' ? 'subdued' : undefined, tone: d.ck_tone && d.ck_tone !== 'auto' ? d.ck_tone : undefined };
  return Render ? <TextStyle.Provider value={text}><Render exp={exp} c={exp.content || {}} payload={payload} page={page} /></TextStyle.Provider> : null;
}

/** The block's text colour and tone (Design step), applied to every text in it. */
const TextStyle = createContext({});

function T({ color, tone, type, children }) {
  const style = useContext(TextStyle);
  return <s-text type={type} color={color || style.color} tone={tone || style.tone}>{children}</s-text>;
}

/** A random id for this buyer, kept in the extension's storage (undefined while loading). */
function useVisitor(needed) {
  const [id, setId] = useState(undefined);
  useEffect(() => {
    if (!needed) return;
    Promise.resolve(shopify.storage?.read('oo_vid'))
      .then((saved) => {
        if (saved) return setId(String(saved));
        const fresh = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        setId(fresh);
        return shopify.storage?.write('oo_vid', fresh);
      })
      .catch(() => setId(null)); // storage unavailable: the block shows as published
  }, [!!needed]);
  return id;
}

/**
 * The outer frame for a layout: banners for announcements, boxes for cards, plain for compact.
 * `as` asks for a banner, plain or subdued frame for one layout. The Design step's box styles
 * (background, border, corners, spacing, width, height) apply on top; any of them turns a banner
 * into a box, since banners can't be restyled.
 */
function Frame({ exp, heading, tone, as, children }) {
  const box = boxStyle(exp.style, exp.design, as);
  if (box.kind === 'banner') {
    return <Sized size={box.size}><s-banner heading={heading || undefined} tone={tone || 'info'}><s-stack gap="small-200">{children}</s-stack></s-banner></Sized>;
  }
  if (box.kind === 'plain') {
    return <Sized size={box.size}><s-stack gap="small-200">{heading ? <T type="strong">{heading}</T> : null}{children}</s-stack></Sized>;
  }
  return (
    <s-box {...box.props} {...box.size}>
      <s-stack gap="small-300">{heading ? <s-heading>{heading}</s-heading> : null}{children}</s-stack>
    </s-box>
  );
}

function Sized({ size, children }) {
  return size.inlineSize || size.minBlockSize ? <s-box {...size}>{children}</s-box> : children;
}

function Stars({ rating }) {
  const n = Math.round(Number(rating) || 0);
  return <T tone="warning">{'★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n)}</T>;
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

// ------------------------------------------------------------------ checkout blocks

function Reviews({ exp, c }) {
  const reviews = c.reviews || [];
  const [i, setI] = useState(0);
  if (!reviews.length) return null;
  const count = c.review_count ? ' · ' + shopify.i18n.translate('reviews', { count: Number(c.review_count).toLocaleString() }) : '';
  const summary = c.rating ? (
    <s-stack direction="inline" gap="small-200" alignItems="center">
      <Stars rating={c.rating} />
      <T type="strong">{c.rating}</T>
      {count ? <T>{count}</T> : null}
    </s-stack>
  ) : null;
  const quote = (r) => (
    <s-stack gap="small-100">
      <Stars rating={r.rating} />
      <T>“{r.quote}”</T>
      <T color="subdued">{r.author}</T>
    </s-stack>
  );

  if (exp.style === 'card') {
    return (
      <Frame exp={exp} heading={c.headline}>
        {summary}
        <s-grid gridTemplateColumns="1fr 1fr" gap="base">{reviews.map((r) => <Tile>{quote(r)}</Tile>)}</s-grid>
      </Frame>
    );
  }
  if (exp.style === 'slider') {
    const r = reviews[i % reviews.length];
    return (
      <Frame exp={exp}>
        <s-stack gap="small-200" alignItems="center">{quote(r)}</s-stack>
        {reviews.length > 1 ? (
          <s-stack direction="inline" gap="small-200" alignItems="center" justifyContent="center">
            <s-button variant="secondary" accessibilityLabel={shopify.i18n.translate('previous')} onClick={() => setI((i + reviews.length - 1) % reviews.length)}>‹</s-button>
            <T color="subdued">{shopify.i18n.translate('slide', { current: (i % reviews.length) + 1, total: reviews.length })}</T>
            <s-button variant="secondary" accessibilityLabel={shopify.i18n.translate('next')} onClick={() => setI(i + 1)}>›</s-button>
          </s-stack>
        ) : null}
      </Frame>
    );
  }
  if (exp.style === 'premium') {
    const r = reviews[0];
    return (
      <Frame exp={exp}>
        <Row>
          {c.rating ? <s-heading>{c.rating}</s-heading> : null}
          <s-stack gap="small-100">
            <Stars rating={c.rating || r.rating} />
            <T color="subdued">{c.headline}{count}</T>
          </s-stack>
        </Row>
        <s-divider />
        <T>“{r.quote}”</T>
        <Row>
          <T color="subdued">{r.author}</T>
          <s-badge icon="check-circle">{shopify.i18n.translate('verified')}</s-badge>
        </Row>
      </Frame>
    );
  }
  // Classic: an open list, reviews separated by dividers.
  return (
    <Frame exp={exp} as="plain">
      <Row justify="space-between">
        {c.headline ? <T type="strong">{c.headline}</T> : null}
        {summary}
      </Row>
      <Divided items={reviews.map(quote)} />
    </Frame>
  );
}

function Countdown({ exp, c }) {
  const [now, setNow] = useState(Date.now());
  const [started, setStarted] = useState(null);
  const timed = c.mode === 'hours' || c.mode === 'minutes';

  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);

  // Hour and minute timers start when this shopper first reaches checkout; the start is kept in
  // the extension's storage, so reloading the page doesn't restart the timer.
  useEffect(() => {
    if (!timed) return;
    const key = 'oo_cd_' + exp.id + '_' + exp.version;
    Promise.resolve(shopify.storage?.read(key))
      .then((saved) => {
        if (saved) return setStarted(Number(saved));
        const first = Date.now();
        setStarted(first);
        return shopify.storage?.write(key, String(first));
      })
      .catch(() => setStarted(Date.now()));
  }, [exp.id]);

  const left = deadlineLeft(c, now, started);
  if (left === null) return null; // waiting for the saved start
  if (left <= 0) return c.ended === 'message' ? <Frame exp={exp}><T>{c.ended_message}</T></Frame> : null;

  const pad = (n) => (n < 10 ? '0' : '') + n;
  const days = Math.floor(left / 864e5);
  const parts = [Math.floor((left % 864e5) / 36e5), Math.floor((left % 36e5) / 6e4), Math.floor((left % 6e4) / 1e3)].map(pad);
  const time = (days ? days + 'd ' : '') + parts.join(':');

  if (exp.style === 'banner') {
    return <Frame exp={exp} heading={c.headline} tone="warning"><T>{shopify.i18n.translate('endsIn', { time })}</T></Frame>;
  }
  if (exp.style === 'card') {
    const units = (days ? [[days, 'days']] : []).concat([[parts[0], 'hours'], [parts[1], 'minutes'], [parts[2], 'seconds']]);
    return (
      <Frame exp={exp} heading={c.headline}>
        <s-grid gridTemplateColumns={columns(units.length)} gap="small-300">
          {units.map(([n, unit]) => <Tile center><s-heading>{String(n)}</s-heading><T color="subdued">{shopify.i18n.translate(unit)}</T></Tile>)}
        </s-grid>
      </Frame>
    );
  }
  if (exp.style === 'premium') {
    return (
      <Frame exp={exp}>
        <s-stack gap="small-200" alignItems="center">
          <s-badge icon="clock" tone="critical">{shopify.i18n.translate('limitedTime')}</s-badge>
          <s-heading><T tone="critical">{time}</T></s-heading>
          <T color="subdued">{c.headline}</T>
        </s-stack>
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <Row justify="space-between">
        <s-stack direction="inline" gap="small-200" alignItems="center">
          <s-icon type="clock" tone="critical" />
          <T type="strong">{c.headline}</T>
        </s-stack>
        <T type="strong" tone="critical">{time}</T>
      </Row>
    </Frame>
  );
}

function cartLines() {
  return (shopify.lines?.value || []).map((l) => ({
    quantity: l.quantity,
    amount: Number(l.cost?.totalAmount?.value?.amount ?? l.cost?.totalAmount?.amount ?? 0),
    attributes: l.attributes || [],
  }));
}

const capital = (s) => String(s || '').charAt(0).toUpperCase() + String(s || '').slice(1);

function ShippingProgress({ exp, c, payload }) {
  if (!payload?.gifts) return null;
  const st = progress(payload.gifts, cartLines(), (m) => c.milestones === 'all' || m.reward === 'shipping');
  if (!st.milestones.length) return null;
  const amount = (v) => (st.byCount ? v + (v === 1 ? ' item' : ' items') : money(v));
  const text = st.next
    ? fill(c.progress_message, { remaining: amount(st.remaining), reward: st.next.label })
    : fill(c.unlocked_message, { reward: st.milestones[st.milestones.length - 1].label });

  if (exp.style === 'ladder') {
    return (
      <Frame exp={exp}>
        <T>{text}</T>
        <Divided items={st.milestones.map((m) => {
          const done = st.value >= m.threshold;
          return (
            <Row justify="space-between">
              <s-stack direction="inline" gap="small-200" alignItems="center">
                <s-icon type={done ? 'check-circle-filled' : 'circle-dashed'} tone={done ? 'success' : undefined} />
                <T type="strong">{capital(m.label)}</T>
              </s-stack>
              {done ? <T tone="success">{shopify.i18n.translate('unlocked')}</T> : <T color="subdued">{amount(m.threshold)}</T>}
            </Row>
          );
        })} />
      </Frame>
    );
  }
  if (exp.style === 'multi') {
    return (
      <Frame exp={exp}>
        <T>{text}</T>
        <s-progress value={st.percent} max={100} />
        <s-stack direction="inline" gap="small-200">
          {st.milestones.map((m) => {
            const done = st.value >= m.threshold;
            return <s-badge icon={done ? 'check-circle' : undefined} color={done ? 'base' : 'subdued'}>{amount(m.threshold) + ' · ' + m.label}</s-badge>;
          })}
        </s-stack>
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <Row>
        <s-icon type="delivery" tone="info" size="large" />
        <s-stack gap="small-200" inlineSize="fill">
          <T>{text}</T>
          <s-progress value={st.percent} max={100} />
        </s-stack>
      </Row>
    </Frame>
  );
}

function FreeGift({ exp, c, payload }) {
  const [status, setStatus] = useState('');
  if (!payload?.gifts) return null;
  const st = progress(payload.gifts, cartLines(), (m) => m.reward === 'gift' || m.reward === 'choice');
  if (!st.milestones.length) return null;
  const unlocked = st.reached.filter((m) => st.claimed.indexOf(String(m.index)) === -1);
  const offer = unlocked[0];
  const product = offer && (offer.products || [])[0];
  const canAdd = shopify.instructions?.value?.lines?.canAddCartLine !== false;
  const amount = (v) => (st.byCount ? v + (v === 1 ? ' item' : ' items') : money(v));

  async function claim() {
    const result = await shopify.applyCartLinesChange({
      type: 'addCartLine',
      merchandiseId: 'gid://shopify/ProductVariant/' + numericId(product.variant_id),
      quantity: offer.quantity || 1,
      attributes: [{ key: '_oo_offer', value: payload.gifts.id }, { key: '_oo_gift', value: String(offer.index) }],
    });
    setStatus(result.type === 'success' ? shopify.i18n.translate('giftAdded') : shopify.i18n.translate('giftFailed'));
    if (result.type === 'success') track(exp, 'reward_unlocked');
  }

  if (offer && product) {
    return (
      <Frame exp={exp} heading={exp.style === 'unlocked' ? c.unlocked_message : undefined} tone="success">
        {exp.style !== 'unlocked' ? (
          <s-stack direction="inline" gap="small-200" alignItems="center"><s-icon type="gift" tone="success" /><T type="strong">{c.unlocked_message}</T></s-stack>
        ) : null}
        <Row>
          {product.image ? <s-product-thumbnail src={product.image} alt={product.title} /> : null}
          <s-stack gap="small-100">
            <T type="strong">{product.title}</T>
            <T color="subdued">{shopify.i18n.translate('free')}</T>
          </s-stack>
        </Row>
        {canAdd ? <s-button variant="primary" onClick={claim}>{c.button_text}</s-button> : null}
        {status ? <T color="subdued">{status}</T> : null}
      </Frame>
    );
  }
  if (!st.next) return null;
  const message = fill(c.progress_message, { remaining: amount(st.remaining), reward: st.next.label });
  const next = (st.next.products || [])[0];

  if (exp.style === 'card' && next) {
    return (
      <Frame exp={exp}>
        <Row>
          {next.image ? <s-product-thumbnail src={next.image} alt={next.title} /> : null}
          <s-stack gap="small-200" inlineSize="fill">
            <s-badge icon="gift">{shopify.i18n.translate('freeGift')}</s-badge>
            <T type="strong">{next.title}</T>
            <T color="subdued">{message}</T>
            <s-progress value={st.percent} max={100} />
          </s-stack>
        </Row>
      </Frame>
    );
  }
  if (exp.style === 'unlocked') {
    // Not reached yet: an info banner until the gift unlocks.
    return (
      <Frame exp={exp} tone="info">
        <T>{message}</T>
        <s-progress value={st.percent} max={100} />
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <Row justify="space-between">
        <s-stack direction="inline" gap="small-200" alignItems="center">
          <s-icon type="gift" size="large" />
          <T>{message}</T>
        </s-stack>
        <T color="subdued">{st.percent + '%'}</T>
      </Row>
      <s-progress value={st.percent} max={100} />
    </Frame>
  );
}

function Promotion({ exp, c }) {
  const [status, setStatus] = useState('');
  const canApply = shopify.instructions?.value?.discounts?.canUpdateDiscountCodes !== false;
  async function apply() {
    const result = await shopify.applyDiscountCodeChange({ type: 'addDiscountCode', code: c.code });
    setStatus(result.type === 'success' ? shopify.i18n.translate('copied') : shopify.i18n.translate('codeFailed'));
    track(exp, 'experience_clicked', { action: 'apply_code' });
  }
  const done = status ? <T color="subdued">{status}</T> : null;
  const applyButton = (primary) => (c.code && canApply ? <s-button variant={primary ? 'primary' : 'secondary'} onClick={apply}>{c.apply_text}</s-button> : null);

  if (exp.style === 'announcement') {
    return (
      <Frame exp={exp} heading={c.headline} tone="info">
        {c.message ? <T>{c.message}</T> : null}
        {c.code ? <Row><T type="strong">{c.code}</T>{applyButton(false)}</Row> : null}
        {done}
      </Frame>
    );
  }
  if (exp.style === 'banner') {
    return (
      <Frame exp={exp} heading={c.headline} tone="success">
        {c.message ? <T>{c.message}</T> : null}
        {c.code ? <Row><T type="strong">{c.code}</T>{applyButton(true)}</Row> : null}
        {done}
      </Frame>
    );
  }
  if (exp.style === 'premium') {
    return (
      <Frame exp={exp}>
        <s-stack gap="small-300" alignItems="center">
          <s-badge icon="star">{shopify.i18n.translate('exclusive')}</s-badge>
          <s-heading>{c.headline}</s-heading>
          {c.message ? <T color="subdued">{c.message}</T> : null}
          {c.code ? (
            <s-box border="large base dashed" borderRadius="base" padding="base" background="base" inlineSize="fill">
              <s-stack direction="inline" gap="base" alignItems="center" justifyContent="center"><T type="strong">{c.code}</T>{applyButton(true)}</s-stack>
            </s-box>
          ) : null}
          {done}
        </s-stack>
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <Row align="start">
        <s-icon type="discount" tone="info" size="large" />
        <s-stack gap="small-200">
          <s-heading>{c.headline}</s-heading>
          {c.message ? <T>{c.message}</T> : null}
        </s-stack>
      </Row>
      {c.code ? (
        <Row>
          <s-box border="base base dashed" borderRadius="base" padding="small"><T type="strong">{c.code}</T></s-box>
          {applyButton(false)}
        </Row>
      ) : null}
      {done}
    </Frame>
  );
}

function Trust({ exp, c }) {
  const badges = c.badges || [];
  const icon = (b, tone, size) => <s-icon type={ICON[b.icon] || 'check-circle'} tone={tone} size={size} />;
  const guarantee = c.guarantee ? <T color="subdued">{c.guarantee}</T> : null;

  if (exp.style === 'grid') {
    return (
      <Frame exp={exp} heading={c.headline}>
        <s-grid gridTemplateColumns={columns(Math.min(3, badges.length))} gap="small-300">
          {badges.map((b) => <Tile center>{icon(b, undefined, 'large')}<T type="strong">{b.label}</T></Tile>)}
        </s-grid>
        {guarantee}
      </Frame>
    );
  }
  if (exp.style === 'card') {
    return (
      <Frame exp={exp} heading={c.headline}>
        <Divided items={badges.map((b) => (
          <Row justify="space-between">
            <s-stack direction="inline" gap="small-200" alignItems="center">{icon(b, 'success')}<T>{b.label}</T></s-stack>
            <s-icon type="check-circle" tone="success" />
          </Row>
        ))} />
        {guarantee}
      </Frame>
    );
  }
  if (exp.style === 'banner') {
    return (
      <Frame exp={exp} heading={c.headline} tone="success">
        {c.guarantee ? <T>{c.guarantee}</T> : null}
        <s-stack direction="inline" gap="base">{badges.map((b) => <T color="subdued">✓ {b.label}</T>)}</s-stack>
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      <s-stack direction="inline" gap="large" justifyContent="space-between">
        {badges.map((b) => <s-stack direction="inline" gap="small-200" alignItems="center">{icon(b, 'success')}<T>{b.label}</T></s-stack>)}
      </s-stack>
      {guarantee}
    </Frame>
  );
}

// ------------------------------------------------------------------ Thank You & Order Status

function storefront(path) {
  const base = String(shopify.shop?.storefrontUrl || '').replace(/\/$/, '');
  return base + (path || '');
}

function CrossSell({ exp, c }) {
  const products = (c.products || []).slice(0, exp.style === 'featured' ? 1 : 4);
  if (!products.length) return null;
  const href = (p) => storefront(p.handle ? '/products/' + p.handle : '');
  const clicked = () => track(exp, 'upsell_accepted');

  if (exp.style === 'featured') {
    const p = products[0];
    return (
      <Frame exp={exp} as="subdued">
        <Row align="start">
          {p.image ? <s-box inlineSize="128px"><s-image src={p.image} alt={p.title} aspectRatio="1" objectFit="cover" borderRadius="large" /></s-box> : null}
          <s-stack gap="small-200">
            <s-badge icon="star">{shopify.i18n.translate('pickedForYou')}</s-badge>
            <s-heading>{p.title}</s-heading>
            {p.price != null ? <T>{money(p.price)}</T> : null}
            <T color="subdued">{c.message || c.headline}</T>
            <s-button variant="primary" href={href(p)} onClick={clicked}>{c.button_text}</s-button>
          </s-stack>
        </Row>
      </Frame>
    );
  }
  if (exp.style === 'list') {
    return (
      <Frame exp={exp} heading={c.headline}>
        {c.message ? <T>{c.message}</T> : null}
        <Divided items={products.map((p) => (
          <Row justify="space-between">
            <s-stack direction="inline" gap="base" alignItems="center">
              {p.image ? <s-product-thumbnail src={p.image} alt={p.title} size="small" /> : null}
              <s-stack gap="small-100">
                <T type="strong">{p.title}</T>
                {p.price != null ? <T color="subdued">{money(p.price)}</T> : null}
              </s-stack>
            </s-stack>
            <s-button variant="secondary" href={href(p)} onClick={clicked}>{c.button_text}</s-button>
          </Row>
        ))} />
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      <s-grid gridTemplateColumns={columns(Math.min(3, products.length))} gap="base">
        {products.slice(0, 3).map((p) => (
          <s-stack gap="small-100">
            {p.image ? <s-image src={p.image} alt={p.title} aspectRatio="1" objectFit="cover" borderRadius="base" /> : null}
            <T type="strong">{p.title}</T>
            {p.price != null ? <T color="subdued">{money(p.price)}</T> : null}
            <s-link href={href(p)} onClick={clicked}>{c.button_text}</s-link>
          </s-stack>
        ))}
      </s-grid>
    </Frame>
  );
}

function Reorder({ exp, c }) {
  const lines = (shopify.lines?.value || []).filter((l) => l.merchandise?.id);
  if (!lines.length) return null;
  const permalink = storefront('/cart/' + lines.map((l) => numericId(l.merchandise.id) + ':' + l.quantity).join(','));
  const button = <s-button variant="primary" href={permalink} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.button_text}</s-button>;
  if (exp.style === 'button') {
    return (
      <Frame exp={exp}>
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            <s-icon type="order-repeat" tone="info" size="large" />
            <s-stack gap="small-100">
              <T type="strong">{c.headline}</T>
              {c.message ? <T color="subdued">{c.message}</T> : null}
            </s-stack>
          </s-stack>
          {button}
        </Row>
      </Frame>
    );
  }
  const images = lines.map((l) => l.merchandise?.image?.url).filter(Boolean).slice(0, 4);
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      {images.length ? (
        <Row>
          {images.map((src) => <s-product-thumbnail src={src} />)}
          <T color="subdued">{shopify.i18n.translate('itemsInOrder', { count: lines.length })}</T>
        </Row>
      ) : null}
      {button}
    </Frame>
  );
}

function ReviewRequest({ exp, c }) {
  const url = c.review_url || storefront('');
  const clicked = () => track(exp, 'experience_clicked', { action: 'review' });
  if (exp.style === 'products') {
    const items = (shopify.lines?.value || []).filter((l) => l.merchandise).slice(0, 4);
    return (
      <Frame exp={exp} heading={c.headline}>
        {c.message ? <T>{c.message}</T> : null}
        <Divided items={items.map((l) => {
          const m = l.merchandise;
          const title = m.product?.title || m.title;
          return (
            <Row justify="space-between">
              <s-stack direction="inline" gap="base" alignItems="center">
                {m.image?.url ? <s-product-thumbnail src={m.image.url} alt={title} size="small" /> : null}
                <T type="strong">{title}</T>
              </s-stack>
              <s-link href={c.review_url || storefront(m.product?.handle ? '/products/' + m.product.handle : '')} onClick={clicked}>{shopify.i18n.translate('review')}</s-link>
            </Row>
          );
        })} />
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <s-stack gap="small-200" alignItems="center">
        <s-heading><T tone="warning">★★★★★</T></s-heading>
        <s-heading>{c.headline}</s-heading>
        {c.message ? <T color="subdued">{c.message}</T> : null}
        <s-button variant="primary" href={url} onClick={clicked}>{c.button_text}</s-button>
      </s-stack>
    </Frame>
  );
}

function Referral({ exp, c }) {
  if (!c.code) return null;
  const share = c.share_url || storefront('');
  const clicked = () => track(exp, 'experience_clicked', { action: 'share' });
  if (exp.style === 'banner') {
    return (
      <Frame exp={exp} heading={c.headline} tone="info">
        {c.message ? <T>{c.message}</T> : null}
        <Row><T type="strong">{c.code}</T><s-link href={share} onClick={clicked}>{shopify.i18n.translate('share')}</s-link></Row>
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <s-stack gap="small-300" alignItems="center">
        <s-icon type="share" tone="info" size="large" />
        <s-heading>{c.headline}</s-heading>
        {c.message ? <T color="subdued">{c.message}</T> : null}
        <s-box border="large base dashed" borderRadius="base" padding="base" inlineSize="fill">
          <s-stack direction="inline" gap="base" alignItems="center" justifyContent="center">
            <T type="strong">{c.code}</T>
            <s-button variant="primary" href={share} onClick={clicked}>{shopify.i18n.translate('share')}</s-button>
          </s-stack>
        </s-box>
      </s-stack>
    </Frame>
  );
}

function Survey({ exp, c }) {
  const [answer, setAnswer] = useState('');
  const [sent, setSent] = useState(false);
  if (sent) return <Frame exp={exp} as="banner" tone="success"><T>{c.thanks_message}</T></Frame>;
  const options = (c.options || []).map((o) => o.label).filter(Boolean);
  if (!options.length) return null;
  // Answers reach Analytics through the OrderOrbit Space web pixel.
  const send = (value) => {
    track(exp, 'survey_answered', { answer: String(value).slice(0, 120) });
    setSent(true);
  };

  if (exp.style === 'card') {
    // One tap answers.
    return (
      <Frame exp={exp} as="subdued">
        <s-stack direction="inline" gap="small-200" alignItems="center"><s-icon type="chat" tone="info" /><T type="strong">{c.question}</T></s-stack>
        <s-grid gridTemplateColumns="1fr 1fr" gap="small-300">
          {options.map((label) => <s-button variant="secondary" inlineSize="fill" onClick={() => send(label)}>{label}</s-button>)}
        </s-grid>
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.question}>
      <s-choice-list name={'oo-survey-' + exp.id} onChange={(e) => setAnswer((e.currentTarget.values || [])[0] || e.currentTarget.value || '')}>
        {options.map((label) => <s-choice value={label}>{label}</s-choice>)}
      </s-choice-list>
      <s-button variant="primary" disabled={!answer} onClick={() => send(answer)}>{c.button_text}</s-button>
    </Frame>
  );
}

function NextDiscount({ exp, c }) {
  if (!c.code) return null;
  if (exp.style === 'banner') {
    return (
      <Frame exp={exp} heading={c.headline} tone="success">
        {c.message ? <T>{c.message}</T> : null}
        <Row><T type="strong">{c.code}</T>{c.expiry_text ? <T color="subdued">{c.expiry_text}</T> : null}</Row>
      </Frame>
    );
  }
  return (
    <Frame exp={exp}>
      <s-stack gap="small-300" alignItems="center">
        <s-icon type="gift" tone="success" size="large" />
        <s-heading>{c.headline}</s-heading>
        {c.message ? <T color="subdued">{c.message}</T> : null}
        <s-box border="large base dashed" borderRadius="base" padding="base" inlineSize="fill">
          <s-stack alignItems="center"><T type="strong">{c.code}</T></s-stack>
        </s-box>
        {c.expiry_text ? <T color="subdued">{c.expiry_text}</T> : null}
      </s-stack>
    </Frame>
  );
}

function Message({ exp, c }) {
  if (!c.headline && !c.message) return null;
  const go = () => track(exp, 'experience_clicked', { action: 'link' });
  const hasButton = c.button_text && c.button_url;
  if (exp.style === 'loyalty') {
    return (
      <Frame exp={exp} heading={c.headline} tone="success">
        {c.message ? <T>{c.message}</T> : null}
        {hasButton ? <s-link href={c.button_url} onClick={go}>{c.button_text}</s-link> : null}
      </Frame>
    );
  }
  if (exp.style === 'education') {
    return (
      <Frame exp={exp}>
        <Row align="start">
          <s-icon type="book-open" tone="info" size="large" />
          <s-stack gap="small-200">
            {c.headline ? <s-heading>{c.headline}</s-heading> : null}
            {c.message ? <T color="subdued">{c.message}</T> : null}
            {hasButton ? <s-button variant="secondary" href={c.button_url} onClick={go}>{c.button_text}</s-button> : null}
          </s-stack>
        </Row>
      </Frame>
    );
  }
  if (exp.style === 'support') {
    return (
      <Frame exp={exp}>
        <s-divider />
        <Row justify="space-between">
          <s-stack direction="inline" gap="base" alignItems="center">
            <s-icon type="question-circle" size="large" />
            <s-stack gap="small-100">
              {c.headline ? <T type="strong">{c.headline}</T> : null}
              {c.message ? <T color="subdued">{c.message}</T> : null}
            </s-stack>
          </s-stack>
          {hasButton ? <s-link href={c.button_url} onClick={go}>{c.button_text}</s-link> : null}
        </Row>
      </Frame>
    );
  }
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <T>{c.message}</T> : null}
      {hasButton ? <s-button variant="secondary" href={c.button_url} onClick={go}>{c.button_text}</s-button> : null}
    </Frame>
  );
}

function ImageBlock({ exp, c }) {
  if (!c.image) return null;
  const img = imageStyle(c);
  const picture = <s-image src={c.image} alt={c.alt || ''} loading="lazy" {...img.image} />;
  const framed = img.clip ? <s-box {...img.clip}>{picture}</s-box> : picture;
  const sized = <s-box inlineSize={img.width}>{c.link_url ? (
    <s-clickable href={c.link_url.charAt(0) === '/' ? storefront(c.link_url) : c.link_url} accessibilityLabel={c.alt || undefined} onClick={() => track(exp, 'experience_clicked', { action: 'image' })}>{framed}</s-clickable>
  ) : framed}</s-box>;
  return (
    <Frame exp={exp}>
      <s-stack gap="small-200" alignItems={c.align || 'center'}>
        {sized}
        {c.caption ? <T color="subdued">{c.caption}</T> : null}
      </s-stack>
    </Frame>
  );
}

const BLOCKS = {
  'checkout-reviews': Reviews,
  'checkout-countdown': Countdown,
  'checkout-shipping': ShippingProgress,
  'checkout-gift': FreeGift,
  'checkout-promo': Promotion,
  'checkout-trust': Trust,
  'ty-cross-sell': CrossSell,
  'ty-reorder': Reorder,
  'ty-review': ReviewRequest,
  'ty-referral': Referral,
  'ty-survey': Survey,
  'ty-discount': NextDiscount,
  'ty-message': Message,
  'checkout-image': ImageBlock,
  'ty-image': ImageBlock,
};
