import { useState } from 'react';
import { useApi } from '../useApi.js';
import { appUrl, useShared } from '../router.jsx';
import { LoadError, Skeleton, Sparkline, Trend, money, number } from '../components/ui.jsx';

const RANGES = [7, 30, 90];

/**
 * Home: what matters first. The page appears at once and each card loads on its own, so a slow
 * report never holds up the rest.
 */
export default function Home() {
  const shared = useShared();
  const [days, setDays] = useState(30);
  const overview = useApi('dashboard/overview');

  return (
    <s-page inlineSize="large" heading="Home">
      <div className="dash">
        <header className="dash-head">
          <div>
            <h1>{greeting()}{shared.user?.name ? `, ${shared.user.name}` : ''}</h1>
            <p>Here's how {shared.storeName || 'your store'} is doing.</p>
          </div>
          <div className="dash-range" role="tablist" aria-label="Date range">
            {RANGES.map((d) => (
              <button key={d} type="button" role="tab" aria-selected={days === d} className={days === d ? 'on' : ''} onClick={() => setDays(d)}>
                {d} days
              </button>
            ))}
          </div>
        </header>

        {overview.data?.alerts?.map((a, i) => (
          <s-banner key={i} tone={a.tone}>
            {a.text} <s-button variant="tertiary" href={appUrl(a.href)}>{a.action}</s-button>
          </s-banner>
        ))}

        {overview.data && !overview.data.setup.done && <SetupCard setup={overview.data.setup} />}

        <Kpis days={days} />

        <div className="dash-two">
          <TopExperiences days={days} />
          <QuickActions next={overview.data?.next} />
        </div>

        <Activity />
      </div>
    </s-page>
  );
}

function greeting() {
  const h = new Date().getHours();
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}

function SetupCard({ setup }) {
  const pct = Math.round((setup.completed / setup.total) * 100);
  return (
    <s-section heading="Finish setting up">
      <div className="setup">
        <div className="setup-progress" aria-label={`${setup.completed} of ${setup.total} steps done`}><i style={{ width: `${pct}%` }} /></div>
        <p className="muted">{setup.completed} of {setup.total} steps done</p>
        <div className="setup-next">
          <div>
            <strong>Next: {setup.next.label}</strong>
            <p className="muted">{setup.next.help}</p>
          </div>
          <s-button variant="primary" href={appUrl(setup.next.href)}>{setup.next.action}</s-button>
        </div>
        <ol className="setup-steps">
          {setup.steps.map((s) => <li key={s.label} className={s.done ? 'done' : ''}>{s.label}</li>)}
        </ol>
      </div>
    </s-section>
  );
}

function Kpis({ days }) {
  const { data, error, loading, reload } = useApi(`dashboard/kpis?days=${days}`, [days]);
  const cards = data ? [
    { label: 'Revenue from your offers', value: money(data.influenced_revenue, data.currency), trend: data.trends.influenced_revenue, help: 'Sales from items your bundles, gifts and upsells added', spark: data.daily.influenced },
    { label: 'Store revenue', value: money(data.revenue, data.currency), trend: data.trends.revenue, help: `${number(data.orders)} orders`, spark: data.daily.revenue },
    { label: 'Average order value', value: money(data.aov, data.currency), trend: data.trends.aov, help: data.influenced_aov ? `${money(data.influenced_aov, data.currency)} when an offer is used` : 'Revenue ÷ orders' },
    { label: 'Conversion rate', value: data.conversion === null ? '—' : `${data.conversion.toFixed(2)}%`, trend: data.trends.conversion, help: `${number(data.sessions)} visits` },
  ] : [];
  return (
    <s-section heading={`Last ${days} days`}>
      {error ? <LoadError error={error} onRetry={reload} /> : (
        <div className="kpis">
          {loading && !data ? [0, 1, 2, 3].map((i) => <div className="kpi" key={i}><Skeleton lines={3} /></div>) : cards.map((c) => (
            <div className="kpi" key={c.label}>
              <small>{c.label}</small>
              <b>{c.value} <Trend value={c.trend} /></b>
              <span>{c.help}</span>
              {c.spark && <Sparkline values={c.spark} />}
            </div>
          ))}
        </div>
      )}
      {data && !data.has_events && <p className="muted small">No visits recorded yet. Numbers appear once shoppers visit your store with analytics allowed.</p>}
    </s-section>
  );
}

