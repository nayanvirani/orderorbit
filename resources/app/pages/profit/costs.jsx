// Profit › Costs (G1): what has a cost, the missing-cost finder (best sellers first) with inline
// and bulk fixes, CSV import, and the cost rules (fees, shipping, fixed monthly costs).
import { useRef, useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { money, ProfitNotices } from './_profit.jsx';

const SHIPPING = { none: 'Not counted', per_order: 'Per order', per_item: 'Per item', percent: '% of shipping charged' };

export default function ProfitCosts({ summary: s, missing, overrides, rules, currency, access, syncing, locked }) {
  const { submit } = useRouter();
  const m = (v) => money(v, currency);
  const [edits, setEdits] = useState({});
  const [picked, setPicked] = useState([]);
  const [percent, setPercent] = useState('');
  const [r, setR] = useState(() => ({
    fees: { percent: rules.fees?.percent ?? 2.9, fixed: rules.fees?.fixed ?? 0.3, countries: rules.fees?.countries || {} },
    shipping: { mode: rules.shipping?.mode || 'none', value: rules.shipping?.value ?? 0 },
    fixed_costs: rules.fixed_costs || [], default_cost_percent: rules.default_cost_percent ?? '',
  }));
  const [errors, setErrors] = useState({});
  const [cc, setCc] = useState('');
  const file = useRef(null);
  const changed = Object.keys(edits).length;

  const saveCosts = async (costs, source) => {
    const res = await submit(route('app.profit.costs.save'), { costs, source });
    if (res.ok) { setEdits({}); setPicked([]); }
  };
  const byPercent = () => {
    const p = Number(percent);
    if (!p || !picked.length) return;
    saveCosts(Object.fromEntries(missing.filter((x) => picked.includes(x.variant_id)).map((x) => [x.variant_id, Math.round(x.price * p) / 100])), 'percent');
  };
  const importCsv = async () => {
    const f = file.current?.files?.[0];
    if (!f) return;
    const body = new FormData();
    body.append('file', f);
    const res = await submit(route('app.profit.costs.import'), body);
    setErrors(res.errors || {});
  };
  const saveRules = async () => {
    const res = await submit(route('app.profit.costs.rules'), { ...r, default_cost_percent: r.default_cost_percent === '' ? null : r.default_cost_percent });
    setErrors(res.errors || {});
  };
  const setRule = (path, value) => setR((x) => {
    const next = structuredClone(x);
    const keys = path.split('.');
    let o = next;
    keys.slice(0, -1).forEach((k) => { o = o[k]; });
    o[keys[keys.length - 1]] = value;
    return next;
  });

  return (
    <Page heading="Costs">
      <p className="oo-muted" style={{ marginTop: 0 }}>Growvia reads "Cost per item" from Shopify and never changes it. Costs you add here are used for profit only.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <div className="pf-kpis">
            <div className="pf-kpi"><small>Products with a cost</small><b>{s.withCost} of {s.variants}</b><em>Variants</em></div>
            <div className="pf-kpi"><small>Sales with no cost, 30 days</small><b>{m(s.missingSales)}</b><em>{s.missingShare}% of net sales</em></div>
            <div className="pf-kpi"><small>Cost source</small><b>{s.fromShopify} from Shopify</b><em>{s.inGrowvia} set in Growvia</em></div>
          </div>

          <s-section heading="Missing costs">
            <p className="oo-muted" style={{ marginTop: 0 }}>Sorted by sales at risk, best sellers first. Profit leaves these out until they have a cost.</p>
            {!missing.length ? <p>Every product has a cost.</p> : (
              <>
                <div className="pf-bar">
                  <label className="pf-inline">Set selected to <input type="number" min="0" max="100" step="0.1" value={percent} onChange={(e) => setPercent(e.target.value)} aria-label="Cost as % of price" /> % of price</label>
                  <s-button onClick={byPercent} disabled={!picked.length || !percent || undefined}>Apply to {picked.length} selected</s-button>
                  <s-button variant="primary" onClick={() => saveCosts(edits, 'manual')} disabled={!changed || undefined}>Save {changed} {changed === 1 ? 'change' : 'changes'}</s-button>
                </div>
                <table className="oo-table stack">
                  <thead><tr><th><input type="checkbox" aria-label="Select all" checked={picked.length === missing.length} onChange={(e) => setPicked(e.target.checked ? missing.map((x) => x.variant_id) : [])} /></th><th>Product</th><th>Price</th><th>Sales, 30 days</th><th>Unit cost</th></tr></thead>
                  <tbody>
                    {missing.map((x) => (
                      <tr key={x.variant_id}>
                        <td><input type="checkbox" aria-label={`Select ${x.name}`} checked={picked.includes(x.variant_id)} onChange={(e) => setPicked((p) => (e.target.checked ? [...p, x.variant_id] : p.filter((id) => id !== x.variant_id)))} /></td>
                        <td data-label="Product">{x.name}</td>
                        <td data-label="Price">{x.price ? m(x.price) : '—'}</td>
                        <td data-label="Sales, 30 days">{m(x.sales)}</td>
                        <td data-label="Unit cost"><input type="number" min="0" step="0.01" className="pf-cost" aria-label={`Unit cost for ${x.name}`} value={edits[x.variant_id] ?? ''} onChange={(e) => setEdits((d) => ({ ...d, [x.variant_id]: e.target.value }))} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </>
            )}
          </s-section>

          <s-section heading="Import costs">
            <p className="oo-muted" style={{ marginTop: 0 }}>Variant ID (or SKU) in the first column, cost in the last. The template lists every variant with its current costs.</p>
            <div className="pf-bar">
              <input ref={file} type="file" accept=".csv,text/csv" aria-label="Costs CSV" />
              <s-button onClick={importCsv}>Import CSV</s-button>
              <s-button variant="tertiary" href={appUrl(route('app.profit.costs.template'))} target="_blank">Download template</s-button>
            </div>
            {errors.file && <p className="b-error">{errors.file}</p>}
          </s-section>

          {overrides.length > 0 && (
            <s-section heading="Costs set in Growvia">
              <table className="oo-table stack">
                <thead><tr><th>Product</th><th>Growvia cost</th><th>Shopify cost</th><th /></tr></thead>
                <tbody>
                  {overrides.map((x) => (
                    <tr key={x.variant_id}>
                      <td data-label="Product">{x.name}</td>
                      <td data-label="Growvia cost">{m(x.cost)} <span className="oo-muted">· {x.source === 'percent' ? '% of price' : x.source === 'csv' ? 'CSV' : 'typed in'}</span></td>
                      <td data-label="Shopify cost">{x.shopify === null ? '—' : m(x.shopify)}</td>
                      <td><s-button variant="tertiary" onClick={() => saveCosts({ [x.variant_id]: null })}>Use Shopify's</s-button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </s-section>
          )}

          <s-section heading="Cost rules">
            <p className="oo-muted" style={{ marginTop: 0 }}>Changing a rule recalculates past profit. Fixed costs only come off in Estimated net.</p>
            <div className="pf-rules">
              <fieldset>
                <legend>Payment fees</legend>
                <label>% of each order<input type="number" min="0" max="20" step="0.01" value={r.fees.percent} onChange={(e) => setRule('fees.percent', e.target.value)} /></label>
                <label>+ fixed per order<input type="number" min="0" step="0.01" value={r.fees.fixed} onChange={(e) => setRule('fees.fixed', e.target.value)} /></label>
                {Object.entries(r.fees.countries).map(([cc, v]) => (
                  <div className="pf-country" key={cc}>
                    <b>{cc}</b>
                    <input type="number" min="0" step="0.01" aria-label={`${cc} %`} value={v.percent} onChange={(e) => setRule(`fees.countries.${cc}.percent`, e.target.value)} />%
                    + <input type="number" min="0" step="0.01" aria-label={`${cc} fixed`} value={v.fixed} onChange={(e) => setRule(`fees.countries.${cc}.fixed`, e.target.value)} />
                    <button type="button" className="pd-link" onClick={() => setR((x) => { const n = structuredClone(x); delete n.fees.countries[cc]; return n; })}>Remove</button>
                  </div>
                ))}
                <div className="pf-country">
                  <input aria-label="Country code" maxLength={2} placeholder="GB" value={cc} onChange={(e) => setCc(e.target.value.toUpperCase())} style={{ width: 60 }} />
                  <s-button variant="tertiary" disabled={!/^[A-Z]{2}$/.test(cc) || undefined} onClick={() => { setRule(`fees.countries.${cc}`, { percent: r.fees.percent, fixed: r.fees.fixed }); setCc(''); }}>Add a country rule</s-button>
                </div>
              </fieldset>
              <fieldset>
                <legend>Shipping cost</legend>
                <label>How it's counted<select value={r.shipping.mode} onChange={(e) => setRule('shipping.mode', e.target.value)}>{Object.entries(SHIPPING).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></label>
                {r.shipping.mode !== 'none' && <label>{r.shipping.mode === 'percent' ? '% of shipping charged' : `Amount (${currency})`}<input type="number" min="0" step="0.01" value={r.shipping.value} onChange={(e) => setRule('shipping.value', e.target.value)} /></label>}
              </fieldset>
              <fieldset>
                <legend>Fixed monthly costs</legend>
                {r.fixed_costs.map((c, i) => (
                  <div className="pf-country" key={i}>
                    <input aria-label="Name" maxLength={80} value={c.name} onChange={(e) => setRule(`fixed_costs.${i}.name`, e.target.value)} placeholder="Apps, staff, rent" />
                    <input type="number" min="0" step="0.01" aria-label="Amount a month" value={c.amount} onChange={(e) => setRule(`fixed_costs.${i}.amount`, e.target.value)} />
                    <button type="button" className="pd-link" onClick={() => setR((x) => ({ ...x, fixed_costs: x.fixed_costs.filter((_, j) => j !== i) }))}>Remove</button>
                  </div>
                ))}
                <s-button variant="tertiary" onClick={() => setR((x) => ({ ...x, fixed_costs: [...x.fixed_costs, { name: '', amount: 0 }] }))}>Add a fixed cost</s-button>
              </fieldset>
              <fieldset>
                <legend>Products without a cost</legend>
                <label>Use this % of price (optional)<input type="number" min="0" max="100" step="0.1" value={r.default_cost_percent} onChange={(e) => setRule('default_cost_percent', e.target.value)} placeholder="Leave out of profit" /></label>
              </fieldset>
            </div>
            {Object.values(errors).filter(Boolean).length > 0 && <p className="b-error">{Object.values(errors)[0]}</p>}
            <s-button variant="primary" onClick={saveRules}>Save rules</s-button>
          </s-section>
        </>
      )}
    </Page>
  );
}
