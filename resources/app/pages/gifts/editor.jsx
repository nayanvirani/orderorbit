// Progressive gifts editor: Rewards / Settings / Design over one JSON config, with a live preview
// driven by a sample cart value. The server (GiftSchema) validates on save.
import { useEffect, useRef, useState } from 'react';
import { clone, Ctx, F, Help, Picker, Row, setIn } from '../../components/editorKit.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { money, Page } from '../../components/ui.jsx';
import { route, useRouter, useShared } from '../../router.jsx';
import ExperienceStatus from '../cro/_status.jsx';

const Card = ({ title, children }) => <section className="bx-card"><header className="bx-inline"><strong>{title}</strong></header>{children}</section>;

export default function GiftEditor({ experience: x, config, fieldErrors: errors, banner, meta }) {
  const { submit } = useRouter();
  const { currency } = useShared();
  const ready = useRuntime();
  const [state, setState] = useState(config);
  const [name, setName] = useState(x.name);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(null);
  const [device, setDevice] = useState('desktop');
  const [cart, setCart] = useState(60);
  const first = Object.keys(errors)[0];
  const [tab, setTab] = useState(first ? (first.startsWith('milestones') ? 'rewards' : first.startsWith('design') ? 'design' : 'settings') : 'rewards');
  const count = state.settings.unlock === 'count';

  const update = (fn) => { setState((s) => fn(clone(s))); setDirty(true); };
  const set = (path, value) => {
    setState((s) => {
      let next = setIn(s, path, value);
      // A new reward type starts with its usual label.
      if (/^milestones\.\d+\.reward$/.test(path)) next = setIn(next, path.replace(/reward$/, 'label'), meta.rewards[value]);
      return next;
    });
    setDirty(true);
  };

  const dirtyRef = useRef(dirty);
  dirtyRef.current = dirty;
  useEffect(() => {
    const warn = (e) => { if (dirtyRef.current) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, []);

  const save = async (action) => {
    setBusy(action);
    await submit(route('app.gifts.update', { gift: x.id }), { action, name, config: state });
    setBusy(null);
    setDirty(false);
  };

  // The storefront payload (mirrors GiftSchema::payload).
  const top = Math.max(...state.milestones.map((m) => Number(m.threshold) || 0), count ? 5 : 100);
  const payload = () => {
    const c = clone(state);
    c.milestones.sort((a, b) => a.threshold - b.threshold).forEach((m, i) => {
      m.index = i;
      if ((m.reward === 'gift' || m.reward === 'choice') && !m.products.length) m.products = m.reward === 'choice' ? meta.samples.slice(0, 3) : [meta.samples[3]];
    });
    const d = c.design;
    return {
      id: x.handle, type: 'progressive-gifts', template: 'editor', style: c.settings.layout, version: 0, priority: 50,
      content: { settings: c.settings, milestones: c.milestones },
      design: { ...d, primary_color: d.accent, accent_color: d.accent, text_color: d.text, background_color: d.background, border: true },
      behavior: {}, targeting: {},
    };
  };

  return (
    <Page heading={x.name} back={route('app.gifts.index')} backLabel="Progressive gifts">
      {banner && <s-banner tone={Object.keys(errors).length ? 'warning' : 'critical'}>{banner}</s-banner>}
      {x.status === 'published' && x.unpublished && <s-banner tone="info">These rewards have changes that aren't live yet. Publish to update your store.</s-banner>}

      <Ctx.Provider value={{ state, set, update, errors, meta, currency, open: new Set(), toggle: () => {}, expand: () => {} }}>
        <div className="bx-editor">
          <div className="bx-editor-top">
            <label className="bx-name"><span className="bx-muted">Name (only you see it)</span><input value={name} maxLength={120} onChange={(e) => { setName(e.target.value); setDirty(true); }} /></label>
            <div className="bx-status"><ExperienceStatus experience={x} /></div>
          </div>

          <div className="bx-layout">
            <div className="bx-panel-col">
              <nav className="bx-tabbar" role="tablist" aria-label="Progressive gifts editor">
                {[['rewards', 'Rewards'], ['settings', 'Settings'], ['design', 'Design']].map(([k, l]) => <button type="button" role="tab" key={k} aria-selected={tab === k} onClick={() => setTab(k)}>{l}</button>)}
              </nav>

              {tab === 'rewards' && (
                <>
                  <Card title="Unlock rewards by">
                    <div className="bx-seg" role="group">
                      {[['value', 'Cart value'], ['count', 'Item count']].map(([k, l]) => <button type="button" key={k} aria-pressed={state.settings.unlock === k} onClick={() => set('settings.unlock', k)}>{l}</button>)}
                    </div>
                  </Card>
                  <Card title={`Rewards (${state.milestones.length})`}>
                    {state.milestones.map((m, i) => {
                      const p = `milestones.${i}`;
                      return (
                        <div className="bx-milestone" key={i}>
                          <div className="bx-milestone-head"><strong>Reward {i + 1}</strong><button type="button" className="bx-tool danger" aria-label="Remove reward" onClick={() => update((s) => { s.milestones.splice(i, 1); return s; })}>🗑</button></div>
                          <Row>
                            <F path={`${p}.threshold`} label={count ? 'Unlocks at (items)' : `Unlocks at (${currency})`} type="number" min={0} step={count ? 1 : 0.01} />
                            <F path={`${p}.reward`} label="Reward" type="select" options={meta.rewards} />
                          </Row>
                          <F path={`${p}.label`} label="Label shoppers see" max={40} />
                          {m.reward === 'gift' && <Row><Picker path={`${p}.products`} label="Gift product" max={1} help="" /><F path={`${p}.quantity`} label="Quantity" type="number" min={1} max={5} /></Row>}
                          {m.reward === 'choice' && <Picker path={`${p}.products`} label="Gifts shoppers choose from" max={8} help="" />}
                          {(m.reward === 'percent' || m.reward === 'amount') && <F path={`${p}.value`} label={m.reward === 'percent' ? '% off the order' : `Amount off (${currency})`} type="number" min={0} step={0.01} />}
                        </div>
                      );
                    })}
                    {errors.milestones && <p className="b-error">{errors.milestones}</p>}
                    {state.milestones.length < 5 && (
                      <div className="b-actions" style={{ marginTop: 12 }}>
                        <button type="button" className="b-btn b-primary" onClick={() => update((s) => {
                          const last = s.milestones[s.milestones.length - 1];
                          s.milestones.push({ threshold: last ? Number(last.threshold) + (count ? 1 : 25) : 50, reward: 'shipping', label: 'Free shipping', value: 0, products: [], quantity: 1 });
                          return s;
                        })}>+ Add reward</button>
                      </div>
                    )}
                    <Help text="Gifts are free at checkout while the cart qualifies; if it drops below, the gift is taken out of the cart. Free shipping and order discounts apply automatically." />
                  </Card>
                </>
              )}

              {tab === 'settings' && (
                <>
                  <Card title="Messages">
                    <F path="settings.title" label="Title (optional)" max={80} />
                    <F path="settings.progress_message" label="Progress message" max={160} help="Use {remaining} and {reward}." />
                    <F path="settings.unlocked_message" label="When everything is unlocked" max={160} />
                  </Card>
                  <Card title="Where it shows">
                    <Row>
                      <F path="settings.placement" label="Pages" type="select" options={{ both: 'Product pages and cart', product: 'Product pages', cart: 'Cart page' }} />
                      <F path="settings.position" label="On product pages" type="select" options={{ below_atc: 'Below the add to cart button', above_atc: 'Above the add to cart button', block: 'Only where I place the block' }} />
                    </Row>
                    <Help text="The cart page shows it where you add the Growvia block in the Theme Editor." />
                    <F path="settings.claim" label="Single gifts" type="select" options={{ auto: 'Add to the cart automatically', claim: 'Shopper claims the gift' }} />
                    <F path="settings.show_empty" label="Show when the cart is empty" type="toggle" />
                  </Card>
                  <Card title="Schedule">
                    <Row><F path="schedule.starts_at" label={`Start (${meta.timezone})`} type="datetime" /><F path="schedule.ends_at" label={`End (${meta.timezone})`} type="datetime" /></Row>
                  </Card>
                </>
              )}

              {tab === 'design' && (
                <>
                  <Card title="Layout">
                    <div className="bx-layouts" style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(110px,1fr))' }}>
                      {Object.entries(meta.layouts).map(([k, l]) => <button type="button" key={k} className={`bx-layout-tile ${state.settings.layout === k ? 'on' : ''}`} aria-pressed={state.settings.layout === k} onClick={() => set('settings.layout', k)}>{l}</button>)}
                    </div>
                  </Card>
                  <Card title="Colours">
                    <Row><F path="design.accent" label="Progress and unlocked" type="color" /><F path="design.track" label="Track" type="color" /></Row>
                    <Row><F path="design.background" label="Background" type="color" /><F path="design.border" label="Border" type="color" /></Row>
                    <Row><F path="design.text" label="Text" type="color" /><F path="design.muted" label="Locked rewards" type="color" /></Row>
                  </Card>
                  <Card title="Shape">
                    <Row><F path="design.radius" label="Corner radius (px)" type="number" min={0} max={40} /><F path="design.bar_height" label="Bar height (px)" type="number" min={2} max={24} /></Row>
                    <F path="design.title_size" label="Message size (px)" type="number" min={10} max={32} />
                  </Card>
                  <Card title="Custom CSS"><F path="design.custom_css" label="CSS" type="textarea" rows={5} code help="Scoped to these rewards. Classes start with .oo-pg." /></Card>
                </>
              )}
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
                <label className="bx-preview-product">
                  <span className="bx-muted">{count ? 'Items in cart' : 'Cart value'}</span>
                  <input type="range" min="0" max={Math.ceil(top * 1.2)} value={cart} onChange={(e) => setCart(Number(e.target.value))} style={{ flex: 1 }} />
                  <b>{count ? cart : money(cart, currency)}</b>
                </label>
                <div className="b-stage">
                  <div className={`b-frame ${device === 'mobile' ? 'oo-preview-mobile' : ''}`}>
                    <div className="bx-fake-atc-wrap">
                      <span className="bx-fake-atc">Add to cart</span>
                      {/* The slider is the preview cart: its value or item count. */}
                      <Preview ready={ready} experience={payload()} context={{ currency, page: 'product', previewProgress: cart }} className="oo-root" />
                    </div>
                  </div>
                </div>
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
