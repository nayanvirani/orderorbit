import { ActionButton } from '../../components/form.jsx';
import { Hero, Kpi, Page, Upgrade, date, money, number, plural } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';

export default function AnalyticsOverview(props) {
  const { summary: s, trends: t, offers, connected, error, offerAnalytics } = props;
  const m = (v) => money(v, s.currency);
  const daily = Object.entries(s.daily);
  const max = Math.max(1, ...daily.map(([, v]) => v.revenue));
  const diff = s.aov && s.influenced_aov ? s.influenced_aov - s.aov : null;

  return (
    <Page heading="Analytics">
      <Hero eyebrow="Analytics" icon="chart" tone="analytics" title="What your offers <em>earn.</em>" lead="Revenue, orders and conversion from your store, and how much of it came from lines your bundles, gifts and upsells added." />

      {!connected ? (
        <s-banner tone="warning" heading="Analytics isn't connected yet">
          <s-paragraph>{error ? `Shopify said: ${error}` : 'Connect the Growvia pixel to start recording visits and orders.'} If the app asks for permissions, approve them and try again.</s-paragraph>
          <ActionButton slot="secondary-actions" url={route('app.analytics.connect')}>Connect analytics</ActionButton>
        </s-banner>
      ) : !s.last_event_at && (
        <s-banner tone="info" heading="Connected — waiting for the first visit">
          <s-paragraph>Numbers appear after shoppers visit your store. Orders placed before analytics was connected aren't included. Shopify only sends data for shoppers who allow analytics.</s-paragraph>
        </s-banner>
      )}

      <AnalyticsNav {...props} locked={false} />

      <s-section heading={`Growvia impact · vs previous ${s.days} days`}>
        <div className="ob-kpis">
          <Kpi label="Revenue from offers" icon="sparkle" tone="analytics" value={m(s.influenced_revenue)} trend={t.influenced_revenue} sub="Lines added by bundles, gifts and upsells" spark={daily.map(([, v]) => v.influenced)} />
          <Kpi label="Orders with an offer" icon="cart" tone="bundles" value={number(s.influenced_orders)} trend={t.influenced_orders} sub={s.orders ? `${Math.round((s.influenced_orders / s.orders) * 100)}% of orders` : 'No orders yet'} />
          <Kpi label="AOV with an offer" icon="target" tone="gifts" value={m(s.influenced_aov)} sub={diff !== null ? `${diff >= 0 ? '+' : '−'}${m(Math.abs(diff))} vs all orders` : 'Average order value'} />
        </div>
      </s-section>

      <s-section heading="Store">
        <div className="ob-kpis">
          <Kpi label="Revenue" icon="chart" tone="upsells" value={m(s.revenue)} trend={t.revenue} sub={`${number(s.orders)} ${plural('order', s.orders)}`} />
          <Kpi label="AOV" icon="cart" tone="gifts" value={m(s.aov)} trend={t.aov} sub="Average order value" />
          <Kpi label="Conversion rate" icon="target" tone="countdown" value={s.conversion === null ? '—' : `${s.conversion.toFixed(2)}%`} trend={t.conversion} sub={`${number(s.sessions)} ${plural('session', s.sessions)}`} />
        </div>
        <div className="an-chart" role="img" aria-label="Daily revenue, with the part from offers highlighted">
          {daily.map(([day, v]) => (
            <span key={day} title={`${date(day)}: ${m(v.revenue)} (${m(v.influenced)} from offers)`}>
              <i style={{ height: `${(v.revenue / max) * 100}%` }}><b style={{ height: `${v.revenue ? Math.min(100, (v.influenced / v.revenue) * 100) : 0}%` }} /></i>
            </span>
          ))}
        </div>
        <p className="an-legend"><span><i className="all" />Revenue</span><span><i className="oo" />From offers</span></p>
      </s-section>

      <s-section heading="By offer">
        {!offerAnalytics ? (
          <Upgrade feature="offer_analytics">Your plan shows store totals. Upgrade to see views, adds to cart, orders and revenue for each bundle, gift and upsell.</Upgrade>
        ) : !offers.length ? (
          <s-paragraph><span className="oo-muted">Views, adds to cart and revenue for each bundle, gift and upsell appear here.</span></s-paragraph>
        ) : (
          <div className="oo-scroll">
            <table className="oo-table stack">
              <thead><tr><th>Offer</th><th>Type</th><th>Views</th><th>Added to cart</th><th>Rewards unlocked</th><th>Upsell take rate</th><th>Orders</th><th>Revenue</th><th>Conversion</th></tr></thead>
              <tbody>
                {offers.map((r) => (
                  <tr key={r.handle}>
                    <td data-label="Offer">{r.experience ? <s-link href={appUrl(route('app.cro.experiences.show', { experience: r.experience.id }))}>{r.experience.name}</s-link> : <span className="oo-muted">{r.handle}</span>}</td>
                    <td data-label="Type">{r.experience ? r.experience.type : 'Removed'}</td>
                    <td data-label="Views">{number(r.views)}</td>
                    <td data-label="Added to cart">{number(r.adds)}</td>
                    <td data-label="Rewards unlocked">{r.unlocks ? number(r.unlocks) : '—'}</td>
                    <td data-label="Upsell take rate">{r.accepts + r.declines ? `${Math.round((r.accepts / (r.accepts + r.declines)) * 100)}%` : '—'}</td>
                    <td data-label="Orders">{number(r.orders)}</td>
                    <td data-label="Revenue">{m(r.revenue)}</td>
                    <td data-label="Conversion">{r.views ? `${((r.orders / r.views) * 100).toFixed(1)}%` : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </s-section>
    </Page>
  );
}
