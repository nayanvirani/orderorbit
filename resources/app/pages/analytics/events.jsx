import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useUrl, withQuery } from '../../router.jsx';
import AnalyticsNav, { Spark, TrendPill } from './_nav.jsx';

const DEVICES = { mobile: 'Mobile', tablet: 'Tablet', desktop: 'Desktop' };

export default function EventExplorer(props) {
  const { events, catalogue, dimensions, pageTypes, filters, selected, selectedLabel, dimension, breakdown, experiences, days, locked } = props;
  const url = useUrl();
  const { visit } = useRouter();
  const [f, setF] = useState({ device: '', country: '', source: '', campaign: '', experience: '', ...filters });
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const apply = () => {
    const params = { page: null };
    ['device', 'country', 'source', 'campaign', 'experience'].forEach((k) => { params[`f[${k}]`] = f[k] || null; });
    visit(withQuery(url, params));
  };
  const top = Math.max(1, ...breakdown.map((r) => r.total));
  const sel = selected ? events[selected] : null;
  const dimValue = (row) => {
    if (row.value === null || row.value === undefined) return <span className="oo-muted">Not set</span>;
    if (dimension === 'experience_handle') return experiences[row.value]?.name || row.value;
    if (dimension === 'product_id') return row.label || `Product ${row.value}`;
    if (dimension === 'page_type') return pageTypes[row.value] || row.value;
    return row.value;
  };

  return (
    <Page heading="Event Explorer" back={route('app.analytics', { days })} backLabel="Analytics">
      <AnalyticsNav {...props} />
      {!locked && (
        <>
          <Form className="an-filters" onSubmit={apply}>
            <Field label="Device"><select value={f.device} onChange={set('device')}><option value="">All</option>{Object.entries(DEVICES).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></Field>
            <Field label="Market"><input type="text" value={f.country} onChange={set('country')} maxLength={2} placeholder="US" /></Field>
            <Field label="UTM source"><input type="text" value={f.source} onChange={set('source')} maxLength={60} /></Field>
            <Field label="UTM campaign"><input type="text" value={f.campaign} onChange={set('campaign')} maxLength={100} /></Field>
            <Field label="Experience"><select value={f.experience} onChange={set('experience')}><option value="">All</option>{Object.entries(experiences).map(([h, e]) => <option key={h} value={h}>{e.name}</option>)}</select></Field>
            <s-button type="submit">Apply</s-button>
            <s-button href={appUrl(withQuery(url, { export: 'csv' }))} target="_blank" variant="tertiary">Export CSV</s-button>
          </Form>

          {selected && (
            <s-section heading={selectedLabel}>
              <p className="oo-muted oo-small">
                <span className="oo-code">{selected}</span> · {number(sel?.total || 0)} events from {number(sel?.visitors || 0)} visitors · <a href={appUrl(withQuery(url, { event: null, by: null }))}>Back to all events</a>
              </p>
              <nav className="bx-tabs" aria-label="Break down by">
                {Object.entries(dimensions).map(([key, label]) => <a key={key} href={appUrl(withQuery(url, { by: key }))} className={dimension === key ? 'on' : ''}>{label}</a>)}
              </nav>
              {!breakdown.length ? <s-paragraph><span className="oo-muted">No events in this period.</span></s-paragraph> : (
                <table className="oo-table stack an-break">
                  <thead><tr><th>{dimensions[dimension]}</th><th>Events</th><th>Visitors</th><th className="an-bar-col" /></tr></thead>
                  <tbody>
                    {breakdown.map((row, i) => (
                      <tr key={i}>
                        <td data-label={dimensions[dimension]}>{dimValue(row)}</td>
                        <td data-label="Events">{number(row.total)}</td>
                        <td data-label="Visitors">{number(row.visitors)}</td>
                        <td className="an-bar-col"><span className="an-bar"><i style={{ width: `${(row.total / top) * 100}%` }} /></span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </s-section>
          )}

          {Object.entries(catalogue).map(([heading, list]) => (
            <s-section heading={heading} key={heading}>
              <div className="oo-scroll">
                <table className="oo-table stack">
                  <thead><tr><th>Event</th><th>Events</th><th>Unique visitors</th><th>Sessions</th><th>Trend</th><th>Daily</th></tr></thead>
                  <tbody>
                    {Object.entries(list).map(([name, label]) => {
                      const r = events[name];
                      return (
                        <tr key={name} className={r ? '' : 'an-dim'}>
                          <td data-label="Event">{r ? <a href={appUrl(withQuery(url, { event: name }))}>{label}</a> : label}<br /><span className="oo-muted oo-small">{name}</span></td>
                          <td data-label="Events">{number(r?.total || 0)}</td>
                          <td data-label="Unique visitors">{number(r?.visitors || 0)}</td>
                          <td data-label="Sessions">{number(r?.sessions || 0)}</td>
                          <td data-label="Trend">{r ? <TrendPill value={r.trend} /> : <span className="oo-muted">—</span>}</td>
                          <td data-label="Daily">{r && <Spark values={r.daily} />}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </s-section>
          ))}
          <p className="oo-muted oo-small">Events come from the OrderOrbit Space web pixel and only include shoppers who allow analytics. Trends compare with the previous {days} days.</p>
        </>
      )}
    </Page>
  );
}
