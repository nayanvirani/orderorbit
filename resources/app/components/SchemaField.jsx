// One field of an experience schema (resources/experiences/*): text, number, money, select,
// toggle, checkboxes, color, image, datetime, products/collections, segments and list rows.
// Used by the experience builder and the A/B test setup. Styled by builder.css (b-*).
import { useId, useState } from 'react';
import { appUrl, route } from '../router.jsx';

/** Whether a field's "when" rules match the other values in its section. */
export function visible(field, values) {
  if (!field.when) return true;
  return Object.entries(field.when).every(([key, want]) => {
    const raw = values?.[key];
    const value = typeof raw === 'boolean' ? (raw ? '1' : '0') : String(raw ?? '');
    return Array.isArray(want) ? want.map(String).includes(value) : value === String(want);
  });
}

/** An ISO date shown as a datetime-local value in the store's time zone. */
export function toLocalInput(iso, timeZone) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const p = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(d).map((x) => [x.type, x.value]));
  return `${p.year}-${p.month}-${p.day}T${p.hour === '24' ? '00' : p.hour}:${p.minute}`;
}

/**
 * Opens Shopify's resource picker and returns the picks as the app stores them (products with
 * chosen variants and prices; collections as id/title/handle), or null when cancelled.
 */
export async function pickResources(kind, max, current = []) {
  if (!window.shopify?.resourcePicker) return null;
  const picked = await window.shopify.resourcePicker({
    type: kind, multiple: max > 1 ? max : false, filter: kind === 'product' ? { variants: true } : undefined,
    // Re-opening keeps the variants already mapped for each product.
    selectionIds: current.map((c) => (c.variants?.length ? { id: c.id, variants: c.variants.map((v) => ({ id: v.id })) } : { id: c.id })),
  });
  if (!picked) return null;
  return kind === 'collection' ? picked.map((r) => ({ id: r.id, title: r.title, handle: r.handle })) : mapPicked(picked, current);
}

/** Shopify's resource picker result as the app stores it (with chosen variants and prices). */
function mapPicked(picked, current) {
  return picked.map((r) => {
    const chosen = (r.variants || []).filter((v) => v && v.id);
    const variant = chosen[0] || {};
    const before = current.find((c) => c.id === r.id) || {};
    return {
      variants: r.hasOnlyDefaultVariant === false || chosen.length > 1
        ? chosen.map((v) => ({ id: v.id, title: v.title || v.displayName, price: v.price != null ? Number(v.price) : null }))
        : undefined,
      quantity: before.quantity,
      id: r.id, title: r.title, handle: r.handle,
      image: (r.images?.[0] && (r.images[0].originalSrc || r.images[0].url)) || (r.image && (r.image.originalSrc || r.image.url)) || null,
      price: variant.price != null ? Number(variant.price) : null,
      compare_at: variant.compareAtPrice != null ? Number(variant.compareAtPrice) : null,
      variant_id: variant.id || null,
    };
  });
}

