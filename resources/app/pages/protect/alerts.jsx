// Protect › Alerts (G7): problems that quietly cost money. Each says what happened, what it costs and
// how to fix it, and closes itself once fixed. Checked every hour.
import { useState } from 'react';
import { ago, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { CheckNow, ProfitNotices } from '../profit/_profit.jsx';

const SEV = { urgent: ['critical', 'Urgent'], warning: ['warning', 'Warning'] };

function Alert({ a, act }) {
  const external = /^https?:/.test(a.cta_url || '');
  return (
    <article className="pt-alert">
      <div className="pt-alert-head"><s-badge tone={SEV[a.severity]?.[0]}>{SEV[a.severity]?.[1]}</s-badge><span className="oo-muted">{ago(a.opened_at)}</span></div>
      <h2>{a.title}</h2>
      <div className="pt-alert-grid">
        <div><small>What happened</small>{a.what}</div>
        <div><small>What it costs</small><b>{a.cost_text}</b></div>
        <div><small>How to fix</small>{a.fix}</div>
      </div>
      <div className="pf-bar">
        {a.cta && <s-button variant="primary" href={external ? a.cta_url : appUrl(a.cta_url)} target={external ? '_blank' : undefined}>{a.cta}</s-button>}
        {a.status === 'open' ? (
          <>
            <s-button onClick={() => act(a.id, 'snooze')}>Snooze 7 days</s-button>
            <s-button variant="tertiary" onClick={() => act(a.id, 'mute')}>Mute this alert</s-button>
            <s-button variant="tertiary" onClick={() => act(a.id, 'done')}>Done</s-button>
          </>
        ) : <s-button onClick={() => act(a.id, 'restore')}>{a.status === 'muted' ? 'Unmute' : 'Bring back now'}</s-button>}
      </div>
    </article>
  );
}

export default function ProtectAlerts({ open, counts, snoozed, rules, alertsCheckedAt, access, syncing, locked, pending, checkedAt }) {
  const { submit } = useRouter();
  const [on, setOn] = useState(Object.fromEntries(rules.map((r) => [r.key, r.on])));
  const act = (id, action) => submit(route('app.protect.alerts.action', { alert: id }), { action });
  const save = () => submit(route('app.protect.settings'), { rules: on, from: 'alerts' });

  return (
    <Page heading="Alerts">
      <p className="oo-muted" style={{ marginTop: 0 }}>Problems that quietly cost money. Each one says what happened, what it costs and how to fix it, and closes itself once fixed.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && <CheckNow from="alerts" checkedAt={checkedAt} pending={pending} />}
      {!access && !pending && (
        <div className="pt-layout">
          <div className="pt-list">
            <p className="pt-counts"><b>{open.length} open</b> <span className="oo-muted">· {counts.snoozed} snoozed · {counts.muted} muted · {counts.closed} closed this month{alertsCheckedAt ? ` · checked ${ago(alertsCheckedAt)}` : ''}</span></p>
            {!open.length && <div className="pt-empty">Nothing is leaking money right now. Growvia checks again every hour.</div>}
            {open.map((a) => <Alert key={a.id} a={a} act={act} />)}
            {snoozed.length > 0 && (
              <details className="pt-snoozed">
                <summary>Snoozed and muted ({snoozed.length})</summary>
                {snoozed.map((a) => <Alert key={a.id} a={a} act={act} />)}
              </details>
            )}
          </div>
          <s-section heading="Alert settings">
            {rules.map((r) => (
              <label className="pt-rule" key={r.key}>
                <span><b>{r.name}</b><small>{r.when}</small></span>
                {r.locked ? <s-badge>{r.locked}</s-badge> : <input type="checkbox" checked={!!on[r.key]} onChange={(e) => setOn({ ...on, [r.key]: e.target.checked })} />}
              </label>
            ))}
            <p className="oo-muted pf-small">Each alert waits 24 hours before it's emailed again. Email goes to people who can see profit.</p>
            <s-button onClick={save}>Save settings</s-button>
          </s-section>
        </div>
      )}
    </Page>
  );
}
