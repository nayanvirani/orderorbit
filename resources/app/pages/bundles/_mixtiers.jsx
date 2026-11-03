// Editor for Mix and match quantity tiers (G44): the items shoppers mix, the rule and its tiers by total
// quantity, the price per unit and how it's shown, with a worked example of the price.
import { useContext } from 'react';
import { Ctx, F, Help, Picker, Row } from '../../components/editorKit.jsx';
import { Delivery } from './_tiered.jsx';

const RULES = { free: 'Buy X, get Y free', percent: '% off', fixed: 'Set price (e.g. 3 for 99)' };
const VALUE = { free: 'Free items', percent: '% off', fixed: 'Set price' };

function Card({ title, children }) {
  return <section className="bx-card tx-card"><h2 className="tx-title">{title}</h2>{children}</section>;
}

// The same arithmetic as the storefront and the Growvia discount at checkout.
function priceOf(m, prices) {
  const full = prices.reduce((s, v) => s + v, 0);
  let tier = null;
  m.tiers.forEach((t) => { if (prices.length >= t.count) tier = t; });
  if (!tier) return { full, total: full, tier };
  const sorted = [...prices].sort((a, b) => a - b);
  const sets = Math.floor(prices.length / tier.count);
  if (m.rule === 'percent') return { full, total: full * (1 - tier.value / 100), tier };
  if (m.rule === 'free') return { full, total: full - sorted.slice(0, sets * tier.value).reduce((s, v) => s + v, 0), tier };
  const inSets = sorted.slice(0, sets * tier.count).reduce((s, v) => s + v, 0);
  return { full, total: full - inSets + sets * tier.value, tier };
}

