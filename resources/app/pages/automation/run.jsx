import { ActionButton } from '../../components/form.jsx';
import { KeyValue, Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import { RunStatus, utc } from './_shared.jsx';

const when = (v) => new Date(v).toLocaleString('en-US', { timeZone: 'UTC', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

export default function Run({ run }) {
  return (
    <Page heading={`Run #${run.id}${run.test ? ' (test)' : ''}`} back={route('app.automation.runs')} backLabel="Runs">
      {run.test && (
        <s-banner tone="info" heading="Test run">
          <s-paragraph>This run used a sample order and changed nothing: waits were skipped and each action says what it would do.</s-paragraph>
        </s-banner>
      )}
      <s-section heading="Summary">
        <KeyValue rows={[
          ['Workflow', <>{run.workflow ? <s-link href={appUrl(route('app.automation.edit', { workflow: run.workflow_id }))}>{run.workflow}</s-link> : '—'} {run.version ? `· version ${run.version}` : ''}</>],
          ['For', run.subject],
          ['Status', <><RunStatus status={run.status} />{run.resume_at && <span className="oo-muted"> until {utc(run.resume_at)} UTC</span>}</>],
          ['Trigger', run.trigger],
          ['Started', `${utc(run.created_at)} UTC`],
          run.finished_at && ['Finished', `${utc(run.finished_at)} UTC`],
          ['Duration', `${run.duration}${run.finished_at ? '' : ' so far'}`],
          run.attempts > 0 && ['Attempts', run.attempts],
          run.error && ['Last error', run.error],
          ['Idempotency key', <><span className="oo-code">{run.idempotency_key}</span> <span className="oo-muted oo-small">The same Shopify event never starts this workflow twice.</span></>],
        ]} />
        {run.can_retry && (
          <div className="ui-row" style={{ marginTop: 12 }}>
            <ActionButton variant="primary" url={route('app.automation.runs.retry', { run: run.id })}>Retry from the failed step</ActionButton>
            <span className="oo-muted oo-small">Steps that already finished won't run again.</span>
          </div>
        )}
      </s-section>
      <s-section heading="Log">
        <table className="oo-table stack">
          <thead><tr><th>Step</th><th>What happened</th><th>Result</th><th>When</th></tr></thead>
          <tbody>
            {run.logs.map((l) => (
              <tr key={l.id}>
                <td data-label="Step">{l.step ?? '—'}</td>
                <td data-label="What happened">{l.message}</td>
                <td data-label="Result"><RunStatus status={l.status} /></td>
                <td data-label="When">{when(l.created_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </s-section>
    </Page>
  );
}
