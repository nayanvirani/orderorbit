// Profit › Breakdowns (G4): profit by product, variant, order, sales channel, country and day,
// with CSV export. Ad spend follows the tags on each expense; untagged spend is shared by sales.
import { Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import { money, pct, ProfitNotices, Ranges } from './_profit.jsx';

const BY = { product: 'Product', variant: 'Variant', order: 'Order', channel: 'Sales channel', country: 'Country', day: 'Day' };

export default function ProfitBreakdowns({ range, ranges, by, rows, totals: t, currency, access, syncing, locked }) {
  const m = (v) => money(v, currency);
  return (
    <Page heading="Profit breakdowns">
      <p className="oo-muted" style={{ marginTop: 0 }}>Every number on the dashboard, split by what made it.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <div className="pf-bar">
            <Ranges ranges={ranges} range={range} name="app.profit.breakdowns" extra={{ by }} />
            <s-button href={appUrl(route('app.profit.breakdowns.export', { range, by }))} target="_blank">Export CSV</s-button>
          </div>
          <nav className="pf-tabs" aria-label="Break down by">
            {Object.entries(BY).map(([k, l]) => <a key={k} href={appUrl(route('app.profit.breakdowns', { range, by: k }))} aria-current={k === by ? 'page' : undefined}>{l}</a>)}
          </nav>
          <s-section>
            <div className="oo-scroll">
              <table className="oo-table">
                <thead><tr><th>{BY[by]}</th><th>Orders</th><th>Net sales</th><th>Product cost</th><th>Fees and shipping</th><th>Ad spend</th><th>Profit after ads</th><th>Margin</th></tr></thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.key}>
                      <td>{r.name}{r.missing_cost && <> <s-badge tone="warning">No cost</s-badge></>}</td>
                      <td>{r.orders}</td><td>{m(r.net_sales)}</td><td>{m(r.cogs)}</td><td>{m(r.fees_and_shipping)}</td><td>{m(r.ad_spend)}</td>
                      <td className={r.after_ads < 0 ? 'pf-neg' : ''}>{m(r.after_ads)}</td><td>{pct(r.margin)}</td>
                    </tr>
                  ))}
                  <tr className="pf-total-row">
                    <td>All</td><td>{t.orders}</td><td>{m(t.net_sales)}</td><td>{m(t.cogs)}</td><td>{m(t.fees_and_shipping)}</td><td>{m(t.ad_spend)}</td><td>{m(t.after_ads)}</td><td>{pct(t.margin)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p className="oo-muted pf-small">Ad spend is split by the products you tag on each expense; untagged spend is shared by sales. Items without a cost count in sales but are left out of product cost.</p>
          </s-section>
        </>
      )}
    </Page>
  );
}
