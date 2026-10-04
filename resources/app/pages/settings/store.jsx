import { ActionButton } from '../../components/form.jsx';
import { ago, date, KeyValue, Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';

const yesNo = (value, yes, no, unknown = 'Not checked yet') => (value === null || value === undefined ? ['neutral', unknown] : value ? ['success', yes] : ['warning', no]);
const badge = ([tone, text]) => <s-badge tone={tone}>{text}</s-badge>;

export default function StoreSettings({ store, capabilities: cap, missingScopes, grantedScopes, themeEditorUrl }) {
  const { can } = useShared();
  const surfaces = [
    ['Storefront blocks', yesNo(cap.online_store_2, 'Supported', 'Theme update needed'), 'Bundles, upsells, shipping bar and more, placed in the Theme Editor.'],
    ['Blocks inside checkout', yesNo(cap.checkout_blocks, 'Supported', 'Requires Shopify Plus'), cap.development_store ? 'Development stores can preview checkout blocks.' : 'Trust, reviews, shipping progress and offers in the checkout steps.'],
    ['Thank You and Order Status', yesNo(cap.thank_you_blocks ?? true, 'Supported', 'Not available'), 'Cross-sells, reorder, reviews and support after purchase.'],
    ['Customer accounts', yesNo(cap.new_customer_accounts, 'Supported', 'Requires new customer accounts'), 'Reorder, rewards and reviews in customer accounts.'],
  ];

  return (
    <Page heading="Settings">
      {missingScopes.length > 0 && (
        <s-banner tone="critical" heading="Your Shopify connection needs attention">
          <s-paragraph>OrderOrbit Space is missing permissions it needs: {missingScopes.join(', ')}. Reconnect to approve them.</s-paragraph>
        </s-banner>
      )}

      <s-section heading="Shopify connection">
        <KeyValue rows={[
          ['Status', store.installed && !missingScopes.length ? <s-badge tone="success">Connected</s-badge> : <s-badge tone="critical">Needs attention</s-badge>],
          ['Store', <>{store.name || '—'} <span className="oo-muted">· {store.shop_domain}</span></>],
          ['Shopify plan', store.shopify_plan],
          ['Currency', store.currency],
          ['Selling currencies', (cap.presentment_currencies || []).join(', ') || '—'],
          ['Timezone', store.timezone],
          ['Installed', date(store.installed_at)],
          ['Granted permissions', <span className="oo-inline">{grantedScopes.length ? grantedScopes.map((s) => <span key={s} className="oo-code">{s}</span>) : '—'}</span>],
        ]} />
        {can.manage_settings && (
          <s-stack direction="inline" gap="small-200" paddingBlockStart="base">
            <ActionButton url={route('app.settings.store.reconnect')}>Reconnect</ActionButton>
          </s-stack>
        )}
      </s-section>

      <s-section heading="Theme">
        <KeyValue rows={[
          ['Live theme', store.theme_name],
          ['App blocks', badge(yesNo(cap.online_store_2, 'Supported (Online Store 2.0)', 'Not supported — switch to an Online Store 2.0 theme'))],
          ['OrderOrbit Space app embed', <s-badge>Available with the first OrderOrbit Space block</s-badge>],
        ]} />
        <s-stack direction="inline" gap="small-200" paddingBlockStart="base">
          <s-button href={themeEditorUrl} target="_top">Open Theme Editor</s-button>
        </s-stack>
      </s-section>

      <s-section heading="What your store supports">
        <s-paragraph>OrderOrbit Space only shows the Shopify surfaces your store can use.</s-paragraph>
        <div className="oo-scroll">
          <table className="oo-table">
            <thead><tr><th>Surface</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
              {surfaces.map(([name, status, note]) => (
                <tr key={name}><td>{name}</td><td>{badge(status)}</td><td className="oo-muted">{note}</td></tr>
              ))}
              <tr>
                <td>Analytics</td>
                <td><s-badge tone={store.pixel ? 'success' : 'warning'}>{store.pixel ? 'Connected' : 'Not connected'}</s-badge></td>
                <td className="oo-muted">The consent-aware web pixel. <a href={appUrl(route('app.settings.integrations'))}>Details</a></td>
              </tr>
            </tbody>
          </table>
        </div>
        <s-stack direction="inline" gap="small-200" alignItems="center" paddingBlockStart="base">
          {can.manage_settings && <ActionButton url={route('app.settings.store.recheck')}>Re-check capabilities</ActionButton>}
          <span className="oo-muted oo-small">Last checked {ago(store.capabilities_checked_at)}</span>
        </s-stack>
      </s-section>
    </Page>
  );
}
