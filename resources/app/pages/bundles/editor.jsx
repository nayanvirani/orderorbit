// Bundle editor: Settings / Offers / Design panels over one JSON config, with a live storefront
// preview. The server (BundleSchema) validates on save.
import { useContext, useEffect, useRef, useState } from 'react';
import { clone, Ctx, F, getIn, Help, Picker, Row, Section, setIn } from '../../components/editorKit.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useRouter, useShared } from '../../router.jsx';
import ExperienceStatus from '../cro/_status.jsx';

export default function BundleEditor({ experience: x, config, fieldErrors: errors, banner, type, meta, subscriptionLayouts }) {
  const { submit } = useRouter();
  const { currency } = useShared();
  const ready = useRuntime();
  const [state, setState] = useState(config);
  const [name, setName] = useState(x.name);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(null);
  const [open, setOpen] = useState(() => new Set(['offer-0', 'visibility', 'mix', 'box-size', 'box-price', 'd-overall']));
  const [device, setDevice] = useState('desktop');
  const [context, setContext] = useState({ currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });
  const tabOf = (k) => (k.startsWith('offers') || k.startsWith('mix') || k.startsWith('upsells') ? 'offers' : k.startsWith('design') ? 'design' : 'settings');
  const errorTabs = Object.keys(errors).map(tabOf);
  const [tab, setTab] = useState(errorTabs[0] || 'settings');

  const update = (fn) => { setState((s) => fn(clone(s))); setDirty(true); };
  const set = (path, value) => { setState((s) => setIn(s, path, value)); setDirty(true); };
  const toggle = (key) => setOpen((o) => { const n = new Set(o); n.has(key) ? n.delete(key) : n.add(key); return n; });
  const expand = (key) => setOpen((o) => new Set(o).add(key));

  const dirtyRef = useRef(dirty);
  dirtyRef.current = dirty;
  useEffect(() => {
    const warn = (e) => { if (dirtyRef.current) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, []);

  const save = async (action) => {
    setBusy(action);
    await submit(route('app.bundles.update', { bundle: x.id }), { action, name, config: state });
    setBusy(null);
    setDirty(false);
  };

  // The storefront payload (mirrors BundleSchema::payload); empty pickers use sample products.
  const payload = () => {
    const c = clone(state);
    const samples = meta.samples.slice(0, 3);
    c.offers.forEach((o, i) => {
      o.index = i;
      if (o.kind === 'multi' && !o.products.length) o.products = samples.slice(0, 2);
      if (o.kind === 'mono' && !o.product.length) o.product = [samples[i % 3]];
      (o.gifts || []).forEach((g) => { if (!g.product.length) g.product = [meta.samples[3]]; });
    });
    if (!c.mix.pool.length) c.mix.pool = samples;
    const d = c.design;
    return {
      id: x.handle, type: 'bundles', template: 'editor', style: c.settings.layout, version: 0, priority: 50,
      content: { bundle_type: c.bundle_type, settings: c.settings, offers: c.offers.filter((o) => o.visible), mix: c.mix, gifts: c.gifts, upsells: c.upsells, summary: c.summary, subscription: c.subscription },
      design: { ...d, primary_color: d.button_background, accent_color: d.accent, text_color: d.text, background_color: d.background, border: false },
      behavior: { priority: 50, after_add: c.settings.after_add, position: c.settings.position, animation: 'none' },
      targeting: {},
    };
  };

  const previewProduct = async () => {
    if (!window.shopify?.resourcePicker) return;
    const picked = await window.shopify.resourcePicker({ type: 'product', multiple: false });
    const p = picked?.[0];
    if (!p) return;
    const first = p.variants?.[0] || {};
    const image = p.images?.[0] && (p.images[0].originalSrc || p.images[0].url);
    const variants = (p.variants || []).map((v) => ({ id: v.id, title: v.title, price: Number(v.price), available: true }));
    setContext({ ...context, productTitle: p.title, productPrice: Math.round(Number(first.price || 29) * 100), productImage: image || null,
      pageProduct: { title: p.title, price: Number(first.price || 29), image: image || null, variants: variants.length > 1 ? variants : null } });
  };

  const ctx = { state, set, update, errors, meta: { ...meta, subscriptionLayouts }, open, toggle, expand, currency };

  return (
    <Page heading={x.name} back={route('app.bundles.index')} backLabel="Bundles">
      {banner && <s-banner tone={Object.keys(errors).length ? 'warning' : 'critical'}>{banner}</s-banner>}
      {x.status === 'published' && x.unpublished && <s-banner tone="info">This bundle has changes that aren't live yet. Publish to update your store.</s-banner>}

      <Ctx.Provider value={ctx}>
        <div className="bx-editor">
          <div className="bx-editor-top">
            <label className="bx-name"><span className="bx-muted">Bundle name (only you see it)</span><input value={name} maxLength={120} onChange={(e) => { setName(e.target.value); setDirty(true); }} /></label>
            <div className="bx-status"><ExperienceStatus experience={x} /><span className="bx-pill">{type.label}</span></div>
          </div>

          <div className="bx-layout">
            <div className="bx-panel-col">
              <div className="bx-card bx-inline-card">
                <div className="bx-inline"><strong>Schedule</strong><span className="bx-muted">Optional start and end. Shopify applies the bundle pricing only in this window.</span></div>
                <Row><F path="schedule.starts_at" label={`Start (${meta.timezone})`} type="datetime" /><F path="schedule.ends_at" label={`End (${meta.timezone})`} type="datetime" /></Row>
              </div>

              <nav className="bx-tabbar" role="tablist" aria-label="Bundle editor">
                {[['settings', 'Settings'], ['offers', state.bundle_type === 'mix-match' || state.bundle_type === 'byob' ? 'Products' : 'Offers'], ['design', 'Design']].map(([k, l]) => (
                  <button type="button" role="tab" key={k} aria-selected={tab === k} className={errorTabs.includes(k) ? 'has-error' : ''} onClick={() => setTab(k)}>{l}</button>
                ))}
              </nav>
              {tab === 'settings' && <SettingsPanel />}
              {tab === 'offers' && <OffersPanel />}
              {tab === 'design' && <DesignPanel />}
            </div>

            <aside className="bx-preview-col">
              <div className="bx-card bx-preview">
                <div className="bx-preview-head">
                  <strong>Preview</strong>
                  <div className="b-seg" role="group" aria-label="Preview device">
                    <button type="button" aria-pressed={device === 'desktop'} onClick={() => setDevice('desktop')}>Desktop</button>
                    <button type="button" aria-pressed={device === 'mobile'} onClick={() => setDevice('mobile')}>Mobile</button>
                  </div>
                </div>
                <div className="bx-preview-product"><span className="bx-muted">Product</span><button type="button" className="b-btn" onClick={previewProduct}>{context.productTitle}</button></div>
                <div className="b-stage">
                  <div className={`b-frame ${device === 'mobile' ? 'oo-preview-mobile' : ''}`}>
                    <div className="bx-fake-atc-wrap">
                      <Preview ready={ready} experience={payload()} context={context} className="oo-root" empty="Add an offer to see the preview." />
                      <span className="bx-fake-atc">Theme add to cart</span>
                    </div>
                  </div>
                </div>
                <p className="bx-muted bx-small">Live preview with sample prices. On your store, prices and variants come from Shopify.</p>
              </div>
              <div className="bx-savebar">
                {dirty && <span className="b-dirty">Unsaved changes</span>}
                <button type="button" className="b-btn" disabled={!!busy} onClick={() => save('save')}>{busy === 'save' ? 'Saving…' : 'Save as draft'}</button>
                <button type="button" className="b-btn b-primary" disabled={!!busy} onClick={() => save('publish')}>{busy === 'publish' ? 'Publishing…' : x.status === 'published' ? 'Publish changes' : 'Publish'}</button>
              </div>
            </aside>
          </div>
        </div>
      </Ctx.Provider>
    </Page>
  );
}

function SettingsPanel() {
  const { state, set, meta } = useContext(Ctx);
  const s = state.settings;
  return (
    <>
      <Section id="visibility" title="Visibility and market">
        <div className="b-field">
          <span className="b-label">Visibility</span>
          <div className="bx-seg" role="group">
            {[['all', 'All products'], ['collections', 'Collection(s)'], ['products', 'Product(s)']].map(([k, l]) => <button type="button" key={k} aria-pressed={s.visibility === k} onClick={() => set('settings.visibility', k)}>{l}</button>)}
          </div>
          <Help text="Which product pages show this bundle." />
        </div>
        {s.visibility === 'products' && <Picker path="settings.products" label="Show on these products" max={100} />}
        {s.visibility === 'collections' && <Picker path="settings.collections" label="Show on products in these collections" kind="collection" max={50} />}
        <Picker path="settings.excluded" label="Excluded products" max={100} help="The bundle won’t appear on these product pages." />
        <F path="settings.countries" label="Markets (countries)" placeholder="All markets" help="Two-letter country codes separated by commas, e.g. US, CA. Leave empty for all markets." />
      </Section>
      <Section id="titles" title="Titles">
        <Row><F path="settings.title" label="Header title" max={80} /><F path="settings.subtitle" label="Subtitle" max={160} help={state.bundle_type === 'byob' ? 'Use {min} and {max} for the box limits.' : ''} /></Row>
        <F path="settings.hide_lines" label="Hide header lines" type="toggle" />
      </Section>
      <Section id="timer" title="Timer">
        <F path="settings.timer.enabled" label="Show bundle timer" type="toggle" help="A real deadline: end of today in the shopper’s time, or a date you set. It never resets per visitor." />
        {s.timer.enabled && (
          <>
            <Row><F path="settings.timer.mode" label="Ends" type="select" options={{ end_of_day: 'At the end of each day', date: 'On a date' }} /><F path="settings.timer.text" label="Timer text" max={60} /></Row>
            {s.timer.mode === 'date' && <F path="settings.timer.ends_at" label={`Ends at (${meta.timezone})`} type="datetime" />}
          </>
        )}
      </Section>
      <Section id="layout" title="Layout and position">
        <div className="b-field">
          <span className="b-label">Layout</span>
          <div className="bx-layouts">
            {[['vertical', 'Vertical'], ['horizontal', 'Horizontal'], ['grid', 'Grid']].map(([k, l]) => (
              <button type="button" key={k} className={`bx-layout-tile ${s.layout === k ? 'on' : ''}`} aria-pressed={s.layout === k} onClick={() => set('settings.layout', k)}>
                <span className={`bx-lt bx-lt-${k}`}><i /><i /><i />{k === 'grid' && <i />}</span>{l}
              </button>
            ))}
          </div>
        </div>
        <Row>
          <F path="settings.style" label="Style" type="select" options={{ cards: 'Cards', compact: 'Compact list', fbt: 'Frequently bought together', checklist: 'Checklist' }} />
          <F path="settings.position" label="Bundle position" type="select" options={{ above_atc: 'Above the add to cart button', below_atc: 'Below the add to cart button', block: 'Only where I place the block' }} help="Above/below needs the OrderOrbit app embed turned on in the Theme Editor." />
        </Row>
        <Row>
          <F path="settings.button_text" label="Button text" max={40} />
          <F path="settings.after_add" label="After adding to cart" type="select" options={{ cart: 'Go to the cart', stay: 'Stay on the page', checkout: 'Skip cart and go to checkout' }} />
        </Row>
        <F path="settings.show_variants" label="Show product variant selection" type="toggle" help="Shoppers choose a variant for each item (#1, #2 …)." />
        <F path="settings.hide_theme_form" label="Hide the theme’s product form" type="toggle" help="Hides your theme’s variant picker, quantity, add to cart, buy-now and subscription options where the bundle shows, so they don’t conflict." />
        {s.hide_theme_form && <F path="settings.hide_selectors" label="Extra elements to hide (CSS selectors)" placeholder=".my-theme-variant-picker, .my-subscriptions" help="Only needed if your theme uses a custom product form." />}
        <F path="behavior.priority" label="Priority" type="number" min={1} max={100} help="When several bundles match a product, the highest priority shows." />
      </Section>
    </>
  );
}

function offerTitle(o) {
  if (o.kind === 'multi') return `${(o.products || []).length} products`;
  if (o.kind === 'mono') return (o.product[0] || {}).title || 'Choose product';
  return `${o.quantity} product${o.quantity > 1 ? 's' : ''}`;
}

function Extras() {
  return (
    <>
      <Section id="gifts" title="Gifts" toggle="gifts.enabled">
        <F path="gifts.title" label="Gifts title" max={80} />
        <Help text="Add gift products inside each offer. Gifts are free at checkout when the offer is bought." />
      </Section>
      <Section id="upsells" title="Upsells" toggle="upsells.enabled">
        <F path="upsells.title" label="Title" max={80} />
        <Picker path="upsells.products" label="Add-on products" max={4} />
        <F path="upsells.discount_percent" label="Add-on discount (%)" type="number" min={0} max={100} step={0.01} help="Applies only to add-ons ticked in this bundle." />
      </Section>
      <Subscription />
      <Section id="summary" title="Savings summary" toggle="summary.enabled">
        <F path="summary.text" label="Text" max={80} help="Use {saving} for the amount saved." />
      </Section>
    </>
  );
}

function Subscription() {
  const { meta } = useContext(Ctx);
  return (
    <Section id="subscription" title="Subscriptions" toggle="subscription.enabled">
      <Help text="Shoppers choose one-time or subscribe & save, and how often. Plans come from your subscription app (Shopify Subscriptions, Recharge, Skio, Loop, Seal, Appstle and others), so add a subscription plan to the bundle's products there first. The option shows only when every product in the selected offer has a plan for the same frequency." />
      <Row>
        <F path="subscription.layout" label="Layout" type="select" options={meta.subscriptionLayouts || { cards: 'Two option cards', toggle: 'Toggle', checkbox: 'Checkbox' }} />
        <F path="subscription.default" label="Selected at first" type="select" options={{ subscribe: 'Subscribe & save', once: 'One-time purchase' }} />
      </Row>
      <Row>
        <F path="subscription.once_label" label="One-time label" max={40} />
        <F path="subscription.subscribe_label" label="Subscribe label" max={40} />
      </Row>
      <F path="subscription.frequency_label" label="Frequency label" max={40} />
      <F path="subscription.benefits" label="Benefits (one per line, up to 4)" type="textarea" rows={3} />
      <F path="subscription.recurring_text" label="Later deliveries text" max={80} help="Use {price} for the price of each later delivery and {frequency} for how often." />
      <Help text="Pricing: the bundle discount applies to the first delivery. Later deliveries are priced by your subscription app's plan (for example 10% off every delivery). Shopify doesn't let apps change the price of later subscription deliveries." />
    </Section>
  );
}

/** Discount steps by number of items (mix & match and build your own box). */
function Tiers({ max }) {
  const { state, update, errors } = useContext(Ctx);
  return (
    <div className="b-field">
      <span className="b-label">Discount by number of items</span>
      {state.mix.tiers.map((t, j) => (
        <div className="bx-tier" key={j}>
          <F path={`mix.tiers.${j}.count`} label="Items" type="number" min={1} max={max} />
          <F path={`mix.tiers.${j}.discount`} label="% off" type="number" min={0} max={100} step={0.01} />
          <button type="button" className="b-icon-btn" aria-label="Remove" onClick={() => update((s) => { s.mix.tiers.splice(j, 1); return s; })}>×</button>
        </div>
      ))}
      <button type="button" className="b-btn" onClick={() => update((s) => { const last = s.mix.tiers[s.mix.tiers.length - 1]; s.mix.tiers.push({ count: last ? last.count + 1 : 2, discount: last ? last.discount + 5 : 10 }); return s; })}>Add discount step</button>
      {errors['mix.tiers'] && <p className="b-error">{errors['mix.tiers']}</p>}
      <Help text="The best step reached applies to the whole box." />
    </div>
  );
}

/** Build your own box: where the products come from, the box size and limits, and the price. */
function BoxPanel() {
  const { state } = useContext(Ctx);
  const m = state.mix;
  return (
    <>
      <Section id="mix" title="Products in the box">
        <F path="mix.source" label="Shoppers choose from" type="select" options={{ products: 'Products I pick', collection: 'A collection' }} />
        {m.source === 'collection'
          ? <Picker path="mix.collection" kind="collection" label="Collection" max={1} help="Products added to the collection later appear in the box automatically. Sold-out products can't be added." />
          : <Picker path="mix.pool" label="Products" max={100} />}
      </Section>
      <Section id="box-size" title="Box size and limits">
        <Row>
          <F path="mix.min" label="Minimum items" type="number" min={1} max={100} help="The box can't be added to the cart with fewer items." />
          <F path="mix.slots" label="Maximum items" type="number" min={1} max={100} help="Shoppers can't add more than this." />
        </Row>
        <F path="mix.per_product" label="Most of one product (0 = no limit)" type="number" min={0} max={100} help="E.g. 2: shoppers can add up to 2 of each product." />
        <Help text="These limits are also checked at checkout, so the box price only applies to a box that respects them." />
      </Section>
      <Section id="box-price" title="Box price">
        <F path="mix.pricing" label="Pricing" type="select" options={{ tiers: 'Discount by number of items', fixed: 'One price for the box', none: 'No discount' }} />
        {m.pricing === 'tiers' && <Tiers max={100} />}
        {m.pricing === 'fixed' && <F path="mix.fixed_price" label="Box price" type="number" min={0} step={0.01} help="For a box of an exact size: set the minimum and maximum to the same number." />}
      </Section>
      <Extras />
    </>
  );
}

function OffersPanel() {
  const { state, update, errors, meta, open, toggle, expand } = useContext(Ctx);
  if (state.bundle_type === 'byob') return <BoxPanel />;
  if (state.bundle_type === 'mix-match') {
    return (
      <>
        <Section id="mix" title="Products and slots">
          <Picker path="mix.pool" label="Products shoppers can choose from" max={50} />
          <Row><F path="mix.slots" label="Number of slots" type="number" min={2} max={8} /><F path="mix.slot_text" label="Empty slot text" max={24} /></Row>
          <div className="b-field">
            <span className="b-label">Progressive discounts</span>
            {state.mix.tiers.map((t, j) => (
              <div className="bx-tier" key={j}>
                <F path={`mix.tiers.${j}.count`} label="Items" type="number" min={1} max={8} />
                <F path={`mix.tiers.${j}.discount`} label="% off" type="number" min={0} max={100} step={0.01} />
                <button type="button" className="b-icon-btn" aria-label="Remove" onClick={() => update((s) => { s.mix.tiers.splice(j, 1); return s; })}>×</button>
              </div>
            ))}
            <button type="button" className="b-btn" onClick={() => update((s) => { const last = s.mix.tiers[s.mix.tiers.length - 1]; s.mix.tiers.push({ count: last ? last.count + 1 : 2, discount: last ? last.discount + 5 : 10 }); return s; })}>Add discount step</button>
            <Help text="The best step reached applies to the whole bundle." />
          </div>
        </Section>
        <Extras />
      </>
    );
  }
  const addOffer = (kind) => update((s) => {
    const n = s.offers.length;
    s.offers.push({ id: `o${Date.now().toString(36)}`, kind, title: kind === 'multi' ? 'Bundle pack' : `${n + 1} Products`, subtitle: 'You save {saving}', quantity: kind === 'quantity' ? n + 1 : 1, product: [], products: [], discount_type: 'percentage', discount_value: 10, badge: '', label: '', highlight: false, preselected: false, visible: true, gifts: [] });
    expand(`offer-${n}`);
    return s;
  });
  return (
    <>
      <section className="bx-card">
        <header className="bx-inline"><strong>Offers ({state.offers.length})</strong>{errors.offers && <p className="b-error">{errors.offers}</p>}</header>
        {state.offers.map((o, i) => (
          <div key={o.id || i} className={`bx-offer ${open.has(`offer-${i}`) ? 'open' : ''} ${o.visible ? '' : 'hidden-offer'}`}>
            <div className="bx-offer-head">
              <button type="button" className="bx-section-toggle" onClick={() => toggle(`offer-${i}`)}><span className="bx-chevron" />Offer {i + 1} <span className="bx-muted">– {o.title || offerTitle(o)}</span></button>
              <div className="bx-offer-tools">
                <button type="button" className={`bx-tool ${o.highlight ? 'on' : ''}`} title="Highlight this offer" aria-label="Highlight" onClick={() => update((s) => { s.offers[i].highlight = !o.highlight; return s; })}>★</button>
                <button type="button" className={`bx-tool ${o.visible ? 'on' : ''}`} title={o.visible ? 'Hide offer' : 'Show offer'} aria-label="Visible" onClick={() => update((s) => { s.offers[i].visible = !o.visible; return s; })}>👁</button>
                <button type="button" className="bx-tool" title="Move up" aria-label="Move up" disabled={!i} onClick={() => update((s) => { const [m] = s.offers.splice(i, 1); s.offers.splice(i - 1, 0, m); return s; })}>↑</button>
                <button type="button" className="bx-tool" title="Move down" aria-label="Move down" disabled={i >= state.offers.length - 1} onClick={() => update((s) => { const [m] = s.offers.splice(i, 1); s.offers.splice(i + 1, 0, m); return s; })}>↓</button>
                <button type="button" className="bx-tool" title="Duplicate" aria-label="Duplicate" onClick={() => update((s) => { const c = clone(s.offers[i]); c.id = `o${Date.now().toString(36)}`; c.preselected = false; s.offers.splice(i + 1, 0, c); return s; })}>⧉</button>
                <button type="button" className="bx-tool danger" title="Delete" aria-label="Delete" onClick={() => {
                  if (!window.confirm('Delete this offer?')) return;
                  update((s) => { s.offers.splice(i, 1); if (s.offers.length && !s.offers.some((x) => x.preselected)) s.offers[0].preselected = true; return s; });
                }}>🗑</button>
              </div>
            </div>
            <div className="bx-offer-body"><OfferBody o={o} i={i} /></div>
          </div>
        ))}
        <div className="bx-add-offer">
          <span className="b-label">Add offer</span>
          <div className="b-actions">{Object.entries(meta.offerKinds).map(([k, l]) => <button type="button" key={k} className="b-btn b-primary" onClick={() => addOffer(k)}>+ {l}</button>)}</div>
        </div>
      </section>
      <Extras />
    </>
  );
}

function OfferBody({ o, i }) {
  const { state, update, meta, currency } = useContext(Ctx);
  const p = `offers.${i}`;
  return (
    <>
      {Object.keys(meta.offerKinds).length > 1 && <F path={`${p}.kind`} label="Offer type" type="select" options={meta.offerKinds} />}
      <Row><F path={`${p}.title`} label="Title" max={80} /><F path={`${p}.subtitle`} label="Subtitle" max={120} help="Use {saving} for the amount saved and {percent} for the % off." /></Row>
      {o.kind === 'quantity' && <F path={`${p}.quantity`} label="Quantity of the product on the page" type="number" min={1} max={50} />}
      {o.kind === 'mono' && <><Picker path={`${p}.product`} label="Product (choose one variant for a pack size)" max={1} /><F path={`${p}.quantity`} label="Quantity" type="number" min={1} max={50} /></>}
      {o.kind === 'multi' && <Picker path={`${p}.products`} label="Products in this pack" max={10} quantities />}
      <Row>
        <F path={`${p}.discount_type`} label="Discount" type="select" options={meta.discounts} />
        {o.discount_type !== 'none' && <F path={`${p}.discount_value`} label={o.discount_type === 'percentage' ? '% off' : `Value (${currency})`} type="number" min={0} step={0.01} help={o.discount_type === 'fixed_price' ? 'The total the shopper pays for this offer.' : ''} />}
      </Row>
      <Row><F path={`${p}.label`} label="Ribbon" max={40} placeholder="e.g. Most popular" /><F path={`${p}.badge`} label="Badge" max={24} placeholder="Automatic: −20%" /></Row>
      <div className="b-field b-toggle"><label><input type="radio" name="preselected" checked={!!o.preselected} onChange={() => update((s) => { s.offers.forEach((x, j) => { x.preselected = j === i; }); return s; })} /> Selected by default</label></div>
      {state.gifts.enabled && (
        <div className="bx-sub-list">
          <span className="b-label">Free gifts with this offer</span>
          {(o.gifts || []).map((g, j) => (
            <div className="bx-sub-item" key={j}>
              <Picker path={`${p}.gifts.${j}.product`} label={`Gift ${j + 1}`} max={1} />
              <F path={`${p}.gifts.${j}.quantity`} label="Quantity" type="number" min={1} max={10} />
              <button type="button" className="b-icon-btn" aria-label="Remove gift" onClick={() => update((s) => { s.offers[i].gifts.splice(j, 1); return s; })}>×</button>
            </div>
          ))}
          <button type="button" className="b-btn" onClick={() => update((s) => { (s.offers[i].gifts = s.offers[i].gifts || []).push({ product: [], quantity: 1 }); return s; })}>Add gift</button>
        </div>
      )}
    </>
  );
}

function DesignPanel() {
  const { state, update, meta } = useContext(Ctx);
  const d = 'design.';
  return (
    <>
      <Section id="d-overall" title="Overall design of the bundle">
        <div className="b-field">
          <span className="b-label">Colour preset</span>
          <div className="bx-swatches">
            {Object.entries(meta.presets).map(([k, p]) => (
              <button type="button" key={k} className="bx-swatch" style={{ '--sw': p.accent }} aria-pressed={state.design.preset === k} aria-label={k} onClick={() => update((s) => { s.design = { ...s.design, ...meta.presets[k], preset: k }; return s; })} />
            ))}
          </div>
        </div>
        <Row><F path={`${d}accent`} label="Accent (selected offer)" type="color" /><F path={`${d}selected_background`} label="Selected background" type="color" /></Row>
        <Row><F path={`${d}background`} label="Background" type="color" /><F path={`${d}border`} label="Border" type="color" /></Row>
        <Row><F path={`${d}text`} label="Text" type="color" /><F path={`${d}muted`} label="Secondary text" type="color" /></Row>
        <Row><F path={`${d}radius`} label="Corner radius (px)" type="number" min={0} max={40} /><F path={`${d}border_width`} label="Border width (px)" type="number" min={0} max={6} /></Row>
        <Row>
          <F path={`${d}spacing`} label="Spacing" type="select" options={{ compact: 'Compact', comfortable: 'Comfortable', spacious: 'Spacious' }} />
          <F path={`${d}font`} label="Font" type="select" options={{ theme: 'Match my theme', system: 'System font' }} />
        </Row>
      </Section>
      <Section id="d-type" title="Typography and images">
        <Row><F path={`${d}title_size`} label="Header size (px)" type="number" min={10} max={40} /><F path={`${d}offer_title_size`} label="Offer title size (px)" type="number" min={10} max={40} /></Row>
        <Row><F path={`${d}price_size`} label="Price size (px)" type="number" min={10} max={40} /><F path={`${d}image_size`} label="Image size (px)" type="number" min={24} max={80} /></Row>
      </Section>
      <Section id="d-labels" title="Ribbons and badges">
        <Row><F path={`${d}label_background`} label="Ribbon background" type="color" /><F path={`${d}label_text`} label="Ribbon text" type="color" /></Row>
        <Row><F path={`${d}badge_background`} label="Badge background" type="color" /><F path={`${d}badge_text`} label="Badge text" type="color" /></Row>
      </Section>
      <Section id="d-button" title="Button"><Row><F path={`${d}button_background`} label="Button background" type="color" /><F path={`${d}button_text`} label="Button text" type="color" /></Row></Section>
      <Section id="d-gift" title="Gift design"><F path={`${d}gift_background`} label="Gift tile background" type="color" /></Section>
      <Section id="d-summary" title="Savings summary design"><Row><F path={`${d}summary_background`} label="Background" type="color" /><F path={`${d}summary_text`} label="Text" type="color" /></Row></Section>
      <Section id="d-css" title="Custom CSS"><F path={`${d}custom_css`} label="CSS" type="textarea" rows={6} code help="Scoped to this bundle. Classes start with .oo-b." /></Section>
    </>
  );
}
