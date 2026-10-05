import { useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import SchemaField from '../../components/SchemaField.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';

const STEPS = [['experience', 'Experience'], ['variants', 'Variants'], ['traffic', 'Traffic'], ['audience', 'Audience'], ['primary', 'Primary metric'], ['secondary', 'Secondary metrics'], ['guardrails', 'Guardrails'], ['duration', 'Duration'], ['launch', 'Preview & launch']];
const PRIMARY_HELP = {
  conversion_rate: 'Share of visitors who place an order after seeing the widget. Two-proportion z-test.',
  revenue_per_visitor: 'Order revenue divided by visitors, so bigger orders count. Welch\'s t-test.',
  revenue: 'Total revenue, compared per visitor so unequal splits stay fair. Welch\'s t-test.',
  click_rate: 'Share of visitors who click the block (a button, link or accepting its offer). Best for Thank You and Order Status blocks, where the order is already placed. Two-proportion z-test.',
};

export default function ExperimentSetup(props) {
  const { experiment: x, experience, config, templates, textFields, designFields, audienceFields, checkoutBlock, canHoldout, problems, fieldErrors: errors, banner, previews, labels, segments, currency, docsUrl } = props;
  const { submit } = useRouter();
  const ready = useRuntime({ checkout: checkoutBlock });
  const control = x.variants.A?.template_key || experience.template_key;
  const blankVariant = (key) => ({ key, name: key === 'C' ? 'Variant C' : `Variant ${key}`, allocation: 33, hidden: false, template_key: control, content: {}, design: {} });

  const [form, setForm] = useState({
    name: x.name, hypothesis: x.hypothesis || '', audience: { ...x.audience }, primary_metric: x.primary_metric,
    secondary_metrics: x.secondary_metrics, min_days: x.min_days, min_visitors: x.min_visitors, min_conversions: x.min_conversions, ends_at: x.ends_at || '',
    guardrails: Object.fromEntries(Object.keys(labels.guardrails).map((k) => {
      const g = x.guardrails.find((y) => y.metric === k);
      return [k, { enabled: !!g, threshold: g?.threshold ?? 5 }];
    })),
  });
  const [variants, setVariants] = useState({ A: x.variants.A || blankVariant('A'), B: x.variants.B || blankVariant('B'), C: x.variants.C || blankVariant('C') });
  const [cOn, setCOn] = useState(!!x.variants.C);
  const [busy, setBusy] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const setVariant = (key, k, v) => setVariants((all) => ({ ...all, [key]: { ...all[key], [k]: v } }));
  const setVariantField = (key, group, field, v) => setVariants((all) => ({ ...all, [key]: { ...all[key], [group]: { ...all[key][group], [field]: v } } }));
  const keys = cOn ? ['A', 'B', 'C'] : ['A', 'B'];
  const total = keys.reduce((n, k) => n + (Number(variants[k].allocation) || 0), 0);
  const err = (k) => errors[k];

  const splitEvenly = () => {
    const each = Math.floor(100 / keys.length);
    setVariants((all) => {
      const next = { ...all };
      keys.forEach((k, i) => { next[k] = { ...next[k], allocation: i === 0 ? 100 - each * (keys.length - 1) : each }; });
      return next;
    });
  };

  const save = async (action) => {
    setBusy(action);
    await submit(route('app.experiments.update', { experiment: x.id }), {
      ...form,
      guardrails: Object.entries(form.guardrails).map(([metric, g]) => ({ metric, enabled: g.enabled, threshold: g.threshold })),
      variants: Object.fromEntries(keys.map((k) => [k, variants[k]])),
      action,
    });
    setBusy(null);
  };

  return (
    <Page heading={x.name} back={route('app.experiments.index', { tab: 'drafts' })} backLabel="A/B tests">
      {banner && <s-banner tone={Object.keys(errors).length ? 'warning' : 'critical'}>{banner}</s-banner>}
      {x.status === 'paused' && (
        <s-banner tone="warning" heading="This test is paused">
          <s-paragraph>Everyone sees the control. You can change the setup, then resume. Visitors keep their variant.</s-paragraph>
        </s-banner>
      )}

      <nav className="xp-steps" aria-label="Setup steps">
        {STEPS.map(([id, label], i) => <a key={id} href={`#step-${id}`} data-reload>{i + 1}. {label}</a>)}
        <a href={`${docsUrl}#setup`} target="_blank" rel="noreferrer" className="xp-doc">View documentation ↗</a>
      </nav>

      <div className="xp-form">
        <s-section heading="1. Widget" id="step-experience">
          <p className="oo-muted">Testing <strong>{experience.name}</strong> ({experience.type}). Variants can change its template, design and text; products, prices and discounts stay as published, so checkout always matches what shoppers saw.{checkoutBlock && ' The split happens in Shopify\'s checkout, so it shows wherever the OrderOrbit Space block for this type is placed.'}</p>
          {!experience.published && <s-banner tone="warning">This widget isn't published. Publish it before launching the test.</s-banner>}
          <div className="b-field"><label htmlFor="xp-name">Test name</label><input id="xp-name" type="text" value={form.name} onChange={(e) => set('name', e.target.value)} maxLength={120} /></div>
          <div className="b-field"><label htmlFor="xp-hypothesis">Hypothesis (optional)</label><textarea id="xp-hypothesis" rows={2} maxLength={1000} value={form.hypothesis} onChange={(e) => set('hypothesis', e.target.value)} placeholder="Showing the upsell as a slider instead of cards will raise add to cart, because…" /></div>
        </s-section>

        <s-section heading="2. Variants" id="step-variants">
          {err('variants') && <p className="b-error">{err('variants')}</p>}
          <div className="xp-variants">
            {['A', 'B', 'C'].map((key) => {
              const v = variants[key];
              const off = key === 'C' && !cOn;
              const changed = (group) => Object.keys(v[group] || {}).length;
              return (
                <fieldset key={key} className={`xp-variant ${off ? 'is-off' : ''}`}>
                  <legend>{key === 'A' ? 'A · Control' : `Variant ${key}`}</legend>
                  {key === 'C' && <label className="xp-toggle"><input type="checkbox" checked={cOn} onChange={(e) => setCOn(e.target.checked)} /> Add a third variant (A/B/C)</label>}
                  {!off && (
                    <div className="xp-variant-body">
                      <div className="b-field"><label>Name</label><input type="text" value={v.name} maxLength={80} onChange={(e) => setVariant(key, 'name', e.target.value)} /></div>
                      {key === 'A' ? (
                        <p className="oo-muted oo-small">The experience exactly as published ({templates.find((t) => t.key === control)?.name || control}).</p>
                      ) : (
                        <>
                          {canHoldout && <label className="xp-toggle"><input type="checkbox" checked={!!v.hidden} onChange={(e) => setVariant(key, 'hidden', e.target.checked)} /> Holdout: don't show the widget to this group (measures its overall effect)</label>}
                          <div className="b-field">
                            <label>Template</label>
                            <select value={v.template_key || control} onChange={(e) => setVariant(key, 'template_key', e.target.value)}>
                              {templates.map((t) => <option key={t.key} value={t.key}>{t.name}{t.key === control ? ' (control)' : ''}</option>)}
                            </select>
                          </div>
                          {Object.keys(textFields).length > 0 && (
                            <details open={changed('content') > 0 || undefined}>
                              <summary>Text{changed('content') ? ` · ${changed('content')} changed` : ''}</summary>
                              {Object.entries(textFields).map(([fKey, f]) => {
                                const value = v.content?.[fKey] ?? config.content?.[fKey] ?? '';
                                return (
                                  <div className="b-field" key={fKey}>
                                    <label>{f.label}</label>
                                    {f.type === 'textarea'
                                      ? <textarea rows={2} maxLength={f.max ?? 1000} value={value} onChange={(e) => setVariantField(key, 'content', fKey, e.target.value)} />
                                      : <input type="text" maxLength={f.max ?? 255} value={value} onChange={(e) => setVariantField(key, 'content', fKey, e.target.value)} />}
                                  </div>
                                );
                              })}
                            </details>
                          )}
                          {Object.keys(designFields).length > 0 && (
                            <details open={changed('design') > 0 || undefined}>
                              <summary>Design{changed('design') ? ` · ${changed('design')} changed` : ''}</summary>
                              {Object.entries(designFields).map(([fKey, f]) => (
                                <SchemaField key={fKey} name={fKey} field={f} currency={currency} error={err(`variants.${key}.design.${fKey}`)}
                                  value={v.design?.[fKey] ?? config.design?.[fKey] ?? f.default ?? null} onChange={(val) => setVariantField(key, 'design', fKey, val)} />
                              ))}
                            </details>
                          )}
                        </>
                      )}
                    </div>
                  )}
                </fieldset>
              );
            })}
          </div>
        </s-section>

        <s-section heading="3. Traffic allocation" id="step-traffic">
          <p className="oo-muted">The share of visitors who see each variant. Each visitor always sees the same one.</p>
          <div className="xp-alloc">
            {keys.map((key) => (
              <label className="oo-field" key={key}>
                {key}
                <span className="xp-pct"><input type="number" min={1} max={98} value={variants[key].allocation} onChange={(e) => setVariant(key, 'allocation', e.target.value === '' ? '' : Number(e.target.value))} /> %</span>
                {err(`variants.${key}.allocation`) && <span className="b-error">{err(`variants.${key}.allocation`)}</span>}
              </label>
            ))}
            <p className={`xp-total ${total === 100 ? '' : 'bad'}`}>Total: {total}%{total === 100 ? '' : ' (must be 100%)'}</p>
          </div>
          {err('variants.allocation') && <p className="b-error">{err('variants.allocation')}</p>}
          <s-button variant="tertiary" onClick={splitEvenly}>Split evenly</s-button>
        </s-section>

        <s-section heading="4. Audience" id="step-audience">
          <p className="oo-muted">Who takes part. Visitors outside the audience see the widget as published and aren't counted. Leave empty for everyone who sees the widget.</p>
          {Object.entries(audienceFields).map(([key, field]) => (
            <SchemaField key={key} name={key} field={field} currency={currency} segments={segments} error={err(`audience.${key}`)}
              value={form.audience[key] ?? field.default ?? null} onChange={(val) => set('audience', { ...form.audience, [key]: val })} />
          ))}
          <p className="oo-muted oo-small">{checkoutBlock ? 'Checkout and Thank You pages only know the cart value and the buyer\'s country.' : 'Segments come from Audiences. Testing a chosen audience is part of Advanced experimentation.'}</p>
        </s-section>

        <s-section heading="5. Primary metric" id="step-primary">
          <p className="oo-muted">The one number that decides the winner.</p>
          {Object.entries(labels.primary).map(([key, label]) => (
            <label className="oo-radio" key={key}>
              <input type="radio" name="primary_metric" checked={form.primary_metric === key} onChange={() => set('primary_metric', key)} />
              <span><strong>{label}</strong><small>{PRIMARY_HELP[key]}</small></span>
            </label>
          ))}
        </s-section>

        <s-section heading="6. Secondary metrics" id="step-secondary">
          <div className="b-checks">
            {Object.entries(labels.secondary).map(([key, label]) => (
              <label key={key}><input type="checkbox" checked={form.secondary_metrics.includes(key)} onChange={(e) => set('secondary_metrics', e.target.checked ? [...form.secondary_metrics, key] : form.secondary_metrics.filter((m) => m !== key))} /> {label}</label>
            ))}
          </div>
        </s-section>

        <s-section heading="7. Guardrails" id="step-guardrails">
          <p className="oo-muted">A variant can't be declared the winner if it makes these worse by more than the threshold (percentage points vs control).</p>
          {Object.entries(labels.guardrails).map(([key, label]) => (
            <div className="oo-form-row xp-guard" key={key}>
              <label className="xp-toggle"><input type="checkbox" checked={form.guardrails[key].enabled} onChange={(e) => set('guardrails', { ...form.guardrails, [key]: { ...form.guardrails[key], enabled: e.target.checked } })} /> {label}</label>
              <label className="oo-field">Max increase<span className="xp-pct"><input type="number" min={0.1} max={100} step={0.1} value={form.guardrails[key].threshold} onChange={(e) => set('guardrails', { ...form.guardrails, [key]: { ...form.guardrails[key], threshold: e.target.value } })} /> pts</span></label>
            </div>
          ))}
          <p className="oo-muted oo-small">Refund rate isn't available as a guardrail: refunds can't be matched to test visitors reliably.</p>
        </s-section>

        <s-section heading="8. Duration and sample" id="step-duration">
          <p className="oo-muted">No winner is named before all three minimums are reached in every variant. These are the lowest values allowed.</p>
          <div className="oo-form-row">
            <label className="oo-field">Minimum days<input type="number" min={7} max={90} value={form.min_days} onChange={(e) => set('min_days', e.target.value)} /></label>
            <label className="oo-field">Visitors per variant<input type="number" min={1000} value={form.min_visitors} onChange={(e) => set('min_visitors', e.target.value)} /></label>
            <label className="oo-field">{form.primary_metric === 'click_rate' ? 'Clicks' : 'Conversions'} per variant<input type="number" min={100} value={form.min_conversions} onChange={(e) => set('min_conversions', e.target.value)} /></label>
            <label className="oo-field">End date (optional)<input type="date" value={form.ends_at} onChange={(e) => set('ends_at', e.target.value)} /></label>
          </div>
          {err('ends_at') && <p className="b-error">{err('ends_at')}</p>}
        </s-section>

        <s-section heading="9. Preview and launch" id="step-launch">
          <div className="xp-previews">
            {Object.keys(x.variants).map((key) => (
              <div className="xp-preview" key={key}>
                <strong>{key} · {x.variants[key].name}</strong>
                {previews[key]
                  ? <Preview ready={ready} experience={previews[key]} context={{ currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' }} />
                  : <p className="oo-muted xp-holdout">Holdout: this group doesn't see the widget.</p>}
              </div>
            ))}
          </div>
          <p className="oo-muted oo-small">Previews show the last saved setup. Save to refresh them.</p>
          {problems.length > 0 && (
            <s-banner tone="warning" heading="Before you can launch">
              <s-unordered-list>{problems.map((p) => <s-list-item key={p}>{p}</s-list-item>)}</s-unordered-list>
            </s-banner>
          )}
          <div className="oo-inline" style={{ marginTop: 12 }}>
            <s-button onClick={() => save('save')} loading={busy === 'save' || undefined}>Save draft</s-button>
            <s-button variant="primary" onClick={() => save('launch')} loading={busy === 'launch' || undefined}>{x.status === 'paused' ? 'Save and resume' : 'Launch test'}</s-button>
          </div>
        </s-section>
      </div>

      {x.status === 'draft' && (
        <s-section>
          <ActionButton tone="critical" variant="tertiary" url={route('app.experiments.destroy', { experiment: x.id })} confirm="Delete this test? This can't be undone.">Delete draft</ActionButton>
        </s-section>
      )}
    </Page>
  );
}
