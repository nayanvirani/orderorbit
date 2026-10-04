import { ago, Page, Pagination } from '../../components/ui.jsx';
import { route, useUrl } from '../../router.jsx';
import { PlanNote } from './_shared.jsx';

const STATUS = { sent: ['success', 'Sent'], failed: ['critical', 'Failed'] };

export default function Emails({ emails, automationOn }) {
  const url = useUrl();
  return (
    <Page heading="Emails" back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      <s-banner tone="info" heading="Email sending isn't connected yet">
        <s-paragraph>Workflows prepare their emails and keep them here. Sending starts once an email provider is connected; nothing has been sent to customers yet.</s-paragraph>
      </s-banner>
      <s-section heading="Prepared emails">
        {!emails.data.length ? <s-paragraph>No emails yet. Workflows with a "Send email" step prepare them here.</s-paragraph> : (
          <>
            <table className="oo-table stack">
              <thead><tr><th>Subject</th><th>For</th><th>Status</th><th>Prepared</th></tr></thead>
              <tbody>
                {emails.data.map((e) => {
                  const [tone, label] = STATUS[e.status] || ['info', 'Waiting for email provider'];
                  return (
                    <tr key={e.id}>
                      <td data-label="Subject"><details><summary>{e.subject}</summary><pre style={{ whiteSpace: 'pre-wrap', font: 'inherit', margin: '8px 0 0' }}>{e.body}</pre></details></td>
                      <td data-label="For">{e.customer_id ? `Customer ${e.customer_id}` : '—'}</td>
                      <td data-label="Status"><s-badge tone={tone}>{label}</s-badge></td>
                      <td data-label="Prepared">{ago(e.created_at)}</td>
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
