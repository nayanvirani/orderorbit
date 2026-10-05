import { ActionButton } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Hero, Page, Tabs } from '../../components/ui.jsx';
import { route, useShared } from '../../router.jsx';

const SURFACE = { product: 'Product page', cart: 'Cart', any: 'Any page' };

export default function Templates({ type, templates, types }) {
  const { can, currency } = useShared();
  const ready = useRuntime({ checkout: true });
  const context = { currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
  return (
    <Page heading="Templates">
      <Hero icon="grid" eyebrow="Template library" title="Start from a <em>proven template.</em>" lead="Every template shares your brand colours and fonts. Pick one, customise it in the builder, and publish it from the Theme Editor." />
      <Tabs items={[['All', route('app.cro.templates'), !type], ...Object.entries(types).map(([k, l]) => [l, route('app.cro.templates', { type: k }), type === k])]} />
      <div className="b-templates" style={{ gridTemplateColumns: 'repeat(auto-fill,minmax(270px,1fr))' }}>
        {templates.map((t) => (
          <div className="b-template" style={{ cursor: 'default' }} key={`${t.type}:${t.key}`}>
            <Preview ready={ready} experience={t.preview} context={context} className="b-template-preview oo-preview" />
            <span className="b-template-name">{t.name}</span>
            <span className="oo-inline oo-small">
              <s-badge>{t.type_label}</s-badge>
              <span className="oo-muted">v{t.version} · {SURFACE[t.surface] || t.surface}</span>
              {t.used_by > 0 && <span className="oo-muted">· used by {t.used_by}</span>}
            </span>
            {can.manage_experiences && <ActionButton url={route('app.cro.experiences.store')} data={{ type: t.type, template: t.key }}>Use template</ActionButton>}
          </div>
        ))}
      </div>
    </Page>
  );
}
