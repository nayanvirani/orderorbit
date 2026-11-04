// Insights › Sales (G14): the same numbers as the Profit dashboard, so the two always agree.
import { Page } from '../../components/ui.jsx';
import { money, ProfitNotices, Ranges } from '../profit/_profit.jsx';

const delta = (now, before, points) => {
  if (now === null || before === null || before === undefined) return null;
  if (points) return `${now - before >= 0 ? '+' : '−'}${Math.abs(now - before)} pts`;
  return before ? `${now >= before ? '+' : '−'}${Math.abs(Math.round(((now - before) / before) * 100))}%` : null;
};

export default function SalesInsights({ range, ranges, currency, now, before, weeks, best, worst, countries, access, locked }) {
  const m = (v) => money(v, currency);
  const top = Math.max(1, ...weeks.map((w) => w.new + w.returning));
  const kpis = [
    ['Net sales', m(now.net_sales), delta(now.net_sales, before.net_sales)],
    ['Orders', now.orders, delta(now.orders, before.orders)],
    ['Average order', now.aov === null ? '—' : m(now.aov), now.aov !== null && before.aov !== null ? `${now.aov >= before.aov ? '+' : '−'}${m(Math.abs(now.aov - before.aov))}` : null],
    ['Returning customers', now.returning === null ? '—' : `${now.returning}%`, delta(now.returning, before.returning, true)],
  ];
  return (
    <Page heading="Sales insights">
      <p className="oo-muted" style={{ marginTop: 0 }}>Uses the same numbers as the Profit dashboard, so the two always agree.</p>
      <ProfitNotices access={access} locked={locked} />
      {!access && (
        <>
          <Ranges ranges={ranges} range={range} name="app.insights.sales" />
          <div className="pf-kpis">
            {kpis.map(([l, v, d]) => <div className="pf-kpi" key={l}><small>{l}</small><b>{v}</b>{d && <span className={d.startsWith('−') ? 'down' : 'up'}>{d} vs before</span>}</div>)}
          </div>
          <s-section heading="Weekly sales, new and returning customers">
            <div className="si-weeks" role="img" aria-label="Weekly net sales by new and returning customers">
              {weeks.map((w) => (
                <div className="si-week" key={w.week} title={`Week of ${w.week}: ${m(w.new)} new, ${m(w.returning)} returning`}>
                  <i className="ret" style={{ height: `${(w.returning / top) * 100}%` }} />
                  <i className="new" style={{ height: `${(w.new / top) * 100}%` }} />
                </div>
              ))}
            </div>
            <p className="pf-small"><span className="si-key new" /> New <span className="si-key ret" /> Returning · {weeks[0]?.week} to week of {weeks[weeks.length - 1]?.week}</p>
          </s-section>
          <div className="pf-two">
            <s-section heading="Most profit">
              {best.map((p) => <div className="pf-row" key={p.key}><span>{p.name}</span><b>{m(p.after_ads)}</b></div>)}
              {!best.length && <p className="oo-muted">No sales in this range.</p>}
            </s-section>
            <s-section heading="Least profit">
              {worst.map((p) => <div className="pf-row" key={p.key}><span>{p.name}</span><b className={p.after_ads < 0 ? 'pf-neg' : ''}>{m(p.after_ads)}</b></div>)}
              {!worst.length && <p className="oo-muted">No sales in this range.</p>}
            </s-section>
          </div>
          <s-section heading="Sales by country">
            {countries.map((c) => <div className="pf-row" key={c.name}><span>{c.name}</span><b>{m(c.sales)} · {c.share}%</b></div>)}
            {!countries.length && <p className="oo-muted">No sales in this range.</p>}
          </s-section>
        </>
      )}
    </Page>
  );
}
