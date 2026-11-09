// Profit › Goals (G6): a monthly goal and whether the month is on track at the current pace.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { route, useRouter, useShared } from '../../router.jsx';
import { money, ProfitNotices } from './_profit.jsx';

function Pacing({ s, fmt }) {
  // x: day of month; y: cumulative amount. Lines: actual so far, projection to month end, goal pace.
  const W = 640; const H = 230; const left = 44; const bottom = 205;
  const top = Math.max(s.goal.amount, s.projected, ...s.cumulative.map((c) => c.total), 1) * 1.1;
  const x = (d) => left + (d / s.days) * (W - left - 12);
  const y = (v) => bottom - (Math.max(0, v) / top) * (bottom - 12);
  const actual = [[0, 0], ...s.cumulative.map((c, i) => [i + 1, c.total])];
  const last = actual[actual.length - 1];
  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="pg-chart" role="img" aria-label={`Pacing: ${fmt(s.actual)} so far, ${fmt(s.projected)} projected, goal ${fmt(s.goal.amount)}`}>
      {[0, 0.5, 1].map((f) => <g key={f}><line x1={left} x2={W - 12} y1={y(top * f)} y2={y(top * f)} className="grid" /><text x={left - 6} y={y(top * f) + 4} textAnchor="end">{fmt(top * f)}</text></g>)}
      <polyline className="goal" points={`${x(0)},${y(0)} ${x(s.days)},${y(s.goal.amount)}`} />
      <polyline className="proj" points={`${x(last[0])},${y(last[1])} ${x(s.days)},${y(s.projected)}`} />
      <polyline className="act" points={actual.map(([d, v]) => `${x(d)},${y(v)}`).join(' ')} />
      <text x={x(s.days)} y={y(s.goal.amount) - 6} textAnchor="end" className="lbl">Goal</text>
    </svg>
  );
}

export default function ProfitGoals({ status: s, goal, types, alerts, currency, access, locked }) {
  const { submit } = useRouter();
  const { can } = useShared();
  const [form, setForm] = useState({ type: goal?.type || 'after_ads', amount: goal?.amount ?? '', alert: goal?.alert || 10 });
  const [busy, setBusy] = useState(false);
  const margin = (s?.goal.type || form.type) === 'margin';
  const fmt = (v) => (v === null || v === undefined ? '—' : margin ? `${v}%` : money(v, currency));
  const save = async () => { setBusy(true); await submit(route('app.profit.goals.save'), form); setBusy(false); };

  return (
    <Page heading="Goals">
      <p className="oo-muted" style={{ marginTop: 0 }}>Set a monthly profit goal and see each day whether the month is on track.</p>
      <ProfitNotices access={access} locked={locked} />
      {!access && (
        <div className="pg-layout">
          <div>
            {!s ? <div className="pt-empty">Set a goal on the right to see how the month is pacing.</div> : (
              <>
                <s-banner tone={s.on_track ? 'success' : s.behind >= s.goal.alert ? 'warning' : 'info'} heading={s.on_track ? 'On track' : `${s.behind}% behind`}>
                  {margin
                    ? `Margin so far is ${fmt(s.actual)} against a ${fmt(s.goal.amount)} goal.`
                    : `At this pace ${s.month} ends at ${fmt(s.projected)}${s.on_track ? `, above the ${fmt(s.goal.amount)} goal.` : `, short of the ${fmt(s.goal.amount)} goal.`}${s.need !== null && !s.on_track ? ` You need about ${fmt(s.need)} a day from here, up from ${fmt(s.per_day)}.` : ''}`}
                </s-banner>
                <div className="pf-kpis" style={{ marginTop: 12 }}>
                  <div className="pf-kpi"><small>{s.month} goal</small><b>{fmt(s.goal.amount)}</b><em>{s.label}</em></div>
                  <div className="pf-kpi"><small>So far ({s.elapsed} {s.elapsed === 1 ? 'day' : 'days'})</small><b>{fmt(s.actual)}</b><em>{s.goal_share !== null ? `${s.goal_share}% of goal, ` : ''}{s.month_share}% of month gone</em></div>
                  {!margin && <div className="pf-kpi"><small>Projected month end</small><b>{fmt(s.projected)}</b><em>{s.projected >= s.goal.amount ? '+' : '−'}{fmt(Math.abs(s.projected - s.goal.amount))} vs goal</em></div>}
                </div>
                {!margin && (
                  <s-section heading="Pacing">
                    <Pacing s={s} fmt={(v) => money(v, currency)} />
                    <p className="pf-small"><span className="pg-key act" /> Actual <span className="pg-key proj" /> Projected <span className="pg-key goal" /> Goal pace</p>
                  </s-section>
                )}
              </>
            )}
          </div>
          <s-section heading="Goal settings">
            <div className="pf-form" style={{ gridTemplateColumns: '1fr' }}>
              <label>Goal type
                <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} disabled={!!locked}>
                  {Object.entries(types).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                </select>
              </label>
              <label>Monthly goal {form.type === 'margin' ? '(%)' : `(${currency})`}
                <input type="number" min="0" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} disabled={!!locked} />
              </label>
              <label>Email me when the month falls behind by
                <select value={form.alert} onChange={(e) => setForm({ ...form, alert: Number(e.target.value) })} disabled={!!locked}>
                  {alerts.map((a) => <option key={a} value={a}>{a}%</option>)}
                </select>
              </label>
            </div>
            {can.manage_settings && !locked && <s-button variant="primary" loading={busy ? '' : undefined} onClick={save}>Save goal</s-button>}
            <p className="oo-muted pf-small">The warning goes at most once a month, from day 7, to people who can see profit. Today isn't counted until it's over.</p>
          </s-section>
        </div>
      )}
    </Page>
  );
}
