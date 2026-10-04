import { ago, KeyValue, number, Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';

const ok = (on, yes, no) => <s-badge tone={on ? 'success' : 'warning'}>{on ? yes : no}</s-badge>;

const PLANNED = [
  ['Klaviyo', 'Send segments and events to your email flows.'],
  ['Judge.me and Yotpo', 'Real reviews in review blocks and requests.'],
  ['Google Analytics 4', 'OrderOrbit events in GA4.'],
  ['Gorgias', 'Support tickets with order context.'],
];

export default function Integrations({ connection: c, webhooks }) {
  return (
    <Page heading="Settings">
      <s-section heading="Shopify">
        <KeyValue rows={[
          ['Connection', <>{ok(c.connected, 'Connected', 'Needs attention')} <s-link href={appUrl(route('app.settings.store'))}>Details</s-link></>],
          ['Theme app embed', <>{ok(c.themeBlocks, 'Supported theme', 'Theme doesn\'t support app blocks')} <s-link href={c.themeEditorUrl} target="_top">Open Theme Editor</s-link></>],
          ['Web pixel (analytics)', <>{ok(c.pixel, 'Connected', 'Not connected')} <span className="oo-muted oo-small">Last event {c.lastPixelEvent ? ago(c.lastPixelEvent) : 'not yet'}</span></>],
          ['Checkout blocks', ok(c.checkoutBlocks, 'Available', 'Thank You and Order Status only (needs Shopify Plus)')],
          ['Customer accounts', ok(c.customerAccounts, 'New customer accounts', 'Classic accounts: blocks unavailable')],
        ]} />
      </s-section>

      <s-section heading="Webhooks · last 30 days">
        <p className="oo-muted">Shopify tells OrderOrbit Space about these events. Each is verified with Shopify's signature and handled once.</p>
        <div className="oo-scroll">
          <table className="oo-table stack">
            <thead><tr><th>Event</th><th>Topic</th><th>Received</th><th>Last received</th><th>Status</th></tr></thead>
            <tbody>
              {webhooks.map((w) => (
                <tr key={w.topic}>
                  <td data-label="Event">{w.label}</td>
                  <td data-label="Topic"><span className="oo-code">{w.topic}</span></td>
                  <td data-label="Received">{number(w.received)}</td>
                  <td data-label="Last received">{w.last_at ? ago(w.last_at) : '—'}</td>
                  <td data-label="Status">{w.unprocessed ? <s-badge tone="warning">{w.unprocessed} not processed</s-badge> : w.received ? <s-badge tone="success">OK</s-badge> : <span className="oo-muted">Subscribed</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </s-section>

      <s-section heading="OrderOrbit Space extensions">
        <ul className="oo-list">
          <li><strong>Theme app extension</strong>: storefront blocks and the app embed.</li>
          <li><strong>Web pixel</strong>: consent-aware analytics.</li>
          <li><strong>Checkout UI extension</strong>: checkout, Thank You and Order Status blocks.</li>
          <li><strong>Customer account extension</strong>: account blocks and "Buy again".</li>
          <li><strong>Post-purchase extension</strong>: the one-click offer after payment.</li>
          <li><strong>Discount function and cart transform</strong>: bundle prices and offer savings at checkout.</li>
        </ul>
      </s-section>

      <s-section heading="Coming integrations">
        <div className="ob-kpis">
          {PLANNED.map(([name, text]) => <div className="ob-kpi" key={name}><small>Planned</small><b style={{ fontSize: 18 }}>{name}</b><span>{text}</span></div>)}
        </div>
      </s-section>
    </Page>
  );
}