function TopExperiences({ days }) {
  const { data, error, loading, reload } = useApi(`dashboard/top?days=${days}`, [days]);
  return (
    <s-section heading="What's working">
      {error ? <LoadError error={error} onRetry={reload} /> : loading && !data ? <Skeleton lines={4} /> : !data.items.length ? (
        <div className="empty">
          <p>Your best experiences show up here once shoppers see them.</p>
          <s-button href={appUrl('/app/cro')}>Create an experience</s-button>
        </div>
      ) : (
        <ul className="top-list">
          {data.items.map((e) => (
            <li key={e.id}>
              <a href={appUrl(e.href)}>{e.name}</a>
              <span className="muted small">{e.type}</span>
              <span className="top-stats"><b>{money(e.revenue, data.currency)}</b> <span className="muted small">{number(e.views)} views · {e.conversion}% bought</span></span>
            </li>
          ))}
        </ul>
      )}
    </s-section>
  );
}

const ACTIONS = [
  { label: 'Create a bundle', help: 'Sell more per order with packs and mix & match.', href: '/app/cro/bundles/new', icon: '📦' },
  { label: 'Add free shipping or gifts', help: 'Rewards that unlock as the cart grows.', href: '/app/cro/progressive-gifts/new', icon: '🎁' },
  { label: 'Start an A/B test', help: 'Find out which version earns more.', href: '/app/experiments', icon: '🧪' },
  { label: 'Follow up automatically', help: 'Review requests, win-backs and more.', href: '/app/automation/templates', icon: '⚡' },
];

/** Recommended steps first, topped up with the usual shortcuts. */
function quickActions(next = []) {
  const picked = next.map((n) => ({ label: n.action, help: n.text, href: n.href, icon: '✨' }));
  const hrefs = new Set(picked.map((a) => a.href));
  return [...picked, ...ACTIONS.filter((a) => !hrefs.has(a.href))].slice(0, 4);
}

function QuickActions({ next }) {
  return (
    <s-section heading="Quick actions">
      <ul className="actions">
        {quickActions(next).map((a) => (
          <li key={a.label}>
            <a href={appUrl(a.href)}>
              <span className="icon" aria-hidden="true">{a.icon}</span>
              <span><strong>{a.label}</strong><span className="muted small">{a.help}</span></span>
              <span className="arrow" aria-hidden="true">→</span>
            </a>
          </li>
        ))}
      </ul>
    </s-section>
  );
}

function Activity() {
  const { data, error, loading, reload } = useApi('dashboard/activity');
  if (error) return <s-section><LoadError error={error} onRetry={reload} /></s-section>;
  return (
    <div className="dash-three">
      <s-section heading="Your features">
        {loading && !data ? <Skeleton lines={5} /> : (
          <ul className="features">
            {data.features.map((f) => (
              <li key={f.key}>
                <a href={appUrl(f.href)}>{f.label}</a>
                <s-badge tone={f.live ? 'success' : 'neutral'}>{f.live ? `${f.live} live` : f.total ? 'Not live' : 'Not set up'}</s-badge>
              </li>
            ))}
          </ul>
        )}
      </s-section>
      <s-section heading="Automation">
        {loading && !data ? <Skeleton lines={4} /> : !data.automation.available ? (
          <p className="muted">Workflows that follow up after orders aren't on your plan yet. <a href={appUrl('/app/automation')}>Learn more</a></p>
        ) : (
          <dl className="facts">
            <dt>Active workflows</dt><dd>{data.automation.workflows}</dd>
            <dt>Runs (30 days)</dt><dd>{number(data.automation.runs)}</dd>
            <dt>Success rate</dt><dd>{data.automation.success_rate === null ? '—' : `${data.automation.success_rate}%`}</dd>
            <dt>Failed</dt><dd>{data.automation.failed ? <a href={appUrl('/app/automation/runs', { status: 'failed' })}>{data.automation.failed}</a> : 0}</dd>
          </dl>
        )}
      </s-section>
      <s-section heading="A/B tests">
        {loading && !data ? <Skeleton lines={3} /> : !data.tests.length ? (
          <p className="muted">No tests running. <a href={appUrl('/app/experiments')}>Start one</a></p>
        ) : (
          <ul className="tests">
            {data.tests.map((t) => (
              <li key={t.id}>
                <a href={appUrl(t.href)}>{t.name}</a>
                <div className="setup-progress small"><i style={{ width: `${t.progress}%` }} /></div>
                <span className="muted small">{t.headline}</span>
              </li>
            ))}
          </ul>
        )}
      </s-section>
    </div>
  );
}
