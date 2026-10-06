import { TemplateCard, TemplateGrid } from '../../components/templates.jsx';
import { useRuntime } from '../../components/runtime.jsx';
import { Hero, Page, Tabs } from '../../components/ui.jsx';
import { route, useRouter, useShared } from '../../router.jsx';

const SURFACE = { product: 'Product page', cart: 'Cart', any: 'Any page', global: 'Every page', checkout: 'Checkout', 'thank-you': 'Thank You & Order Status', 'post-purchase': 'After checkout', account: 'Customer accounts' };

export default function Templates({ feature, features, templates }) {
  const { can, currency } = useShared();
  const { submit } = useRouter();
  const ready = useRuntime({ checkout: true });
  const context = { currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
  return (
    <Page heading="Templates">
      <Hero icon="grid" eyebrow="Template library" title="Start from a <em>proven template.</em>" lead="Every template shares your brand colours and fonts. Pick one and it opens in the builder, ready to customise and publish." />
      <Tabs items={[['All', route('app.cro.templates'), !feature], ...Object.entries(features).map(([k, l]) => [l, route('app.cro.templates', { feature: k }), feature === k])]} />
      <TemplateGrid>
        {templates.map((t) => (
          <TemplateCard key={`${t.type}:${t.key}`} preview={t.preview} context={context} ready={ready} name={t.name}
            meta={<span className="oo-inline oo-small"><s-badge>{t.type_label}</s-badge><span className="oo-muted">{SURFACE[t.surface] || t.surface}{t.used_by > 0 ? ` · used by ${t.used_by}` : ''}</span></span>}
            onUse={can.manage_experiences ? () => submit(route('app.cro.experiences.store'), { type: t.type, template: t.key }) : undefined} />
        ))}
      </TemplateGrid>
    </Page>
  );
}
