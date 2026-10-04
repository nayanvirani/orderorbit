import { ActionButton } from '../../components/form.jsx';
import { ago, date, Page } from '../../components/ui.jsx';
import { route } from '../../router.jsx';
import { PlanNote } from './_shared.jsx';

export default function Inbox({ open, done, automationOn }) {
  return (
    <Page heading="Inbox" back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      <s-section heading="To do">
        {!open.length ? <s-paragraph>Nothing to do. Tasks and notifications from your workflows appear here.</s-paragraph> : (
          <table className="oo-table stack">
            <thead><tr><th>Item</th><th>Type</th><th>Due</th><th /></tr></thead>
            <tbody>
              {open.map((item) => (
                <tr key={item.id}>
                  <td data-label="Item"><strong>{item.title}</strong>{item.body && <><br /><span className="oo-muted">{item.body}</span></>}</td>
                  <td data-label="Type">{item.kind === 'task' ? 'Task' : 'Notification'}</td>
                  <td data-label="Due">{item.due_at ? date(item.due_at) : '—'}</td>
                  <td><ActionButton url={route('app.automation.inbox.done', { item: item.id })}>{item.kind === 'task' ? 'Mark done' : 'Dismiss'}</ActionButton></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </s-section>
      {done.length > 0 && (
        <s-section heading="Done recently">
          {done.map((item) => <s-paragraph key={item.id}><span className="oo-muted">✓ {item.title} · {ago(item.done_at)}</span></s-paragraph>)}
        </s-section>
      )}
    </Page>
  );
}
