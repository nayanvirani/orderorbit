import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';

export default function BundleTypes({ types }) {
  const { currency } = useShared();
  const ready = useRuntime();
  const context = { currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
  return (
    <Page heading="Step 1/2 · Select bundle type" back={route('app.bundles.index')} backLabel="Bundles">
      <p className="bx-lead">Choose the bundle type that best suits your needs. Every type is fully customisable after you pick a model.</p>
      <div className="bx-types">
        {types.map((t) => (
          <a key={t.key} className="bx-type" href={appUrl(route('app.bundles.models', { type: t.key }))}>
            <span className="tpl-stage"><Preview ready={ready} experience={t.preview} context={context} className="oo-preview" /></span>
            <span className="bx-type-body">
              <strong>{t.label}</strong>
              <span className="bx-muted">{t.lead}</span>
              <span className="bx-hint"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a6 6 0 0 0-3.5 10.9V15h7v-2.1A6 6 0 0 0 10 2ZM7.5 17h5" fill="none" stroke="currentColor" strokeWidth="1.5" /></svg>{t.example}</span>
              <span className="bx-hint"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" strokeWidth="1.5" /><circle cx="10" cy="10" r="3" fill="none" stroke="currentColor" strokeWidth="1.5" /></svg>{t.goal}</span>
              <span className="bx-cta">Create →</span>
            </span>
          </a>
        ))}
      </div>
    </Page>
  );
}
