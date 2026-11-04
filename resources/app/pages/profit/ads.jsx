// Profit › Ad expenses (G2): type in what was spent, or import a CSV from any ad platform. No ad
// account connection. Spend is spread over the days it covers; a monthly entry repeats.
import { useRef, useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';
import { money, ProfitNotices } from './_profit.jsx';

const today = () => new Date().toISOString().slice(0, 10);
const BLANK = { starts_on: today(), ends_on: today(), recurring: false, channel: 'meta', amount: '', note: '', tags: [] };

export default function ProfitAds({ channels, byChannel, entries, currency, access, syncing, locked }) {
  const { submit } = useRouter();
  const m = (v) => money(v, currency);
  const [form, setForm] = useState(BLANK);
  const [editing, setEditing] = useState(null);
  const [errors, setErrors] = useState({});
  const [importChannel, setImportChannel] = useState('meta');
  const file = useRef(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const total = byChannel.reduce((s, c) => s + c.amount, 0);

  const pick = async () => {
    if (!window.shopify?.resourcePicker) return;
    const picked = await window.shopify.resourcePicker({ type: 'product', multiple: true, selectionIds: form.tags.map((t) => ({ id: t.id })) });
    if (picked) set('tags', picked.map((p) => ({ type: 'product', id: p.id, title: p.title })));
  };
  const save = async () => {
    const res = await submit(editing ? route('app.profit.ads.update', { ad: editing }) : route('app.profit.ads.store'), { ...form, ends_on: form.ends_on || null });
    setErrors(res.errors || {});
    if (res.ok) { setForm(BLANK); setEditing(null); }
  };
  const importCsv = async () => {
    const f = file.current?.files?.[0];
    if (!f) return;
    const body = new FormData();
    body.append('file', f);
    body.append('channel', importChannel);
    const res = await submit(route('app.profit.ads.import'), body);
    setErrors(res.errors || {});
  };

  return (
    <Page heading="Ad expenses">
      <p className="oo-muted" style={{ marginTop: 0 }}>Type in what you spent on ads, or import a CSV from any ad platform. No ad account connection needed.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <div className="pf-two">
          <s-section heading={editing ? 'Edit spend' : 'Add spend'}>
            <div className="pf-form">
              <label>From<input type="date" value={form.starts_on} onChange={(e) => set('starts_on', e.target.value)} /></label>
              <label>{form.recurring ? 'Last month (optional)' : 'To'}<input type="date" value={form.ends_on || ''} onChange={(e) => set('ends_on', e.target.value)} /></label>
              <label>Channel<select value={form.channel} onChange={(e) => set('channel', e.target.value)}>{Object.entries(channels).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></label>
              <label>Amount ({currency})<input type="number" min="0" step="0.01" value={form.amount} onChange={(e) => set('amount', e.target.value)} /></label>
              <label className="pf-wide">Note<input maxLength={255} value={form.note || ''} onChange={(e) => set('note', e.target.value)} /></label>
              <div className="pf-wide">
                <span className="pd-label">Products (optional)</span>
                <div className="pd-chips">{form.tags.length ? form.tags.map((t) => <span className="pd-chip" key={t.id}>{t.title}</span>) : <span className="oo-muted">Whole store</span>}</div>
                <s-button variant="tertiary" onClick={pick}>Choose products</s-button>
                <small className="oo-muted" style={{ display: 'block' }}>Tagging lets Growvia work out POAS for these products.</small>
              </div>
              <label className="pf-check"><input type="checkbox" checked={form.recurring} onChange={(e) => set('recurring', e.target.checked)} /> Repeat every month</label>
            </div>
            {Object.values(errors)[0] && <p className="b-error">{Object.values(errors)[0]}</p>}
            <div className="pf-bar">
              <s-button variant="primary" onClick={save}>{editing ? 'Save' : 'Add spend'}</s-button>
              {editing && <s-button variant="tertiary" onClick={() => { setForm(BLANK); setEditing(null); }}>Cancel</s-button>}
            </div>
            <p className="oo-muted pf-small">Spend is spread evenly over the days it covers; a monthly entry is spread over each month.</p>

            <h3 className="pf-h3">Import a CSV</h3>
            <p className="oo-muted pf-small">From Meta, Google, TikTok or any export with a date column and a spend (or cost, amount) column.</p>
            <div className="pf-bar">
              <select value={importChannel} onChange={(e) => setImportChannel(e.target.value)} aria-label="Channel for the CSV">{Object.entries(channels).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
              <input ref={file} type="file" accept=".csv,text/csv" aria-label="Ad spend CSV" />
              <s-button onClick={importCsv}>Import CSV</s-button>
            </div>
          </s-section>

          <div>
            <s-section heading="Last 30 days by channel">
              <p className="pf-total">{m(total)}</p>
              {byChannel.map((c) => <div className="pf-row" key={c.name}><span>{c.name}</span><b>{m(c.amount)}</b></div>)}
              {!byChannel.length && <p className="oo-muted">No ad spend yet. Profit after ads shows "Add your ad spend" until you add some.</p>}
            </s-section>
            <s-section heading="Entries">
              {!entries.length ? <p className="oo-muted">None yet.</p> : (
                <table className="oo-table stack">
                  <thead><tr><th>Dates</th><th>Channel</th><th>Tagged</th><th>Amount</th><th /></tr></thead>
                  <tbody>
                    {entries.map((e) => (
                      <tr key={e.id}>
                        <td data-label="Dates">{e.recurring ? `${e.starts_on.slice(0, 7)} on, monthly` : e.ends_on && e.ends_on !== e.starts_on ? `${e.starts_on} to ${e.ends_on}` : e.starts_on}</td>
                        <td data-label="Channel">{channels[e.channel] || e.channel}</td>
                        <td data-label="Tagged">{e.tags.length ? e.tags.map((t) => t.title).join(', ') : 'Whole store'}</td>
                        <td data-label="Amount">{m(e.amount)}</td>
                        <td>
                          <s-button variant="tertiary" onClick={() => { setForm({ ...BLANK, ...e, ends_on: e.ends_on || '' }); setEditing(e.id); }}>Edit</s-button>
                          <s-button variant="tertiary" tone="critical" onClick={() => submit(route('app.profit.ads.delete', { ad: e.id }), {}, { confirm: 'Delete this entry?' })}>Delete</s-button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </s-section>
          </div>
        </div>
      )}
    </Page>
  );
}
