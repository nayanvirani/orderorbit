import { useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useShared } from '../../router.jsx';

const LAYOUTS = [['all', 'All'], ['vertical', 'Vertical'], ['horizontal', 'Horizontal'], ['grid', 'Grid']];

export default function BundleModels({ type, models, presets }) {
  const { currency } = useShared();
  const ready = useRuntime();
  const [layout, setLayout] = useState('all');
  const [preset, setPreset] = useState('black');
  const [context, setContext] = useState({ currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });

  // Preview with one of the store's products (title, price, image, variants).
  const pickProduct = async () => {
    if (!window.shopify?.resourcePicker) return;
    const picked = await window.shopify.resourcePicker({ type: 'product', multiple: false });
    const p = picked?.[0];
    if (!p) return;
    const image = p.images?.[0] && (p.images[0].originalSrc || p.images[0].url);
    const variants = (p.variants || []).map((v) => ({ id: v.id, title: v.title, price: Number(v.price), available: true }));
    const price = Number(p.variants?.[0]?.price || 29);
    setContext({ ...context, productTitle: p.title, productPrice: Math.round(price * 100), productImage: image || null, pageProduct: { title: p.title, price, variants: variants.length > 1 ? variants : null, image: image || null } });
  };

  return (
    <Page heading="Step 2/2 · Choose your model" back={route('app.bundles.types')} backLabel="Bundle types">
      <p className="bx-lead">{type.label}: {type.lead} Choose a ready-made model, then customise everything.</p>
      <div className="bx-filters">
        <div className="bx-filter-row">
          <strong>Filters and preview</strong>
          <div className="bx-seg" role="group" aria-label="Layout">
            {LAYOUTS.map(([k, l]) => <button type="button" key={k} aria-pressed={layout === k} onClick={() => setLayout(k)}>{l}</button>)}
          </div>
          <div className="bx-swatches" role="group" aria-label="Colour">
            {Object.entries(presets).map(([k, p]) => <button type="button" key={k} className="bx-swatch" style={{ '--sw': p.accent }} aria-pressed={preset === k} aria-label={k} onClick={() => setPreset(k)} />)}
          </div>
        </div>
        <div className="bx-filter-row">
          <strong>Preview product (optional)</strong>
          <button type="button" className="b-btn" onClick={pickProduct}>Select product</button>
          <span className="bx-muted">{context.productTitle}</span>
        </div>
      </div>
      <div className="bx-models">
        {models.filter((m) => layout === 'all' || m.layout === layout).map((m) => (
          <div className="bx-model" key={m.key}>
            <div className="tpl-stage tpl-tall"><Preview ready={ready} experience={m.previews[preset]} context={context} /></div>
            <div className="tpl-body">
              <strong className="b-template-name">{m.name}</strong>
              <p className="bx-muted">{m.description}</p>
              <div className="tpl-action"><ActionButton variant="primary" inlineSize="fill" url={route('app.bundles.store')} data={{ model: m.key, preset }}>Use this template</ActionButton></div>
            </div>
          </div>
        ))}
      </div>
    </Page>
  );
}
