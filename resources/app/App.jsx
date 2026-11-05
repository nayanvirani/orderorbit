import { appUrl, pageComponent, route, useRouter } from './router.jsx';
import { Icon } from './components/ui.jsx';
import { api } from './api.js';
import Chat from './components/Chat.jsx';
import ConfirmHost from './components/Confirm.jsx';
import { useEffect, useState } from 'react';

/** The frame around every page: section sidebar, plan-limit notice, then the page itself. */
export default function App() {
  const { page, shared } = useRouter();
  const { nav, limit, legal, announcements } = shared;
  return (
    <>
      {nav ? <Framed nav={nav} limit={limit} legal={legal} announcements={announcements} page={page} /> : (
        <>
          <Limit limit={limit} />
          <Legal legal={legal} />
          <Announcements list={announcements} />
          <ErrorBanner />
          <Current page={page} />
        </>
      )}
      {shared.chat && <Chat />}
      <ConfirmHost />
    </>
  );
}

/** The page's error (from a form or a request), shown until it's closed or another page opens. */
function ErrorBanner() {
  const [message, setMessage] = useState(() => window.OO_ERROR || null);
  useEffect(() => {
    const on = (e) => setMessage(e.detail);
    window.addEventListener('oo:error', on);
    return () => window.removeEventListener('oo:error', on);
  }, []);
  useEffect(() => { if (message) window.scrollTo({ top: 0, behavior: 'smooth' }); }, [message]);
  if (!message) return null;
  return <s-banner tone="critical" dismissible onDismiss={() => setMessage(null)}>{message}</s-banner>;
}

function Framed({ nav, limit, legal, announcements, page }) {
  return (
    <div className="ob-shell">
      <Sidebar nav={nav} />
      <div className="ob-shell-main">
        <Limit limit={limit} />
        <Legal legal={legal} />
        <Announcements list={announcements} />
        <ErrorBanner />
        <Current page={page} />
      </div>
    </div>
  );
}

function Current({ page }) {
  const Component = pageComponent(page.component);
  // Keyed by URL so a page's local state resets when you open another record.
  return <Component key={page.url.split('?')[0]} {...page.props} />;
}

function Sidebar({ nav }) {
  return (
    <aside className="ob-sidebar">
      <nav className="ob-subnav" aria-label={nav.label}>
        {nav.section !== 'cro' && <p className="ob-side-head" style={{ marginTop: 4 }}>{nav.label}</p>}
        {nav.groups.map((group, g) => [
          group.heading && <p className="ob-side-head" key={`h${g}`}>{group.heading}</p>,
          ...group.items.map((item) => (
            <a key={item.href + item.label} href={appUrl(item.href)} aria-current={item.active ? 'page' : undefined} className={g === 0 && nav.section === 'cro' ? 'ob-side-top' : undefined}>
              <Icon name={item.icon} size="sm" tone={item.tone || 'default'} /><span>{item.label}</span>
            </a>
          )),
        ])}
      </nav>
    </aside>
  );
}

function Limit({ limit }) {
  if (!limit) return null;
  return (
    <div className={`ob-limit ob-limit-${limit.state}`} role="status">
      <div><strong>{limit.title}</strong> {limit.text}</div>
      <a href={appUrl('/app/settings/billing')}>{limit.action}</a>
    </div>
  );
}

/** Updated Terms or policies: links to each, and a button that records the review for this store. */
function Legal({ legal }) {
  const { submit, busy } = useRouter();
  if (!legal?.pages?.length) return null;
  const names = legal.pages.map((p, i) => (
    <span key={p.slug}>{i > 0 && (i === legal.pages.length - 1 ? ' and ' : ', ')}<a href={p.url} target="_blank" rel="noopener noreferrer">{p.title}</a></span>
  ));
  const effective = legal.pages[0].effective;
  return (
    <div className="ob-limit ob-limit-info ob-legal" role="status">
      <div><strong>We've updated our {names}.</strong> {effective ? `Effective ${effective}. ` : ''}Please take a moment to review the changes.</div>
      <button type="button" disabled={busy} onClick={() => submit(route('app.legal.acknowledge'))}>I've reviewed them</button>
    </div>
  );
}

/** Messages from the Growvia team (Internal Admin › Announcements), shown between their start and end time. */
function Announcements({ list }) {
  const { submit, busy } = useRouter();
  if (!list?.length) return null;
  return list.map((a) => (
    <div className="ob-announce" key={a.id}>
      <s-banner tone={a.tone} heading={a.title} dismissible={a.dismissible || undefined} onDismiss={() => submit(route('app.announcements.dismiss', { announcement: a.id }))}>
        <s-paragraph>{a.body}</s-paragraph>
        {a.cta && (
          <s-button slot="secondary-actions" href={a.cta.external ? a.cta.url : appUrl(a.cta.url)} target={a.cta.external ? '_blank' : undefined}
            disabled={busy || undefined} onClick={() => { api(`announcements/${a.id}/click`, { method: 'POST' }).catch(() => {}); }}>{a.cta.text}</s-button>
        )}
      </s-banner>
    </div>
  ));
}

