import { useState } from 'react';
import { ago, KeyValue, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';
import { confirmDialog } from '../../components/Confirm.jsx';

const ok = (on, yes, no) => <s-badge tone={on ? 'success' : 'warning'}>{on ? yes : no}</s-badge>;

/** One connected app (phase 6): connect with its keys, choose what to share, test, activity, disconnect. */
function ConnectedApp({ app: a }) {
  const { submit } = useRouter();
  const { can } = useShared();
  const [keys, setKeys] = useState({});
  const [events, setEvents] = useState(Object.fromEntries(a.events.map((e) => [e.name, e.on])));
  const [segments, setSegments] = useState((a.segments || []).filter((s) => s.on).map((s) => s.id));
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);
  const run = async (key, path, data = {}) => {
    setBusy(key);
    const r = await submit(route(path, { app: a.key }), data);
    setBusy(null);
    setError(r?.errors ? Object.values(r.errors)[0] : null);
  };
  const edit = can.manage_settings && !a.locked;
  return (
    <div className="ca-app">
      <div className="ca-head">
        <b>{a.name}</b>
        {a.locked ? <s-badge>{a.locked}</s-badge> : a.connected ? <s-badge tone={a.status === 'failing' ? 'critical' : 'success'}>{a.status === 'failing' ? 'Needs attention' : 'Connected'}</s-badge> : <s-badge>Not connected</s-badge>}
      </div>
      <p className="oo-small">{a.what}</p>
      <p className="oo-muted oo-small"><b>Data shared:</b> {a.data}</p>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {a.connected && a.error && !error && <s-banner tone="warning">{a.error}</s-banner>}
      {!a.connected && edit && (
        <form className="ca-form" onSubmit={(e) => { e.preventDefault(); run('connect', 'app.settings.integrations.connect', keys); }}>
          {a.fields.map((f) => (
            <label key={f.name}>{f.label}
              <input type="password" autoComplete="off" value={keys[f.name] || ''} onChange={(e) => setKeys({ ...keys, [f.name]: e.target.value })} />
              <small className="oo-muted">{f.help}</small>
            </label>
          ))}
          <s-button variant="primary" type="submit" loading={busy === 'connect' ? '' : undefined}>Connect</s-button>
        </form>
      )}
      {a.connected && (
        <>
          <div className="ca-events">
            <small><b>{a.optionsTitle}</b></small>
            {a.events.map((e) => (
              <label key={e.name} className="ca-check">
                <input type="checkbox" disabled={!edit} checked={!!events[e.name]} onChange={(ev) => setEvents({ ...events, [e.name]: ev.target.checked })} />
                {e.label}{a.optionsTitle === 'Send these events' && <> <span className="oo-code">{e.name}</span></>}
              </label>
            ))}
          </div>
          {a.segments && (
            <div className="ca-events">
              <small><b>Sync these segments to Klaviyo lists (daily)</b></small>
              {!a.segments.length && <span className="oo-muted oo-small">No segments that can sync yet. Segments built from orders, total spent or customer tags can.</span>}
              {a.segments.map((s) => (
                <label key={s.id} className="ca-check">
                  <input type="checkbox" disabled={!edit} checked={segments.includes(s.id)} onChange={(ev) => setSegments(ev.target.checked ? [...segments, s.id] : segments.filter((x) => x !== s.id))} />
                  {s.name}
                </label>
              ))}
            </div>
          )}
          {edit && (
            <div className="pf-bar">
              <s-button loading={busy === 'settings' ? '' : undefined} onClick={() => run('settings', 'app.settings.integrations.settings', { events, segments })}>Save</s-button>
              <s-button loading={busy === 'test' ? '' : undefined} onClick={() => run('test', 'app.settings.integrations.test')}>{a.optionsTitle === 'Send these events' ? 'Send test' : 'Sync now'}</s-button>
              <s-button variant="tertiary" tone="critical" onClick={async () => (await confirmDialog(`Disconnect ${a.name}? Its keys are deleted and nothing more is sent.`, { title: 'Disconnect', action: 'Disconnect' })) && run('disconnect', 'app.settings.integrations.disconnect')}>Disconnect</s-button>
            </div>
          )}
          <small><b>Recent activity</b></small>
          {!a.log.length ? <p className="oo-muted oo-small">Nothing yet.</p> : (
            <ul className="ca-log">{a.log.map((l, i) => <li key={i} className={l.ok ? '' : 'bad'}><span>{l.text}</span><span className="oo-muted">{ago(l.at)}</span></li>)}</ul>
          )}
        </>
      )}
    </div>
  );
}

export default function Integrations({ connection: c, webhooks, apps = [] }) {
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
        <p className="oo-muted">Shopify tells Growvia about these events. Each is verified with Shopify's signature and handled once.</p>
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

      <s-section heading="Growvia extensions">
        <ul className="oo-list">
          <li><strong>Theme app extension</strong>: storefront blocks and the app embed.</li>
          <li><strong>Web pixel</strong>: consent-aware analytics.</li>
          <li><strong>Checkout UI extension</strong>: checkout, Thank You and Order Status blocks.</li>
          <li><strong>Customer account extension</strong>: account blocks and "Buy again".</li>
          <li><strong>Post-purchase extension</strong>: the one-click offer after payment.</li>
          <li><strong>Discount function and cart transform</strong>: bundle prices and offer savings at checkout.</li>
        </ul>
      </s-section>

      {apps.length > 0 && (
        <s-section heading="Connected apps">
          <p className="oo-muted" style={{ marginTop: 0 }}>Connect the apps you already use. Each one shares only what it needs, and disconnecting deletes the keys at once.</p>
          {apps.map((a) => <ConnectedApp key={a.key} app={a} />)}
        </s-section>
      )}
    </Page>
  );
}
