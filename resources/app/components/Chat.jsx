import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api.js';
import { appUrl, useRouter } from '../router.jsx';
import { confirmDialog } from './Confirm.jsx';

/**
 * Live chat with the Growvia team (G43): a round button at the bottom-right of every page opens
 * the chat over the page. It checks for new replies every few seconds while open, and less often
 * while closed so the red badge can show a reply that came in meanwhile.
 */
export default function Chat() {
  const { page, shared } = useRouter();
  const [open, setOpen] = useState(false);
  const [data, setData] = useState(null);
  const [fresh, setFresh] = useState(false);
  const [error, setError] = useState(null);
  const openRef = useRef(open);
  openRef.current = open;

  const load = useCallback(() => {
    if (document.hidden && !openRef.current) return Promise.resolve();
    return api('chat' + (openRef.current ? '?open=1' : '')).then(setData, () => {});
  }, []);

  useEffect(() => {
    load();
    const timer = setInterval(load, open ? 3000 : 20000);
    return () => clearInterval(timer);
  }, [open, load]);

  // "Chat with us" links elsewhere in the app open the panel.
  useEffect(() => {
    const show = () => setOpen(true);
    window.addEventListener('growvia:chat', show);
    return () => window.removeEventListener('growvia:chat', show);
  }, []);

  const chat = data?.chat;
  const starting = !chat || (chat.state === 'closed' && fresh);

  async function send(body, files, topic) {
    setError(null);
    const form = new FormData();
    form.append('body', body);
    if (topic) form.append('topic', topic);
    form.append('page', page.url.split('?')[0]);
    files.forEach((f) => form.append('attachments[]', f));
    const res = await fetch(appUrl('/app/api/chat'), { method: 'POST', headers: { Accept: 'application/json' }, body: form });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      setError(json.message || 'Your message wasn\'t sent. Try again.');
      return false;
    }
    setFresh(false);
    setData(json);
    return true;
  }

  const post = (path, body) => api(`chat/${chat.id}/${path}`, { method: 'POST', body }).then(setData, (e) => setError(e.message));

  return (
    <div className="ob-chat">
      {open && data && (
        <section className="ob-chat-panel" role="dialog" aria-label="Chat with Growvia support">
          <header className="ob-chat-head">
            <div>
              <strong>Growvia support</strong>
              <small><span className={`ob-chat-dot ${data.status}`} />{data.statusText}</small>
            </div>
            {chat && !starting && chat.state !== 'closed' && (
              <button type="button" className="ob-chat-link" onClick={async () => (await confirmDialog('End this chat? A copy goes to your email.', { title: 'End chat', action: 'End chat' })) && post('close')}>End chat</button>
            )}
            <button type="button" className="ob-chat-x" aria-label="Close chat" onClick={() => setOpen(false)}>✕</button>
          </header>
          {starting
            ? <Start name={shared.user?.name} topics={data.topics} onSend={send} error={error} />
            : <Conversation chat={chat} offline={data.status === 'offline'} onSend={send} onTyping={() => api(`chat/${chat.id}/typing`, { method: 'POST' }).catch(() => {})}
                onRate={(rating) => post('rate', { rating })} onNew={() => setFresh(true)} error={error} />}
        </section>
      )}
      <button type="button" className="ob-chat-fab" aria-label={open ? 'Close chat' : 'Chat with us'} aria-expanded={open} onClick={() => setOpen(!open)}>
        {open ? <span aria-hidden="true">✕</span> : (
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z" /></svg>
        )}
        {!open && data?.unread > 0 && <span className="ob-chat-badge">{data.unread}</span>}
      </button>
    </div>
  );
}

function Start({ name, topics, onSend, error }) {
  const [topic, setTopic] = useState(null);
  return (
    <>
      <div className="ob-chat-body">
        <p className="ob-chat-hello">Hi {name || 'there'}, how can we help?</p>
        <p className="ob-chat-muted">Pick a topic, then tell us what you need.</p>
        <div className="ob-chat-topics">
          {topics.map((t) => (
            <button type="button" key={t.key} className={topic === t.key ? 'on' : ''} aria-pressed={topic === t.key} onClick={() => setTopic(t.key)}>{t.label}</button>
          ))}
        </div>
      </div>
      <Composer onSend={(body, files) => onSend(body, files, topic)} error={error} placeholder="Type your message…" />
    </>
  );
}

