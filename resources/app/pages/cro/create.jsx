import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Hero, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';

export default function CreateExperience({ type, typeDef, creatable, previews, selected }) {
  const { submit } = useRouter();
  const { currency } = useShared();
  const ready = useRuntime({ checkout: !!typeDef?.checkout });
  const [template, setTemplate] = useState(selected && previews.some((p) => p.key === selected) ? selected : previews[0]?.key);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const context = { currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };

  return (
    <Page heading="Create widget">
      <Hero eyebrow="New widget" title={typeDef ? `Choose a <em>${typeDef.lower.replace(/</g, '&lt;')}</em> template.` : 'What do you want to <em>build?</em>'}
        lead={typeDef ? typeDef.lead : 'Pick a feature. Every one goes live on your storefront, and savings apply automatically at checkout.'} />
      <div className="b-steps" aria-label="Steps">
        <span className="b-step" aria-current={type ? 'false' : 'step'}><span>1</span>Type</span>
        <span className="b-step" aria-current={type ? 'step' : 'false'}><span>2</span>Template</span>
        <span className="b-step"><span>3</span>Configure</span>
      </div>

      {!type ? (
        <s-section>
          <div className="ob-types">
            {creatable.map((t) => (
              <a key={t.key} className="ob-type" href={appUrl(route('app.cro.experiences.create', { type: t.key }))}>
                <h4>{t.label}</h4><p>{t.description}</p><div className="ob-row"><span style={{ color: 'var(--ob-sun)' }}>Choose →</span></div>
              </a>
            ))}
          </div>
        </s-section>
      ) : (
        <s-section>
          <Form onSubmit={async () => { setBusy(true); await submit(route('app.cro.experiences.store'), { type, template, name }); setBusy(false); }}>
            <div className="b-templates" style={{ gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))' }}>
              {previews.map((p) => (
                <label className="b-template" key={p.key}>
                  <input type="radio" name="template" checked={template === p.key} onChange={() => setTemplate(p.key)} />
                  <Preview ready={ready} experience={p.preview} context={context} className="b-template-preview oo-preview b-zoom" />
                  <span className="b-template-name">{p.name}</span>
                </label>
              ))}
            </div>
            <div className="oo-form-row" style={{ marginTop: 16 }}>
              <Field label="Internal name (optional)" className="grow"><input maxLength={120} value={name} onChange={(e) => setName(e.target.value)} placeholder={typeDef.placeholder} style={{ minWidth: 260 }} /></Field>
              <s-button type="submit" variant="primary" loading={busy || undefined}>Continue</s-button>
              <s-button href={appUrl(route('app.cro.experiences.create'))}>Back</s-button>
            </div>
          </Form>
        </s-section>
      )}
    </Page>
  );
}
