import { useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { ago, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import AudienceNotes from './_nav.jsx';

function RuleRow({ rule, fields, experiences, error, onChange, onRemove }) {
  const def = fields[rule.field] || fields.orders_count;
  const type = def.type === 'money' ? 'number' : def.type;
  const setField = (field) => {
    const d = fields[field];
    onChange({ field, op: Object.keys(d.ops)[0], value: d.type === 'select' ? Object.keys(d.options)[0] : d.type === 'products' ? [] : '' });
  };
  const pick = async () => {
    if (!window.shopify?.resourcePicker) return;
    const picked = await window.shopify.resourcePicker({ type: 'product', multiple: true });
    if (picked) onChange({ ...rule, value: picked.map((p) => ({ id: p.id, title: p.title })) });
  };
  const value = rule.value ?? '';
  return (
    <div className={`au-rule ${error ? 'b-has-error' : ''}`}>
      <select value={rule.field} onChange={(e) => setField(e.target.value)} aria-label="Rule">
        {Object.entries(fields).map(([k, f]) => <option key={k} value={k}>{f.label}</option>)}
      </select>
      <select value={rule.op} onChange={(e) => onChange({ ...rule, op: e.target.value })} aria-label="Condition">
        {Object.entries(def.ops).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
      </select>
      {type === 'number' && <span><input type="number" min="0" step="any" value={typeof value === 'object' ? '' : value} onChange={(e) => onChange({ ...rule, value: e.target.value })} aria-label="Value" /></span>}
      {type === 'text' && <span><input type="text" maxLength={200} value={typeof value === 'object' ? '' : value} onChange={(e) => onChange({ ...rule, value: e.target.value })} aria-label="Value" /></span>}
      {type === 'select' && <span><select value={value} onChange={(e) => onChange({ ...rule, value: e.target.value })} aria-label="Value">{Object.entries(def.options || {}).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></span>}
      {type === 'products' && (
        <span className="au-pick">
          <button type="button" className="b-btn" onClick={pick}>Choose products</button>
          <span className="oo-muted oo-small">{Array.isArray(value) && value.length ? value.map((p) => p.title || p.id).join(', ') : 'No products chosen'}</span>
        </span>
      )}
      {type === 'experience' && (
        <span>
          <select value={value} onChange={(e) => onChange({ ...rule, value: e.target.value })} aria-label="Experience">
            <option value="">Choose a widget</option>
            {experiences.map((e) => <option key={e.handle} value={e.handle}>{e.name}</option>)}
          </select>
        </span>
      )}
      <button type="button" className="au-x" onClick={onRemove} aria-label="Remove rule">✕</button>
      <p className="b-help au-help-line">{def.help || ''}</p>
      {error && <p className="b-error">{error}</p>}
    </div>
  );
}

export default function Segment(props) {
  const { segment: s, fieldErrors: errors, usage, fields, experiences } = props;
  const { submit } = useRouter();
  const [name, setName] = useState(s.name);
  const [description, setDescription] = useState(s.description || '');
  const [match, setMatch] = useState(s.match);
  const [rules, setRules] = useState(s.rules);
  const [busy, setBusy] = useState(false);
  const hasErrors = Object.keys(errors).length > 0;

  const save = async () => {
    setBusy(true);
    await submit(route('app.audiences.segments.update', { segment: s.id }), { name, description, match, rules });
    setBusy(false);
  };

  return (
    <Page heading={s.name} back={route('app.audiences.segments')} backLabel="Audiences">
      <AudienceNotes {...props} />
      {hasErrors && <s-banner tone="warning">Saved, but some rules need attention before the segment can be used.</s-banner>}
      {s.archived && <s-banner tone="info">This segment is archived. Restore it to use it again.</s-banner>}

      <s-section heading="Segment">
        <div className={`b-field ${errors.name ? 'b-has-error' : ''}`}><label htmlFor="sg-name">Name</label><input id="sg-name" type="text" value={name} maxLength={80} onChange={(e) => setName(e.target.value)} />{errors.name && <p className="b-error">{errors.name}</p>}</div>
        <div className="b-field"><label htmlFor="sg-desc">Description (optional)</label><input id="sg-desc" type="text" value={description} maxLength={300} onChange={(e) => setDescription(e.target.value)} /></div>
      </s-section>

      <s-section heading="Who's in it">
        <div className="b-field">
          <label htmlFor="sg-match">Shoppers who match</label>
          <select id="sg-match" value={match} onChange={(e) => setMatch(e.target.value)}><option value="all">All of these rules</option><option value="any">Any of these rules</option></select>
        </div>
        {errors.rules && <p className="b-error">{errors.rules}</p>}
        <div className="au-rules">
          {rules.map((r, i) => (
            <RuleRow key={i} rule={r} fields={fields} experiences={experiences} error={errors[`rules.${i}`]}
              onChange={(next) => setRules(rules.map((x, j) => (j === i ? next : x)))} onRemove={() => setRules(rules.filter((_, j) => j !== i))} />
          ))}
        </div>
        <s-button onClick={() => setRules([...rules, { field: 'orders_count', op: 'gte', value: '' }])}>Add rule</s-button>
        <details className="au-help">
          <summary>Where the data comes from</summary>
          <p className="oo-small">Orders, total spent, average order, days since last order, tags and purchased products come from the signed-in customer's account (guests have 0 orders). Market and device come from the visit. "Used a widget" is remembered on the shopper's device. Everything is worked out on your store; nothing about the shopper is sent to OrderOrbit Space.</p>
        </details>
      </s-section>

      <s-section heading="Members">
        {s.countable ? (
          <p>{s.member_count !== null ? `${number(s.member_count)} customers in Shopify match` : 'Not counted yet.'}{s.counted_at && <span className="oo-muted oo-small"> · counted {ago(s.counted_at)}</span>}</p>
        ) : <p className="oo-muted">Count unavailable: this segment uses browsing or purchase rules that are only known during a visit.</p>}
      </s-section>

      <div className="oo-inline" style={{ margin: '0 0 16px' }}>
        <s-button variant="primary" onClick={save} loading={busy || undefined}>Save segment</s-button>
      </div>

      <s-section heading="Used by">
        {!usage.length ? (
          <s-paragraph><span className="oo-muted">Not used yet. Target a widget with it (Targeting → Only these segments), add a personalization rule, use it as an A/B test audience, or check it in a workflow condition.</span></s-paragraph>
        ) : (
          <ul className="au-usage">{usage.map((u) => <li key={u.kind + u.href}>{u.kind} · <s-link href={appUrl(u.href)}>{u.name}</s-link></li>)}</ul>
        )}
        <div className="oo-inline" style={{ marginTop: 12 }}>
          {s.countable && <ActionButton variant="tertiary" url={route('app.audiences.segments.count', { segment: s.id })}>Recount members</ActionButton>}
          <ActionButton variant="tertiary" url={route('app.audiences.segments.duplicate', { segment: s.id })}>Duplicate</ActionButton>
          <ActionButton variant="tertiary" tone={s.archived ? undefined : 'critical'} url={route('app.audiences.segments.archive', { segment: s.id })} confirm={s.archived ? undefined : 'Archive this segment?'}>{s.archived ? 'Restore' : 'Archive'}</ActionButton>
        </div>
      </s-section>
    </Page>
  );
}
