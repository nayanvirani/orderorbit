// Editor for the tiered gift bundle (G36 design): a compact tiers table, what's in each tier, then the
// sale header, gifts row, delivery bar and button. It edits the same config as the classic Offers panel.
import { useContext, useState } from 'react';
import { Ctx, F, Help, Picker, Row } from '../../components/editorKit.jsx';

// The tiered templates' colour styles (BundleSchema models qg-tiered and qg-tiered-sage).
const STYLES = {
  berry: { accent: '#7d1420', selected_background: '#f5e6e8', button_background: '#7d1420', label_background: '#7d1420', background: '#ffffff', border: '#e2b8bf', gift_background: '#f6eaec', summary_background: '#f3f2ea', summary_text: '#15181c' },
  sage: { accent: '#1f5446', selected_background: '#e6f2ec', button_background: '#1f5446', label_background: '#1f5446', background: '#ffffff', border: '#b9d3c7', gift_background: '#eef4f1', summary_background: '#f3f2ea', summary_text: '#15181c' },
};
const DELIVERY_TEXTS = ['Order now for delivery by: {date}', 'Order in {time} for delivery by {date}', 'Order in {time} to ship today'];
const DELIVERY_DAYS = { '3-5': 'Delivery takes 3–5 working days', '2-3': '2–3 working days', '5-8': '5–8 working days', '1-2': '1–2 working days' };

const units = (o) => (o.kind === 'multi' ? (o.products || []).reduce((s, p) => s + Number(p.quantity || 1), 0) : Number(o.quantity || 1));
const giftNames = (o) => (o.gifts || []).map((g) => g.product?.[0]?.title).filter(Boolean).join(', ');

export default function TieredPanel() {
  const { state } = useContext(Ctx);
  const [tier, setTier] = useState(() => Math.max(0, state.offers.findIndex((o) => o.preselected)));
  const current = Math.min(tier, state.offers.length - 1);
  return (
    <>
      <Tiers onPick={setTier} />
      {state.offers.length > 0 && <InEachTier tier={current} onPick={setTier} />}
      <SaleHeader />
      <GiftsRow />
      <Delivery />
      <Button />
    </>
  );
}

function Card({ title, children, tag }) {
  return (
    <section className="bx-card tx-card">
      <h2 className="tx-title">{title}{tag && <span className="tx-tag">{tag}</span>}</h2>
      {children}
    </section>
  );
}

