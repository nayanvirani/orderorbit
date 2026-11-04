// Protect › Inventory (G8): days of stock from the last 30 days of sales, what to reorder and when.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';
import { ApproveButton, download, money, ProfitNotices } from '../profit/_profit.jsx';

const TABS = { reorder: 'Reorder soon', sold_out: 'Sold out', no_sales: 'No sales', all: 'All products' };

export default function ProtectInventory({ rows, location, locations, settings, summary: s, canLocations, currency, access, syncing, locked }) {
  const { submit, visit } = useRouter();
  const [tab, setTab] = useState(s.reorder ? 'reorder' : 'all');
  const [lead, setLead] = useState(settings.lead_days);
  const [cover, setCover] = useState(settings.cover_days);
  const shown = rows.filter((r) => tab === 'all' || r.status === tab);
  const date = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) : '—');

  return (
    <Page heading="Inventory">
      <p className="oo-muted" style={{ marginTop: 0 }}>Days of stock from the last 30 days of sales, so you know what to reorder and when.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <div className="pf-bar">
            {Object.keys(locations).length > 0 && (
              <select aria-label="Location" value={location || ''} onChange={(e) => visit(route('app.protect.inventory', { location: e.target.value || undefined }))}>
                <option value="">All locations</option>
                {Object.entries(locations).map(([id, name]) => <option key={id} value={id}>{name}</option>)}
              </select>
            )}
            {!canLocations && <><span className="oo-muted pf-small">See stock per location:</span><ApproveButton scopes={['read_locations']}>Approve locations in Shopify</ApproveButton></>}
            <s-button onClick={() => download(route('app.protect.inventory.reorder', { location: location || undefined }), 'growvia-reorder-list.csv')}>Export reorder list</s-button>
          </div>
          <div className="pf-kpis">
            <div className="pf-kpi"><small>Reorder this week</small><b>{s.reorder}</b></div>
            <div className="pf-kpi"><small>Sold out</small><b>{s.sold_out}</b></div>
            <div className="pf-kpi"><small>Healthy stock</small><b>{s.healthy}</b><em>over 21 days</em></div>
            <div className="pf-kpi"><small>No sales in 30 days</small><b>{s.no_sales}</b><em>{money(s.idle_value, currency)} of stock at cost</em></div>
          </div>
          <nav className="pf-tabs" aria-label="Show">
            {Object.entries(TABS).map(([k, l]) => <a key={k} href="#" aria-current={k === tab ? 'page' : undefined} onClick={(e) => { e.preventDefault(); setTab(k); }}>{l}{k !== 'all' ? ` (${s[k]})` : ''}</a>)}
          </nav>
          <s-section>
            <div className="oo-scroll">
              <table className="oo-table">
                <thead><tr><th>Product</th><th>Available</th><th>Sold per day</th><th>Days of stock</th><th>Reorder by</th><th>Suggested qty</th></tr></thead>
                <tbody>
                  {shown.map((r) => (
                    <tr key={r.variant_id}>
                      <td>{r.name}{r.sku && <small className="oo-muted" style={{ display: 'block' }}>{r.sku}</small>}</td>
                      <td className={r.available <= 0 ? 'pf-neg' : ''}>{r.available}</td><td>{r.rate}</td><td>{r.days ?? '—'}</td>
                      <td>{r.status === 'sold_out' ? 'Now' : date(r.reorder_by)}</td><td>{r.qty || '—'}</td>
                    </tr>
                  ))}
                  {!shown.length && <tr><td colSpan={6} className="oo-muted">Nothing here.</td></tr>}
                </tbody>
              </table>
            </div>
            <div className="pf-bar" style={{ marginTop: 12 }}>
              <label className="pf-inline">Supplier lead time <input type="number" min="0" max="365" value={lead} onChange={(e) => setLead(e.target.value)} /> days</label>
              <label className="pf-inline">Cover <input type="number" min="1" max="365" value={cover} onChange={(e) => setCover(e.target.value)} /> days of sales</label>
              <s-button onClick={() => submit(route('app.protect.settings'), { lead_days: lead, cover_days: cover, from: 'inventory' })}>Save</s-button>
            </div>
            <p className="oo-muted pf-small">Suggested quantity covers {settings.cover_days} days of sales plus your {settings.lead_days}-day supplier lead time. Only products that track inventory are listed.</p>
          </s-section>
        </>
      )}
    </Page>
  );
}
