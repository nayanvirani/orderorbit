import { Preview, useRuntime } from '../../components/runtime.jsx';
import { ago, EmptyState, Hero, Icon, money, number, Page, plural } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import ExperienceStatus from './_status.jsx';

export default function CroOverview({ counts, notPlaced, recent, activeLimit, activeUsed, revenue, features }) {
  const { currency } = useShared();
  const ready = useRuntime({ checkout: true });
  const context = { currency, cartTotal: 6000, productPrice: 2900, productTitle: 'Glow Serum', page: 'product' };
  return (
    <Page heading="CRO overview">
      <Hero eyebrow="CRO" icon="sparkle" title="Every conversion tool, <em>one design.</em>" lead="Bundles, progressive gifts, cart upsells, countdowns, sticky add to cart and trust badges — sharing your brand, with savings applied at checkout and one set of numbers.">
        <s-button variant="primary" href={appUrl(route('app.bundles.types'))}>Create a bundle</s-button>
        <s-button href={appUrl(route('app.analytics'))}>View analytics</s-button>
      </Hero>

      {notPlaced > 0 && (
        <s-banner tone="warning">
          <s-paragraph>{notPlaced} published {plural('experience', notPlaced)} {notPlaced === 1 ? 'isn\'t' : 'aren\'t'} placed in your theme yet. Add the Growvia block in the Theme Editor.</s-paragraph>
          <s-button slot="secondary-actions" href={appUrl(route('app.cro.experiences.index', { status: 'not_placed' }))}>View widgets</s-button>
        </s-banner>
      )}

      <s-section>
        <div className="ob-kpis">
          <div className="ob-kpi"><small>Active widgets</small><b>{activeUsed}<span style={{ display: 'inline', font: '400 18px var(--ob-serif)', color: 'var(--ob-muted)' }}> / {activeLimit === null ? '∞' : activeLimit}</span></b><span>On your current plan</span></div>
          <div className="ob-kpi"><small>Drafts</small><b>{counts.draft}</b><span>Not live yet</span></div>
          <div className="ob-kpi"><small>Paused</small><b>{counts.paused}</b><span>Hidden from shoppers</span></div>
          <div className="ob-kpi"><small>Revenue from offers · 30 days</small><b style={{ fontSize: 26 }}>{money(revenue.amount, revenue.currency)}</b><span>{number(revenue.orders)} orders with an offer</span></div>
        </div>
      </s-section>

      <s-section heading="Features">
        <div className="ob-features">
          {features.map((f) => (
            <a key={f.key} className={`ob-feature t-${f.tone}`} href={appUrl(f.href)}>
              <span className="ob-feature-shot"><Preview ready={ready} experience={f.preview} context={context} /></span>
              <span className="ob-feature-body">
                <strong><Icon name={f.icon} size="sm" />{f.label} {f.count > 0 && <span className="ob-badge soft">{f.count}</span>}</strong>
                <span>{f.lead}</span>
                <em>Open →</em>
              </span>
            </a>
          ))}
        </div>
      </s-section>

      <s-section heading="Recently updated">
        {!recent.length ? (
          <EmptyState title="Create your first widget" text="Pick a template, customise it and publish it from the Theme Editor.">
            <s-button variant="primary" href={appUrl(route('app.cro.experiences.create'))}>Create widget</s-button>
          </EmptyState>
        ) : recent.map((e) => (
          <div key={e.id} className="ui-row" style={{ padding: '6px 0', borderBottom: '1px solid #f1f1f1' }}>
            <s-link href={appUrl(route('app.cro.experiences.show', { experience: e.id }))}>{e.name}</s-link>
            <span className="oo-muted">{e.type_label}</span>
            <ExperienceStatus experience={e} />
            <span className="oo-muted oo-small">{ago(e.updated_at)}</span>
          </div>
        ))}
      </s-section>
    </Page>
  );
}
