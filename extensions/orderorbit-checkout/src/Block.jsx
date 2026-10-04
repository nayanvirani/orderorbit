/*
 * OrderOrbit Space blocks for checkout, Thank You and Order Status. The merchant places this block
 * in Shopify's checkout editor and picks a block type; the live configuration comes from the
 * $app:checkout shop metafield that the app publishes. Everything is drawn with Shopify's own
 * components, so it follows the store's checkout branding.
 */
import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { useEffect, useState } from 'preact/hooks';
import { choose, fill, numericId, pageFor, progress } from './select.js';

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
    }, extra || {}));
  } catch (e) {
    /* analytics not available on this page */
  }
}

function Extension() {
  const page = pageFor(shopify.extension.target);
  const payload = readPayload();
  const subtotal = Number(shopify.cost?.subtotalAmount?.value?.amount || 0);
  const exp = choose(payload, shopify.settings?.value, {
    page,
    subtotal,
    country: shopify.localization?.country?.value?.isoCode,
  });

  useEffect(() => {
    if (exp && exp.analytics?.track_views !== false) track(exp, 'experience_viewed', { page_type: page });
  }, [exp && exp.id]);

  if (!exp) return null;
  const Render = BLOCKS[exp.type];
  return Render ? <Render exp={exp} c={exp.content || {}} payload={payload} page={page} /> : null;
}

/** The outer frame for a layout: banners for announcements, boxes for cards, plain for compact. */
function Frame({ exp, heading, tone, children }) {
  const style = exp.style;
  if (style === 'banner' || style === 'announcement' || style === 'unlocked') {
    return <s-banner heading={heading || undefined} tone={tone || 'info'}><s-stack gap="small-200">{children}</s-stack></s-banner>;
  }
  if (style === 'compact' || style === 'row' || style === 'button' || style === 'simple') {
    return <s-stack gap="small-200">{heading ? <s-text type="strong">{heading}</s-text> : null}{children}</s-stack>;
  }
  return (
    <s-box padding="base" border="base" borderRadius="base" background={style === 'premium' ? 'subdued' : undefined}>
      <s-stack gap="small-300">{heading ? <s-heading>{heading}</s-heading> : null}{children}</s-stack>
    </s-box>
  );
}

function Stars({ rating }) {
  const n = Math.round(Number(rating) || 0);
  return <s-text>{'★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n)}</s-text>;
}

// ------------------------------------------------------------------ checkout blocks

function Reviews({ exp, c }) {
  const reviews = c.reviews || [];
  const [i, setI] = useState(0);
  if (!reviews.length) return null;
  const shown = exp.style === 'slider' || exp.style === 'premium' ? [reviews[i % reviews.length]] : reviews;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.rating ? (
        <s-stack direction="inline" gap="small-200" alignItems="center">
          <Stars rating={c.rating} />
          <s-text>{c.rating}{c.review_count ? ' · ' + shopify.i18n.translate('reviews', { count: Number(c.review_count).toLocaleString() }) : ''}</s-text>
        </s-stack>
      ) : null}
      {shown.map((r) => (
        <s-stack gap="small-100">
          <Stars rating={r.rating} />
          <s-text>“{r.quote}”</s-text>
          <s-text color="subdued">{r.author}</s-text>
        </s-stack>
      ))}
      {exp.style === 'slider' && reviews.length > 1 ? (
        <s-stack direction="inline" gap="small-200" alignItems="center">
          <s-button variant="secondary" onClick={() => setI((i + reviews.length - 1) % reviews.length)}>{shopify.i18n.translate('previous')}</s-button>
          <s-text color="subdued">{shopify.i18n.translate('slide', { current: (i % reviews.length) + 1, total: reviews.length })}</s-text>
          <s-button variant="secondary" onClick={() => setI(i + 1)}>{shopify.i18n.translate('next')}</s-button>
        </s-stack>
      ) : null}
    </Frame>
  );
}

