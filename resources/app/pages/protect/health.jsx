// Protect › Health Score (G12): one number for how well the store is set up and protected. What
// can't be measured yet is left out, not scored as 0.
import { Page } from '../../components/ui.jsx';
import { appUrl } from '../../router.jsx';
import { ProfitNotices } from '../profit/_profit.jsx';

const tone = (v) => (v === null ? 'none' : v >= 85 ? 'great' : v >= 70 ? 'good' : v >= 50 ? 'fair' : 'poor');

function Ring({ score }) {
  const r = 70;
  const c = 2 * Math.PI * r;
  return (
    <div className={`hs-ring ${tone(score)}`}>
      <svg viewBox="0 0 180 180" aria-hidden="true">
        <circle cx="90" cy="90" r={r} className="track" />
        <circle cx="90" cy="90" r={r} className="value" strokeDasharray={`${(c * (score || 0)) / 100} ${c}`} transform="rotate(-90 90 90)" />
      </svg>
      <div><b>{score ?? '—'}</b><small>out of 100</small></div>
    </div>
  );
}

export default function HealthScore({ score, label, change, weeks, ups, reach, categories, access, syncing, locked }) {
  return (
    <Page heading="Health Score">
      <p className="oo-muted" style={{ marginTop: 0 }}>One number for how well the store is set up and protected. Things that can't be measured yet are left out, not scored as 0.</p>
      <ProfitNotices access={access} syncing={syncing} locked={locked} />
      {!access && (
        <>
          <div className="pf-two">
            <s-section>
              <div className="hs-top">
                <Ring score={score} />
                <div className="hs-says">
                  {score === null ? <p>Not enough to score yet. It appears after Growvia's first check of your products and orders.</p> : (
                    <>
                      <p className="hs-label">{label}{change !== null && change !== 0 && <span className={change > 0 ? 'up' : 'down'}> {change > 0 ? '+' : '−'}{Math.abs(change)} since last week</span>}</p>
                      {ups.length > 0 && reach > score ? <p>Fixing {ups.length === 1 ? 'the item' : `the ${ups.length} items`} below would bring you to about {reach}.</p> : <p>Nothing big to fix right now.</p>}
                    </>
                  )}
                  {weeks.length > 1 && (
                    <>
                      <small className="oo-muted">Last {weeks.length} weeks</small>
                      <div className="hs-weeks" role="img" aria-label={`Score by week: ${weeks.map((w) => w.score).join(', ')}`}>
                        {weeks.map((w) => <i key={w.week} title={`Week of ${w.week}: ${w.score}`} className={tone(w.score)} style={{ height: `${Math.max(6, Math.round(w.score * 0.54))}px` }} />)}
                      </div>
                    </>
                  )}
                </div>
              </div>
            </s-section>

            <s-section heading="What raises it most">
              {!ups.length ? <p className="oo-muted">Nothing to raise right now.</p> : ups.map((u) => (
                <div className="hs-up" key={u.key}>
                  <span className="hs-pts">+{u.pts}</span>
                  <div><b>{u.title}</b><small>{u.detail}</small></div>
                  <a href={appUrl(u.href)}>{u.cta}</a>
                </div>
              ))}
            </s-section>
          </div>

          <s-section heading="By category">
            <div className="hs-cats">
              {categories.map((c) => (
                <div className="hs-cat" key={c.key}>
                  <div className="hs-cat-head"><b>{c.name}</b><span>{c.value === null ? 'Not scored' : c.value}</span></div>
                  <div className="hs-bar"><i className={tone(c.value)} style={{ width: `${c.value || 0}%` }} /></div>
                  <small className="oo-muted">{c.note}</small>
                </div>
              ))}
            </div>
          </s-section>
        </>
      )}
    </Page>
  );
}
