import { ActionButton } from '../../components/form.jsx';
import { Kpi, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useShared, useUrl, withQuery } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';
import FunnelForm from './_funnelForm.jsx';

const pct = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`);
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

export default function FunnelReport(props) {
  const { funnel, report, previous, compare, compareOptions, maxEvents, experiences, days, locked } = props;
  const { can } = useShared();
  const url = useUrl();
  const steps = report?.steps || [];
  const last = steps[steps.length - 1];
  const overall = steps[0]?.visitors ? (last.visitors / steps[0].visitors) * 100 : null;
  const prevSteps = previous?.steps || [];
  const prevOverall = prevSteps[0]?.visitors ? (prevSteps[prevSteps.length - 1].visitors / prevSteps[0].visitors) * 100 : null;
  const trend = overall !== null && prevOverall ? ((overall - prevOverall) / prevOverall) * 100 : null;

  return (
    <Page heading={funnel.name} back={route('app.analytics.funnels', { days })} backLabel="Funnels">
      <AnalyticsNav {...props} />

      {report && (
        <>
          <s-section heading={`Conversion · ${funnel.window}`}>
            <div className="ob-kpis">
              <Kpi label="Entered" icon="users" tone="analytics" value={number(steps[0].visitors)} sub="Visitors who did the first step" />
              <Kpi label="Completed" icon="bag" tone="upsells" value={number(last.visitors)} sub="Visitors who did every step" />
              <Kpi label="Funnel conversion" icon="target" tone="countdown" value={pct(overall)} trend={trend} spark={Object.values(report.daily || {}).map((d) => (d.entered ? (d.completed / d.entered) * 100 : 0))} sub="First step to last" />
            </div>
            <nav className="bx-tabs" aria-label="Compare" style={{ marginTop: 16 }}>
              <a href={appUrl(withQuery(url, { compare: null }))} className={!compare ? 'on' : ''}>No comparison</a>
              {Object.entries({ ...compareOptions, period: 'Previous period' }).map(([key, label]) => (
                <a key={key} href={appUrl(withQuery(url, { compare: key }))} className={compare === key ? 'on' : ''}>By {label.toLowerCase()}</a>
              ))}
            </nav>
            <ol className="an-funnel">
              {steps.map((step, i) => (
                <li key={i}>
                  <div className="an-funnel-head">
                    <strong>{i + 1}. {step.label}</strong>
                    {step.experience && <span className="oo-muted"> · {experiences[step.experience]?.name || step.experience}</span>}
                  </div>
                  <div className="an-funnel-bar"><i style={{ width: `${step.from_first}%` }} /><span>{number(step.visitors)} visitors · {pct(step.from_first)}</span></div>
                  <div className="an-funnel-meta oo-small">
                    {i > 0 && (
                      <>
                        <span>{pct(step.from_previous)} from the previous step</span>
                        <span className="an-drop">{number(step.dropped)} dropped off</span>
                        <span>Median time from the previous step: {step.median}</span>
                      </>
                    )}
                    {previous && <span>Previous period: {number(prevSteps[i].visitors)} ({pct(prevSteps[i].from_first)})</span>}
                  </div>
                </li>
              ))}
            </ol>
            {report.truncated && <p className="oo-muted oo-small">This period has a lot of events; the report uses the first {number(maxEvents)}. Pick a shorter range for exact numbers.</p>}
          </s-section>

          {report.segments && Object.keys(report.segments).length > 0 ? (
            <s-section heading={`By ${compareOptions[compare].toLowerCase()}`}>
              <div className="oo-scroll">
                <table className="oo-table stack">
                  <thead><tr><th>{compareOptions[compare]}</th>{steps.map((s, i) => <th key={i}>{i + 1}. {s.label}</th>)}<th>Conversion</th></tr></thead>
                  <tbody>
                    {Object.entries(report.segments).map(([segment, counts]) => (
                      <tr key={segment}>
                        <td data-label={compareOptions[compare]}>{cap(segment)}</td>
                        {counts.map((n, i) => <td key={i} data-label={steps[i].label}>{number(n)}</td>)}
                        <td data-label="Conversion">{pct(counts[0] ? (counts[counts.length - 1] / counts[0]) * 100 : null)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </s-section>
          ) : compare && compare !== 'period' ? (
            <s-section><s-paragraph><span className="oo-muted">Nothing to compare yet in this period.</span></s-paragraph></s-section>
          ) : null}
        </>
      )}

      {can.manage_experiences && !locked && (
        <s-section heading="Edit funnel">
          <details>
            <summary>Change the steps, name or window</summary>
            <FunnelForm {...props} funnel={funnel} action={route('app.analytics.funnels.update', { funnel: funnel.id })} />
          </details>
          <div style={{ marginTop: 12 }}>
            <ActionButton url={route('app.analytics.funnels.destroy', { funnel: funnel.id })} tone="critical" variant="tertiary" confirm="Delete this funnel? Your events are kept.">Delete funnel</ActionButton>
          </div>
        </s-section>
      )}
    </Page>
  );
}
