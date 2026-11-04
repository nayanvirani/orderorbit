// Profit › ROAS and POAS (G5): the ad return needed to break even (1 ÷ contribution margin) and how
// much contribution each ad dollar brings, for the store and per tagged product.
import { Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import { money, pct, ProfitNotices, Ranges } from './_profit.jsx';

const n = (v) => (v === null || v === undefined ? '—' : Number(v).toFixed(2));

export default function ProfitRoas({ range, ranges, totals: t, store: s, products, currency, access, syncing, locked }) {
  const verdict = (r) => (r.poas === null ? 'Needs ad spend' : r.poas >= 1 ? 'Ads pay for themselves' : 'Ads cost more than they earn');
  return (
    <Page heading="ROAS and POAS">
      <p className="oo-muted" style={{ marginTop: 0 }}>How much ad return you need to break even, and how much profit each ad dollar really brings.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <Ranges ranges={ranges} range={range} name="app.profit.roas" />
          <div className="pf-kpis">
            <div className="pf-kpi"><small>Break-even ROAS, store</small><b>{n(s.break_even)}</b><em>1 ÷ {pct(t.contribution_margin)} contribution margin. Below this, ads lose money.</em></div>
            <div className="pf-kpi"><small>Actual ROAS, all ads</small><b>{n(s.roas)}</b><em>{s.roas === null ? 'Add your ad spend' : s.break_even ? `${(s.roas / s.break_even).toFixed(1)}× break-even` : 'Net sales ÷ ad spend'}</em></div>
            <div className="pf-kpi"><small>POAS, all ads</small><b>{n(s.poas)}</b><em>{s.poas === null ? 'Add your ad spend' : `Each ${money(1, currency)} of ads brought ${money(s.poas, currency)} of contribution.`}</em></div>
          </div>
          <s-section heading="By product">
            <p className="oo-muted" style={{ marginTop: 0 }}>Tag ad spend to products in <a href={appUrl(route('app.profit.ads'))}>Ad expenses</a> to see each product's POAS.</p>
            <div className="oo-scroll">
              <table className="oo-table">
                <thead><tr><th>Product</th><th>Margin before ads</th><th>Break-even ROAS</th><th>Tagged ad spend</th><th>Actual ROAS</th><th>POAS</th><th>Verdict</th></tr></thead>
                <tbody>
                  {products.map((r) => (
                    <tr key={r.key}>
                      <td>{r.name}</td><td>{pct(r.contribution_margin)}</td><td>{n(r.break_even)}</td><td>{r.tagged_spend ? money(r.tagged_spend, currency) : '—'}</td>
                      <td>{n(r.roas)}</td><td>{n(r.poas)}</td><td>{verdict(r)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="oo-muted pf-small">An ad platform shows ROAS 3.1 for a product? Compare it with that product's break-even ROAS here: above it the campaign makes money, below it every sale costs more in ads than it earns.</p>
          </s-section>
        </>
      )}
    </Page>
  );
}
