// Experience builder (section 16 / D2): steps on the left, a live storefront preview on the right.
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import SchemaField, { visible } from '../../components/SchemaField.jsx';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';

const STEPS = [['type', 'Type'], ['template', 'Template'], ['content', 'Content'], ['design', 'Design'], ['behavior', 'Behavior'], ['targeting', 'Targeting'], ['analytics', 'Analytics'], ['preview', 'Preview'], ['publish', 'Publish']];
const SECTIONS = { content: ['content'], design: ['design'], behavior: ['behavior'], targeting: ['targeting', 'schedule'], analytics: ['analytics'] };
const TITLES = { content: 'Content', design: 'Design', behavior: 'Behavior', targeting: 'Targeting', schedule: 'Schedule', analytics: 'Analytics' };
const CUSTOMERS = { all: 'Everyone', new: 'New shoppers', returning: 'Returning customers' };
const DEVICES = { all: 'All devices', mobile: 'Mobile', desktop: 'Desktop' };

export default function Builder(props) {
  const { experience: x, type, fields, fieldErrors: errors, banner, templates, timezone, tzOffset, currency, segments, pageTypes, publishHelp, editor, designNote, checkout } = props;
  const { submit } = useRouter();
  const ready = useRuntime({ checkout });
  const [config, setConfig] = useState(props.config);
  const [template, setTemplate] = useState(x.template_key);
  const [meta, setMeta] = useState({ name: x.name, description: x.description || '', change_note: '' });
  const [device, setDevice] = useState('desktop');
  const [cart, setCart] = useState(45);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(null);
  const errorSteps = useMemo(() => [...new Set(Object.keys(errors).map((k) => k.split('.')[0]).map((s) => (s === 'schedule' ? 'targeting' : s)))], [errors]);
  const [step, setStep] = useState(errorSteps[0] || 'content');
  const index = STEPS.findIndex(([k]) => k === step);
  const styles = Object.fromEntries(templates.map((t) => [t.key, t.style]));

  const set = (section, key, value) => {
    setConfig((c) => ({ ...c, [section]: { ...c[section], [key]: value } }));
    setDirty(true);
  };

  // Leaving with unsaved changes asks first.
  const dirtyRef = useRef(dirty);
  dirtyRef.current = dirty;
  useEffect(() => {
    const warn = (e) => { if (dirtyRef.current) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, []);

  const experience = (key = template) => ({
    id: x.handle, type: x.type, template: key, style: styles[key] || 'card', version: 0, priority: 50,
    content: config.content || {}, design: config.design || {}, behavior: config.behavior || {},
    targeting: config.targeting || {}, analytics: config.analytics || {},
  });
  const context = { currency, cartTotal: Math.round(Number(cart || 0) * 100), productPrice: 2900, productTitle: 'Sample product', page: 'product' };

  const save = async (action) => {
    setBusy(action);
    const r = await submit(route('app.cro.experiences.update', { experience: x.id }), { action, config, template_key: template, ...meta });
    setBusy(null);
    if (r.ok || r.data) setDirty(false);
  };

  return (
    <Page heading={x.name} back={route('app.cro.experiences.show', { experience: x.id })} backLabel="Experience">
      {banner && <s-banner tone={Object.keys(errors).length ? 'warning' : 'info'}>{banner}</s-banner>}

      <nav className="b-steps" aria-label="Builder steps">
        {STEPS.map(([key, label], i) => (
          <button type="button" key={key} className={`b-step ${errorSteps.includes(key) ? 'b-step-error' : ''}`} aria-current={step === key ? 'step' : 'false'} onClick={() => setStep(key)}>
            <span>{i + 1}</span>{label}
          </button>
        ))}
      </nav>

      <div className="b-layout">
        <div className="b-panel">
          {step === 'type' && (
            <section className="b-card">
              <h2>Type</h2>
              <p><strong>{type.label}</strong> — {type.description}</p>
              <p className="b-muted">The type is set when an experience is created. Duplicate from another type to switch.</p>
            </section>
          )}

          {step === 'template' && (
            <section className="b-card">
              <h2>Template</h2>
              <p className="b-muted">Switching templates keeps your content and design settings.</p>
              <div className="b-templates">
                {templates.map((t) => (
                  <label className="b-template" key={t.key}>
                    <input type="radio" name="template_key" checked={template === t.key} onChange={() => { setTemplate(t.key); setDirty(true); }} />
                    <Preview ready={ready} experience={experience(t.key)} context={context} className="b-template-preview oo-preview" />
                    <span className="b-template-name">{t.name}</span>
                  </label>
                ))}
              </div>
            </section>
          )}

          {SECTIONS[step] && (
            <section className="b-card">
              {SECTIONS[step].map((section) => (
                <div key={section}>
                  <h2>{TITLES[section]}</h2>
                  {section === 'design' && designNote === 'checkout' && <p className="b-muted">Checkout blocks use your checkout's own fonts and colours, set in Shopify under Settings → Checkout → Customize.</p>}
                  {section === 'design' && designNote === 'checkout-boxes' && <p className="b-muted">Shopify doesn't let apps use their own colours in checkout, so backgrounds, borders and text tones use the colours from your checkout branding (Settings → Checkout → Customize). Sizes, borders and corners are yours to set.</p>}
                  {section === 'design' && designNote === 'brand' && <p className="b-muted">Defaults come from <a href={appUrl(route('app.settings.branding'))}>Settings → Branding</a>.</p>}
                  {section === 'schedule' && <p className="b-muted">Optional. Leave empty to go live as soon as you publish.</p>}
                  {Object.entries(fields[section] || {}).filter(([, f]) => visible(f, config[section])).map(([key, f]) => (
                    <SchemaField key={key} name={key} field={f} value={config[section]?.[key] ?? null} onChange={(v) => set(section, key, v)}
                      error={errors[`${section}.${key}`]} rowErrors={Object.entries(errors).filter(([k]) => k.startsWith(`${section}.${key}.`)).map(([, m]) => m)}
                      currency={currency} timezone={timezone} tzOffset={tzOffset} segments={segments} />
                  ))}
                </div>
              ))}
            </section>
          )}

          {step === 'preview' && (
            <section className="b-card">
              <h2>Preview</h2>
              <p className="b-muted">Use the preview on the right. Try a different cart value to see progress states.</p>
              <dl className="b-summary">
                <dt>Template</dt><dd>{templates.find((t) => t.key === template)?.name}</dd>
                <dt>Shows on</dt><dd>{(config.targeting?.page_types || []).map((p) => pageTypes[p] || p).join(', ') || 'Wherever you place the block'}</dd>
                <dt>Shoppers</dt><dd>{CUSTOMERS[config.targeting?.customer || 'all']} · {DEVICES[config.targeting?.device || 'all']}</dd>
              </dl>
            </section>
          )}

          {step === 'publish' && (
            <section className="b-card">
              <h2>Publish</h2>
              <div className="b-field"><label htmlFor="f-name">Internal name</label><input id="f-name" value={meta.name} maxLength={120} onChange={(e) => { setMeta({ ...meta, name: e.target.value }); setDirty(true); }} /></div>
              <div className="b-field"><label htmlFor="f-description">Description</label><textarea id="f-description" rows={2} maxLength={500} value={meta.description} onChange={(e) => { setMeta({ ...meta, description: e.target.value }); setDirty(true); }} /></div>
              <div className="b-field"><label htmlFor="f-note">Change note</label><input id="f-note" maxLength={190} value={meta.change_note} onChange={(e) => setMeta({ ...meta, change_note: e.target.value })} placeholder="What changed in this version?" /></div>
              {publishHelp.map((html, i) => <p key={i} className="b-muted" dangerouslySetInnerHTML={{ __html: html }} />)}
              <div className="b-actions">
                <button type="button" className="b-btn b-primary" disabled={!!busy} onClick={() => save('publish')}>{busy === 'publish' ? 'Publishing…' : config.schedule?.starts_at ? 'Schedule' : 'Publish'}</button>
                <a className="b-btn" target="_top" href={editor.url}>{editor.label}</a>
              </div>
            </section>
          )}

          <div className="b-savebar">
            <button type="button" className="b-btn" disabled={index === 0} onClick={() => setStep(STEPS[Math.max(0, index - 1)][0])}>Back</button>
            {dirty && <span className="b-dirty">Unsaved changes</span>}
            <button type="button" className="b-btn" disabled={!!busy} onClick={() => save('save')}>{busy === 'save' ? 'Saving…' : 'Save draft'}</button>
            {index < STEPS.length - 1 && <button type="button" className="b-btn b-primary" onClick={() => setStep(STEPS[index + 1][0])}>Next</button>}
          </div>
        </div>

        <aside className="b-preview-col">
          <div className="b-preview-bar">
            <div className="b-seg" role="group" aria-label="Preview device">
              <button type="button" aria-pressed={device === 'desktop'} onClick={() => setDevice('desktop')}>Desktop</button>
              <button type="button" aria-pressed={device === 'mobile'} onClick={() => setDevice('mobile')}>Mobile</button>
            </div>
            <label className="b-cart">Cart value <input type="number" min="0" step="1" value={cart} onChange={(e) => setCart(e.target.value)} aria-label="Preview cart value" /></label>
          </div>
          <div className="b-stage">
            <div className={`b-frame oo-preview ${device === 'mobile' ? 'oo-preview-mobile' : ''}`}>
              <div className="b-fake-page" aria-hidden="true"><i /><i /><i className="short" /></div>
              <Preview ready={ready} experience={experience()} context={context} className="oo-root" empty="Nothing to show with these settings (for example, the countdown has ended and is set to hide)." />
              <div className="b-fake-page" aria-hidden="true"><i className="short" /><i /></div>
            </div>
          </div>
          <p className="b-muted b-small">Preview uses sample prices where the storefront would show live ones.</p>
        </aside>
      </div>

      {x.discardable && (
        <div style={{ marginTop: 12 }}>
          <ActionButton variant="tertiary" url={route('app.cro.experiences.lifecycle', { experience: x.id, action: 'discard' })} confirm="Discard all changes since the last publish?">Discard changes since last publish</ActionButton>
        </div>
      )}
    </Page>
  );
}
