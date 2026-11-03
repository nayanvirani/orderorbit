// Texts and visibility for any offer (app/Experiences/Parts.php): a switch for each part shoppers see
// and a field for each fixed word. Hidden parts are saved as a list; an emptied word hides it.

/** The CSS that hides the chosen parts in the live preview, as the storefront does. */
export function lookCss(look, hide) {
  const selectors = (hide || []).map((k) => look?.parts?.[k]?.selector).filter(Boolean);
  return selectors.length ? `${selectors.join(', ')}{display:none!important}` : '';
}

/** A design object with the hidden parts added to its custom CSS, for previews. */
export function withLook(design, look, hide) {
  const css = lookCss(look, hide);
  return css ? { ...design, custom_css: `${design?.custom_css || ''}\n${css}` } : design;
}

export default function Look({ look, hide = [], words = {}, onChange, note }) {
  const parts = Object.entries(look?.parts || {});
  const fixed = Object.entries(look?.words || {});
  if (!parts.length && !fixed.length) return null;
  const toggle = (key, shown) => onChange({ hide: shown ? hide.filter((k) => k !== key) : [...new Set([...hide, key])], words });
  return (
    <div className="lk">
      <h2>Texts and visibility</h2>
      <p className="b-muted">{note || 'Turn off any part you don\'t want shoppers to see, and change any fixed word. The texts above are edited in their own fields; empty one to hide it.'}</p>
      {parts.length > 0 && (
        <div className="lk-parts" role="group" aria-label="Parts shoppers see">
          {parts.map(([key, p]) => (
            <label className="lk-part" key={key}>
              <input type="checkbox" checked={!hide.includes(key)} onChange={(e) => toggle(key, e.target.checked)} /> {p.label}
            </label>
          ))}
        </div>
      )}
      {fixed.length > 0 && (
        <div className="lk-words">
          {fixed.map(([key, w]) => (
            <div className="b-field" key={key}>
              <label htmlFor={`lk-${key}`}>{w.label}</label>
              <input id={`lk-${key}`} type="text" maxLength={80} value={words?.[key] ?? w.default} placeholder="Empty = hidden"
                onChange={(e) => onChange({ hide, words: { ...words, [key]: e.target.value } })} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
