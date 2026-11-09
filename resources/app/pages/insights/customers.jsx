// Insights › Customers (G19): how often customers come back and what they're worth over time.
// Built from order data only (customer ids, never names).
import { Page } from '../../components/ui.jsx';
import { money, ProfitNotices } from '../profit/_profit.jsx';

const shade = (v) => (v === null ? 'transparent' : `color-mix(in srgb, var(--ob-accent) ${Math.min(80, 8 + v * 3)}%, transparent)`);

export default function CustomerValue({ summary: s, cohorts = [], best = [], min_buyers: minBuyers, currency, access, locked }) {
  return (
    <Page heading="Customers">
      <p className="oo-muted" style={{ marginTop: 0 }}>How often customers come back and what they&apos;re worth over time. Built from order data only, no names.</p>
      <ProfitNotices access={access} locked={locked} />
      {!access && s && (
        <>
          <div className="pf-kpis">
            <div className="pf-kpi"><small>Repeat rate</small><b>{s.repeat_rate === null ? '—' : `${s.repeat_rate}%`}</b><em>ordered 2+ times in 12 months</em></div>
            <div className="pf-kpi"><small>Time to second order</small><b>{s.second_order_days === null ? '—' : `${s.second_order_days} days`}</b><em>typical, for those who return</em></div>
            <div className="pf-kpi"><small>Customer value, 12 months</small><b>{s.value === null ? '—' : money(s.value, currency)}</b><em>net sales per customer</em></div>
            <div className="pf-kpi"><small>Customers, 12 months</small><b>{s.customers.toLocaleString()}</b><em>with at least one order</em></div>
          </div>

          <s-section heading="Customers still ordering, by month of first order">
            <div className="oo-scroll">
              <table className="oo-table cv-cohorts">
                <thead><tr><th>First order</th><th>Customers</th>{[1, 2, 3, 4, 5, 6].map((k) => <th key={k}>Month {k}</th>)}</tr></thead>
                <tbody>
                  {cohorts.map((c) => (
                    <tr key={c.month}>
                      <td>{c.month}</td><td>{c.customers}</td>
                      {c.cells.map((v, i) => <td key={i} style={{ background: shade(v) }}>{v === null ? '' : `${v}%`}</td>)}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="oo-muted pf-small">Share of each month&apos;s new customers who ordered again in each month after. Blank months haven&apos;t finished yet.</p>
          </s-section>

          <s-section heading="Best first products">
            <p className="oo-muted pf-small" style={{ marginTop: 0 }}>Customers whose first order had these products came back the most (products with at least {minBuyers} first-time buyers).</p>
            {!best.length ? <p className="oo-muted">Not enough first orders yet.</p> : best.map((b) => (
              <div className="cv-best" key={b.name}>
                <span>{b.name}</span>
                <i style={{ width: `${Math.max(4, Math.round((b.return_rate / Math.max(1, best[0].return_rate)) * 100))}%` }} />
                <b>{b.return_rate}% return · {money(b.value, currency)}</b>
              </div>
            ))}
          </s-section>
        </>
      )}
    </Page>
  );
}