export default function SchemaField({ name, field, value, onChange, error, rowErrors = {}, currency = 'USD', timezone = 'UTC', tzOffset = '+00:00', segments = [] }) {
  const id = useId();
  const t = field.type;
  // List rows: errors keyed "<row>.<sub-field>", shown under the box they belong to.
  const cellErrors = rowErrors;
  const cls = `b-field${t === 'toggle' ? ' b-toggle' : ''}${error || Object.keys(cellErrors).length ? ' b-has-error' : ''}`;
  const footer = error ? <p className="b-error" role="alert">{error}</p> : field.help ? <p className="b-help">{field.help}</p> : null;
  let control;

  switch (t) {
    case 'toggle':
      control = (
        <label htmlFor={id}>
          <input type="checkbox" id={id} checked={!!value && value !== '0'} onChange={(e) => onChange(e.target.checked)} />
          <span>{field.label}</span>
        </label>
      );
      break;
    case 'textarea':
      control = (
        <>
          <label htmlFor={id}>{field.label}</label>
          <textarea id={id} rows={name === 'custom_css' ? 5 : 3} maxLength={field.max ?? 1000} value={value ?? ''} onChange={(e) => onChange(e.target.value)}
            spellCheck={name === 'custom_css' ? false : undefined} className={name === 'custom_css' ? 'b-code' : undefined} />
        </>
      );
      break;
    case 'number':
    case 'money':
      control = (
        <>
          <label htmlFor={id}>{field.label}{t === 'money' && <span className="b-muted"> ({currency})</span>}</label>
          <input type="number" id={id} value={value ?? ''} step={field.step ?? (t === 'money' ? '0.01' : '1')} min={field.min} max={field.max}
            onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))} />
        </>
      );
      break;
    case 'select':
      control = (
        <>
          <label htmlFor={id}>{field.label}</label>
          <select id={id} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
            {Object.entries(field.options || {}).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </>
      );
      break;
    case 'checkboxes': {
      const list = Array.isArray(value) ? value : [];
      control = (
        <>
          <span className="b-label">{field.label}</span>
          <div className="b-checks">
            {Object.entries(field.options || {}).map(([v, l]) => (
              <label key={v}><input type="checkbox" checked={list.includes(v)} onChange={(e) => onChange(e.target.checked ? [...list, v] : list.filter((x) => x !== v))} /> {l}</label>
            ))}
          </div>
        </>
      );
      break;
    }
    case 'segments': {
      const list = (Array.isArray(value) ? value : []).map(Number);
      control = (
        <>
          <span className="b-label">{field.label}</span>
          {!segments.length ? (
            <p className="b-help">No segments yet. Create them in <a href={appUrl(route('app.audiences.segments'))}>Audiences</a>.</p>
          ) : (
            <div className="b-checks">
              {segments.map((s) => (
                <label key={s.id}><input type="checkbox" checked={list.includes(s.id)} onChange={(e) => onChange(e.target.checked ? [...list, s.id] : list.filter((x) => x !== s.id))} /> {s.name}</label>
              ))}
            </div>
          )}
        </>
      );
      break;
    }
    case 'color':
      control = (
        <>
          <label htmlFor={id}>{field.label}</label>
          <div className="b-color">
            <input type="color" value={/^#[0-9a-f]{6}$/i.test(value || '') ? value : '#000000'} onChange={(e) => onChange(e.target.value)} aria-label={`${field.label} picker`} />
            <input type="text" id={id} value={value ?? ''} maxLength={7} onChange={(e) => onChange(e.target.value)} />
          </div>
        </>
      );
      break;
    case 'image':
      control = <ImageField id={id} field={field} value={value} onChange={onChange} />;
      break;
    case 'datetime':
      control = (
        <>
          <label htmlFor={id}>{field.label} <span className="b-muted">({timezone})</span></label>
          <input type="datetime-local" id={id} value={toLocalInput(value, timezone)} onChange={(e) => onChange(e.target.value ? `${e.target.value}:00${tzOffset}` : null)} />
        </>
      );
      break;
    case 'products':
    case 'collections':
      control = <ResourceField field={field} value={value} onChange={onChange} />;
      break;
    case 'list':
      control = <ListField field={{ ...field, key: name }} value={value} onChange={onChange} errors={cellErrors} />;
      break;
    default:
      control = (
        <>
          <label htmlFor={id}>{field.label}</label>
          <input type="text" id={id} value={value ?? ''} maxLength={field.max ?? 255} onChange={(e) => onChange(e.target.value)} />
        </>
      );
  }

  return (
    <div className={cls} data-field={name}>
      {control}
      {footer}
    </div>
  );
}

