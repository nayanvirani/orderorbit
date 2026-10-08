import { ActionButton } from '../../components/form.jsx';
import { ago, Hero, number, Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import { PlanNote, WorkflowStatus } from './_shared.jsx';

export default function Automation({ workflows, stats, automationOn, docsUrl }) {
  return (
    <Page heading="Automation">
      <Hero eyebrow="Automation" title="Follow up <em>automatically.</em>" lead="Workflows react to orders, customers and Growvia events: wait, check conditions, then tag, create codes, send emails, notify your team or call a webhook." icon="bolt" tone="default">
        <s-button variant="primary" href={appUrl(route('app.automation.templates'))}>Start from a template</s-button>
        <ActionButton url={route('app.automation.store')}>Blank workflow</ActionButton>
        <s-button href={docsUrl} target="_blank" variant="tertiary">View documentation</s-button>
      </Hero>
      <PlanNote automationOn={automationOn} />

      <s-section>
        <div className="ob-kpis">
          <div className="ob-kpi"><small>Enabled workflows</small><b>{stats.enabled}</b></div>
          <div className="ob-kpi"><small>Runs · 30 days</small><b>{number(stats.runs)}</b></div>
          <div className="ob-kpi"><small>Waiting</small><b>{number(stats.waiting)}</b></div>
          <div className="ob-kpi"><small>Failed · 30 days</small><b>{number(stats.failed)}</b></div>
        </div>
      </s-section>

      <s-section heading="Workflows">
        {!workflows.length ? (
          <>
            <s-paragraph>No workflows yet. Templates cover review requests, welcomes, VIPs, reorder reminders, win-backs and more.</s-paragraph>
            <s-button href={appUrl(route('app.automation.templates'))}>Browse templates</s-button>
          </>
        ) : (
          <table className="oo-table stack">
            <thead><tr><th>Workflow</th><th>Starts when</th><th>Status</th><th>Runs · 30 days</th><th>Last run</th></tr></thead>
            <tbody>
              {workflows.map((w) => (
                <tr key={w.id}>
                  <td data-label="Workflow"><s-link href={appUrl(route('app.automation.edit', { workflow: w.id }))}>{w.name}</s-link>{w.unpublished && <> <s-badge>Unpublished changes</s-badge></>}</td>
                  <td data-label="Starts when">{w.trigger}</td>
                  <td data-label="Status"><WorkflowStatus status={w.status} /></td>
                  <td data-label="Runs">{number(w.runs)}{w.failed > 0 && <span className="oo-muted"> · {w.failed} failed</span>}</td>
                  <td data-label="Last run">{w.last_run_at ? ago(w.last_run_at) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </s-section>
    </Page>
  );
}
