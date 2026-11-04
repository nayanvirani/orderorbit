// Profit › Dashboard (G3): profit levels, the waterfall from sales to profit, profit after ads by
// day and products by contribution. Every number says which profit level it is.
import { Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import { money, pct, ProfitNotices, Ranges } from './_profit.jsx';

const change = (now, before) => (before ? Math.round(((now - before) / Math.abs(before)) * 100) : null);

export default function ProfitDashboard({ range, ranges, totals: t, previous: p, daily, products, adsEntered, currency, access, syncing, locked, snapshot }) {
  const m = (v) => money(v, currency);
  const kpis = [
    ['Net sales', t.net_sales, p.net_sales, 'After discounts and refunds, before tax'],
    ['Gross profit', t.gross_profit, p.gross_profit, 'Net sales − product cost'],
    ['Contribution', t.contribution, p.contribution, '− payment fees and shipping'],
    ['Profit after ads', adsEntered ? t.after_ads : null, p.after_ads, adsEntered ? '− ad spend' : 'Add your ad spend'],
  ];
  const steps = [
    ['Net sales', t.net_sales, 'level'], ['Product cost', -t.cogs, 'cost'], ['Gross profit', t.gross_profit, 'level'],
    ['Fees and shipping', -t.fees_and_shipping, 'cost'], ['Contribution', t.contribution, 'level'],
    ['Ad spend', -t.ad_spend, 'cost'], ['Profit after ads', t.after_ads, 'level'], ['Fixed costs', -t.fixed_costs, 'cost'], ['Estimated net', t.estimated_net, 'level'],
  ];
  const top = Math.max(1, ...steps.map((s) => Math.abs(s[1])));
  const peak = Math.max(1, ...daily.map((d) => Math.abs(d.after_ads)));

  return (
    <Page heading="Profit">
      <p className="oo-muted" style={{ marginTop: 0 }}>From your Shopify orders and the costs you've entered. Every number says which profit level it is.{snapshot ? ' Your plan shows the last 30 days.' : ''}</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <div className="pf-bar">
            <Ranges ranges={ranges} range={range} name="app.profit.dashboard" />
            {!snapshot && <s-button href={appUrl(route('app.profit.breakdowns', { range }))}>Open breakdowns</s-button>}
          </div>

          {t.missing_cost_sales > 0 && (
            <s-banner tone="warning">
              {m(t.missing_cost_sales)} of sales ({t.net_sales ? Math.round((t.missing_cost_sales / t.net_sales) * 100) : 0}%) has no product cost. Profit for those items is left out, not counted as $0.
              {' '}<a href={appUrl(route('app.profit.costs'))}>Add missing costs</a>
            </s-banner>
          )}

          <div className="pf-kpis">
            {kpis.map(([label, value, before, level]) => {
              const c = value === null ? null : change(value, before);
              return (
                <div className="pf-kpi" key={label}>
                  <small>{label}</small>
                  <b>{value === null ? '—' : m(value)}</b>
                  {c !== null && <span className={c >= 0 ? 'up' : 'down'}>{c >= 0 ? '+' : ''}{c}% vs before</span>}
                  <em>{level}</em>
                </div>
              );
            })}
            <div className="pf-kpi"><small>Margin after ads</small><b>{pct(t.margin)}</b><em>Profit after ads ÷ net sales</em></div>
          </div>

          <div className="pf-two">
            <s-section heading="From sales to profit">
              <div className="pf-fall">
                {steps.map(([label, value, kind]) => (
                  <div className={`pf-step ${kind}`} key={label}>
                    <span>{label}</span>
                    <i style={{ width: `${Math.round((Math.abs(value) / top) * 100)}%` }} />
                    <b>{kind === 'cost' && value ? '−' : ''}{m(Math.abs(value))}</b>
                  </div>
                ))}
              </div>
              <p className="oo-muted pf-small">Profit levels in bold; costs taken away in between. Fixed costs only come off in Estimated net.</p>
            </s-section>

            <s-section heading="Profit after ads, by day">
              <div className="pf-trend" role="img" aria-label="Profit after ads by day">
                {daily.map((d) => <i key={d.day} title={`${d.day}: ${m(d.after_ads)}`} className={d.after_ads < 0 ? 'neg' : ''} style={{ height: `${Math.max(2, Math.round((Math.abs(d.after_ads) / peak) * 100))}%` }} />)}
              </div>
              <p className="oo-muted pf-small">Average {m(daily.length ? daily.reduce((s, d) => s + d.after_ads, 0) / daily.length : 0)} a day · {daily[0]?.day} to {daily[daily.length - 1]?.day}</p>
            </s-section>
          </div>

          <s-section heading="Products by contribution">
            {!products.length ? <p className="oo-muted">No orders in this range yet.</p> : (
              <table className="oo-table stack">
                <thead><tr><th>Product</th><th>Net sales</th><th>Units</th><th>Contribution</th><th>Margin</th></tr></thead>
                <tbody>
                  {products.map((r) => (
                    <tr key={r.key}>
                      <td data-label="Product">{r.name}{r.missing_cost && <> <s-badge tone="warning">No cost</s-badge></>}</td>
                      <td data-label="Net sales">{m(r.net_sales)}</td>
                      <td data-label="Units">{r.units}</td>
                      <td data-label="Contribution">{m(r.contribution)}</td>
                      <td data-label="Margin">{pct(r.contribution_margin)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            {!snapshot && <p className="pf-small"><a href={appUrl(route('app.profit.breakdowns', { range, by: 'product' }))}>See all products</a></p>}
          </s-section>
        </>
      )}
    </Page>
  );
}