function ResourceField({ field, value, onChange }) {
  const items = Array.isArray(value) ? value : [];
  const type = field.type === 'products' ? 'product' : 'collection';
  const max = field.max_items ?? 20;
  const pick = async () => {
    const picked = await pickResources(type, max, items);
    if (picked) onChange(picked);
  };
  const setQty = (i, q) => onChange(items.map((it, j) => (j === i ? { ...it, quantity: Math.max(1, Math.min(20, Number(q) || 1)) } : it)));
  return (
    <>
      <span className="b-label">{field.label}</span>
      <ul className="b-chips">
        {items.map((item, i) => (
          <li className="b-chip" key={item.id}>
            {item.image && <img src={item.image} alt="" width={24} height={24} />}
            <span className="b-chip-label">
              {item.title || item.id}
              {item.variants?.length > 0 && <small title={item.variants.map((v) => v.title).join(', ')}>{item.variants.length} variant{item.variants.length > 1 ? 's' : ''}: {item.variants.map((v) => v.title).join(', ')}</small>}
            </span>
            {field.quantities && <input type="number" className="b-chip-qty" min={1} max={20} value={item.quantity || 1} onChange={(e) => setQty(i, e.target.value)} aria-label={`Quantity of ${item.title || 'item'} in the bundle`} />}
            <button type="button" aria-label={`Remove ${item.title || 'item'}`} onClick={() => onChange(items.filter((_, j) => j !== i))}>×</button>
          </li>
        ))}
      </ul>
      <button type="button" className="b-btn" onClick={pick}>{field.type === 'products' ? 'Choose products' : 'Choose collections'}</button>
      {field.type === 'products' && <p className="b-help">For products with options, tick the variants to offer in the picker; shoppers choose only from those.</p>}
    </>
  );
}

/** The value a new list row starts with for one sub-field (the server treats a row still at these values as blank). */
export function rowDefault(f) {
  if (f.type === 'select') return Object.keys(f.options)[0];
  if (f.type === 'number' || f.type === 'money') return f.default ?? null;
  return f.default ?? '';
}

// Whole numbers in a short range (e.g. 1–5 stars) are picked from a list rather than typed.
const smallRange = (f) => f.type === 'number' && Number.isInteger(f.min) && Number.isInteger(f.max) && f.max - f.min <= 10 && !f.step;
// Long text gets a full-width box with several lines.
const longText = (f) => f.type === 'textarea' || (f.type === 'text' && (f.max ?? 0) >= 120);