function Conversation({ chat, offline, onSend, onTyping, onRate, onNew, error }) {
  const scroll = useRef(null);
  const last = chat.messages[chat.messages.length - 1]?.id;
  useEffect(() => { scroll.current?.scrollTo(0, scroll.current.scrollHeight); }, [last, chat.typing]);
  const closed = chat.state === 'closed';

  return (
    <>
      <div className="ob-chat-body" ref={scroll}>
        {chat.messages.map((m) => (
          <div key={m.id} className={`ob-chat-msg ${m.team ? 'team' : 'me'}`}>
            <div className="ob-chat-bubble">
              {m.body}
              {m.attachments.map((a) => <a key={a.url} href={appUrl(a.url)} target="_blank" rel="noopener noreferrer">📎 {a.filename} ({a.kb} KB)</a>)}
            </div>
            <small>{m.author} · {new Date(m.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</small>
          </div>
        ))}
        {chat.typing && <p className="ob-chat-muted">Growvia is typing…</p>}
        {chat.state === 'waiting' && (
          <p className="ob-chat-note">You're number {chat.position} in line{chat.wait ? ` · about ${chat.wait} ${chat.wait === 1 ? 'minute' : 'minutes'}` : ''}. Keep using Growvia; we'll reply here.</p>
        )}
        {chat.state === 'offline' && <p className="ob-chat-note">Thanks, we have your message. We'll reply here and by email{offline ? ' when we\'re back' : ''}.</p>}
        {chat.articles.length > 0 && (
          <div className="ob-chat-articles">
            <p>These may help while you wait</p>
            {chat.articles.map((a) => <details key={a.q}><summary>{a.q}</summary><div>{a.a}</div></details>)}
          </div>
        )}
        {closed && (
          <div className="ob-chat-end">
            {chat.rating === null ? (
              <>
                <p><strong>How was this chat?</strong></p>
                <div className="ob-chat-topics"><button type="button" onClick={() => onRate(1)}>👍 Good</button><button type="button" onClick={() => onRate(-1)}>👎 Not good</button></div>
              </>
            ) : <p><strong>Thanks for your feedback.</strong></p>}
            <p className="ob-chat-muted">A copy is in Support and your email.</p>
            <button type="button" className="ob-chat-send" onClick={onNew}>Start a new chat</button>
          </div>
        )}
      </div>
      {!closed && <Composer onSend={onSend} onTyping={onTyping} error={error} placeholder="Reply…" />}
    </>
  );
}

function Composer({ onSend, onTyping, error, placeholder }) {
  const [body, setBody] = useState('');
  const [files, setFiles] = useState([]);
  const [busy, setBusy] = useState(false);
  const typed = useRef(0);
  const input = useRef(null);

  async function submit(e) {
    e?.preventDefault();
    if (!body.trim() || busy) return;
    setBusy(true);
    if (await onSend(body.trim(), files)) {
      setBody('');
      setFiles([]);
      if (input.current) input.current.value = '';
    }
    setBusy(false);
  }

  return (
    <form className="ob-chat-compose" onSubmit={submit}>
      {error && <p className="ob-chat-error" role="alert">{error}</p>}
      {files.length > 0 && <p className="ob-chat-muted">📎 {files.map((f) => f.name).join(', ')}</p>}
      <textarea rows={2} value={body} placeholder={placeholder} aria-label="Message" maxLength={5000}
        onChange={(e) => {
          setBody(e.target.value);
          if (onTyping && Date.now() - typed.current > 3000) { typed.current = Date.now(); onTyping(); }
        }}
        onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) submit(e); }} />
      <div className="ob-chat-actions">
        <label className="ob-chat-link">📎 Attach
          <input ref={input} type="file" multiple hidden accept="image/png,image/jpeg,image/gif,image/webp,application/pdf,text/plain,text/csv"
            onChange={(e) => setFiles(Array.from(e.target.files).slice(0, 3))} />
        </label>
        <button type="submit" className="ob-chat-send" disabled={busy || !body.trim()}>{busy ? 'Sending…' : 'Send'}</button>
      </div>
    </form>
  );
}
