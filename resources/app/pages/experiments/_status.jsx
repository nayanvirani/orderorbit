const STATUS = { running: ['success', 'Running'], paused: ['warning', 'Paused'], draft: ['neutral', 'Draft'], completed: ['info', 'Completed'], stopped: ['neutral', 'Stopped early'] };

export default function TestStatus({ status }) {
  const [tone, label] = STATUS[status] || ['neutral', status];
  return <s-badge tone={tone}>{label}</s-badge>;
}
