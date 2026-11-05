// Protect › URL monitor (G10): key storefront pages checked around the clock. Two failed checks in a
// row raise a Page down alert; the next good check closes it.
import { useState } from 'react';
import { ago, Page } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';
import { ProfitNotices } from '../profit/_profit.jsx';
import { confirmDialog } from '../../components/Confirm.jsx';

const STATUS = { up: ['success', 'Up'], slow: ['warning', 'Slow'], failing: ['warning', 'Failed once'], down: ['critical', 'Down'], locked: ['info', 'Password page'], new: ['info', 'Not checked yet'] };
const every = (m) => (m >= 60 ? 'every hour' : `every ${m} minutes`);

export default function UrlMonitor({ rows, down, limit, used, interval, domain, passwordProtected, locked, canEdit }) {
  const { submit } = useRouter();
  const [url, setUrl] = useState('');
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);
  const run = async (key, path, data) => {
    setBusy(key);
    const r = await submit(path, data);
    setBusy(null);
    setError(r?.errors ? Object.values(r.errors)[0] : null);
    return r;
  };
  const add = async (e) => {
    e.preventDefault();
    const r = await run('add', route('app.protect.urls.add'), { url });
    if (!r?.errors) setUrl('');
  };
  const remove = async (row) => {
    if (await confirmDialog(`Stop checking ${row.path}?`, { title: 'Remove page', action: 'Remove' })) run(`rm${row.id}`, route('app.protect.urls.remove', { url: row.id }), {});
  };

  if (locked) return <Page heading="URL monitor"><p className="oo-muted" style={{ marginTop: 0 }}>Your home page, best sellers, collections and your own pages, checked around the clock. Two failed checks in a row send an alert.</p><ProfitNotices locked={locked} /></Page>;

  return (
    <Page heading="URL monitor">
      <p className="oo-muted" style={{ marginTop: 0 }}>Key pages are checked {every(interval)}. Two failed checks in a row raise an alert; the next good check closes it.</p>
      {passwordProtected && <s-banner tone="info">Your store is password protected, so shoppers (and the monitor) only see the password page. Checks start counting once the store is open.</s-banner>}
      {!passwordProtected && down.length > 0 && (
        <s-banner tone="critical" heading={down.length === 1 ? `${down[0].path} is down` : `${down.length} pages are down`}>
          {down.map((d) => <div key={d.id}>{d.path}: {d.error || 'doesn\'t load'}{d.down_since ? ` since ${new Date(d.down_since).toLocaleString(undefined, { weekday: 'short', hour: '2-digit', minute: '2-digit' })}` : ''}</div>)}
        </s-banner>
      )}

      <div className="pf-bar">
        <span><b>{used}</b> of {limit === null ? 'unlimited' : limit} pages used</span>
        {canEdit && <s-button loading={busy === 'check' ? '' : undefined} onClick={() => run('check', route('app.protect.urls.check'), {})}>Check now</s-button>}
      </div>

      {canEdit && (limit === null || used < limit) && (
        <s-section>
          <form className="um-add" onSubmit={add}>
            <label htmlFor="um-url">Add a page on {domain}</label>
            <div>
              <input id="um-url" type="text" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="/pages/size-guide or a full address on your store" maxLength={500} />
              <s-button variant="primary" type="submit" loading={busy === 'add' ? '' : undefined}>Add page</s-button>
            </div>
            {error && <span className="b-error">{error}</span>}
          </form>
        </s-section>
      )}

      <s-section heading="Pages">
        {!rows.length ? <p className="oo-muted">No pages yet. Add one above, or use Check now to find your home page, best sellers and collections.</p> : (
          <table className="oo-table stack">
            <thead><tr><th>Page</th><th>Type</th><th>Status</th><th>Load time</th><th>Last 24 hours</th><th>Uptime, 30 days</th><th /></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td data-label="Page"><a href={r.url} target="_blank" rel="noreferrer"><b>{r.path}</b></a>{r.label && r.kind !== 'home' && <small className="oo-muted um-sub">{r.label}</small>}</td>
                  <td data-label="Type"><span className="pd-chip">{r.type}</span></td>
                  <td data-label="Status"><s-badge tone={STATUS[r.status]?.[0]}>{STATUS[r.status]?.[1] || r.status}</s-badge>{r.error && r.status !== 'up' && <small className="oo-muted um-sub">{r.error}</small>}</td>
                  <td data-label="Load time">{r.ms ? `${(r.ms / 1000).toFixed(1)} s` : '—'}</td>
                  <td data-label="Last 24 hours">
                    <div className="um-strip" role="img" aria-label={`Last 24 hours: ${r.strip.filter((s) => s === 'down').length} hours with a failed check`}>
                      {r.strip.map((s, i) => <i key={i} className={s} />)}
                    </div>
                  </td>
                  <td data-label="Uptime, 30 days">{r.uptime === null ? '—' : `${r.uptime}%`}</td>
                  <td>{canEdit && <s-button variant="tertiary" loading={busy === `rm${r.id}` ? '' : undefined} onClick={() => remove(r)}>Remove</s-button>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {rows[0]?.checked_at && <p className="oo-muted pf-small">Last checked {ago(rows.map((r) => r.checked_at).filter(Boolean).sort().pop())}. Slow means over 3 seconds to answer.</p>}
      </s-section>
    </Page>
  );
}
