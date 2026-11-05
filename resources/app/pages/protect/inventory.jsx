// Protect › Inventory (G8): what to reorder and when, in plain words. Growvia looks at the last 30 days
// of sales: how fast each product sells, how long its stock lasts, and when to order more.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';
import { ApproveButton, CheckNow, download, money, ProfitNotices } from '../profit/_profit.jsx';

const TABS = { reorder: 'Order soon', sold_out: 'Sold out', no_sales: 'Not selling', all: 'All products' };

/** "about 9 a month", "about 2 a week", "about 3 a day". */
function sells(rate) {
  if (!rate) return 'Not selling';
  if (rate >= 1) return `about ${Math.round(rate)} a day`;
  if (rate * 7 >= 1) return `about ${Math.round(rate * 7)} a week`;
  return `about ${Math.max(1, Math.round(rate * 30))} a month`;
}

/** "about 5 days", "about 3 weeks", "about 4 months", "over a year". */
function lasts(r) {
  if (r.available <= 0) return 'Sold out';
  if (r.days === null) return '—';
  if (r.over_year) return 'Over a year';
  if (r.days < 14) return `about ${r.days} ${r.days === 1 ? 'day' : 'days'}`;
  if (r.days < 60) return `about ${Math.round(r.days / 7)} weeks`;
  return `about ${Math.round(r.days / 30)} months`;
}

export default function ProtectInventory({ rows, status, location, locations, settings, summary: s, canLocations, currency, access, syncing, locked, pending, checkedAt }) {
  const { submit, visit } = useRouter();
  const [tab, setTab] = useState(status || (s.reorder ? 'reorder' : s.sold_out ? 'sold_out' : 'all'));
  const [lead, setLead] = useState(settings.lead_days);
  const [cover, setCover] = useState(settings.cover_days);
  const shown = rows.filter((r) => tab === 'all' || r.status === tab);
  const date = (d) => {
    if (!d) return '';
    const x = new Date(`${d}T00:00:00`);
    return x.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', ...(x.getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) });
  };
  const todo = (r) => {
    if (r.status === 'sold_out') return <b className="pf-neg">Order {r.qty} now</b>;
    if (r.status === 'reorder') return <b>Order {r.qty} by {date(r.reorder_by)}</b>;
    if (r.status === 'no_sales') return <span className="oo-muted">{r.stock_value > 0 ? `${money(r.stock_value, currency)} of stock sitting` : 'Not selling'}</span>;
    return <span className="oo-muted">Nothing to do{r.reorder_by ? ` (order by ${date(r.reorder_by)})` : ''}</span>;
  };

  return (
    <Page heading="Inventory">
      <p className="oo-muted" style={{ marginTop: 0 }}>Growvia looks at what sold in the last 30 days and tells you what to order, how many and by when, so best sellers never run out.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && <CheckNow from="inventory" checkedAt={checkedAt} pending={pending} />}
      {!access && !pending && (
        <>
          <div className="pf-kpis">
            <button type="button" className="pf-kpi pf-kpi-btn" onClick={() => setTab('reorder')}><small>Order soon</small><b>{s.reorder}</b><em>Will run out within {settings.lead_days + 7} days</em></button>
            <button type="button" className="pf-kpi pf-kpi-btn" onClick={() => setTab('sold_out')}><small>Sold out</small><b className={s.sold_out ? 'pf-neg' : ''}>{s.sold_out}</b><em>Were selling, now none left</em></button>
            <button type="button" className="pf-kpi pf-kpi-btn" onClick={() => setTab('all')}><small>Enough stock</small><b>{s.healthy}</b><em>Lasts more than 3 weeks</em></button>
            <button type="button" className="pf-kpi pf-kpi-btn" onClick={() => setTab('no_sales')}><small>Not selling</small><b>{s.no_sales}</b><em>{money(s.idle_value, currency)} of stock sitting</em></button>
          </div>
          <div className="pf-bar">
            <nav className="pf-tabs" aria-label="Show" style={{ margin: 0 }}>
              {Object.entries(TABS).map(([k, l]) => <a key={k} href="#" aria-current={k === tab ? 'page' : undefined} onClick={(e) => { e.preventDefault(); setTab(k); }}>{l}{k !== 'all' ? ` (${s[k]})` : ''}</a>)}
            </nav>
            {Object.keys(locations).length > 0 && (
              <select aria-label="Location" value={location || ''} onChange={(e) => visit(route('app.protect.inventory', { location: e.target.value || undefined }))}>
                <option value="">All locations</option>
                {Object.entries(locations).map(([id, name]) => <option key={id} value={id}>{name}</option>)}
              </select>
            )}
            {!canLocations && <ApproveButton scopes={['read_locations']}>Show stock per location</ApproveButton>}
            <s-button onClick={() => download(route('app.protect.inventory.reorder', { location: location || undefined }), 'growvia-reorder-list.csv')}>Download order list</s-button>
          </div>
          <s-section>
            <div className="oo-scroll">
              <table className="oo-table">
                <thead><tr><th>Product</th><th>In stock{location ? '' : ' (all locations)'}</th><th>Sells</th><th>Lasts</th><th>What to do</th></tr></thead>
                <tbody>
                  {shown.map((r) => (
                    <tr key={r.variant_id}>
                      <td>{r.name}{r.sku && <small className="oo-muted" style={{ display: 'block' }}>{r.sku}</small>}</td>
                      <td>{r.available}</td>
                      <td>{sells(r.rate)}</td>
                      <td className={r.available <= 0 ? 'pf-neg' : ''}>{lasts(r)}</td>
                      <td>{todo(r)}</td>
                    </tr>
                  ))}
                  {!shown.length && <tr><td colSpan={5} className="oo-muted">{tab === 'reorder' ? 'Nothing needs ordering soon.' : tab === 'sold_out' ? 'Nothing that sells is sold out.' : 'Nothing here.'}</td></tr>}
                </tbody>
              </table>
            </div>
          </s-section>
          <s-section heading="How the order amounts are worked out">
            <p className="pf-small" style={{ marginTop: 0 }}>
              Growvia takes how many sold in the last 30 days and assumes the next weeks look the same. It suggests ordering in time for your supplier to deliver,
              and enough to last a while after it arrives. Products that don't track stock in Shopify aren't listed.
            </p>
            <div className="pf-bar">
              <label className="pf-inline">My supplier takes <input type="number" min="0" max="365" value={lead} onChange={(e) => setLead(e.target.value)} /> days to deliver</label>
              <label className="pf-inline">Order enough for <input type="number" min="1" max="365" value={cover} onChange={(e) => setCover(e.target.value)} /> days of sales</label>
              <s-button onClick={() => submit(route('app.protect.settings'), { lead_days: lead, cover_days: cover, from: 'inventory' })}>Save</s-button>
            </div>
          </s-section>
        </>
      )}
    </Page>
  );
}
