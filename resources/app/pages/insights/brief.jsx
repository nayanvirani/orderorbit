// Insights › Morning Brief (G17): yesterday in a few lines, written by Growvia from your numbers
// (or by AI when it's switched on). Every line links to the number it comes from; on a quiet day
// there's no brief.
import { Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';

const STATUS = {
  quiet: 'Nothing notable changed, so there was no brief.',
  failed: 'The brief couldn\'t be written this morning.',
};

function Brief({ b, today }) {
  const day = new Date(`${b.day}T12:00:00`).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
  return (
    <s-section heading={today ? `Today · ${day}` : day}>
      {b.status === 'ready' && <small className="oo-muted">{b.source === 'ai' ? 'Written by AI from your numbers' : 'Written by Growvia from your numbers'}</small>}
      {b.status !== 'ready' ? <p className="oo-muted">{STATUS[b.status] ?? STATUS.failed}</p> : (
        <ol className="mb-lines">
          {b.lines.map((l, i) => (
            <li key={i}>
              <span>{l.text}</span>
              <small>{l.facts.map((id) => b.facts[id] && <a key={id} href={appUrl(b.facts[id].href)}>{b.facts[id].label}: {b.facts[id].value}</a>)}</small>
            </li>
          ))}
        </ol>
      )}
    </s-section>
  );
}

export default function MorningBrief({ briefs, locked }) {
  const today = new Date().toLocaleDateString('en-CA');
  return (
    <Page heading="Morning Brief">
      <p className="oo-muted" style={{ marginTop: 0 }}>Every morning: yesterday against your usual, what changed and the one thing to do today. Each line links to the number it comes from.</p>
      {locked && <s-banner tone="info">{locked} <a href={appUrl(route('app.settings.billing'))}>See plans</a></s-banner>}
      {!briefs.length && !locked && <div className="pt-empty">Your first brief arrives tomorrow morning.</div>}
      {briefs.map((b) => <Brief key={b.day} b={b} today={b.day === today} />)}
    </Page>
  );
}