function Tiers({ onPick }) {
  const { state, set, update, errors, meta, currency } = useContext(Ctx);
  const add = () => update((s) => {
    const last = s.offers[s.offers.length - 1];
    const q = last ? units(last) + 1 : 1;
    s.offers.push({ id: `o${Date.now().toString(36)}`, kind: 'quantity', title: `Buy ${q}`, subtitle: 'You save {saving}', quantity: q, product: [], products: [], discount_type: 'percentage', discount_value: last ? Math.min(90, Number(last.discount_value || 0) + 5) : 0, badge: '', label: '', label_style: 'filled', highlight: false, preselected: false, visible: true, notes: [], gifts: [] });
    return s;
  });
  return (
    <Card title="Tiers">
      {errors.offers && <p className="b-error">{errors.offers}</p>}
      <div className="tx-table" role="table" aria-label="Tiers">
        <div className="tx-tr tx-th" role="row"><span>Qty</span><span>Title</span><span>Off</span><span>Badge</span><span>Free gifts</span><span /></div>
        {state.offers.map((o, i) => (
          <div className={`tx-tr ${errors[`offers.${i}.title`] ? 'tx-err' : ''}`} role="row" key={o.id || i}>
            {o.kind === 'multi'
              ? <span className="tx-fixed" title="A mix of products: change it under What's in each tier">{units(o)}</span>
              : <input type="number" min={1} max={50} aria-label={`Tier ${i + 1} quantity`} value={o.quantity} onChange={(e) => set(`offers.${i}.quantity`, Math.max(1, Number(e.target.value) || 1))} />}
            <input aria-label={`Tier ${i + 1} title`} value={o.title} maxLength={80} onChange={(e) => set(`offers.${i}.title`, e.target.value)} />
            <span className="tx-off">
              <input type="number" min={0} step={0.01} aria-label={`Tier ${i + 1} discount`} value={o.discount_type === 'none' ? '' : o.discount_value}
                onChange={(e) => update((s) => { const v = e.target.value === '' ? 0 : Number(e.target.value); s.offers[i].discount_value = v; if (s.offers[i].discount_type === 'none' && v > 0) s.offers[i].discount_type = 'percentage'; return s; })} />
              <select aria-label={`Tier ${i + 1} discount type`} value={o.discount_type} onChange={(e) => set(`offers.${i}.discount_type`, e.target.value)}>
                <option value="percentage">%</option><option value="amount">{currency} off</option><option value="fixed_price">{currency} total</option><option value="none">—</option>
              </select>
            </span>
            <input aria-label={`Tier ${i + 1} badge`} value={o.label} maxLength={40} placeholder="None" onChange={(e) => set(`offers.${i}.label`, e.target.value)} />
            <button type="button" className="tx-gifts" onClick={() => onPick(i)} title="Edit under What's in each tier">{giftNames(o) || ((o.gifts || []).length ? `${o.gifts.length} to choose` : 'None')}</button>
            <button type="button" className="b-icon-btn" aria-label={`Delete tier ${i + 1}`} disabled={state.offers.length < 2}
              onClick={() => window.confirm(`Delete "${o.title}"?`) && update((s) => { s.offers.splice(i, 1); if (!s.offers.some((x) => x.preselected)) s.offers[0].preselected = true; return s; })}>×</button>
          </div>
        ))}
      </div>
      <div className="tx-actions">
        <button type="button" className="b-btn" onClick={add} disabled={state.offers.length >= 8}>Add tier</button>
        <label className="tx-inline">Selected first
          <select value={Math.max(0, state.offers.findIndex((o) => o.preselected))} onChange={(e) => update((s) => { s.offers.forEach((x, j) => { x.preselected = j === Number(e.target.value); }); return s; })}>
            {state.offers.map((o, i) => <option key={i} value={i}>{o.title || `Tier ${i + 1}`}</option>)}
          </select>
        </label>
      </div>
      <F path="tiers.pictures" label="Show product and gift pictures in each tier" type="toggle" />
      <label className="b-field">Variant picker
        <select value={state.settings.show_variants ? 'show' : 'hide'} onChange={(e) => set('settings.show_variants', e.target.value === 'show')}>
          <option value="show">Show: shoppers choose a variant for each item (#1, #2 …)</option>
          <option value="hide">Hide: add the first variant in stock</option>
        </select>
      </label>
      <Row>
        <F path="tiers.second_line" label="Second line under the title" type="select" options={{ subtitle: 'You save {saving}', each: 'Price per item', none: 'None' }} />
        <label className="b-field">Badge style
          <select value={state.offers.find((o) => o.label)?.label_style || 'filled'} onChange={(e) => update((s) => { s.offers.forEach((o, j) => { if (o.label) o.label_style = j === s.offers.length - 1 && e.target.value === 'mixed' ? 'outlined' : e.target.value === 'mixed' ? 'filled' : e.target.value; }); return s; })}>
            <option value="filled">Filled (Most Popular)</option><option value="outlined">Outlined (Best Value)</option><option value="mixed">Filled, last tier outlined</option>
          </select>
        </label>
      </Row>
      {state.tiers.second_line === 'each' && <F path="tiers.unit" label="Item word" max={20} placeholder="bottle" help='Shows "$28.67 per bottle".' />}
      <Help text={meta.tiered ? 'Shoppers pick a tier; checkout charges the tier\'s price and makes its gifts free.' : ''} />
    </Card>
  );
}

function InEachTier({ tier, onPick }) {
  const { state, update, currency } = useContext(Ctx);
  const o = state.offers[tier];
  const p = `offers.${tier}`;
  const mix = () => update((s) => { s.offers[tier].kind = 'multi'; s.offers[tier].products = []; return s; });
  const single = () => update((s) => { s.offers[tier].kind = 'quantity'; s.offers[tier].quantity = Math.max(1, units(s.offers[tier])); s.offers[tier].products = []; return s; });
  return (
    <Card title="What's in each tier">
      <F path="tiers.details" label="Open the selected tier to list its items and free gifts, each with a check icon, value and short description" type="toggle" />
      <div className="tx-seg" role="tablist" aria-label="Tier">
        {state.offers.map((x, i) => <button type="button" role="tab" key={i} aria-selected={i === tier} onClick={() => onPick(i)}>{x.title || `Tier ${i + 1}`}</button>)}
      </div>
      <p className="tx-sub">Items in "{o.title}"</p>
      {o.kind === 'multi' ? (
        <>
          <Picker path={`${p}.products`} label="Products in this tier, with quantities" max={10} quantities help="For example 2× serum and 1× night cream. Checkout merges them into one bundle line at the tier's price." />
          {(o.products || []).map((x, j) => (
            <div className="tx-item" key={j}>
              <span className="tx-name">{x.quantity || 1}× {x.title}</span>
              <F path={`${p}.notes.${j}.value`} label={`Value (${currency})`} type="number" min={0} step={0.01} />
              <F path={`${p}.notes.${j}.desc`} label="Short description" max={80} />
            </div>
          ))}
          <button type="button" className="b-btn" onClick={single}>Use only the product on the page</button>
        </>
      ) : (
        <>
          <div className="tx-item">
            <span className="tx-name">{o.quantity}× the product on the page</span>
            <F path={`${p}.notes.0.value`} label={`Value (${currency})`} type="number" min={0} step={0.01} />
            <F path={`${p}.notes.0.desc`} label="Short description" max={80} placeholder="e.g. Brightening daily serum" />
          </div>
          <button type="button" className="b-btn" onClick={mix}>Add another product</button>
        </>
      )}
      <p className="tx-sub">Free gifts in this tier (shown under "PLUS FREE GIFTS")</p>
      <F path="gifts.enabled" label="Free gifts in the tiers" type="toggle" help="Off: gifts aren't added or made free at checkout." />
      {(o.gifts || []).map((g, j) => (
        <div className="tx-item tx-gift" key={j}>
          <Picker path={`${p}.gifts.${j}.product`} label={`Gift ${j + 1}`} max={1} help="" />
          <F path={`${p}.gifts.${j}.quantity`} label="Qty" type="number" min={1} max={10} />
          <F path={`${p}.gifts.${j}.value`} label={`Value (${currency})`} type="number" min={0} step={0.01} />
          <F path={`${p}.gifts.${j}.desc`} label="Short description" max={80} />
          <button type="button" className="b-icon-btn" aria-label="Remove gift" onClick={() => update((s) => { s.offers[tier].gifts.splice(j, 1); return s; })}>×</button>
        </div>
      ))}
      {(o.gifts || []).length < 4 && <button type="button" className="b-btn" onClick={() => update((s) => { (s.offers[tier].gifts = s.offers[tier].gifts || []).push({ product: [], quantity: 1, desc: '', value: null }); return s; })}>Add gift</button>}
      <F path="tiers.value_price" label="Crossed-out price = total value of the items and gifts" type="toggle" help="Values come from each product's compare-at price (or price) and can be changed. The total becomes the tier's crossed-out price." />
    </Card>
  );
}

function SaleHeader() {
  const { state } = useContext(Ctx);
  return (
    <Card title="Sale price header">
      <F path="tiers.sale_header" label="Show the selected price, the compare-at price and a sale badge above the tiers" type="toggle" />
      {state.tiers.sale_header && <F path="tiers.sale_badge" label="Badge text" max={60} help="{saving} becomes the amount saved for the chosen tier, e.g. FALL SALE · Save {saving}." />}
    </Card>
  );
}

function GiftsRow() {
  const { state, set, currency } = useContext(Ctx);
  const t = state.tiers;
  // Each gift once, with the first tier that includes it (as the storefront shows them).
  const seen = new Set();
  const rewards = [];
  state.offers.forEach((o) => (o.gifts || []).forEach((g) => {
    const name = g.product?.[0]?.title;
    if (name && !seen.has(name)) { seen.add(name); rewards.push([name, o]); }
  }));
  const ship = state.offers.find((o) => o.id === t.free_shipping);
  return (
    <Card title="Gifts row">
      <F path="tiers.gifts_row" label="Show the gifts row under the tiers" type="toggle" />
      {t.gifts_row && (
        <>
          <Row><F path="tiers.gifts_title" label="Title" max={60} /><F path="tiers.unlocked_title" label="When everything is unlocked" max={60} /></Row>
          <F path="gifts.locked_text" label="Locked text" max={30} help='Shown with "With Buy 3" under a gift the chosen tier doesn&apos;t include.' />
          <ul className="tx-rewards">
            {ship && <li><b>Free shipping</b><span>unlocks with {ship.title}</span></li>}
            {rewards.map(([name, o]) => <li key={name}><b>{name}</b><span>unlocks with {o.title}</span></li>)}
            {!rewards.length && !ship && <li className="tx-muted">Choose gift products in each tier to see them here.</li>}
          </ul>
        </>
      )}
      <Row>
        <label className="b-field">Free shipping as a reward
          <select value={t.free_shipping} onChange={(e) => set('tiers.free_shipping', e.target.value)}>
            <option value="">No free shipping</option>
            {state.offers.map((o) => <option key={o.id} value={o.id}>From "{o.title}" up</option>)}
          </select>
        </label>
        {t.free_shipping && <F path="tiers.shipping_value" label={`Shipping value (${currency})`} type="number" min={0} step={0.01} help="Shown crossed out. 0 = no amount." />}
      </Row>
      {t.free_shipping && <Help text="Checkout gives free shipping only when the cart holds that tier or a bigger one." />}
    </Card>
  );
}

function Delivery() {
  const { state, set, update } = useContext(Ctx);
  const d = state.tiers.delivery;
  const days = `${d.min_days}-${d.max_days}`;
  const custom = !DELIVERY_TEXTS.includes(d.text);
  return (
    <Card title="Delivery date">
      <F path="tiers.delivery.enabled" label="Show a delivery bar under the gifts" type="toggle" />
      {d.enabled && (
        <>
          <label className="b-field">Text
            <select value={custom ? 'custom' : d.text} onChange={(e) => set('tiers.delivery.text', e.target.value === 'custom' ? `${d.text} ` : e.target.value)}>
              {DELIVERY_TEXTS.map((x) => <option key={x} value={x}>{x}</option>)}
              <option value="custom">Your own text…</option>
            </select>
          </label>
          {custom && <F path="tiers.delivery.text" label="Your text" max={80} help="{date} becomes the delivery date and {time} the time left to order today." />}
          <Row>
            <label className="b-field">Delivery takes
              <select value={DELIVERY_DAYS[days] ? days : 'custom'} onChange={(e) => e.target.value !== 'custom' && update((s) => { const [a, b] = e.target.value.split('-').map(Number); s.tiers.delivery.min_days = a; s.tiers.delivery.max_days = b; return s; })}>
                {Object.entries(DELIVERY_DAYS).map(([k, l]) => <option key={k} value={k}>{l.replace('Delivery takes ', '')}</option>)}
                {!DELIVERY_DAYS[days] && <option value="custom">{d.min_days}–{d.max_days} working days</option>}
              </select>
            </label>
            <F path="tiers.delivery.cutoff" label="Same-day cutoff (store time)" max={5} placeholder="14:00" />
          </Row>
          <F path="tiers.delivery.skip_weekends" label="Skip weekends" type="toggle" />
          <F path="tiers.delivery.holidays" label="Holidays" placeholder="2026-12-25, 2027-01-01" help="Dates that don't count, separated by commas." />
        </>
      )}
    </Card>
  );
}

function Button() {
  const { state, update, meta } = useContext(Ctx);
  const style = Object.keys(STYLES).find((k) => STYLES[k].accent === state.design.accent) || (meta.presets[state.design.preset] ? state.design.preset : 'custom');
  const pick = (k) => update((s) => {
    s.design = { ...s.design, ...(STYLES[k] || meta.presets[k] || {}), preset: k };
    return s;
  });
  return (
    <Card title="Add to cart button">
      <F path="tiers.button_price" label='Show the price in the button, with the compare-at price, e.g. "ADD TO CART | $86.00 $156.00"' type="toggle" />
      <Row>
        <F path="settings.button_text" label="Button text" max={40} />
        <label className="b-field">Style
          <select value={style} onChange={(e) => pick(e.target.value)}>
            <option value="berry">Berry</option><option value="sage">Sage</option><option value="night">Night</option><option value="luxe">Luxe</option>
            {style === 'custom' && <option value="custom">Your colours</option>}
          </select>
          <small className="b-help">Every colour and block is under Design.</small>
        </label>
      </Row>
    </Card>
  );
}

/** Whether a bundle uses the tiered editor: the tiered style on a gift bundle. */
export function usesTiered(state) {
  return ['quantity-gifts', 'fixed-gifts'].includes(state.bundle_type) && state.settings?.skin === 'tiered' && !!state.tiers;
}