function ListField({ field, value, onChange, errors = {} }) {
  const rows = Array.isArray(value) ? value : [];
  const max = field.max_items ?? 10;
  const fields = field.fields || {};
  const item = field.item_label || 'Row';
  const setCell = (i, key, v) => onChange(rows.map((r, j) => (j === i ? { ...r, [key]: v } : r)));
  const add = () => onChange([...rows, Object.fromEntries(Object.entries(fields).map(([k, f]) => [k, rowDefault(f)]))]);
  const move = (i, d) => {
    const next = rows.slice();
    [next[i], next[i + d]] = [next[i + d], next[i]];
    onChange(next);
  };
  return (
    <>
      <span className="b-label">{field.label}</span>
      <div className="b-list">
        {rows.map((row, i) => (
          <div className="b-row" key={i}>
            <div className="b-row-head">
              <strong>{item} {i + 1}</strong>
              <span className="b-row-actions">
                <button type="button" className="b-icon-btn" aria-label={`Move ${item.toLowerCase()} ${i + 1} up`} disabled={i === 0} onClick={() => move(i, -1)}>↑</button>
                <button type="button" className="b-icon-btn" aria-label={`Move ${item.toLowerCase()} ${i + 1} down`} disabled={i === rows.length - 1} onClick={() => move(i, 1)}>↓</button>
                <button type="button" className="b-icon-btn" aria-label={`Remove ${item.toLowerCase()} ${i + 1}`} onClick={() => onChange(rows.filter((_, j) => j !== i))}>×</button>
              </span>
            </div>
            <div className="b-row-fields">
              {Object.entries(fields).map(([key, f]) => {
                const number = f.type === 'number' || f.type === 'money';
                const error = errors[`${i}.${key}`];
                const id = `${field.key || 'list'}-${i}-${key}`;
                let control;
                if (f.type === 'select' || smallRange(f)) {
                  const options = f.type === 'select' ? Object.entries(f.options)
                    : Array.from({ length: f.max - f.min + 1 }, (_, n) => f.max - n).map((v) => [String(v), key === 'rating' ? `${'★'.repeat(v)} ${v}` : String(v)]);
                  control = (
                    <select id={id} value={String(row[key] ?? '')} aria-invalid={error ? true : undefined}
                      onChange={(e) => setCell(i, key, f.type === 'select' ? e.target.value : Number(e.target.value))}>
                      {f.type !== 'select' && row[key] == null && <option value="">Choose</option>}
                      {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                  );
                } else if (longText(f)) {
                  control = <textarea id={id} rows={3} maxLength={f.max} placeholder={f.placeholder} value={row[key] ?? ''} aria-invalid={error ? true : undefined} onChange={(e) => setCell(i, key, e.target.value)} />;
                } else {
                  control = (
                    <input id={id} type={number ? 'number' : 'text'} step={f.type === 'money' ? '0.01' : undefined} min={f.min} max={number ? f.max : undefined} maxLength={!number ? f.max : undefined}
                      placeholder={f.placeholder} value={row[key] ?? ''} aria-invalid={error ? true : undefined}
                      onChange={(e) => setCell(i, key, number ? (e.target.value === '' ? null : Number(e.target.value)) : e.target.value)} />
                  );
                }
                return (
                  <div className={`b-row-field${longText(f) ? ' b-row-wide' : ''}`} key={key}>
                    <label htmlFor={id}>{f.label}{f.required ? ' *' : ''}</label>
                    {control}
                    {error && <span className="b-error">{error}</span>}
                  </div>
                );
              })}
            </div>
          </div>
        ))}
        {rows.length < max ? (
          <button type="button" className="b-btn b-add" onClick={add}>{field.add_label || `Add ${item.toLowerCase()}`}</button>
        ) : (
          <p className="b-help">You've added the most {item.toLowerCase()}s this allows ({max}).</p>
        )}
      </div>
    </>
  );
}

function ImageField({ id, field, value, onChange }) {
  const [status, setStatus] = useState('');
  const ok = /^https:\/\//.test((value || '').trim());
  const upload = async (file) => {
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      setStatus('That image is over 5 MB.');
      return;
    }
    setStatus('Uploading…');
    try {
      const body = new FormData();
      body.append('image', file);
      const res = await fetch(appUrl(route('app.uploads.image')), { method: 'POST', body, headers: { Accept: 'application/json' } });
      const result = await res.json().catch(() => ({}));
      if (!res.ok || !result.url) throw new Error(result.message || 'The upload failed. Try again.');
      onChange(result.url);
      setStatus('Uploaded to your Shopify Files.');
    } catch (e) {
      setStatus('');
      window.shopify?.toast?.show(e.message, { isError: true });
    }
  };
  return (
    <>
      <label htmlFor={id}>{field.label}</label>
      <div className="b-image">
        {ok && <img src={value.trim()} alt="" />}
        <input type="url" id={id} value={value ?? ''} maxLength={1000} placeholder="https://cdn.shopify.com/…" onChange={(e) => onChange(e.target.value)} />
        <div className="b-image-actions">
          <label className="b-btn">
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden onChange={(e) => { const f = e.target.files[0]; e.target.value = ''; upload(f); }} />
            Upload image
          </label>
          {value && <button type="button" className="b-btn" onClick={() => onChange('')}>Remove</button>}
          <span className="b-muted" role="status">{status}</span>
        </div>
      </div>
    </>
  );
}
