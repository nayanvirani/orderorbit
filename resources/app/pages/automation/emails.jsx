import { ago, Page, Pagination } from '../../components/ui.jsx';
import { route, useUrl } from '../../router.jsx';
import { PlanNote } from './_shared.jsx';

const STATUS = {
  sent: ['success', 'Sent'],
  queued: ['info', 'Sending'],
  retrying: ['attention', 'Retrying'],
  waiting_for_provider: ['info', 'Waiting to send'],
  failed: ['critical', 'Not sent'],
  expired: ['warning', 'Expired'],
};

export default function Emails({ emails, automationOn, sending }) {
  const url = useUrl();
  return (
    <Page heading="Emails" back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      {!sending && (
        <s-banner tone="info" heading="Email sending is paused">
          <s-paragraph>Workflow emails are kept here and go out automatically once sending resumes. Emails not sent within 3 days are dropped so customers never get them late.</s-paragraph>
        </s-banner>
      )}
      <s-section heading="Workflow emails">
        {!emails.data.length ? <s-paragraph>No emails yet. Workflows with a "Send email" step send them to your customers and list them here.</s-paragraph> : (
          <>
            <table className="oo-table stack">
              <thead><tr><th>Subject</th><th>To</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>
                {emails.data.map((e) => {
                  const [tone, label] = STATUS[e.status] || ['info', e.status];
                  return (
                    <tr key={e.id}>
                      <td data-label="Subject"><details><summary>{e.subject}</summary><pre style={{ whiteSpace: 'pre-wrap', font: 'inherit', margin: '8px 0 0' }}>{e.body}</pre></details></td>
                      <td data-label="To">{e.to || (e.customer_id ? `Customer ${e.customer_id}` : '—')}</td>
                      <td data-label="Status">
                        <s-badge tone={tone}>{label}</s-badge>
                        {e.status === 'sent' && e.sent_at && <div className="oo-muted oo-small">{ago(e.sent_at)}</div>}
                        {e.reason && <div className="oo-muted oo-small">{e.reason}</div>}
                      </td>
                      <td data-label="Created">{ago(e.created_at)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <Pagination paginator={emails} url={url} />
          </>
        )}
      </s-section>
    </Page>
  );
}
