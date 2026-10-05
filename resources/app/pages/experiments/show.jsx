import { ActionButton } from '../../components/form.jsx';
import { date, dateTime, KeyValue, money, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import TestStatus from './_status.jsx';

const COLORS = { A: '#8a8a8a', B: '#6d5df6', C: '#0c7a43' };
const STATE = { winner: ['success', 'Winner declared'], control: ['info', 'The control wins'], no_winner: ['info', 'No clear winner'], guardrail: ['warning', 'Guardrail breached'], collecting: ['info', 'Collecting data'] };
const PRIMARY_LABEL = { conversion_rate: 'conversion', click_rate: 'clicks', revenue_per_visitor: 'revenue / visitor' };
const signed = (v, d = 1) => `${v >= 0 ? '+' : ''}${Number(v).toFixed(d)}`;
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

export default function ExperimentResults({ experiment: x, results: r, primary, labels, logs, currency, docsUrl, error }) {
  const { can } = useShared();
  const d = r.decision;
  const m = (v) => money(v, currency);
  const pct = (v, digits = 2) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(digits)}%`);
  const [tone, title] = STATE[d.state];
  const variantCount = Object.keys(r.variants).length;
  const active = ['running', 'paused'].includes(x.status);
  const act = (action, extra = {}) => route('app.experiments.action', { experiment: x.id, action, ...extra });
  const series = r.daily || {};
  const all = Object.values(series).flatMap((p) => Object.values(p));
  const top = Math.max(0.1, ...all);
  const devices = [...new Set(Object.values(r.variants).flatMap((v) => Object.keys(v.devices || {})))].sort();
  const lift = (c) => (!c || c.lift === null || c.lift === undefined ? '—' : <>{signed(c.lift)}% <span className="oo-muted oo-small">({signed(c.lift_low)} to {signed(c.lift_high)})</span></>);

  return (
    <Page heading={x.name} back={route('app.experiments.index', { tab: active ? 'active' : 'completed' })} backLabel="A/B tests">
      {error && <s-banner tone="critical">{error}</s-banner>}
      <s-banner tone={tone} heading={title}>
        <s-paragraph>{d.headline}</s-paragraph>
        {d.state === 'collecting' && <span className="oo-meter" style={{ display: 'block', maxWidth: 320 }}><i style={{ width: `${Math.round(d.progress * 100)}%` }} /></span>}
      </s-banner>

      <s-section heading="Summary">
        <KeyValue rows={[
          ['Status', <TestStatus status={x.status} />],
          ['Widget', <s-link href={appUrl(route('app.cro.experiences.show', { experience: x.experience_id }))}>{x.experience}</s-link>],
          x.hypothesis && ['Hypothesis', x.hypothesis],
          ['Primary metric', x.primary_label],
          ['Duration', `${x.started_at ? `${Math.floor(r.days)} of at least ${x.min_days} days · since ${date(x.started_at)}` : 'Not started'}${x.ends_at ? ` · ends ${date(x.ends_at)}` : ''}`],
          ['Minimum sample', `${number(x.min_visitors)} visitors and ${number(x.min_conversions)} conversions per variant`],
          ['Confidence', `${Math.round(r.confidence * 1000) / 10}% per comparison${variantCount > 2 ? ` (95% with the Bonferroni correction for ${variantCount - 1} comparisons)` : ''}`],
          x.audience && ['Audience', x.audience],
        ]} />
        {can.manage_experiences && (
          <div className="oo-inline" style={{ marginTop: 12 }}>
            {x.status === 'running' && <ActionButton url={act('pause')}>Pause</ActionButton>}
            {x.status === 'paused' && (
              <>
                <ActionButton variant="primary" url={act('resume')}>Resume</ActionButton>
                <s-button href={appUrl(route('app.experiments.edit', { experiment: x.id }))}>Edit setup</s-button>
              </>
            )}
            {active && <ActionButton tone="critical" url={act('stop')} confirm="Stop this test? Everyone will see the widget as published.">Stop test</ActionButton>}
            {d.winner && d.winner !== 'A' && ['running', 'paused', 'completed', 'stopped'].includes(x.status) && (
              <ActionButton variant="primary" url={act('apply')} data={{ variant: d.winner }} confirm={`Publish variant ${d.winner} to the widget for everyone?`}>Apply winner ({d.winner})</ActionButton>
            )}
            <s-button href={appUrl(route('app.experiments.export', { experiment: x.id }))} target="_blank">Export CSV</s-button>
            <s-button href={`${docsUrl}#results`} target="_blank" variant="tertiary">How to read results</s-button>
            <ActionButton variant="tertiary" url={route('app.experiments.duplicate', { experiment: x.id })}>Duplicate as new test</ActionButton>
          </div>
        )}
      </s-section>

      <s-section heading="Variants">
        <div className="oo-scroll">
          <table className="oo-table stack xp-table">
            <thead><tr><th>Variant</th><th>Traffic</th><th>Visitors</th><th>Conversions</th><th>Conversion rate</th><th>Revenue</th><th>Revenue / visitor</th><th>AOV</th>{primary === 'click_rate' && <th>Click-through</th>}<th>Lift vs control ({PRIMARY_LABEL[primary]})</th><th>p-value</th></tr></thead>
            <tbody>
              {x.variants.map((v) => {
                const vm = r.variants[v.key];
                const c = r.comparisons?.[v.key]?.[primary];
                return (
                  <tr key={v.key} className={d.winner === v.key ? 'xp-win' : ''}>
                    <td data-label="Variant"><span className="xp-dot" style={{ background: COLORS[v.key] }} /><strong>{v.key}</strong> · {v.name}{v.hidden && <> <s-badge>Holdout</s-badge></>}{d.winner === v.key && <> <s-badge tone="success">Winner</s-badge></>}</td>
                    <td data-label="Traffic">{v.allocation}%</td>
                    <td data-label="Visitors">{number(vm.visitors)}</td>
                    <td data-label="Conversions">{number(vm.conversions)}</td>
                    <td data-label="Conversion rate">{pct(vm.conversion_rate)}</td>
                    <td data-label="Revenue">{m(vm.revenue)}</td>
                    <td data-label="Revenue / visitor">{m(vm.revenue_per_visitor)}</td>
                    <td data-label="AOV">{m(vm.aov)}</td>
                    {primary === 'click_rate' && <td data-label="Click-through">{pct(vm.click_rate)}</td>}
                    <td data-label="Lift">{v.key === 'A' ? <span className="oo-muted">Baseline</span> : lift(c)}</td>
                    <td data-label="p-value">{v.key === 'A' ? '—' : c?.p_label ?? '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <p className="oo-muted oo-small">Lift is relative to the control, with its {Math.round(r.confidence * 1000) / 10}% confidence interval in brackets. A visitor counts for the variant they first saw; their orders after that count for it.</p>
      </s-section>

      <s-section heading="Conversion rate over time (cumulative)">
        {all.reduce((a, b) => a + b, 0) === 0 ? <s-paragraph><span className="oo-muted">The chart fills in as visitors convert.</span></s-paragraph> : (
          <>
            <svg className="xp-chart" viewBox="0 0 600 160" role="img" aria-label="Cumulative conversion rate by variant">
              {Object.entries(series).map(([key, points]) => {
                const vals = Object.values(points);
                const n = Math.max(1, vals.length - 1);
                return <polyline key={key} fill="none" stroke={COLORS[key]} strokeWidth="2.5" points={vals.map((y, i) => `${((i / n) * 600).toFixed(1)},${(155 - (y / top) * 145).toFixed(1)}`).join(' ')} />;
              })}
            </svg>
            <p className="an-legend">{Object.keys(series).map((key) => <span key={key}><i style={{ background: COLORS[key] }} />{key}</span>)} <span className="oo-muted">Top: {top.toFixed(2)}%</span></p>
          </>
        )}
      </s-section>

      {x.secondary_metrics.length > 0 && (
        <s-section heading="Secondary metrics">
          <table className="oo-table stack">
            <thead><tr><th>Metric</th>{x.variants.map((v) => <th key={v.key}>{v.key}</th>)}</tr></thead>
            <tbody>
              {x.secondary_metrics.map((metric) => (
                <tr key={metric}>
                  <td data-label="Metric">{labels.secondary[metric] || metric}</td>
                  {x.variants.map((v) => {
                    const value = r.variants[v.key]?.[metric];
                    const c = r.comparisons?.[v.key]?.aov;
                    return (
                      <td key={v.key} data-label={v.key}>
                        {metric === 'aov' ? m(value) : metric === 'units_per_order' ? (value === null || value === undefined ? '—' : Number(value).toFixed(2)) : pct(value, 1)}
                        {metric === 'aov' && v.key !== 'A' && c && <span className="oo-muted oo-small"> p {c.p_label}</span>}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </s-section>
      )}

      {x.guardrails.length > 0 && (
        <s-section heading="Guardrails">
          <table className="oo-table stack">
            <thead><tr><th>Guardrail</th><th>Control</th>{x.variants.filter((v) => v.key !== 'A').map((v) => <th key={v.key}>{v.key}</th>)}</tr></thead>
            <tbody>
              {x.guardrails.map((g) => (
                <tr key={g.metric}>
                  <td data-label="Guardrail">{labels.guardrails[g.metric] || g.metric} <span className="oo-muted oo-small">(max +{g.threshold} pts)</span></td>
                  <td data-label="Control">{pct(r.variants.A?.[g.metric], 1)}</td>
                  {x.variants.filter((v) => v.key !== 'A').map((v) => {
                    const gr = r.comparisons?.[v.key]?.guardrails?.[g.metric];
                    return <td key={v.key} data-label={v.key}>{pct(r.variants[v.key]?.[g.metric], 1)} {gr?.breached ? <s-badge tone="critical">Breached</s-badge> : gr && gr.change !== null ? <s-badge tone="success">OK</s-badge> : null}</td>;
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </s-section>
      )}

      <s-section heading="By device">
        {!devices.length ? <s-paragraph><span className="oo-muted">No visitors yet.</span></s-paragraph> : (
          <table className="oo-table stack">
            <thead><tr><th>Device</th>{x.variants.map((v) => <th key={v.key}>{v.key}: visitors · conversion</th>)}</tr></thead>
            <tbody>
              {devices.map((device) => (
                <tr key={device}>
                  <td data-label="Device">{cap(device)}</td>
                  {x.variants.map((v) => {
                    const dv = r.variants[v.key]?.devices?.[device] || { visitors: 0, conversions: 0 };
                    return <td key={v.key} data-label={v.key}>{number(dv.visitors)} · {dv.visitors ? `${((dv.conversions / dv.visitors) * 100).toFixed(2)}%` : '—'}</td>;
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </s-section>

      <s-section heading="Statistical method">
        <s-paragraph>
          Fixed-horizon frequentist test. Conversion rate uses a two-proportion z-test; revenue per visitor and AOV use Welch's t-test. Significance is {Math.round((1 - r.confidence) * 10000) / 100}% per comparison ({variantCount > 2 ? `Bonferroni-corrected for ${variantCount - 1} comparisons` : 'one comparison'}). A winner needs at least {x.min_days} days, {number(x.min_visitors)} visitors and {number(x.min_conversions)} {primary === 'click_rate' ? 'clicks' : 'conversions'} in every variant, a significant improvement on the primary metric, and no breached guardrail. Looking at results early doesn't change them, but don't stop a test just because it looks good.
        </s-paragraph>
      </s-section>

      <s-section heading="History">
        <ul className="xp-history">
          {logs.map((l) => <li key={l.id}><span className="oo-muted">{dateTime(l.created_at)}</span> {l.message}{l.user && <span className="oo-muted"> · {l.user}</span>}</li>)}
        </ul>
      </s-section>
    </Page>
  );
}
