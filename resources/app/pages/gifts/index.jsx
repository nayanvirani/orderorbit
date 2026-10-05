import { EmptyState, Hero, money, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';
import ExperienceStatus from '../cro/_status.jsx';

export default function Gifts({ items }) {
  const { can, currency } = useShared();
  const { submit } = useRouter();
  const canManage = can.manage_experiences;
  const create = appUrl(route('app.gifts.models'));
  return (
    <Page heading="Progressive gifts" primary={canManage ? <s-button slot="primary-action" variant="primary" href={create}>Create progressive gifts</s-button> : null}>
      <Hero eyebrow="Progressive gifts" icon="gift" tone="gifts" title="Rewards that grow <em>with the cart.</em>" lead="Free gifts, free shipping and order discounts that unlock by cart value or item count, in one progress bar. Rewards apply automatically at checkout." />
      <s-section>
        {!items.length ? (
          <EmptyState title="No progressive gifts yet" text="Pick a layout, set your milestones and publish. It shows under your add to cart button.">
            {canManage && <s-button variant="primary" href={create}>Create progressive gifts</s-button>}
          </EmptyState>
        ) : (
          <div className="oo-scroll">
            <table className="oo-table stack bx-table">
              <thead><tr><th>Status</th><th>Title</th><th>Unlocks by</th><th>Rewards</th><th>Views</th><th>Orders</th><th>Revenue</th><th style={{ textAlign: 'right' }}>Actions</th></tr></thead>
              <tbody>
                {items.map((g) => {
                  const live = g.status === 'published';
                  return (
                    <tr key={g.id}>
                      <td data-label="Status">
                        {canManage ? (
                          <button type="button" className={`bx-switch ${live ? 'on' : ''}`} role="switch" aria-checked={live} aria-label={`${live ? 'Pause' : 'Publish'} ${g.name}`}
                            onClick={() => submit(route('app.gifts.toggle', { gift: g.id }), {}, { confirm: live ? 'Pause these rewards? They stop showing and stop applying at checkout.' : undefined })}><i /></button>
                        ) : <ExperienceStatus experience={g} />}
                      </td>
                      <td data-label="Title">
                        <a className="bx-title" href={appUrl(canManage ? route('app.gifts.edit', { gift: g.id }) : route('app.cro.experiences.show', { experience: g.id }))}>{g.name}</a>
                        <span className="bx-sub">{g.layout}</span>
                      </td>
                      <td data-label="Unlocks by">{g.unlock}</td>
                      <td data-label="Rewards">{g.rewards}</td>
                      <td data-label="Views">{number(g.stats.views)}</td>
                      <td data-label="Orders">{number(g.stats.orders)}</td>
                      <td data-label="Revenue">{money(g.stats.revenue, currency)}</td>
                      <td data-label="Actions">
                        <div className="bx-actions">
                          {canManage && (
                            <>
                              <a className="bx-icon" href={appUrl(route('app.gifts.edit', { gift: g.id }))} title="Edit" aria-label="Edit">
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m13.6 3.4 3 3-9.1 9.1-3.7.7.7-3.7 9.1-9.1Z" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" /></svg>
                              </a>
                              <button type="button" className="bx-icon danger" title="Archive" aria-label="Archive"
                                onClick={() => submit(route('app.cro.experiences.lifecycle', { experience: g.id, action: 'archive' }), {}, { confirm: 'Archive these rewards? They stop showing and stop applying at checkout.' })}>
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
