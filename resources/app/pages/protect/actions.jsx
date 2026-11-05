// Protect › Action Center (G13): open problems and suggestions in one list, sorted by money at risk.
// Fixing the cause closes an item on the next hourly check.
import { useState } from 'react';
import { ago, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { CheckNow, money, ProfitNotices } from '../profit/_profit.jsx';

const TABS = [['all', 'All'], ['issue', 'Problems'], ['idea', 'Suggestions']];

export default function ActionCenter({ items, atRisk, checkedAt, currency, access, syncing, locked, pending }) {
  const { submit } = useRouter();
  const [tab, setTab] = useState('all');
  const shown = items.filter((i) => tab === 'all' || i.kind === tab);
  const act = (id, action) => submit(route('app.protect.alerts.action', { alert: id }), { action, from: 'actions' });

  return (
    <Page heading="Action Center">
      <p className="oo-muted" style={{ marginTop: 0 }}>Everything worth doing, most urgent and most money first. Fixing the cause closes an item on the next check.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && <CheckNow from="alerts" checkedAt={checkedAt} pending={pending} />}
      {!access && !pending && (
        <>
          <div className="ac-head">
            <div className="pt-chips" role="tablist">
              {TABS.map(([key, label]) => (
                <button type="button" role="tab" key={key} aria-pressed={tab === key} onClick={() => setTab(key)}>
                  {label} <span className="ac-n">{items.filter((i) => key === 'all' || i.kind === key).length}</span>
                </button>
              ))}
            </div>
            <div className="ac-risk"><small>Money at risk from open problems</small><b>{money(atRisk, currency)}</b></div>
          </div>

          <div className="pt-list">
            {!shown.length && <div className="pt-empty">Nothing left here. Growvia checks again every hour.</div>}
            {shown.map((i) => {
              const external = /^https?:/.test(i.cta_url || '');
              return (
                <article className="ac-item" key={i.id}>
                  <div className="ac-money">
                    <b>{i.money ? money(i.money, currency) : '—'}</b>
                    {i.money_note && i.money ? <small>{i.money_note}</small> : null}
                  </div>
                  <div className="ac-body">
                    <div className="ac-tags">
                      <s-badge tone={i.severity === 'urgent' ? 'critical' : i.severity === 'warning' ? 'warning' : 'info'}>{i.kind === 'idea' ? 'Suggestion' : i.severity === 'urgent' ? 'Urgent' : 'Problem'}</s-badge>
                      <span className="pd-chip">{i.area}</span>
                    </div>
                    <h2>{i.title}</h2>
                    <p>{i.detail} {i.fix}</p>
                  </div>
                  <div className="ac-actions">
                    {i.cta && <s-button variant="primary" href={external ? i.cta_url : appUrl(i.cta_url)} target={external ? '_blank' : undefined}>{i.cta}</s-button>}
                    <s-button onClick={() => act(i.id, 'snooze')}>Snooze 7 days</s-button>
                    <s-button variant="tertiary" onClick={() => act(i.id, 'done')}>Done</s-button>
                  </div>
                </article>
              );
            })}
          </div>
          <p className="oo-muted pf-small">Done hides an item until the next check; if the cause is still there, it comes back. {checkedAt ? `Last checked ${ago(checkedAt)}.` : ''}</p>
        </>
      )}
    </Page>
  );
}
