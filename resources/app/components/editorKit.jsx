// Building blocks for the Bundles and Progressive gifts editors: fields bound to a path in one
// JSON config (via EditorContext), collapsible sections and Shopify product pickers.
import { createContext, useContext } from 'react';
import { pickResources, toLocalInput } from './SchemaField.jsx';

/** { state, set, update, errors, meta, open, toggle, expand, currency } */
export const Ctx = createContext(null);
export const getIn = (obj, path) => path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
export function setIn(obj, path, value) {
  const [head, ...rest] = path.split('.');
  const copy = Array.isArray(obj) ? [...obj] : { ...obj };
  copy[head] = rest.length ? setIn(obj?.[head] ?? {}, rest.join('.'), value) : value;
  return copy;
}
export const clone = (v) => JSON.parse(JSON.stringify(v));

export const Row = ({ children }) => <div className="bx-row">{children}</div>;
export const Help = ({ text }) => (text ? <p className="b-help">{text}</p> : null);

export function F({ path, label, type = 'text', min, max, step, options, rows, code, help, placeholder }) {
  const { state, set, errors, meta } = useContext(Ctx);
  const v = getIn(state, path);
  const error = errors[path];
  const id = `f-${path.replace(/\./g, '-')}`;
  const footer = <><Help text={help} />{error && <p className="b-error">{error}</p>}</>;
  if (type === 'toggle') {
    return <div className={`b-field b-toggle ${error ? 'b-has-error' : ''}`}><label><input type="checkbox" checked={!!v} onChange={(e) => set(path, e.target.checked)} /> {label}</label>{footer}</div>;
  }
  let control;
  switch (type) {
    case 'textarea': control = <textarea id={id} rows={rows || 3} className={code ? 'b-code' : undefined} value={v ?? ''} onChange={(e) => set(path, e.target.value)} />; break;
    case 'number': control = <input type="number" id={id} value={v ?? ''} min={min} max={max} step={step || 1} onChange={(e) => set(path, e.target.value === '' ? null : Number(e.target.value))} />; break;
    case 'select': control = <select id={id} value={String(v ?? '')} onChange={(e) => set(path, e.target.value)}>{Object.entries(options).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>; break;
    case 'color': control = <span className="b-color"><input type="color" id={id} value={v || '#000000'} onChange={(e) => set(path, e.target.value)} /><code>{v}</code></span>; break;
    case 'datetime': control = <input type="datetime-local" id={id} value={v ? (/[zZ]|[+-]\d\d:\d\d$/.test(v) ? toLocalInput(v, meta.timezone) : String(v).slice(0, 16)) : ''} onChange={(e) => set(path, e.target.value || null)} />; break;
    default: control = <input type="text" id={id} value={v ?? ''} maxLength={max} placeholder={placeholder} onChange={(e) => set(path, e.target.value)} />;
  }
  return <div className={`b-field ${error ? 'b-has-error' : ''}`}><label htmlFor={id}>{label}</label>{control}{footer}</div>;
}

export function Section({ id, title, toggle: togglePath, children }) {
  const { state, set, open, toggle } = useContext(Ctx);
  const on = open.has(id);
  return (
    <section className={`bx-card bx-section ${on ? 'open' : ''}`}>
      <header>
        <button type="button" className="bx-section-toggle" aria-expanded={on} onClick={() => toggle(id)}><span className="bx-chevron" />{title}</button>
        {togglePath && <label className="bx-switch-lg"><input type="checkbox" checked={!!getIn(state, togglePath)} onChange={(e) => set(togglePath, e.target.checked)} /><i /></label>}
      </header>
      <div className="bx-section-body">{children}</div>
    </section>
  );
}

/** Product / collection pickers with variant mapping and quantities. */
export function Picker({ path, label, kind = 'product', max = 20, quantities, help }) {
  const { state, set, errors } = useContext(Ctx);
  const items = getIn(state, path) || [];
  const pick = async () => {
    const picked = await pickResources(kind, max, items);
    if (picked) set(path, picked);
  };
  return (
    <div className={`b-field ${errors[path] ? 'b-has-error' : ''}`}>
      <span className="b-label">{label}</span>
      <ul className="b-chips">
        {items.map((it, i) => (
          <li className="b-chip" key={it.id}>
            {it.image && <img src={it.image} alt="" width={24} height={24} />}
            <span className="b-chip-label">
              {it.title || it.id}
              {it.variants?.length > 0 && <small title={it.variants.map((v) => v.title).join(', ')}>{it.variants.length} variant{it.variants.length > 1 ? 's' : ''}: {it.variants.map((v) => v.title).join(', ')}</small>}
            </span>
            {quantities && <input type="number" className="b-chip-qty" min={1} max={20} value={it.quantity || 1} aria-label="Quantity" onChange={(e) => set(path, items.map((x, j) => (j === i ? { ...x, quantity: Math.max(1, Math.min(20, Number(e.target.value) || 1)) } : x)))} />}
            <button type="button" aria-label="Remove" onClick={() => set(path, items.filter((_, j) => j !== i))}>×</button>
          </li>
        ))}
      </ul>
      <button type="button" className="b-btn" onClick={pick}>{items.length ? 'Change' : 'Select'} {kind === 'collection' ? 'collections' : max === 1 ? 'product' : 'products'}</button>
      <Help text={help ?? (kind === 'collection' ? '' : 'For products with options, tick the variants to offer in the picker.')} />
      {errors[path] && <p className="b-error">{errors[path]}</p>}
    </div>
  );
}

