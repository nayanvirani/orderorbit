import { Upgrade } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';

const STATUS = { completed: ['success', 'Completed'], waiting: ['info', 'Waiting'], failed: ['critical', 'Failed'], running: ['warning', 'Running'], ok: ['success', 'Done'], skipped: ['neutral', 'Skipped'] };

/** Run and log status badge. */
export function RunStatus({ status }) {
  const [tone, label] = STATUS[status] || ['neutral', status ? status[0].toUpperCase() + status.slice(1) : '—'];
  return <s-badge tone={tone}>{label}</s-badge>;
}

/** Workflow status badge. */
export function WorkflowStatus({ status }) {
  if (status === 'enabled') return <s-badge tone="success">Enabled</s-badge>;
  if (status === 'disabled') return <s-badge>Disabled</s-badge>;
  return <s-badge tone="info">Draft</s-badge>;
}

/** The plan note when workflows can't run yet. */
export function PlanNote({ automationOn }) {
  if (automationOn) return null;
  return (
    <Upgrade feature="automation">You can build and test workflows now. Publishing them, so they run on real orders, needs a plan with automation.</Upgrade>
  );
}

/** "Oct 4, 2026, 9:41 PM" in UTC, like the server's toDayDateTimeString. */
export function utc(value) {
  return value ? new Date(value).toLocaleString('en-US', { timeZone: 'UTC', weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';
}
