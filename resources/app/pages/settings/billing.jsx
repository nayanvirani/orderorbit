import { date, Hero, number, Page, plural } from '../../components/ui.jsx';

const usd = (v) => `$${Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
const price = (v) => (v > 0 ? `$${Number(v).toFixed(2)}` : 'Free');
const STATUS = {
  PENDING: ['warning', 'Waiting for approval', 'Approve the plan on Shopify to activate it.'],
  FROZEN: ['critical', 'Frozen', 'Your Shopify account has a billing issue. Resolve it in Shopify to reactivate OrderOrbit Space. Your data is kept.'],
  DECLINED: ['warning', 'Not approved', 'The plan wasn\'t approved. Choose a plan below to continue.'],
  EXPIRED: ['warning', 'Approval expired', 'The approval request expired. Choose a plan below to continue.'],
  CANCELLED: ['warning', 'Cancelled', 'Your plan was cancelled. Choose a plan below to continue. Your data is kept.'],
};

export default function Billing(p) {
  const { effective, subscription, sales, plans, usage, cycles, pricingUrl, graceDays } = p;
  const planName = (key) => plans.find((x) => x.key === key)?.name || key;
  const status = !subscription && !(p.plan && p.planExpiresAt) && !p.testShop && STATUS[p.latestStatus];

  return (
    <Page heading={effective ? 'Settings' : 'Choose a plan'}>
      {!effective && <Hero eyebrow="Plans" title="Choose your <em>orbit.</em>" lead="Choose a plan below to start using OrderOrbit Space. Start free and upgrade as your store grows. Billing runs through your Shopify invoice." />}
      {p.syncError && <s-banner tone="critical">Your Shopify connection needs attention. Open Settings → Store to review it.</s-banner>}

      {subscription ? (
        <s-section heading="Current plan">
          <s-stack direction="inline" gap="small-200" alignItems="center">
            <s-heading>{planName(subscription.plan)} · {subscription.price > 0 ? `${price(subscription.price)}/mo` : 'Free'}</s-heading>
            {subscription.trial_ends_at ? <s-badge tone="info">Trial</s-badge> : <s-badge tone="success">Active</s-badge>}
            {subscription.test && <s-badge>Test charge</s-badge>}
          </s-stack>
          <s-paragraph>
            {subscription.trial_ends_at && `Trial ends ${date(subscription.trial_ends_at)}. `}
            {subscription.current_period_ends_at && `Renews ${date(subscription.current_period_ends_at)}.`}
          </s-paragraph>
          <s-stack direction="inline" gap="small-200">
            {pricingUrl && <s-button variant="primary" href={pricingUrl} target="_top">Change plan</s-button>}
            <s-button variant="tertiary" href={p.billingUrl} target="_top">Open Shopify billing</s-button>
          </s-stack>
        </s-section>
      ) : p.plan && p.planExpiresAt ? (
        <s-banner tone="warning" heading="Your plan was cancelled">
          <s-paragraph>You keep {planName(p.plan)} until {date(p.planExpiresAt)}, the end of the period you've paid for. Choose a plan to keep publishing after that. Your data is kept either way.</s-paragraph>
          {pricingUrl && <s-button slot="secondary-actions" href={pricingUrl} target="_top">Choose a plan</s-button>}
        </s-banner>
      ) : p.testShop ? (
        <s-banner tone="info" heading="Test store"><s-paragraph>This development store has {planName(effective)} access without a subscription, for testing only.</s-paragraph></s-banner>
      ) : status ? (
        <s-banner tone={status[0]} heading={`Plan status: ${status[1]}`}><s-paragraph>{status[2]}</s-paragraph></s-banner>
      ) : null}

      {effective && <Sales sales={sales} cycles={cycles} graceDays={graceDays} />}

      {effective && usage.length > 0 && (
        <s-section heading="Live offers on your plan">
          <div className="ui-grid">
            {usage.map((m) => (
              <div className="ob-kpi" key={m.meter}>
                <small>{m.label}</small>
                <div><strong>{number(m.used)}</strong> <span className="oo-muted">/ {number(m.limit)} live</span></div>
                <div className="oo-meter"><i className={m.used >= m.limit ? 'full' : ''} style={{ width: `${m.limit ? Math.min(100, Math.round((m.used / m.limit) * 100)) : 0}%` }} /></div>
              </div>
            ))}
          </div>
          <s-paragraph><span className="oo-muted">Countdown, sticky add to cart, trust badges and sales pop are unlimited on every plan. Starter and above make these unlimited too.</span></s-paragraph>
        </s-section>
      )}

      <s-section heading={effective ? 'Plans' : 'Pick a plan'}>
        <div className="ob-plans">
          {plans.map((plan) => {
            const current = effective === plan.key;
            return (
              <div key={plan.key} className={`ob-plan ${plan.key === 'growth' ? 'featured' : ''} ${current ? 'current' : ''}`}>
                {current ? <span className="ob-badge">Current plan</span> : plan.key === 'growth' ? <span className="ob-badge">Most popular</span> : null}
                <h3>{plan.name}</h3>
                <div className="ob-price">{price(plan.price)} {plan.price > 0 && <small>/MO</small>}</div>
                <ul>{plan.features.map((f) => <li key={f}>{f}</li>)}</ul>
                {/* Shopify's hosted plan page (Managed Pricing); it returns to the app with ?charge_id. */}
                {pricingUrl && <s-button variant={current ? 'secondary' : 'primary'} href={pricingUrl} target="_top">{current ? 'Manage plan' : 'Choose plan'}</s-button>}
              </div>
            );
          })}
        </div>
        {!pricingUrl && <s-paragraph><span className="oo-muted">Only store owners can change the plan.</span></s-paragraph>}
      </s-section>

      <s-paragraph><span className="oo-muted">Billed through Shopify. Plan changes happen on Shopify's plan page. Nothing is ever deleted when you change plans; offers a lower plan doesn't cover are paused. If your store's sales pass your plan's limit you have {graceDays} days to upgrade before features stop.</span></s-paragraph>
    </Page>
  );
}

function Sales({ sales: s, cycles, graceDays }) {
  const next = s.next ? ` ${s.next.name} (${price(s.next.price)}/mo) fits your store.` : '';
  return (
    <s-section heading="Store sales · this cycle">
      {s.state === 'paused' && (
        <s-banner tone="critical" heading="All features are stopped">
          <s-paragraph>Your store passed this plan's sales limit and the {graceDays}-day period to upgrade has ended. Choose a higher plan and everything goes live again straight away. Nothing was deleted.{next}</s-paragraph>
        </s-banner>
      )}
      {s.state === 'over' && (
        <s-banner tone="warning" heading={`Upgrade required by ${date(s.deadline)}`}>
          <s-paragraph>Your store has passed this plan's sales limit. Everything keeps working until {date(s.deadline)}; after that every feature stops until you upgrade.{next}</s-paragraph>
        </s-banner>
      )}
      {s.state === 'near' && (
        <s-banner tone="info" heading="You're close to this plan's sales limit">
          <s-paragraph>When your store passes the limit you'll have {graceDays} days to upgrade before every feature stops.</s-paragraph>
        </s-banner>
      )}
      <div className="ob-sales">
        <div className="ob-sales-top">
          {s.sales === null ? (
            <><b>Not counted yet</b><span>Your sales are counted from your Shopify orders shortly after you open the app.</span></>
          ) : (
            <><b>{usd(s.sales)}</b><span>{s.limit === null ? 'Unlimited on your plan' : `of ${usd(s.limit)} on your plan (${s.percent}%)`}</span></>
          )}
        </div>
        {s.limit !== null && s.sales !== null && (
          <div className="ob-sales-bar"><i className={['over', 'paused'].includes(s.state) ? 'over' : s.state === 'near' ? 'near' : ''} style={{ width: `${Math.min(100, s.percent || 0)}%` }} /></div>
        )}
        {s.sales !== null && (
          <span className="oo-muted">
            {number(s.orders)} {plural('order', s.orders)} counted this cycle.
            {s.test_orders && ` ${s.test_orders.count} test ${plural('order', s.test_orders.count)} (${usd(s.test_orders.usd)}) ${s.test_orders.count === 1 ? 'is' : 'are'} not counted: orders paid with a test payment, like every order on a development store, never count toward your limit.`}
          </span>
        )}
        <span className="oo-muted">Cycle {date(s.cycle_start)} – {date(s.cycle_end)}. The count starts again on {date(s.cycle_end)}. Each plan covers a level of total store sales per 30-day cycle (all orders, in USD; test and cancelled orders don't count).</span>
      </div>
      {cycles.length > 0 && (
        <table className="oo-table" style={{ marginTop: 14 }}>
          <thead><tr><th>Past cycle</th><th>Orders</th><th>Store sales</th><th>Plan</th><th /></tr></thead>
          <tbody>
            {cycles.map((c) => (
              <tr key={c.id}>
                <td>{date(c.starts_at)} – {date(c.ends_at)}</td>
                <td>{number(c.orders)}</td>
                <td>{usd(c.sales)}</td>
                <td>{c.plan}{c.limit !== null && ` · up to ${usd(c.limit)}`}</td>
                <td>{c.over && <s-badge tone="warning">Over limit</s-badge>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </s-section>
  );
}
