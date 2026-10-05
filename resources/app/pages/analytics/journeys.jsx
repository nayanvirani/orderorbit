import { ago, date, money, number, Page, Pagination } from '../../components/ui.jsx';
import { appUrl, route, useUrl, withQuery } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';

export default function Journeys(props) {
  const { people, buyers, days, currency } = props;
  const url = useUrl();
  return (
    <Page heading="Customer journeys" back={route('app.analytics', { days })} backLabel="Analytics">
      <AnalyticsNav {...props} />
      {people && (
        <s-section>
          <nav className="bx-tabs" aria-label="Shoppers">
            <a href={appUrl(withQuery(url, { all: null, page: null }))} className={buyers ? 'on' : ''}>Shoppers who bought</a>
            <a href={appUrl(withQuery(url, { all: 1, page: null }))} className={buyers ? '' : 'on'}>All shoppers</a>
          </nav>
          {!people.data.length ? (
            <s-paragraph><span className="oo-muted">No shoppers in this period yet. Journeys build up from the visits and orders the pixel records.</span></s-paragraph>
          ) : (
            <>
              <table className="oo-table stack">
                <thead><tr><th>Shopper</th><th>First seen</th><th>Last active</th><th>Sessions</th><th>Orders</th><th>Revenue</th><th>Source</th></tr></thead>
                <tbody>
                  {people.data.map((p) => (
                    <tr key={p.visitor_id}>
                      <td data-label="Shopper"><s-link href={appUrl(route('app.analytics.journey', { visitor: p.visitor_id }))}>{p.name}</s-link>{p.repeat && <> <s-badge tone="success">Repeat</s-badge></>}</td>
                      <td data-label="First seen">{date(p.first_seen)}</td>
                      <td data-label="Last active">{ago(p.last_seen)}</td>
                      <td data-label="Sessions">{number(p.sessions)}</td>
                      <td data-label="Orders">{number(p.orders)}</td>
                      <td data-label="Revenue">{p.orders ? money(p.revenue, currency) : '—'}</td>
                      <td data-label="Source">{p.source || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <Pagination paginator={people} url={url} />
            </>
          )}
          <p className="oo-muted oo-small">Shoppers are identified by Shopify's anonymous visitor id, and by customer number once they sign in or buy. No names, emails or addresses are stored. A customer's data is deleted when Shopify asks (customer redaction).</p>
        </s-section>
      )}
    </Page>
  );
}
