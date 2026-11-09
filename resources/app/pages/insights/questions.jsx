// Insights › Store questions (G18): over 1,000 ready questions about the store. One click answers
// from the store's own data (no AI): a sentence, the numbers and the table behind it.
import { useMemo, useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { api } from '../../api.js';
import { appUrl, route } from '../../router.jsx';
import { money } from '../profit/_profit.jsx';

const NEEDS = { costs: ['Add costs', 'app.profit.costs'], ads: ['Add ad spend', 'app.profit.ads'], access: ['Approve access', 'app.profit.dashboard'] };

function fmt(v, kind, currency) {
  if (v === null || v === undefined || v === '') return '—';
  if (kind === 'money') return money(v, currency);
  if (kind === 'percent') return `${v}%`;
  if (kind === 'points') return `${v > 0 ? '+' : ''}${v} pts`;
  if (kind === 'number') return Number(v).toLocaleString();
  return String(v);
}

function Answer({ result }) {
  const a = result.answer;
  return (
    <div className="sq-answer">
      <small className="oo-muted">{result.question}{a.range ? ` · ${a.range}` : ''}</small>
      <p className="sq-sentence">{a.sentence}</p>
      {a.needs && <s-button variant="primary" href={appUrl(route(NEEDS[a.needs][1]))}>{NEEDS[a.needs][0]}</s-button>}
      {a.numbers.length > 0 && (
        <div className="pf-kpis">
          {a.numbers.map((n) => <div className="pf-kpi" key={n.label}><small>{n.label}</small><b>{fmt(n.value, n.kind, a.currency)}</b></div>)}
        </div>
      )}
      {a.table && (
        <div className="oo-scroll">
          <table className="oo-table">
            <thead><tr>{a.table.columns.map((c) => <th key={c}>{c}</th>)}</tr></thead>
            <tbody>{a.table.rows.map((r, i) => <tr key={i}>{r.map((v, j) => <td key={j}>{fmt(v, a.table.kinds?.[j], a.currency)}</td>)}</tr>)}</tbody>
          </table>
        </div>
      )}
      <p className="oo-muted pf-small">Same numbers as your dashboards, from your store&apos;s data right now.</p>
    </div>
  );
}

export default function StoreQuestions({ topics, questions, starters, favourites: favs, recent, remaining: left, limit, upgrade }) {
  const byId = useMemo(() => Object.fromEntries(questions.map(([id, topic, text]) => [id, { id, topic, text }])), [questions]);
  const [topic, setTopic] = useState(favs.length ? 'Favourites' : 'Start here');
  const [query, setQuery] = useState('');
  const [favourites, setFavourites] = useState(new Set(favs));
  const [recentIds, setRecent] = useState(recent);
  const [remaining, setRemaining] = useState(left);
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);

  const shown = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (q) return questions.filter(([, , text]) => q.split(/\s+/).every((w) => text.toLowerCase().includes(w))).slice(0, 80).map(([id]) => byId[id]);
    if (topic === 'Start here') return starters.map((id) => byId[id]).filter(Boolean);
    if (topic === 'Favourites') return [...favourites].map((id) => byId[id]).filter(Boolean);
    if (topic === 'Recently asked') return recentIds.map((id) => byId[id]).filter(Boolean);
    return questions.filter(([, t]) => t === topic).map(([id]) => byId[id]);
  }, [query, topic, questions, starters, favourites, recentIds, byId]);

  const ask = async (q) => {
    setBusy(q.id);
    setError(null);
    try {
      const r = await api('questions/ask', { method: 'POST', body: { id: q.id } });
      setResult(r);
      setRemaining(r.remaining);
      setRecent((ids) => [q.id, ...ids.filter((x) => x !== q.id)].slice(0, 8));
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
      setError(e.message);
      if (e.data?.remaining !== undefined) setRemaining(e.data.remaining);
    } finally {
      setBusy(null);
    }
  };
  const star = async (q) => {
    const on = !favourites.has(q.id);
    setFavourites((s) => { const n = new Set(s); on ? n.add(q.id) : n.delete(q.id); return n; });
    await api('questions/favourite', { method: 'POST', body: { id: q.id, on } }).catch(() => {});
  };

  return (
    <Page heading="Store questions">
      <p className="oo-muted" style={{ marginTop: 0 }}>{questions.length.toLocaleString()} ready questions about your store. Click one to get the answer from your data, with the numbers behind it.</p>
      {remaining !== null && (
        <s-banner tone={remaining > 0 ? 'info' : 'warning'}>
          {remaining > 0 ? `${remaining} of ${limit} questions left this month.` : 'This month\'s questions are used up. They reset on the 1st.'} {upgrade && <a href={appUrl(route('app.settings.billing'))}>{upgrade}</a>}
        </s-banner>
      )}
      {error && <s-banner tone="critical">{error}</s-banner>}
      {result && <s-section><Answer result={result} /></s-section>}

      <div className="sq-layout">
        <nav className="sq-topics" aria-label="Topics">
          {['Start here', 'Favourites', 'Recently asked', ...topics].map((t) => (
            <button type="button" key={t} aria-pressed={!query && topic === t} onClick={() => { setTopic(t); setQuery(''); }}>{t}</button>
          ))}
        </nav>
        <s-section>
          <input className="sq-search" type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search questions, e.g. refunds last month" aria-label="Search questions" />
          {!shown.length && <p className="oo-muted">{query ? 'No question matches. Try fewer words.' : topic === 'Favourites' ? 'Star a question to keep it here.' : 'Nothing here yet.'}</p>}
          <ul className="sq-list">
            {shown.map((q) => (
              <li key={q.id}>
                <button type="button" className="sq-q" disabled={busy !== null || remaining === 0} onClick={() => ask(q)}>{busy === q.id ? 'Answering…' : q.text}</button>
                <button type="button" className="sq-star" aria-pressed={favourites.has(q.id)} aria-label={favourites.has(q.id) ? 'Remove from favourites' : 'Add to favourites'} onClick={() => star(q)}>{favourites.has(q.id) ? '★' : '☆'}</button>
              </li>
            ))}
          </ul>
          {query && shown.length === 80 && <p className="oo-muted pf-small">Showing the first 80. Add a word to narrow it down.</p>}
        </s-section>
      </div>
    </Page>
  );
}
