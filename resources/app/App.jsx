import { appUrl, pageComponent, useRouter } from './router.jsx';
import { Icon } from './components/ui.jsx';

/** The frame around every page: section sidebar, plan-limit notice, then the page itself. */
export default function App() {
  const { page, shared } = useRouter();
  const { nav, limit } = shared;
  return nav ? (
    <div className="ob-shell">
      <Sidebar nav={nav} />
      <div className="ob-shell-main">
        <Limit limit={limit} />
        <Current page={page} />
      </div>
    </div>
  ) : (
    <>
      <Limit limit={limit} />
      <Current page={page} />
    </>
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
