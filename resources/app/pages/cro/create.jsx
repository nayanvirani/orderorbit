import { useState } from 'react';
import { Field } from '../../components/form.jsx';
import { useRuntime } from '../../components/runtime.jsx';
import { TemplateCard, TemplateGrid } from '../../components/templates.jsx';
import { Hero, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';

export default function CreateExperience({ type, typeDef, creatable, previews }) {
  const { submit } = useRouter();
  const { currency } = useShared();
  const ready = useRuntime({ checkout: !!typeDef?.checkout });
  const [name, setName] = useState('');
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
          <div className="oo-form-row" style={{ marginBottom: 16 }}>
            <Field label="Internal name (optional)" className="grow"><input maxLength={120} value={name} onChange={(e) => setName(e.target.value)} placeholder={typeDef.placeholder} style={{ minWidth: 260 }} /></Field>
            <s-button href={appUrl(route('app.cro.experiences.create'))}>Back</s-button>
          </div>
          <p className="oo-muted" style={{ margin: '0 0 12px' }}>Click a template to create your {typeDef.lower} and open it in the builder.</p>
          <TemplateGrid>
            {previews.map((p) => (
              <TemplateCard key={p.key} preview={p.preview} context={context} ready={ready} name={p.name}
                onUse={() => submit(route('app.cro.experiences.store'), { type, template: p.key, name })} />
            ))}
          </TemplateGrid>
        </s-section>
      )}
    </Page>
  );
}
