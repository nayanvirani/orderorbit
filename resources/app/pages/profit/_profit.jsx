// Shared parts of the Profit pages (phase 3): the Shopify access approval card, the "importing"
// note while the first sync runs, the plan note, date ranges and money.
import { useState } from 'react';
import { appUrl, route, useRouter } from '../../router.jsx';

export const money = (v, currency = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: Math.abs(v) >= 1000 ? 0 : 2 }).format(v || 0);
/** Downloads a file from the app (the session token is added by App Bridge), without leaving the page. */
export async function download(path, filename) {
  const res = await fetch(appUrl(path));
  if (!res.ok) return window.shopify?.toast?.show('We couldn\'t download that. Please try again.', { isError: true });
  const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await res.blob()), download: filename });
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

export const pct = (v) => (v === null || v === undefined ? '—' : `${v}%`);

const WHY = {
  read_inventory: ['Product costs and stock', 'To work out profit per item from Shopify\'s "Cost per item".'],
  read_all_orders: ['Orders older than 60 days', 'For profit history and trends.'],
};

/** Shopify's own approval window for Profit's access (optional scopes), then the numbers import. */
export function AccessCard({ access }) {
  const { submit, reload } = useRouter();
  const [busy, setBusy] = useState(false);
  const approve = async () => {
    setBusy(true);
    try {
      const result = await window.shopify?.scopes?.request(access.missing);
      if (result?.result === 'granted-all' || result?.result === 'granted') {
        await submit(route('app.profit.access'), {});
      }
    } finally {
      setBusy(false);
      reload();
    }
  };
  return (
    <s-section>
      <div className="pf-access">
        <div>
          <h2>Approve access to turn on Profit</h2>
          <p>Profit reads your product costs and your order history. Shopify asks you to approve this once. Your offers, bundles and automations keep working while you decide.</p>
          <ul className="pf-scopes">
            {access.missing.map((s) => <li key={s}><b>{WHY[s]?.[0] || s}</b><span>{WHY[s]?.[1]}</span></li>)}
          </ul>
          <s-button variant="primary" onClick={approve} loading={busy || undefined}>Approve in Shopify</s-button>
          <p className="oo-muted pf-small">Opens Shopify's own approval window. Growvia never sees or stores more than it lists here.</p>
        </div>
      </div>
    </s-section>
  );
}

/** The notes above every Profit page: access, the first import, the plan. */
export function ProfitNotices({ access, syncing, locked }) {
  if (access) return <AccessCard access={access} />;
  return (
    <>
      {syncing && <s-banner tone="info" heading="Importing your orders and product costs">This runs in the background and usually takes a few minutes. The numbers fill in as it goes.</s-banner>}
      {locked && <s-banner tone="info">{locked} <a href={appUrl(route('app.settings.billing'))}>See plans</a></s-banner>}
    </>
  );
}

export function Ranges({ ranges, range, name, extra = {} }) {
  return (
    <div className="pf-ranges" role="group" aria-label="Date range">
      {Object.entries(ranges).map(([k, l]) => <s-button key={k} variant={k === range ? 'primary' : 'secondary'} href={appUrl(route(name, { ...extra, range: k }))}>{l}</s-button>)}
    </div>
  );
}
