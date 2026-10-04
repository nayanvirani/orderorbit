import { ago, Page, Pagination } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useUrl, withQuery } from '../../router.jsx';
import { PlanNote, RunStatus, utc } from './_shared.jsx';

const FILTERS = [['', 'All'], ['running', 'Running'], ['waiting', 'Waiting'], ['completed', 'Completed'], ['failed', 'Failed']];

export default function Runs({ runs, status, workflow, workflowOptions, automationOn }) {
  const url = useUrl();
  const { visit } = useRouter();
  return (
    <Page heading="Workflow runs" back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      <s-section>
        <div className="oo-inline" style={{ marginBottom: 12 }}>
          {FILTERS.map(([value, label]) => (
            <s-button key={label} href={appUrl(route('app.automation.runs', { status: value || null, workflow: workflow || null }))} variant={status === value ? 'primary' : 'secondary'}>{label}</s-button>
          ))}
          <select className="oo-select" aria-label="Workflow" value={workflow || ''} onChange={(e) => visit(withQuery(url, { workflow: e.target.value || null, page: null }))}>
            <option value="">All workflows</option>
            {workflowOptions.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
          </select>
        </div>
        {!runs.data.length ? <s-paragraph>No runs yet. Runs appear here once an enabled workflow is triggered.</s-paragraph> : (
          <>
            <table className="oo-table stack">
              <thead><tr><th>Run</th><th>Workflow</th><th>Trigger</th><th>For</th><th>Status</th><th>Started</th><th>Duration</th></tr></thead>
              <tbody>
                {runs.data.map((r) => (
                  <tr key={r.id}>
                    <td data-label="Run"><s-link href={appUrl(route('app.automation.runs.show', { run: r.id }))}>#{r.id}</s-link>{r.test && <> <s-badge>Test</s-badge></>}</td>
                    <td data-label="Workflow">{r.workflow || '—'}</td>
                    <td data-label="Trigger">{r.trigger}</td>
                    <td data-label="For">{r.subject}</td>
                    <td data-label="Status"><RunStatus status={r.status} />{r.resume_at && <span className="oo-muted"> until {utc(r.resume_at)}</span>}</td>
                    <td data-label="Started">{ago(r.created_at)}</td>
                    <td data-label="Duration">{r.duration}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <Pagination paginator={runs} url={url} />
          </>
        )}
      </s-section>
    </Page>
  );
}
