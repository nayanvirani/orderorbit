// Small shared building blocks for the React admin (Polaris web components + a few custom tiles).
import { Link } from 'react-router-dom';

/** Placeholder lines while a card loads. */
export function Skeleton({ lines = 3 }) {
  return (
    <div className="ui-skeleton" aria-busy="true" aria-label="Loading">
      {Array.from({ length: lines }).map((_, i) => <span key={i} style={{ width: `${90 - i * 18}%` }} />)}
    </div>
  );
}

/** A card's error with a retry button. */
export function LoadError({ error, onRetry }) {
  return (
    <s-banner tone="critical">
      {error?.message || 'This couldn\'t load.'} <s-button variant="tertiary" onClick={onRetry}>Try again</s-button>
    </s-banner>
  );
}

/** Tiny line chart for a list of numbers. */
export function Sparkline({ values = [], height = 32 }) {
  const max = Math.max(...values, 0);
  if (values.length < 2 || max <= 0) return null;
  const step = 100 / (values.length - 1);
  const points = values.map((v, i) => `${(i * step).toFixed(2)},${(height - (v / max) * (height - 4) - 2).toFixed(2)}`).join(' ');
  return (
    <svg className="ui-spark" viewBox={`0 0 100 ${height}`} preserveAspectRatio="none" aria-hidden="true">
      <polyline points={`0,${height} ${points} 100,${height}`} className="area" />
      <polyline points={points} className="line" />
    </svg>
  );
}

/** Percentage change pill. */
export function Trend({ value }) {
  if (value === null || value === undefined) return null;
  const dir = value > 0.5 ? 'up' : value < -0.5 ? 'down' : 'flat';
  return <span className={`ui-trend ${dir}`}>{dir === 'up' ? '↑' : dir === 'down' ? '↓' : '→'} {Math.abs(Math.round(value))}%</span>;
}

/** A link that stays inside the React app when it can, or loads the page otherwise. */
export function AppLink({ to, href, children, className }) {
  if (to) return <Link to={to} className={className}>{children}</Link>;
  return <a href={href} className={className}>{children}</a>;
}

export function money(value, currency = 'USD') {
  if (value === null || value === undefined) return '—';
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency, currencyDisplay: 'narrowSymbol' }).format(value);
  } catch (e) {
    return `${currency} ${Number(value).toFixed(2)}`;
  }
}

export function number(value) {
  return value === null || value === undefined ? '—' : new Intl.NumberFormat().format(value);
}
