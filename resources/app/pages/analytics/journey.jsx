import { KeyValue, money, Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';

const ICONS = { purchase: '●', automation: '⚙', experience: '★', cart: '🛒', browse: '·' };
const utc = (v, opts) => (v ? new Date(v).toLocaleString('en-US', { timeZone: 'UTC', ...opts }) : '—');
const dayTime = (v) => utc(v, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export default function Journey(props) {
  const { journey: j, visitor, experiences, pageTypes } = props;
  const m = (v) => money(v, j.currency);
  return (
    <Page heading={j.customer ? `Customer ${j.customer}` : `Visitor ${visitor.slice(0, 8)}`} back={route('app.analytics.journeys')} backLabel="Customer journeys">
      <AnalyticsNav {...props} hideRange />
      <s-section heading="Summary">
        <KeyValue rows={[
          ['First seen', `${dayTime(j.first_seen)} UTC`],
          ['Orders', `${j.orders}${j.orders > 1 ? ' (repeat customer)' : ''}`],
          ['Revenue', m(j.revenue)],
          ['Sessions', j.sessions.filter((s) => !s.automation).length],
          j.visitors.length > 1 && ['Devices or browsers', j.visitors.length],
          j.customer && ['Customer', <s-link href={`shopify://admin/customers/${j.customer}`} target="_top">Open in Shopify</s-link>],
        ]} />
      </s-section>

      <s-section heading="Journey">
        <ol className="an-journey">
          {j.sessions.map((session, si) => (
            <li key={si} className={`an-session ${session.automation ? 'is-auto' : ''}`}>
              <div className="an-session-head">
                <strong>{session.automation ? 'Automation' : 'Visit'}</strong>
                <span className="oo-muted">{dayTime(session.started)}{session.source && ` · from ${session.source}`}{session.device && ` · ${session.device}`}</span>
              </div>
              <ul>
                {session.events.map((e, ei) => (
                  <li key={ei} className={`k-${e.kind}`}>
                    <span className="an-dot" aria-hidden="true">{ICONS[e.kind]}</span>
                    <span className="an-time">{utc(e.at, { hour: '2-digit', minute: '2-digit', hour12: false })}</span>
                    <span>
                      {e.order_number ? <><b>{e.order_number > 1 ? `Repeat purchase (order ${e.order_number})` : 'First purchase'}</b> · {m(e.value)}</> : e.label}
                      {e.experience && <> · <span className="oo-muted">{experiences[e.experience]?.name || e.experience}</span></>}
                      {e.detail && !e.order_number && <> · <span className="oo-muted">{e.detail}</span></>}
                      {e.name === 'page_viewed' && e.page_type && <> · <span className="oo-muted">{pageTypes[e.page_type] || e.page_type}</span></>}
                      {e.run_id && <> · <s-link href={appUrl(route('app.automation.runs.show', { run: e.run_id }))}>Run #{e.run_id}</s-link></>}
                    </span>
                  </li>
                ))}
              </ul>
            </li>
          ))}
        </ol>
        <p className="oo-muted oo-small">Only visits where the shopper allowed analytics are shown. Times are UTC.</p>
      </s-section>
    </Page>
  );
}
