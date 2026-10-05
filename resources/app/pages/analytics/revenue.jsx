import { Kpi, money, number, Page, plural } from '../../components/ui.jsx';
import { appUrl, route, useUrl, withQuery } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';

function RevenueTable({ rows, label, total, m }) {
  const entries = Object.entries(rows || {});
  if (!entries.length) return <s-paragraph><span className="oo-muted">No orders in this period.</span></s-paragraph>;
  return (
    <table className="oo-table stack an-break">
      <thead><tr><th>{label}</th><th>Orders</th><th>Revenue</th><th>Share</th><th className="an-bar-col" /></tr></thead>
      <tbody>
        {entries.map(([key, row]) => (
          <tr key={key}>
            <td data-label={label}>{key === 'unknown' ? 'Unknown (before source tracking)' : key}</td>
            <td data-label="Orders">{number(row.orders)}</td>
            <td data-label="Revenue">{m(row.revenue)}</td>
            <td data-label="Share">{total ? Math.round((row.revenue / total) * 100) : 0}%</td>
            <td className="an-bar-col"><span className="an-bar"><i style={{ width: `${total ? (row.revenue / total) * 100 : 0}%` }} /></span></td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

export default function Revenue(props) {
  const { report: r, window: win, model, models, windows, templateNames, tests, experiences, days, currency } = props;
  const url = useUrl();
  const m = (v) => money(v, r?.currency || currency);

  return (
    <Page heading="Revenue & attribution" back={route('app.analytics', { days })} backLabel="Analytics">
      <AnalyticsNav {...props} />
      {r && (
        <>
          <div className="an-controls">
            <span className="oo-muted">Traffic source model</span>
            <nav className="bx-tabs">{Object.entries(models).map(([k, l]) => <a key={k} href={appUrl(withQuery(url, { model: k }))} className={model === k ? 'on' : ''}>{l}</a>)}</nav>
            <span className="oo-muted">Attribution window</span>
            <nav className="bx-tabs">{Object.entries(windows).map(([k, l]) => <a key={k} href={appUrl(withQuery(url, { window: k }))} className={String(win) === k ? 'on' : ''}>{l}</a>)}</nav>
            <s-button href={appUrl(withQuery(url, { export: 'csv' }))} target="_blank" variant="tertiary">Export CSV</s-button>
          </div>

          <s-section heading="Overview">
            <div className="ob-kpis">
              <Kpi label="Revenue" icon="chart" tone="upsells" value={m(r.revenue)} sub={`${number(r.orders)} ${plural('order', r.orders)}`} />
              <Kpi label="AOV" icon="cart" tone="gifts" value={m(r.aov)} sub="Average order value" />
              <Kpi label="Revenue per session" icon="target" tone="countdown" value={m(r.per_session)} sub={`${number(r.sessions)} sessions`} />
              <Kpi label="Revenue per visitor" icon="users" tone="analytics" value={m(r.per_visitor)} sub={`${number(r.visitors)} visitors`} />
              <Kpi label="Orders influenced" icon="sparkle" tone="bundles" value={number(r.influenced_orders)} sub={`${m(r.influenced_revenue)} in orders where shoppers used an offer within ${windows[win]}`} />
            </div>
          </s-section>

          <s-section heading={`By traffic source · ${models[model]}`}>
            <p className="oo-muted oo-small">{model === 'first' ? `Each order goes to the source of the shopper's first visit within the ${windows[win]} before it.` : 'Each order goes to the source of the visit it was placed in.'} Sources come from UTM tags, then the referring site; visits with neither are "direct".</p>
            <RevenueTable rows={r.by_source} label="Source" total={r.revenue} m={m} />
          </s-section>

          {Object.keys(r.by_campaign || {}).length > 0 && (
            <s-section heading={`By UTM campaign · ${models[model]}`}><RevenueTable rows={r.by_campaign} label="Campaign" total={r.revenue} m={m} /></s-section>
          )}

          <s-section heading="By widget">
            <p className="oo-muted oo-small"><b>Direct</b>: the order lines a widget added (bundles, gifts, upsells). <b>Assisted</b>: orders from shoppers who saw or used it within {windows[win]} before buying; each experience gets the whole order, so assisted totals overlap.</p>
            {!Object.keys(r.by_experience || {}).length ? <s-paragraph><span className="oo-muted">No orders with a widget in this period.</span></s-paragraph> : (
              <div className="oo-scroll">
                <table className="oo-table stack">
                  <thead><tr><th>Widget</th><th>Direct orders</th><th>Direct revenue</th><th>Assisted orders</th><th>Assisted revenue</th></tr></thead>
                  <tbody>
                    {Object.entries(r.by_experience).map(([handle, row]) => (
                      <tr key={handle}>
                        <td data-label="Experience">{experiences[handle] ? <s-link href={appUrl(route('app.cro.experiences.show', { experience: experiences[handle].id }))}>{experiences[handle].name}</s-link> : <span className="oo-muted">{handle}</span>}</td>
                        <td data-label="Direct orders">{number(row.direct_orders)}</td>
                        <td data-label="Direct revenue">{m(row.direct_revenue)}</td>
                        <td data-label="Assisted orders">{number(row.assisted_orders)}</td>
                        <td data-label="Assisted revenue">{m(row.assisted_revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </s-section>

          {Object.keys(r.by_template || {}).length > 0 && (
            <s-section heading="By template">
              <table className="oo-table stack">
                <thead><tr><th>Template</th><th>Widgets</th><th>Direct revenue</th><th>Assisted revenue</th></tr></thead>
                <tbody>
                  {Object.entries(r.by_template).map(([key, row]) => (
                    <tr key={key}>
                      <td data-label="Template">{templateNames[key] || key}</td>
                      <td data-label="Experiences">{row.experiences}</td>
                      <td data-label="Direct revenue">{m(row.direct_revenue)}</td>
                      <td data-label="Assisted revenue">{m(row.assisted_revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </s-section>
          )}

          <s-section heading="By experiment variant">
            {!tests.length ? (
              <s-paragraph><span className="oo-muted">No A/B tests ran in this period. <s-link href={appUrl(route('app.experiments.index'))}>Create a test</s-link></span></s-paragraph>
            ) : (
              <table className="oo-table stack">
                <thead><tr><th>Test</th><th>Variant</th><th>Visitors</th><th>Orders</th><th>Revenue</th><th>Revenue per visitor</th></tr></thead>
                <tbody>
                  {tests.flatMap((t) => t.variants.map((v, i) => (
                    <tr key={`${t.id}-${v.key}`}>
                      <td data-label="Test">{i === 0 && <s-link href={appUrl(route('app.experiments.show', { experiment: t.id }))}>{t.name}</s-link>}</td>
                      <td data-label="Variant">{v.key} · {v.name}{v.winner && <> <s-badge tone="success">Winner</s-badge></>}</td>
                      <td data-label="Visitors">{number(v.visitors)}</td>
                      <td data-label="Orders">{number(v.orders)}</td>
                      <td data-label="Revenue">{m(v.revenue)}</td>
                      <td data-label="Revenue per visitor">{m(v.revenue_per_visitor)}</td>
                    </tr>
                  )))}
                </tbody>
              </table>
            )}
          </s-section>
          <p className="oo-muted oo-small">Attribution is an analytical model, not proof that an offer or channel caused a sale. Orders count when the shopper allowed analytics.</p>
        </>
      )}
    </Page>
  );
}
