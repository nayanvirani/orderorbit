import { ActionButton } from '../../components/form.jsx';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import AudienceNotes from './_nav.jsx';

const THEN = { show: 'show', swap: 'show in another template:', hide: 'hide' };

export default function Rules(props) {
  const { rules, conflicts } = props;
  const { can } = useShared();
  const act = (r, action) => route('app.audiences.rules.action', { rule: r.id, action });
  return (
    <Page heading="Audiences" back={route('app.audiences.segments')} backLabel="Audiences">
      <AudienceNotes {...props} />
      <s-section heading="Personalization rules">
        <p className="oo-muted">If a shopper matches a rule, it shows a widget only to them, shows it in another template, or hides it. Rules run top to bottom: for each widget, the first rule that matches wins.</p>
        {conflicts.map((names, i) => <s-banner key={i} tone="warning">“{names.join('”, “')}” target the same experience. When a shopper matches more than one, the one higher in the list wins.</s-banner>)}
        {can.manage_experiences && <div style={{ margin: '12px 0' }}><s-button href={appUrl(route('app.audiences.rules.create'))} variant="primary">Create rule</s-button></div>}
        {!rules.length ? (
          <s-paragraph><span className="oo-muted">No rules yet. Examples: returning customers with a cart over $75 → show the premium upsell; mobile shoppers → show the bundle in its compact template; 3+ orders → show the VIP offer.</span></s-paragraph>
        ) : (
          <ol className="au-rule-list">
            {rules.map((r, i) => (
              <li key={r.id} className={r.enabled ? '' : 'is-off'}>
                <span className="au-pri">{i + 1}</span>
                <div className="au-rule-main">
                  <strong>{can.manage_experiences ? <s-link href={appUrl(route('app.audiences.rules.edit', { rule: r.id }))}>{r.name}</s-link> : r.name}</strong>
                  <span className="oo-small"><b>If</b> {r.who} <b>then</b> {THEN[r.outcome]} {r.template ? `${r.template} ·` : ''} {r.experience}</span>
                  {!r.enabled && <s-badge>Disabled</s-badge>}
                </div>
                {can.manage_experiences && (
                  <div className="au-rule-actions">
                    <ActionButton variant="tertiary" url={act(r, 'up')} disabled={i === 0 || undefined} accessibilityLabel="Move up">↑</ActionButton>
                    <ActionButton variant="tertiary" url={act(r, 'down')} disabled={i === rules.length - 1 || undefined} accessibilityLabel="Move down">↓</ActionButton>
                    <ActionButton variant="tertiary" url={act(r, 'toggle')}>{r.enabled ? 'Disable' : 'Enable'}</ActionButton>
                    <ActionButton variant="tertiary" tone="critical" url={act(r, 'delete')} confirm="Delete this rule?">Delete</ActionButton>
                  </div>
                )}
              </li>
            ))}
          </ol>
        )}
      </s-section>
    </Page>
  );
}
