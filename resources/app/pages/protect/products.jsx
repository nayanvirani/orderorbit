// Protect › Product data (G9): gaps on active products that hurt sales, search or profit numbers.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { money, ProfitNotices } from '../profit/_profit.jsx';

const LABELS = { cost: 'Missing cost', image: 'Missing image', description: 'No description', price: 'No price', sku: 'No SKU', barcode: 'No barcode', weight: 'No weight' };

export default function ProtectProducts({ rows, active, counts, currency, access, syncing, locked }) {
  const [only, setOnly] = useState(null);
  const shown = rows.filter((r) => !only || r.issues[only]);
  return (
    <Page heading="Product data">
      <p className="oo-muted" style={{ marginTop: 0 }}>Gaps on active products that hurt sales, search or profit numbers. Checked every hour.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <p><b>{active} active products · {rows.length} with an issue</b></p>
          <div className="pt-chips">
            <button type="button" aria-pressed={!only} onClick={() => setOnly(null)}>All issues</button>
            {Object.entries(LABELS).filter(([k]) => counts[k]).map(([k, l]) => <button type="button" key={k} aria-pressed={only === k} onClick={() => setOnly(k)}>{counts[k]} {l.toLowerCase()}</button>)}
          </div>
          <s-section heading="Best sellers first">
            <table className="oo-table stack">
              <thead><tr><th>Product</th><th>Sales, 30 days</th><th>Issues</th><th /></tr></thead>
              <tbody>
                {shown.map((r) => (
                  <tr key={r.product_id}>
                    <td data-label="Product">{r.name}</td>
                    <td data-label="Sales, 30 days">{money(r.sales, currency)}</td>
                    <td data-label="Issues"><div className="pd-chips">{Object.values(r.issues).map((t) => <span className="pd-chip" key={t}>{t}</span>)}</div></td>
                    <td><s-button variant="tertiary" href={r.url} target="_blank">Fix in Shopify</s-button></td>
                  </tr>
                ))}
                {!shown.length && <tr><td colSpan={4} className="oo-muted">No gaps here.</td></tr>}
              </tbody>
            </table>
          </s-section>
        </>
      )}
    </Page>
  );
}
