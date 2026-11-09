// Insights › Monthly report (G15): a month's sales, profit, margin and orders against the month
// before, what Growvia's offers earned, top products by profit and 3 things to do next. Emailed
// with a PDF on the 1st; the same numbers as the Profit dashboard for those dates.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';
import { download, money, ProfitNotices } from '../profit/_profit.jsx';

export default function MonthlyReport({ report: r, access, locked }) {
  const { visit, submit } = useRouter();
  const [busy, setBusy] = useState(null);
  const m = (v) => money(v, r.currency);
  const value = (k) => (k.value === null ? '—' : k.kind === 'money' ? m(k.value) : k.kind === 'percent' ? `${k.value}%` : Number(k.value).toLocaleString());
  const run = async (key, fn) => { setBusy(key); await fn(); setBusy(null); };

  return (
    <Page heading="Monthly report">
      <p className="oo-muted" style={{ marginTop: 0 }}>Emailed on the 1st with a PDF to people who can see profit. The numbers match the Profit dashboard for the same dates.</p>
      <ProfitNotices access={access} locked={locked} />
      {!access && (
        <>
          <div className="pf-bar">
            <label className="mr-month">
              <span>Report for</span>
              <select value={r.month} onChange={(e) => visit(route('app.insights.report', { month: e.target.value }))}>
                {Object.entries(r.months).map(([k, label]) => <option key={k} value={k}>{label}</option>)}
              </select>
            </label>
            {!locked && (
              <>
                <s-button loading={busy === 'email' ? '' : undefined} onClick={() => run('email', () => submit(route('app.insights.report.email'), { month: r.month }))}>Email to me</s-button>
                <s-button loading={busy === 'pdf' ? '' : undefined} onClick={() => run('pdf', () => download(route('app.insights.report.pdf', { month: r.month }), `growvia-report-${r.month}.pdf`))}>Download PDF</s-button>
              </>
            )}
          </div>

          <s-section>
            <div className="mr-head">
              <small>{r.store} · Monthly Business Report</small>
              <h2>{r.label}</h2>
              <p>{r.summary}</p>
            </div>
            <div className="pf-kpis">
              {r.kpis.map((k) => (
                <div className="pf-kpi" key={k.label}>
                  <small>{k.label}</small><b>{value(k)}</b>
                  {k.change && <span className={k.change.startsWith('−') ? 'down' : 'up'}>{k.change}</span>}
                </div>
              ))}
            </div>
          </s-section>

          <div className="pf-two">
            <s-section heading="What Growvia's offers earned">
              {!r.widgets.length ? <p className="oo-muted">No offer sales recorded this month.</p> : (
                <>
                  {r.widgets.map((w) => <div className="pf-row" key={w.name}><span>{w.name}</span><b>{m(w.value)}</b></div>)}
                  <div className="pf-row mr-total"><span>Total from offers</span><b>{m(r.widget_total)}{r.widget_share !== null ? ` · ${r.widget_share}% of sales` : ''}</b></div>
                </>
              )}
            </s-section>
            <s-section heading="Top products by profit">
              {!r.top.length ? <p className="oo-muted">Add product costs to see profit by product.</p>
                : r.top.map((t) => <div className="pf-row" key={t.name}><span>{t.name}</span><b>{m(t.value)}{t.margin !== null ? ` · ${t.margin}% margin` : ''}</b></div>)}
            </s-section>
          </div>

          <s-section heading={`${r.problems} open ${r.problems === 1 ? 'problem' : 'problems'} · 3 things to do in ${r.next}`}>
            {!r.todo.length ? <p className="oo-muted">Nothing open right now.</p> : (
              <ol className="mr-todo">
                {r.todo.map((t) => <li key={t.title}><b>{t.title}</b><span>{t.detail}</span></li>)}
              </ol>
            )}
          </s-section>

          <p className="oo-muted pf-small">
            Numbers match the Profit dashboard for {r.from} to {r.to}.
            {r.missing_share > 0 ? ` ${r.missing_share}% of sales had no product cost and is left out of profit.` : ''}
            {' '}Offer sales are the order lines Growvia&apos;s offers added.
          </p>
        </>
      )}
    </Page>
  );
}
