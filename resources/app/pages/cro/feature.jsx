import { Preview, useRuntime } from '../../components/runtime.jsx';
import { EmptyState, Hero, Page, Upgrade, ago } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import ExperienceStatus from './_status.jsx';

export default function Feature({ feature, types, experiences, counts, discounts, templates, editor, checkout, notice, planFeature, accountsUrl, docsUrl }) {
  const { can, currency } = useShared();
  const ready = useRuntime({ checkout });
  const first = types[0];
  const locked = notice === 'plus';
  const create = (type, template) => appUrl(route('app.cro.experiences.create', { type, template }));
  const context = { currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };

  return (
    <Page heading={feature.label}>
      <Hero eyebrow={feature.label} title={feature.tagline} lead={feature.lead} icon={feature.icon} tone={feature.tone}>
        {can.manage_experiences && !locked && types.map((t, i) => <s-button key={t.key} variant={i === 0 ? 'primary' : 'secondary'} href={create(t.key)}>Create {t.singular}</s-button>)}
        <s-button href={editor.url} target="_top">{editor.label}</s-button>
        <s-button href={docsUrl} target="_blank" variant="tertiary">View documentation</s-button>
      </Hero>

      {notice === 'plus' && (
        <s-banner tone="info" heading="Blocks inside checkout need Shopify Plus">
          <s-paragraph>Your store isn't on Shopify Plus, so Shopify doesn't allow apps to add blocks inside checkout. Thank You and Order Status blocks work on every plan.</s-paragraph>
          <s-button slot="secondary-actions" href={appUrl(route('app.features.show', { feature: 'thank-you' }))}>Thank You & Order Status</s-button>
        </s-banner>
      )}
      {notice === 'accounts' && (
        <s-banner tone="warning" heading="Your store uses classic customer accounts">
          <s-paragraph>These blocks need Shopify's new customer accounts. Turn them on in Shopify under Settings → Customer accounts, then re-check your store in OrderOrbit Space Settings. You can set up blocks now.</s-paragraph>
          <s-button slot="secondary-actions" href={accountsUrl} target="_top">Customer account settings</s-button>
        </s-banner>
      )}
      {notice === 'plan' && <Upgrade feature={planFeature} />}

      <s-section>
        <div className="ob-kpis">
          <div className="ob-kpi"><small>Live</small><b>{counts.published}</b><span>Showing to shoppers</span></div>
          <div className="ob-kpi"><small>Drafts</small><b>{counts.draft}</b><span>Not live yet</span></div>
          <div className="ob-kpi"><small>Paused</small><b>{counts.paused}</b><span>Hidden from shoppers</span></div>
          <div className="ob-kpi"><small>Checkout saving</small><b style={{ fontSize: 26 }}>{discounts ? 'Automatic' : 'Not needed'}</b><span>{discounts ? 'Applied by OrderOrbit Space in cart and checkout' : 'This feature doesn\'t change prices'}</span></div>
        </div>
      </s-section>

      <s-section heading="How it works">
        <ol className="ob-how">{feature.steps.map((s) => <li key={s}>{s}</li>)}</ol>
      </s-section>

      <s-section heading={`Your ${feature.lower}`}>
        {!experiences.length ? (
          <EmptyState title={`No ${feature.lower} yet`} text={first.empty}>
            {can.manage_experiences && <s-button variant="primary" href={create(first.key)}>Create {first.singular}</s-button>}
          </EmptyState>
        ) : (
          <div className="oo-scroll">
            <table className="oo-table stack">
              <thead><tr><th>Name</th>{types.length > 1 && <th>Type</th>}<th>Status</th><th>Template</th>{discounts && <th>Checkout saving</th>}<th>Updated</th></tr></thead>
              <tbody>
                {experiences.map((e) => (
                  <tr key={e.id}>
                    <td data-label="Name"><s-link href={appUrl(route('app.cro.experiences.show', { experience: e.id }))}>{e.name}</s-link></td>
                    {types.length > 1 && <td data-label="Type">{e.type_label}</td>}
                    <td data-label="Status"><ExperienceStatus experience={e} /></td>
                    <td data-label="Template">{e.template}</td>
                    {discounts && <td data-label="Checkout saving">{e.discount ? <s-badge tone="success">Active</s-badge> : <span className="oo-muted">—</span>}</td>}
                    <td data-label="Updated" className="oo-muted">{ago(e.updated_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </s-section>

      <s-section heading="Start from a template">
        <div className="b-templates">
          {templates.map((t) => (
            <a key={`${t.type}:${t.key}`} className="b-template" href={can.manage_experiences ? create(t.type, t.key) : undefined}>
              <div className="tpl-stage"><Preview ready={ready} experience={t.preview} context={context} className="b-template-preview oo-preview" /></div>
              <div className="tpl-body">
                <span className="b-template-name">{t.name}</span>
                {types.length > 1 && <span className="oo-muted oo-small">{t.type_label}</span>}
                {can.manage_experiences && <span className="tpl-cta">Use this template →</span>}
              </div>
            </a>
          ))}
        </div>
      </s-section>
    </Page>
  );
}