function Countdown({ exp, c }) {
  const [now, setNow] = useState(Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);
  const left = c.ends_at ? Date.parse(c.ends_at) - now : 0;
  if (left <= 0) return c.ended === 'message' ? <Frame exp={exp}><s-text>{c.ended_message}</s-text></Frame> : null;
  const pad = (n) => (n < 10 ? '0' : '') + n;
  const days = Math.floor(left / 864e5);
  const time = (days ? days + 'd ' : '') + pad(Math.floor((left % 864e5) / 36e5)) + ':' + pad(Math.floor((left % 36e5) / 6e4)) + ':' + pad(Math.floor((left % 6e4) / 1e3));
  return (
    <Frame exp={exp} heading={exp.style === 'banner' ? c.headline : undefined} tone="warning">
      <s-stack direction="inline" gap="base" alignItems="center" justifyContent="space-between">
        {exp.style !== 'banner' ? <s-text type="strong">{c.headline}</s-text> : null}
        <s-text type="strong">{time}</s-text>
      </s-stack>
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

function ShippingProgress({ exp, c, payload }) {
  if (!payload?.gifts) return null;
  const st = progress(payload.gifts, cartLines(), (m) => c.milestones === 'all' || m.reward === 'shipping');
  if (!st.milestones.length) return null;
  const amount = (v) => (st.byCount ? v + (v === 1 ? ' item' : ' items') : money(v));
  const text = st.next
    ? fill(c.progress_message, { remaining: amount(st.remaining), reward: st.next.label })
    : fill(c.unlocked_message, { reward: st.milestones[st.milestones.length - 1].label });
  return (
    <Frame exp={exp} tone={st.next ? 'info' : 'success'}>
      <s-text>{text}</s-text>
      <s-progress value={st.percent} max={100} />
      {exp.style !== 'single'
        ? st.milestones.map((m) => (
          <s-text color={st.value >= m.threshold ? undefined : 'subdued'}>{(st.value >= m.threshold ? '✓ ' : '○ ') + amount(m.threshold) + ' · ' + m.label}</s-text>
        ))
        : null}
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
        {exp.style !== 'unlocked' ? <s-text type="strong">{c.unlocked_message}</s-text> : null}
        <s-stack direction="inline" gap="base" alignItems="center">
          {product.image ? <s-product-thumbnail src={product.image} alt={product.title} /> : null}
          <s-text>{product.title}</s-text>
        </s-stack>
        {canAdd ? <s-button variant="primary" onClick={claim}>{c.button_text}</s-button> : null}
        {status ? <s-text color="subdued">{status}</s-text> : null}
      </Frame>
    );
  }
  if (!st.next) return null;
  return (
    <Frame exp={exp}>
      <s-text>{fill(c.progress_message, { remaining: amount(st.remaining), reward: st.next.label })}</s-text>
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
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      {c.code ? (
        <s-stack direction="inline" gap="base" alignItems="center">
          <s-text type="strong">{c.code}</s-text>
          {canApply ? <s-button variant="secondary" onClick={apply}>{c.apply_text}</s-button> : null}
        </s-stack>
      ) : null}
      {status ? <s-text color="subdued">{status}</s-text> : null}
    </Frame>
  );
}

function Trust({ exp, c }) {
  const badges = c.badges || [];
  const items = badges.map((b) => (
    <s-stack direction="inline" gap="small-200" alignItems="center">
      <s-icon type={ICON[b.icon] || 'check-circle'} />
      <s-text>{b.label}</s-text>
    </s-stack>
  ));
  return (
    <Frame exp={exp} heading={c.headline} tone="success">
      {exp.style === 'grid' ? <s-grid gridTemplateColumns="1fr 1fr" gap="small-300">{items}</s-grid> : <s-stack direction={exp.style === 'row' ? 'inline' : 'block'} gap="base">{items}</s-stack>}
      {c.guarantee ? <s-text color="subdued">{c.guarantee}</s-text> : null}
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
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      <s-grid gridTemplateColumns={exp.style === 'cards' ? '1fr 1fr' : '1fr'} gap="base">
        {products.map((p) => (
          <s-stack direction={exp.style === 'cards' ? 'block' : 'inline'} gap="small-200" alignItems="center">
            {p.image ? <s-product-thumbnail src={p.image} alt={p.title} /> : null}
            <s-stack gap="small-100">
              <s-text type="strong">{p.title}</s-text>
              {p.price != null ? <s-text color="subdued">{money(p.price)}</s-text> : null}
              <s-link href={storefront(p.handle ? '/products/' + p.handle : '')} onClick={() => track(exp, 'upsell_accepted')}>{c.button_text}</s-link>
            </s-stack>
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
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      <s-button variant="primary" href={permalink} onClick={() => track(exp, 'experience_clicked', { action: 'reorder' })}>{c.button_text}</s-button>
    </Frame>
  );
}

function ReviewRequest({ exp, c }) {
  const url = c.review_url || storefront('');
  const titles = (shopify.lines?.value || []).map((l) => l.merchandise?.title || l.merchandise?.product?.title).filter(Boolean);
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      {exp.style === 'products' ? titles.slice(0, 4).map((t) => <s-text>• {t}</s-text>) : null}
      <s-button variant="primary" href={url} onClick={() => track(exp, 'experience_clicked', { action: 'review' })}>{c.button_text}</s-button>
    </Frame>
  );
}

function Referral({ exp, c }) {
  if (!c.code) return null;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      <s-stack direction="inline" gap="base" alignItems="center">
        <s-text type="strong">{c.code}</s-text>
        <s-link href={c.share_url || storefront('')} onClick={() => track(exp, 'experience_clicked', { action: 'share' })}>{shopify.i18n.translate('share')}</s-link>
      </s-stack>
    </Frame>
  );
}

function Survey({ exp, c }) {
  const [answer, setAnswer] = useState('');
  const [sent, setSent] = useState(false);
  if (sent) return <Frame exp={exp} tone="success"><s-text>{c.thanks_message}</s-text></Frame>;
  const options = (c.options || []).map((o) => o.label).filter(Boolean);
  if (!options.length) return null;
  return (
    <Frame exp={exp} heading={c.question}>
      <s-choice-list name={'oo-survey-' + exp.id} onChange={(e) => setAnswer((e.currentTarget.values || [])[0] || e.currentTarget.value || '')}>
        {options.map((label) => <s-choice value={label}>{label}</s-choice>)}
      </s-choice-list>
      <s-button
        variant="primary"
        disabled={!answer}
        onClick={() => {
          // Answers reach Analytics through the OrderOrbit Space web pixel.
          track(exp, 'survey_answered', { answer: String(answer).slice(0, 120) });
          setSent(true);
        }}
      >
        {c.button_text}
      </s-button>
    </Frame>
  );
}

function NextDiscount({ exp, c }) {
  if (!c.code) return null;
  return (
    <Frame exp={exp} heading={c.headline} tone="success">
      {c.message ? <s-text>{c.message}</s-text> : null}
      <s-text type="strong">{c.code}</s-text>
      {c.expiry_text ? <s-text color="subdued">{c.expiry_text}</s-text> : null}
    </Frame>
  );
}

function Message({ exp, c }) {
  if (!c.headline && !c.message) return null;
  return (
    <Frame exp={exp} heading={c.headline}>
      {c.message ? <s-text>{c.message}</s-text> : null}
      {c.button_text && c.button_url ? (
        <s-button variant="secondary" href={c.button_url} onClick={() => track(exp, 'experience_clicked', { action: 'link' })}>{c.button_text}</s-button>
      ) : null}
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
};