export default function MixTiersPanel() {
  const { state, set, update, errors, currency } = useContext(Ctx);
  const m = state.mt;
  const p = 'mt';
  const fmt = (v) => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(v || 0);
  const name = (t) => t.label || (m.rule === 'free' ? `${t.count - t.value}+${t.value}` : m.rule === 'percent' ? `${t.count} items` : `${t.count} for ${fmt(t.value)}`);

  // Worked example: the highest tier, filled with the first items in the pool (sample prices if none yet).
  const pool = m.source === 'variants' ? (m.pool[0]?.variants || []).map((v) => Number(v.price || m.pool[0]?.price || 29)) : m.pool.map((x) => Number(x.price || 29));
  const prices = [];
  const top = m.tiers[m.tiers.length - 1];
  for (let i = 0; i < (top?.count || 3); i++) prices.push(pool.length ? pool[i % pool.length] : [39.95, 34.95, 39.95][i % 3]);
  const ex = priceOf(m, prices);

  return (
    <>
      <Card title="Items shoppers mix">
        <label className="b-field">Choose from
          <select value={m.source} onChange={(e) => update((s) => { s.mt.source = e.target.value; s.mt.pool = []; return s; })}>
            <option value="products">Several products</option>
            <option value="variants">One product's variants (colours, sizes, flavours…)</option>
          </select>
        </label>
        <Picker path={`${p}.pool`} label={m.source === 'variants' ? 'The product (tick the variants to offer)' : 'Products'} max={m.source === 'variants' ? 1 : 30}
          help={m.source === 'variants' ? 'Each variant you tick becomes a tile.' : 'Each product is a tile; its first variant in stock is added.'} />
        <F path={`${p}.max`} label="Most items in one box (0 = no limit)" type="number" min={0} max={200} />
      </Card>

      <Card title="Tiers by total quantity">
        <label className="b-field">Rule
          <select value={m.rule} onChange={(e) => update((s) => {
            s.mt.rule = e.target.value;
            s.mt.tiers = e.target.value === 'free' ? [{ count: 3, value: 1 }, { count: 5, value: 2 }] : e.target.value === 'percent' ? [{ count: 2, value: 10 }, { count: 3, value: 20 }] : [{ count: 3, value: 99 }, { count: 6, value: 179 }];
            return s;
          })}>
            {Object.entries(RULES).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
          </select>
        </label>
        {errors['mt.tiers'] && <p className="b-error">{errors['mt.tiers']}</p>}
        <div className="tx-table" role="table" aria-label="Tiers">
          <div className="tx-tr mx-tr tx-th" role="row"><span>Items in a set</span><span>{VALUE[m.rule]}</span><span>Name</span><span>Badge</span><span /></div>
          {m.tiers.map((t, i) => (
            <div className={`tx-tr mx-tr ${errors[`mt.tiers.${i}.value`] ? 'tx-err' : ''}`} role="row" key={i}>
              <input type="number" min={2} max={100} aria-label={`Tier ${i + 1} items`} value={t.count} onChange={(e) => set(`${p}.tiers.${i}.count`, Number(e.target.value) || 2)} />
              <input type="number" min={0} step={m.rule === 'fixed' ? 0.01 : 1} aria-label={`Tier ${i + 1} ${VALUE[m.rule]}`} value={t.value} onChange={(e) => set(`${p}.tiers.${i}.value`, Number(e.target.value) || 0)} />
              <input aria-label={`Tier ${i + 1} name`} maxLength={24} placeholder={name({ ...t, label: '' })} value={t.label || ''} onChange={(e) => set(`${p}.tiers.${i}.label`, e.target.value)} />
              <input aria-label={`Tier ${i + 1} badge`} maxLength={16} placeholder="Automatic" value={t.badge || ''} onChange={(e) => set(`${p}.tiers.${i}.badge`, e.target.value)} />
              <button type="button" className="b-icon-btn" aria-label={`Delete tier ${i + 1}`} disabled={m.tiers.length < 2} onClick={() => update((s) => { s.mt.tiers.splice(i, 1); return s; })}>×</button>
            </div>
          ))}
          {Object.keys(errors).filter((k) => /^mt\.tiers\.\d+/.test(k)).map((k) => <p className="b-error" key={k}>{errors[k]}</p>)}
        </div>
        <button type="button" className="b-btn" disabled={m.tiers.length >= 6} onClick={() => update((s) => {
          const last = s.mt.tiers[s.mt.tiers.length - 1] || { count: 1, value: 0 };
          s.mt.tiers.push({ count: last.count + (s.mt.rule === 'free' ? 2 : 1), value: s.mt.rule === 'free' ? last.value + 1 : s.mt.rule === 'percent' ? Math.min(90, last.value + 10) : Math.round(last.value * 1.8), label: '', badge: '' });
          return s;
        })}>Add tier</button>
        <Help text={{ free: 'Buy X, get Y free: the cheapest items in the box are free, so mixing prices never gives away the most expensive one. It repeats for each full set.', percent: 'Every item in the box gets the % off of the tier reached.', fixed: 'Each full set of the cheapest items costs the set price; extra items are charged normally.' }[m.rule]} />
      </Card>

      <Card title="How the price is worked out">
        <dl className="mx-calc">
          <div><dt>Items chosen</dt><dd>{prices.length}</dd></div>
          <div><dt>Full price</dt><dd>{fmt(ex.full)}</dd></div>
          <div><dt>Tier reached</dt><dd>{ex.tier ? name(ex.tier) : '—'}</dd></div>
          <div><dt>Saving</dt><dd className="mx-save">{fmt(ex.full - ex.total)}</dd></div>
          <div><dt>Shopper pays</dt><dd><b>{fmt(ex.total)}</b></dd></div>
          {m.units > 0 && <div><dt>Per {m.unit_label} ({m.units} per item)</dt><dd>{fmt(ex.total / (prices.length * m.units))}</dd></div>}
        </dl>
        <Help text={`With ${pool.length ? 'your first items' : 'sample prices'}. Checkout charges exactly this, because the Growvia discount reads the same tiers.`} />
      </Card>

      <Card title="Price per unit">
        <Row>
          <F path={`${p}.units`} label="Units in one item (0 = don't show)" type="number" min={0} max={100000} help="For example 40 capsules, 30 servings." />
          <F path={`${p}.unit_label`} label="Unit name" max={20} placeholder="unit, serving, 100 g" />
        </Row>
      </Card>

      <Card title="Display">
        <Row>
          <F path={`${p}.layout`} label="Items" type="select" options={{ grid: 'Grid', list: 'List with steppers', row: 'Row (swipe)' }} />
          <F path={`${p}.tier_style`} label="Tiers as" type="select" options={{ slider: 'Slider', cards: 'Cards' }} />
        </Row>
        {m.layout === 'grid' && <Row><F path={`${p}.columns_desktop`} label="Columns on desktop" type="number" min={2} max={6} /><F path={`${p}.columns_mobile`} label="Columns on mobile" type="number" min={1} max={4} /></Row>}
        <Row><F path={`${p}.best_label`} label="Ribbon on the tiers" max={24} placeholder="BEST OFFER" /><F path={`${p}.first_label`} label="First step" max={24} /></Row>
        <F path="settings.button_text" label="Add to cart button" max={40} />
      </Card>
      <Card title="Texts and visibility">
        <Help text="Change any text shoppers see, and hide what you don't need. Empty a text to hide it." />
        <p className="tx-sub">Above the items</p>
        <Row><F path="settings.title" label="Title" max={80} placeholder="Empty = no title" /><F path="settings.subtitle" label="Subtitle" max={120} placeholder="Empty = no subtitle" /></Row>
        <F path={`${p}.show_tiers`} label="Show the tiers (slider or cards)" type="toggle" />
        {m.show_tiers !== false && <F path={`${p}.show_badges`} label="Show the discount badges on the tiers (−33%)" type="toggle" />}
        <p className="tx-sub">Items</p>
        <F path={`${p}.show_price`} label="Show each item's price" type="toggle" />
        <Row><F path={`${p}.add_text`} label="Add button" max={16} /><F path={`${p}.sold_text`} label="Sold out text" max={24} /></Row>
        <p className="tx-sub">Under the items</p>
        <F path={`${p}.show_nudge`} label="Show the nudge line" type="toggle" />
        {m.show_nudge !== false && (
          <>
            <F path={`${p}.empty`} label="Before anything is chosen" max={60} placeholder="Choose your items" />
            <F path={`${p}.nudge`} label="While a better tier is left" max={80} help="{count} becomes the items still needed and {tier} the next tier." />
            <F path={`${p}.done`} label="When the best tier is reached" max={60} />
          </>
        )}
        <F path={`${p}.button_price`} label="Show the total in the add to cart button" type="toggle" />
        <F path="tiers.show_clock" label="Show the clock icon in the delivery bar" type="toggle" />
      </Card>
      <Delivery />
    </>
  );
}
