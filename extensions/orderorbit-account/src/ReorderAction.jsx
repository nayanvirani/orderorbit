/*
 * The reorder dialog from an order's menu: the order's items (selectable when the Reorder block
 * lets customers pick) and a button that adds them to the cart on the storefront.
 */
import '@shopify/ui-extensions/preact';
import { render } from 'preact';
import { useEffect, useState } from 'preact/hooks';
import { cartLink } from './logic.js';
import { loadOrder, loadPayload, track } from './data.js';

export default async () => {
  render(<Action />, document.body);
};

const t = (key, vars) => shopify.i18n.translate(key, vars);

function Action() {
  const [state, setState] = useState(null);
  const [picked, setPicked] = useState([]);

  useEffect(() => {
    (async () => {
      const payload = await loadPayload();
      const exp = ((payload && payload.experiences) || []).filter((e) => e.type === 'account-reorder').sort((a, b) => (b.priority || 0) - (a.priority || 0))[0] || null;
      let order = null;
      try {
        order = await loadOrder(shopify.orderId);
      } catch (e) {
        order = null;
      }
      const lines = ((order && order.lineItems && order.lineItems.nodes) || []).filter((l) => l.variantId);
      setPicked(lines.map(() => true));
      setState({ exp, shopUrl: payload && payload.shop_url, lines });
    })();
  }, []);

  if (!state) return <s-customer-account-action heading={t('loading')}><s-spinner /></s-customer-account-action>;
  const c = (state.exp && state.exp.content) || {};
  const pick = c.behavior === 'pick';
  const chosen = pick ? state.lines.filter((l, i) => picked[i]) : state.lines;
  const href = cartLink(state.shopUrl, chosen);

  return (
    <s-customer-account-action heading={c.headline || c.button_text || 'Buy again'}>
      <s-stack gap="base">
        <s-text color="subdued">{pick ? t('chooseItems') : t('allItems')}</s-text>
        {state.lines.map((l, i) => (pick
          ? <s-checkbox checked={picked[i]} label={l.quantity + ' × ' + l.title} onChange={() => setPicked(picked.map((p, j) => (j === i ? !p : p)))} />
          : <s-text>{l.quantity} × {l.title}</s-text>))}
        <s-text color="subdued">{t('unavailable')}</s-text>
      </s-stack>
      <s-button slot="primary-action" href={href || undefined} disabled={!href} onClick={() => state.exp && track(state.exp, 'experience_clicked', { action: 'reorder_menu' })}>
        {pick ? t('addItems', { count: chosen.length }) : (c.button_text || 'Buy again')}
      </s-button>
      <s-button slot="secondary-actions" onClick={() => shopify.close()}>{t('cancel')}</s-button>
    </s-customer-account-action>
  );
}
