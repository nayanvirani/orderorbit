import { ago } from '../../components/ui.jsx';
import { appUrl, route, useUrl, withQuery } from '../../router.jsx';

const RANGES = [[7, 'Last 7 days'], [30, 'Last 30 days'], [90, 'Last 90 days']];

/** Date range (kept when switching reports), data freshness and the plan lock. */
export default function AnalyticsNav({ days, freshest, docsUrl, locked, error, hideRange, lockedTitle, lockedText }) {
  const url = useUrl();
  return (
    <>
      {!hideRange && (
        <nav className="bx-tabs" aria-label="Date range">
          {RANGES.map(([d, label]) => <a key={d} href={appUrl(withQuery(url, { days: d, page: null }))} className={days === d ? 'on' : ''}>{label}</a>)}
        </nav>
      )}
      <p className="oo-muted oo-small an-fresh">
        Data as of {freshest ? ago(freshest) : 'no events yet'} · only shoppers who allow analytics are counted · <a href={docsUrl} target="_blank" rel="noreferrer">Documentation</a>
      </p>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {locked && (
        <s-banner tone="info" heading={lockedTitle || 'This report is on Growth and Scale'}>
          <s-paragraph>{lockedText || 'Event Explorer, funnels, revenue attribution and customer journeys come with the Growth and Scale plans. Your store\'s events are already being recorded, so the reports are full from the day you upgrade.'}</s-paragraph>
          <s-button slot="secondary-actions" href={appUrl(route('app.settings.billing'))}>See plans</s-button>
        </s-banner>
      )}
    </>
  );
}

/** A small trend pill like the Blade pages' ob-trend. */
export function TrendPill({ value }) {
  if (value === null || value === undefined) return <span className="oo-muted">—</span>;
  const dir = value > 0.5 ? 'up' : value < -0.5 ? 'down' : 'flat';
  return <span className={`ob-trend ${dir}`}>{dir === 'up' ? '↑' : dir === 'down' ? '↓' : '→'} {Math.abs(Math.round(value))}%</span>;
}

/** The Blade sparkline (ob-spark). */
export function Spark({ values }) {
  const max = Math.max(...values, 0);
  if (values.length < 2 || max <= 0) return null;
  const line = 'M' + values.map((v, i) => `${((i / (values.length - 1)) * 100).toFixed(2)},${(30 - (v / max) * 26).toFixed(2)}`).join(' L');
  return (
    <svg className="ob-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
      <path className="area" d={`${line} L100,32 L0,32 Z`} /><path className="line" d={line} vectorEffect="non-scaling-stroke" />
    </svg>
  );
}
