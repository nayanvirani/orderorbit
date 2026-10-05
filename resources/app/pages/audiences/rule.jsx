import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import AudienceNotes from './_nav.jsx';

const HELP = {
  show: 'Everyone else doesn\'t see it. Example: show the VIP offer only to VIP customers.',
  swap: 'Matching shoppers see the same widget in a different layout. Example: the compact bundle template on mobile.',
  hide: 'Matching shoppers don\'t see it. Example: hide the first-order discount from returning customers.',
};

export default function Rule(props) {
  const { rule, fieldErrors: errors, experiences, segments, outcomes, currency } = props;
  const { submit } = useRouter();
  const [data, setData] = useState({
    name: rule.name || '', experience_id: rule.experience_id || '', outcome: rule.outcome, template_key: rule.template_key || '',
    segments: rule.segments, conditions: { device: '', cart_min: '', cart_max: '', utm_source: '', utm_campaign: '', ...rule.conditions }, enabled: rule.enabled,
  });
  const [busy, setBusy] = useState(false);
  const set = (k, v) => setData((d) => ({ ...d, [k]: v }));
  const setCond = (k, v) => setData((d) => ({ ...d, conditions: { ...d.conditions, [k]: v } }));
  const templates = experiences.find((e) => String(e.id) === String(data.experience_id))?.templates || {};

  const save = async () => {
    setBusy(true);
    const url = rule.id ? route('app.audiences.rules.update', { rule: rule.id }) : route('app.audiences.rules.store');
    await submit(url, { ...data, template_key: data.template_key || Object.keys(templates)[0] || '' });
    setBusy(false);
  };

  return (
    <Page heading={rule.id ? rule.name : 'New personalization rule'} back={route('app.audiences.rules')} backLabel="Rules">
      <AudienceNotes {...props} />
      {Object.keys(errors).length > 0 && <s-banner tone="warning">Fix the highlighted fields.</s-banner>}

      <s-section heading="1. Which widget">
        <div className={`b-field ${errors.experience_id ? 'b-has-error' : ''}`}>
          <label htmlFor="ru-exp">Widget</label>
          <select id="ru-exp" value={data.experience_id} onChange={(e) => set('experience_id', e.target.value)}>
            <option value="">Choose a widget</option>
            {experiences.map((e) => <option key={e.id} value={e.id}>{e.label}</option>)}
          </select>
          {errors.experience_id && <p className="b-error">{errors.experience_id}</p>}
        </div>
      </s-section>

      <s-section heading="2. For whom">
        <p className="oo-muted">Shoppers in any of the chosen segments, who also match the live conditions you set.</p>
        <div className={`b-field ${errors.segments ? 'b-has-error' : ''}`}>
          <span className="b-label">Segments</span>
          {!segments.length ? (
            <p className="b-help">No segments yet. <a href={appUrl(route('app.audiences.segments'))}>Create one</a>, or use only the conditions below.</p>
          ) : (
            <div className="b-checks">
              {segments.map((s) => (
                <label key={s.id}><input type="checkbox" checked={data.segments.includes(s.id)} onChange={(e) => set('segments', e.target.checked ? [...data.segments, s.id] : data.segments.filter((x) => x !== s.id))} /> {s.name}</label>
              ))}
            </div>
          )}
          {errors.segments && <p className="b-error">{errors.segments}</p>}
        </div>
        <div className="oo-form-row">
          <label className="oo-field">Device<select value={data.conditions.device} onChange={(e) => setCond('device', e.target.value)}><option value="">Any</option><option value="mobile">Mobile</option><option value="desktop">Desktop</option></select></label>
          <label className="oo-field">Cart value at least ({currency})<input type="number" step="0.01" min="0" value={data.conditions.cart_min} onChange={(e) => setCond('cart_min', e.target.value)} /></label>
          <label className="oo-field">Cart value at most<input type="number" step="0.01" min="0" value={data.conditions.cart_max} onChange={(e) => setCond('cart_max', e.target.value)} /></label>
          <label className="oo-field">UTM source<input type="text" maxLength={100} value={data.conditions.utm_source} onChange={(e) => setCond('utm_source', e.target.value)} /></label>
          <label className="oo-field">UTM campaign<input type="text" maxLength={100} value={data.conditions.utm_campaign} onChange={(e) => setCond('utm_campaign', e.target.value)} /></label>
        </div>
        {errors.conditions && <p className="b-error">{errors.conditions}</p>}
      </s-section>

      <s-section heading="3. Then">
        {Object.entries(outcomes).map(([key, label]) => (
          <label className="oo-radio" key={key}>
            <input type="radio" name="outcome" checked={data.outcome === key} onChange={() => set('outcome', key)} />
            <span><strong>{label}</strong><small>{HELP[key]}</small></span>
          </label>
        ))}
        {data.outcome === 'swap' && (
          <div className={`b-field ${errors.template_key ? 'b-has-error' : ''}`}>
            <label htmlFor="ru-tpl">Template to show</label>
            <select id="ru-tpl" value={data.template_key} onChange={(e) => set('template_key', e.target.value)}>
              {Object.entries(templates).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            {errors.template_key && <p className="b-error">{errors.template_key}</p>}
          </div>
        )}
      </s-section>

      <s-section heading="4. Name and status">
        <div className="b-field"><label htmlFor="ru-name">Rule name (optional)</label><input id="ru-name" type="text" maxLength={80} value={data.name} onChange={(e) => set('name', e.target.value)} placeholder="Returning + cart over $75 → premium upsell" /></div>
        <div className="b-field b-toggle"><label><input type="checkbox" checked={data.enabled} onChange={(e) => set('enabled', e.target.checked)} /> <span>Enabled</span></label></div>
        <div className="oo-inline"><s-button variant="primary" onClick={save} loading={busy || undefined}>Save rule</s-button></div>
      </s-section>
    </Page>
  );
}
