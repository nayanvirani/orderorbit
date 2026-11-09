// Run history (automation redesign): runs on the left, the chosen run step by step on the right.
import { ActionButton } from '../../components/form.jsx';
import { ago, number, Page, Pagination } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useUrl, withQuery } from '../../router.jsx';
import { PlanNote, utc } from './_shared.jsx';

const FILTERS = [['', 'All'], ['completed', 'Finished'], ['failed', 'Failed'], ['waiting', 'Waiting']];
const RUN = { completed: ['ok', 'Finished'], failed: ['bad', 'Failed'], waiting: ['wait', 'Waiting'], running: ['wait', 'Running'], skipped: ['muted', 'Stopped'] };
const STEP = {
  finished: ['ok', '✓', 'Finished'], matched: ['ok', '✓', 'Matched → Then'], no_match: ['muted', '↷', 'Did not match → Otherwise'],
  failed: ['bad', '!', 'Failed'], waiting: ['wait', '⏳', 'Waiting'], skipped: ['muted', '–', 'Skipped'], stopped: ['muted', '■', 'Stopped'], not_reached: ['muted', '·', 'Not reached'],
};

export default function FlowRuns({ runs, status, workflow, workflowOptions, totals, run, automationOn }) {
  const url = useUrl();
  const { visit } = useRouter();
  const link = (params) => appUrl(withQuery(url, params));

  return (
    <Page heading="Run history" back={route('app.automation.index')} backLabel="Workflows">
      <PlanNote automationOn={automationOn} />
      <div className="fl-kpis three">
        <div><small>Runs · 30 days</small><b>{number(totals.runs)}</b></div>
        <a className={totals.failed ? 'alert' : ''} href={link({ status: 'failed', page: null, run: null })}><small>Failed · 30 days</small><b>{number(totals.failed)}</b></a>
        <a href={link({ status: 'waiting', page: null, run: null })}><small>Waiting now</small><b>{number(totals.waiting)}</b></a>
      </div>

      <div className="fl-history">
        <section className="fl-history-list" aria-label="Runs">
          <div className="fl-history-tools">
            <div className="fl-filters" role="group" aria-label="Status">
              {FILTERS.map(([v, l]) => <button type="button" key={l} aria-pressed={status === v} onClick={() => visit(withQuery(url, { status: v || null, page: null, run: null }))}>{l}</button>)}
            </div>
            <select aria-label="Workflow" value={workflow || ''} onChange={(e) => visit(withQuery(url, { workflow: e.target.value || null, page: null, run: null }))}>
              <option value="">All workflows</option>
              {workflowOptions.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
            </select>
          </div>
          {!runs.data.length ? <p className="oo-muted fl-pad">No runs yet. Runs appear here once a workflow that's on is triggered. They're kept for 30 days.</p> : (
            <>
              {runs.data.map((r) => {
                const [tone, label] = RUN[r.status] || ['muted', r.status];
                return (
                  <a key={r.id} className="fl-run" href={link({ run: r.id })} aria-current={run?.id === r.id ? 'true' : undefined}>
                    <span className="fl-run-top"><b>{r.workflow || 'Deleted workflow'}</b><span className={`fl-status ${tone}`}>{label}</span></span>
                    <span className="oo-muted">{r.subject}{r.test ? ' · test' : ''} · {ago(r.created_at)}</span>
                  </a>
                );
              })}
              <Pagination paginator={runs} url={url} />
            </>
          )}
        </section>

        <section className="fl-history-run" aria-label="Run details">
          {!run ? <p className="oo-muted fl-pad">Choose a run to see each step.</p> : (
            <>
              <header className="fl-run-head">
                <div>
                  <h2>{run.workflow || 'Deleted workflow'}{run.test && <span className="fl-chip"> Test</span>}</h2>
                  <p className="oo-muted">{run.subject} · {run.trigger} · started {utc(run.created_at)} UTC · {run.duration}{run.version ? ` · version ${run.version}` : ''}</p>
                </div>
                <span className={`fl-status ${(RUN[run.status] || ['muted'])[0]}`}>{(RUN[run.status] || [null, run.status])[1]}{run.resume_at ? ` until ${utc(run.resume_at)} UTC` : ''}</span>
              </header>
              {run.test && <p className="fl-note">A test run uses your latest order (a sample order when the store has none) and changes nothing: waits are skipped and each action says what it would do.</p>}
              <ol className="fl-steps">
                {run.steps.map((s) => {
                  const [tone, mark, label] = STEP[s.status] || ['muted', '·', s.status];
                  return (
                    <li key={s.id} className={`fl-step ${tone}`}>
                      <span className="fl-mark" aria-hidden="true">{mark}</span>
                      <div>
                        <p className="fl-step-msg">{s.message}</p>
                        {s.seen && <p className="fl-step-seen">What it saw: {s.seen}</p>}
                        <p className="oo-muted">{label}{s.created_at ? ` · ${utc(s.created_at)} UTC` : ''}</p>
                      </div>
                    </li>
                  );
                })}
              </ol>
              <div className="fl-run-foot">
                {run.can_retry && <ActionButton variant="primary" url={route('app.automation.runs.retry', { run: run.id })}>Retry failed step</ActionButton>}
                {run.workflow_id && <s-button href={appUrl(route('app.automation.edit', { workflow: run.workflow_id }))}>Open workflow</s-button>}
              </div>
            </>
          )}
        </section>
      </div>
    </Page>
  );
}
