// Design for every offer (G37), step 1 (app/Experiences/Elements.php): a colour for each part of a
// storefront offer, "Use brand colours" and "Copy design from another offer". An empty colour keeps
// the offer's shared colours, so offers look as before until a colour is chosen.
import { useState } from 'react';

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

/** The CSS for the chosen colours, for previews (the storefront gets the same rules on publish). */
export function elementsCss(elements, colours) {
  return Object.entries(elements || {}).filter(([k]) => HEX.test(colours?.[k] || '')).map(([k, e]) => e.rule.replaceAll('%s', colours[k])).join('\n');
}

export function withElements(design, elements) {
  const css = elementsCss(elements, design?.elements);
  return css ? { ...design, custom_css: `${design?.custom_css || ''}\n${css}` } : design;
}

// A colour input only takes #rrggbb.
const full = (v) => (v && v.length === 4 ? `#${v[1]}${v[1]}${v[2]}${v[2]}${v[3]}${v[3]}` : v);

export default function ElementColours({ elements, design, brand, sources = [], onChange }) {
  const [source, setSource] = useState('');
  const list = Object.entries(elements || {});
  if (!list.length) return null;
  const colours = design?.elements || {};
  const setColour = (key, value) => {
    const next = { ...colours };
    if (value) next[key] = value; else delete next[key];
    onChange({ ...design, elements: next });
  };

  const copy = () => {
    const from = sources.find((s) => String(s.id) === source);
    if (!from) return;
    const d = { ...design, ...from.design };
    if (!from.same) d.elements = colours; // another type has other parts: keep this offer's part colours
    onChange(d);
    setSource('');
  };

  return (
    <div className="el">
      <h3>Colour of each part</h3>
      <p className="b-muted">Empty parts follow the colours above. Changes show in the preview straight away.</p>
      <div className="el-actions">
        {brand && <button type="button" className="b-btn" onClick={() => onChange({ ...design, ...brand, elements: {} })}>Use brand colours</button>}
        {sources.length > 0 && (
          <span className="el-copy">
            <select id="el-copy" aria-label="Copy design from another offer" value={source} onChange={(e) => setSource(e.target.value)}>
              <option value="">Copy design from another offer…</option>
              {sources.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.type})</option>)}
            </select>
            <button type="button" className="b-btn" disabled={!source} onClick={copy}>Copy</button>
          </span>
        )}
      </div>
      <div className="el-list">
        {list.map(([key, e]) => {
          const v = colours[key] || '';
          return (
            <div className="el-row" key={key}>
              <label htmlFor={`el-${key}`}>{e.label}</label>
              <input type="color" aria-label={`${e.label} colour`} value={full(v) || '#000000'} className={v ? '' : 'el-unset'} onChange={(ev) => setColour(key, ev.target.value)} />
              <input id={`el-${key}`} type="text" maxLength={7} placeholder="Follows the colours above" value={v}
                onChange={(ev) => { const t = ev.target.value.trim(); if (t === '' || HEX.test(t) || /^#[0-9a-f]{0,5}$/i.test(t)) setColour(key, t); }} />
              {v ? <button type="button" className="b-icon-btn" aria-label={`Reset ${e.label}`} onClick={() => setColour(key, '')}>×</button> : <span />}
            </div>
          );
        })}
      </div>
    </div>
  );
}
