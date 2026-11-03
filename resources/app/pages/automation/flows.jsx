// Workflows (automation redesign): plan usage, runs, emails and anything needing attention, then one
// row per workflow with an on/off switch and the flow as coloured pills.
import { ActionButton } from '../../components/form.jsx';
import { ago, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { PlanNote, Pills } from './_shared.jsx';

export default function Workflows({ workflows, stats, plan, emails, automationOn }) {
  const { submit } = useRouter();
  const finished = stats.runs ? Math.round(((stats.runs - stats.failed) / stats.runs) * 100) : null;
  const runsUrl = (params) => appUrl(route('app.automation.runs', params));

  return (
    <Page heading="Workflows">
      <PlanNote automationOn={automationOn} />
      <div className="fl-list-head">
        <p className="oo-muted">Follow-ups that run on their own when orders and customers change: VIP rewards, reorder reminders, product guides, refund follow-ups and more.</p>
        <div className="fl-buttons">
          <s-button href={appUrl(route('app.automation.templates'))}>Browse templates</s-button>
          <ActionButton variant="primary" url={route('app.automation.store')}>Create workflow</ActionButton>
        </div>
      </div>

      <div className="fl-kpis">
        <div><small>Workflows on</small><b>{stats.enabled}{plan.workflows !== null && <span> of {plan.workflows}</span>}</b><span>{plan.workflows !== null ? `${plan.name} plan: ${plan.workflows} allowed` : `${plan.name} plan: no limit`}</span></div>
        <div><small>Runs · 30 days</small><b>{number(stats.runs)}</b><span>{finished === null ? 'No runs yet' : `${finished}% finished without errors`}</span></div>
        <div><small>Emails · 30 days</small><b>{number(emails?.sent || 0)}</b><span>{emails?.total ? `sent of ${number(emails.total)} prepared` : 'None yet'}</span></div>
        <a className={stats.failed ? 'alert' : ''} href={runsUrl({ status: 'failed' })}><small>Needs attention</small><b>{number(stats.failed)}</b><span>{stats.failed ? 'Failed runs to check' : 'Nothing failed'}</span></a>
      </div>

      {!workflows.length ? (
        <div className="fl-empty-state">
          <h2>No workflows yet</h2>
          <p className="oo-muted">Start from a template, such as a VIP reward or a reorder reminder, or build your own.</p>
          <s-button variant="primary" href={appUrl(route('app.automation.templates'))}>Browse templates</s-button>
        </div>
      ) : (
        <div className="fl-rows">
          {workflows.map((w) => (
            <div className="fl-row" key={w.id}>
              <button type="button" role="switch" aria-checked={w.status === 'enabled'} className="fl-switch" disabled={!w.published}
                title={w.published ? (w.status === 'enabled' ? 'Turn off' : 'Turn on') : 'Publish it first'}
                aria-label={`${w.name}: ${w.status === 'enabled' ? 'on' : 'off'}`}
                onClick={() => submit(route('app.automation.toggle', { workflow: w.id }), {}, { confirm: w.status === 'enabled' ? `Turn off "${w.name}"? New orders won't start it until you turn it on again.` : undefined })}>
                <span />
              </button>
              <div className="fl-row-main">
                <div className="fl-row-title">
                  <a href={appUrl(route('app.automation.edit', { workflow: w.id }))}>{w.name}</a>
                  {!w.published && <span className="fl-chip">Draft</span>}
                  {w.unpublished && <span className="fl-chip warn">Unpublished changes</span>}
                  {w.failed > 0 && <a className="fl-chip bad" href={runsUrl({ workflow: w.id, status: 'failed' })}>{w.failed} failed</a>}
                </div>
                <Pills pills={w.pills} />
              </div>
              <div className="fl-row-stat"><b>{number(w.runs)}</b><small>runs · 30 days</small></div>
              <div className="fl-row-stat"><b>{w.last_run_at ? ago(w.last_run_at) : '—'}</b><small>last run</small></div>
              <s-button href={appUrl(route('app.automation.edit', { workflow: w.id }))}>Edit</s-button>
            </div>
          ))}
        </div>
      )}
    </Page>
  );
}
