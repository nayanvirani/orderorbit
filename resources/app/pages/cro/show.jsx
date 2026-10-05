import { ActionButton } from '../../components/form.jsx';
import { ago, KeyValue, money, number, Page, plural, Tabs } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import ExperienceStatus from './_status.jsx';

const PLACEMENT = { placed: ['success', 'Placed in theme'], not_placed: ['critical', 'Not placed'], external: ['neutral', 'Placed in Shopify checkout settings'] };

export default function ExperiencePage(props) {
  const { tab, experience: e, module, feature, editor, notPlacedText, salesPop, survey, performance: perf, currency, test, testable, sections, versions, activity } = props;
  const { can } = useShared();
  const canEdit = can.manage_experiences;
  const show = (t) => route('app.cro.experiences.show', { experience: e.id, tab: t });
  const life = (action) => route('app.cro.experiences.lifecycle', { experience: e.id, action });
  const tabs = module
    ? [['overview', 'Overview'], ['configuration', 'Configuration'], ['analytics', 'Analytics'], ['history', 'History']]
    : [['overview', 'Overview'], ['configuration', 'Configuration'], ['targeting', 'Targeting'], ['analytics', 'Analytics'], ['experiment', 'Experiment'], ['history', 'History']];
  const editHref = appUrl(module ? module.href : route('app.cro.experiences.edit', { experience: e.id }));
  const surveyTotal = Math.max(1, (survey || []).reduce((n, r) => n + r.total, 0));
  const [ptone, plabel] = PLACEMENT[e.placement] || ['neutral', 'Not checked'];

  return (
    <Page heading={e.name} back={feature?.href} backLabel={feature?.label}
      primary={canEdit && e.status !== 'archived' ? <s-button slot="primary-action" variant="primary" href={editHref}>Edit</s-button> : null}>
      {e.discountUrl && (
        <s-banner tone="success" heading="Saving applies automatically at checkout">
          <s-paragraph>OrderOrbit Space created a Shopify automatic discount for this {e.singular}. It stays in step with this experience: pausing or archiving removes it.</s-paragraph>
          <s-button slot="secondary-actions" href={e.discountUrl} target="_top">View in Shopify</s-button>
        </s-banner>
      )}
      {salesPop && (
        <s-banner tone={salesPop.recent ? 'success' : salesPop.blocked ? 'warning' : 'info'} heading={salesPop.recent ? `${salesPop.recent} recent ${plural('purchase', salesPop.recent)} ready to show` : salesPop.blocked ? 'Shopify needs to approve order access' : 'No recent purchases to show yet'}>
          <s-paragraph>
            {salesPop.blocked ? 'Shopify only lets apps read orders after the developer declares how order data is used. In the Shopify Partner Dashboard, open OrderOrbit Space → API access → Protected customer data, request access to order data (no names, emails or addresses are needed) and save. Then click Import recent orders again.'
              : salesPop.recent ? `Pops cycle through products from your real orders in the last ${salesPop.days} days. New orders are added automatically.`
              : salesPop.canRead ? `Pops appear once your store has an order in the last ${salesPop.days} days. New orders are added automatically, or import your recent orders now.`
              : 'Pops use your real orders. Reload OrderOrbit Space and approve the updated permissions (read orders) so new orders are added automatically.'}
          </s-paragraph>
          {salesPop.canRead && <ActionButton slot="secondary-actions" url={route('app.cro.experiences.import-orders', { experience: e.id })}>Import recent orders</ActionButton>}
        </s-banner>
      )}
      {e.not_placed && (
        <s-banner tone="warning" heading="Published but not placed">
          <s-paragraph>{notPlacedText}</s-paragraph>
          <s-button slot="secondary-actions" href={editor.url} target="_top">{editor.label}</s-button>
        </s-banner>
      )}
      {e.live && e.unpublished && <s-banner tone="info">This experience has changes that aren't live yet. Publish from the editor to update your store.</s-banner>}

      <Tabs items={tabs.map(([k, l]) => [l, show(k), tab === k])} />

      {tab === 'overview' && (
        <>
          <s-section>
            <KeyValue rows={[
              ['Status', <ExperienceStatus experience={e} />],
              ['Type', e.type_label],
              ['Template', <>{e.template} {e.template_version && <span className="oo-muted">· v{e.template_version}</span>}</>],
              ['Experience ID', <><span className="oo-code">{e.handle}</span> <span className="oo-muted oo-small">Pin it in the block's “Experience ID” setting.</span></>],
              ['Live version', e.live ? `v${e.live.version} · ${ago(e.live.at)}` : 'Not published'],
              e.schedule && ['Schedule', e.schedule],
              ['Placement', <><s-badge tone={ptone}>{plabel}</s-badge> <span className="oo-muted oo-small">{e.placement_checked_at ? `Checked ${ago(e.placement_checked_at)}` : ''}</span></>],
              e.description && ['Description', e.description],
            ]} />
          </s-section>

          {survey && (
            <s-section heading="Answers · last 30 days">
              {!survey.length ? <s-paragraph><span className="oo-muted">Answers appear here as customers reply on the Thank You page. Only shoppers who allow analytics are counted.</span></s-paragraph> : (
                <table className="oo-table">
                  <thead><tr><th>Answer</th><th>Replies</th><th>Share</th></tr></thead>
                  <tbody>{survey.map((r) => <tr key={r.label}><td>{r.label}</td><td>{number(r.total)}</td><td>{Math.round((r.total / surveyTotal) * 100)}%</td></tr>)}</tbody>
                </table>
              )}
            </s-section>
          )}

          <s-section heading="Performance · last 30 days">
            <div className="ob-kpis">
              <div className="ob-kpi"><small>Views</small><b>{number(perf.views)}</b></div>
              <div className="ob-kpi"><small>Added to cart</small><b>{number(perf.adds)}</b></div>
              <div className="ob-kpi"><small>Orders</small><b>{number(perf.orders)}</b></div>
              <div className="ob-kpi"><small>Revenue</small><b>{money(perf.revenue, currency)}</b></div>
            </div>
            <s-paragraph><span className="oo-muted">From the OrderOrbit Space pixel, for shoppers who allow analytics. <s-link href={appUrl(route('app.analytics'))}>All analytics</s-link></span></s-paragraph>
          </s-section>

          {canEdit && (
            <s-section heading="Actions">
              <div className="oo-inline">
                {['draft', 'paused'].includes(e.status) && !e.live && <s-button href={editHref}>Open editor to publish</s-button>}
                {e.status === 'published' && <ActionButton url={life('pause')} confirm="Pause this experience? Shoppers stop seeing it right away.">Pause</ActionButton>}
                {e.status === 'paused' && e.live && <ActionButton url={life('resume')}>Resume</ActionButton>}
                <s-button href={editor.url} target="_top">{editor.label}</s-button>
                <ActionButton url={route('app.cro.experiences.placement', { experience: e.id })}>Re-check placement</ActionButton>
                {test ? <s-button href={appUrl(test.href)}>{test.draft ? 'Continue A/B test setup' : 'View A/B test'}</s-button>
                  : testable && <ActionButton url={route('app.experiments.store')} data={{ experience_id: e.id }}>Create A/B test</ActionButton>}
                <ActionButton url={route('app.cro.experiences.duplicate', { experience: e.id })}>Duplicate</ActionButton>
                {e.status === 'archived'
                  ? <ActionButton url={life('unarchive')}>Restore from archive</ActionButton>
                  : <ActionButton tone="critical" url={life('archive')} confirm="Archive this experience? It stops showing on your store.">Archive</ActionButton>}
              </div>
            </s-section>
          )}
        </>
      )}

      {module && ['configuration', 'targeting', 'experiment'].includes(tab) && (
        <s-section heading="Configuration">
          <s-paragraph>{module.summary}</s-paragraph>
          <s-paragraph><span className="oo-muted">Offers, products, settings and design are managed in the editor.</span></s-paragraph>
          {canEdit && e.status !== 'archived' && <s-button variant="primary" href={editHref}>Open editor</s-button>}
        </s-section>
      )}

      {!module && ['configuration', 'targeting'].includes(tab) && (
        <>
          {sections.map((s) => <s-section key={s.heading} heading={s.heading}><KeyValue rows={s.rows} /></s-section>)}
          {canEdit && e.status !== 'archived' && <s-button href={editHref}>Edit</s-button>}
        </>
      )}

      {tab === 'analytics' && (
        <s-section heading="Analytics">
          <KeyValue rows={[['Track views', e.track_views ? 'On' : 'Off'], ['Track clicks', e.track_clicks ? 'On' : 'Off']]} />
          <s-paragraph><span className="oo-muted">Views, adds to cart, orders and revenue for this offer are on the Overview tab and in <s-link href={appUrl(route('app.analytics'))}>Analytics</s-link>.</span></s-paragraph>
        </s-section>
      )}

      {!module && tab === 'experiment' && (
        <s-section heading="Experiment">
          {test ? <s-paragraph><s-link href={appUrl(test.href)}>{test.draft ? 'Continue the A/B test setup' : 'View the A/B test on this experience'}</s-link></s-paragraph> : (
            <>
              <s-paragraph>No test is running on this experience.</s-paragraph>
              {canEdit && testable && <ActionButton url={route('app.experiments.store')} data={{ experience_id: e.id }}>Create A/B test</ActionButton>}
            </>
          )}
        </s-section>
      )}

      {tab === 'history' && (
        <>
          <s-section heading="Versions">
            {!versions.length ? <s-paragraph>No published versions yet.</s-paragraph> : (
              <table className="oo-table stack">
                <thead><tr><th>Version</th><th>Published</th><th>By</th><th>Note</th><th /></tr></thead>
                <tbody>
                  {versions.map((v) => (
                    <tr key={v.id}>
                      <td><strong>v{v.version}</strong> {v.live && <s-badge tone="success">Live</s-badge>}</td>
                      <td data-label="Published">{ago(v.published_at)}</td>
                      <td data-label="By">{v.by || '—'}</td>
                      <td className="oo-muted" data-label="Note">{v.note || '—'}</td>
                      <td style={{ textAlign: 'right' }}>
                        {canEdit && !v.live && <ActionButton variant="tertiary" url={route('app.cro.experiences.versions.restore', { experience: e.id, version: v.id })} confirm={`Load v${v.version} into the draft? Your current draft is replaced.`}>Restore</ActionButton>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </s-section>
          <s-section heading="Activity">
            {!activity.length ? <s-paragraph>No activity yet.</s-paragraph> : activity.map((l) => (
              <s-paragraph key={l.id}><strong>{l.who}</strong> · {l.what} <span className="oo-muted">· {ago(l.at)}</span></s-paragraph>
            ))}
          </s-section>
        </>
      )}
    </Page>
  );
}
