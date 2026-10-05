import { ActionButton } from '../../components/form.jsx';
import { EmptyState, Hero, money, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';
import ExperienceStatus from '../cro/_status.jsx';

const TABS = [['all', 'All'], ['active', 'Active'], ['scheduled', 'Scheduled'], ['draft', 'Draft'], ['paused', 'Paused'], ['archived', 'Archived']];

export default function Bundles({ tab, bundles, counts, revenue }) {
  const { can, currency } = useShared();
  const { submit } = useRouter();
  const canManage = can.manage_experiences;
  const create = appUrl(route('app.bundles.types'));
  return (
    <Page heading="Bundles" primary={canManage ? <s-button slot="primary-action" variant="primary" href={create}>Create bundle</s-button> : null}>
      <Hero eyebrow="Bundles" icon="package" tone="bundles" title="Sell more per order with <em>bundles.</em>" lead="Quantity breaks, mix & match, fixed packs and gift bundles. One bundle line in the cart, stock deducted per product." />

      <s-section>
        <div className="bx-overview">
          <div className="bx-overview-head"><strong>Overview</strong><span className={`bx-pill ${counts.active ? 'on' : ''}`}>{counts.active ? 'Bundles live' : 'No live bundles'}</span></div>
          <div className="ob-kpis">
            <div className="ob-kpi"><small>Live bundles</small><b>{counts.active}</b><span>Showing on product pages</span></div>
            <div className="ob-kpi"><small>Scheduled</small><b>{counts.scheduled}</b><span>Start automatically</span></div>
            <div className="ob-kpi"><small>Drafts</small><b>{counts.draft}</b><span>Not live yet</span></div>
            <div className="ob-kpi"><small>Bundle revenue · 30 days</small><b style={{ fontSize: 26 }}>{money(revenue.amount, currency)}</b><span>{number(revenue.orders)} orders</span></div>
          </div>
        </div>
      </s-section>

      <s-section>
        <nav className="bx-tabs" aria-label="Filter bundles">
          {TABS.map(([key, label]) => <a key={key} href={appUrl(route('app.bundles.index', { tab: key }))} className={tab === key ? 'on' : ''}>{label} <span>({counts[key]})</span></a>)}
        </nav>
        {!bundles.length ? (
          <EmptyState title={tab === 'all' ? 'No bundles yet' : 'Nothing here'} text="Quantity breaks, mix & match, fixed packs and gift bundles. Pick a type, choose a model and publish.">
            {canManage && <s-button variant="primary" href={create}>Create bundle</s-button>}
          </EmptyState>
        ) : (
          <div className="oo-scroll">
            <table className="oo-table stack bx-table">
              <thead><tr><th>Status</th><th>Title</th><th>Type</th><th>Views</th><th>Added to cart</th><th>Orders</th><th>Revenue</th><th style={{ textAlign: 'right' }}>Actions</th></tr></thead>
              <tbody>
                {bundles.map((b) => {
                  const live = b.status === 'published';
                  const st = b.stats;
                  return (
                    <tr key={b.id}>
                      <td data-label="Status">
                        {canManage && b.status !== 'archived' ? (
                          <button type="button" className={`bx-switch ${live ? 'on' : ''}`} role="switch" aria-checked={live} aria-label={`${live ? 'Pause' : 'Publish'} ${b.name}`}
                            onClick={() => submit(route('app.bundles.toggle', { bundle: b.id }), {}, { confirm: live ? 'Pause this bundle? It disappears from your store and its pricing stops.' : undefined })}><i /></button>
                        ) : <ExperienceStatus experience={b} />}
                      </td>
                      <td data-label="Title">
                        <a className="bx-title" href={appUrl(canManage ? route('app.bundles.edit', { bundle: b.id }) : route('app.cro.experiences.show', { experience: b.id }))}>{b.name}</a>
                        <span className="bx-sub">{b.summary}</span>
                      </td>
                      <td data-label="Type">{b.kind}</td>
                      <td data-label="Views">{number(st.views)}</td>
                      <td data-label="Added to cart">{number(st.adds)}{st.views > 0 && <span className="oo-muted"> ({((st.adds / st.views) * 100).toFixed(1)}%)</span>}</td>
                      <td data-label="Orders">{number(st.orders)}</td>
                      <td data-label="Revenue">{money(st.revenue, currency)}</td>
                      <td data-label="Actions">
                        <div className="bx-actions">
                          <a className="bx-icon" href={appUrl(route('app.cro.experiences.show', { experience: b.id }))} title="Details and history" aria-label="Details">
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4C5.5 4 2.7 8 2 10c.7 2 3.5 6 8 6s7.3-4 8-6c-.7-2-3.5-6-8-6Zm0 9.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z" fill="currentColor" /></svg>
                          </a>
                          {canManage && b.status !== 'archived' && (
                            <>
                              <a className="bx-icon" href={appUrl(route('app.bundles.edit', { bundle: b.id }))} title="Edit" aria-label="Edit">
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m13.6 3.4 3 3-9.1 9.1-3.7.7.7-3.7 9.1-9.1Z" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" /></svg>
                              </a>
                              <button type="button" className="bx-icon" title="Duplicate" aria-label="Duplicate" onClick={() => submit(route('app.cro.experiences.duplicate', { experience: b.id }))}>
                                <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="7" y="7" width="9" height="9" rx="2" fill="none" stroke="currentColor" strokeWidth="1.6" /><path d="M13 4H6a2 2 0 0 0-2 2v7" fill="none" stroke="currentColor" strokeWidth="1.6" /></svg>
                              </button>
                              <button type="button" className="bx-icon danger" title="Archive" aria-label="Archive"
                                onClick={() => submit(route('app.cro.experiences.lifecycle', { experience: b.id, action: 'archive' }), {}, { confirm: 'Archive this bundle? It stops showing and its pricing stops. You can restore it later.' })}>
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14v3H3zM4.5 8v8h11V8M8 11h4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" /></svg>
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </s-section>
    </Page>
  );
}
