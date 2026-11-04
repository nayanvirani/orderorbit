// Design for every offer (G37), step 2 (app/Experiences/Layout.php): text and layout for each part
// of a storefront offer, with desktop, tablet and mobile values. Tablet follows desktop and mobile
// follows tablet until changed; an empty setting keeps today's look.
import { useState } from 'react';

const FONTS = { heading: 'var(--font-heading-family, inherit)', body: 'var(--font-body-family, inherit)', system: 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif' };
const DEVICES = [['desktop', 'Desktop'], ['tablet', 'Tablet'], ['mobile', 'Mobile']];

const TEXT = [
  ['font', 'Font', 'select', { heading: 'Theme heading font', body: 'Theme body font', system: 'System font' }],
  ['size', 'Size (px)', 'number', { min: 8, max: 72, step: 1 }],
  ['weight', 'Weight', 'select', { 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'Semibold', 700: 'Bold', 800: 'Extra bold', 900: 'Black' }],
  ['line', 'Line height', 'number', { min: 0.8, max: 3, step: 0.1 }],
  ['track', 'Letter spacing (em)', 'number', { min: -0.1, max: 0.5, step: 0.01 }],
  ['case', 'Case', 'select', { none: 'As typed', uppercase: 'UPPERCASE', lowercase: 'lowercase', capitalize: 'Capitalise Each Word' }],
  ['align', 'Alignment', 'select', { left: 'Left', center: 'Centre', right: 'Right' }],
];
const BOX = [
  ['pad', 'Padding (px)', 'number', { min: 0, max: 80, step: 1 }],
  ['gap', 'Gap between items (px)', 'number', { min: 0, max: 60, step: 1 }],
  ['radius', 'Corner radius (px)', 'number', { min: 0, max: 60, step: 1 }],
  ['border', 'Border width (px)', 'number', { min: 0, max: 8, step: 1 }],
  ['above', 'Space above (px)', 'number', { min: 0, max: 120, step: 1 }],
  ['below', 'Space below (px)', 'number', { min: 0, max: 120, step: 1 }],
  ['cols', 'Items per row', 'number', { min: 1, max: 6, step: 1 }, 'grid'],
];

function declarations(v) {
  const px = (n) => `${Number(n)}px`;
  const map = {
    font: (x) => `font-family:${FONTS[x] || 'inherit'}`, size: (x) => `font-size:${px(x)}`, weight: (x) => `font-weight:${x}`, line: (x) => `line-height:${x}`,
    track: (x) => `letter-spacing:${x}em`, case: (x) => `text-transform:${x}`, align: (x) => `text-align:${x}`,
    pad: (x) => `padding:${px(x)}`, gap: (x) => `gap:${px(x)}`, radius: (x) => `border-radius:${px(x)}`,
    border: (x) => (Number(x) > 0 ? `border:${px(x)} solid color-mix(in srgb, currentColor 18%, transparent)` : 'border:0'),
    above: (x) => `margin-top:${px(x)}`, below: (x) => `margin-bottom:${px(x)}`, cols: (x) => `display:grid;grid-template-columns:repeat(${x}, minmax(0, 1fr))`,
  };
  const css = Object.entries(v).filter(([k, x]) => map[k] && x !== '' && x != null).map(([k, x]) => map[k](x)).join(';');
  return css ? css.split(';').map((d) => `${d}!important`).join(';') : '';
}

/** The values a screen size uses: its own, then the larger screens'. */
export function valuesFor(layout, device, key) {
  const order = device === 'mobile' ? ['desktop', 'tablet', 'mobile'] : device === 'tablet' ? ['desktop', 'tablet'] : ['desktop'];
  return Object.assign({}, ...order.map((d) => layout?.[d]?.[key] || {}));
}

/** Preview CSS for one screen size, scoped to the offer like the storefront's. */
export function layoutCss(meta, layout, handle, device) {
  const scope = `[data-oo-exp="${String(handle).replace(/[^A-Za-z0-9_-]/g, '')}"]`;
  return Object.entries(meta || {}).map(([key, e]) => {
    const decl = declarations(valuesFor(layout, device, key));
    if (!decl) return '';
    const sel = e.selector === '' ? scope : e.selector.split(',').map((s) => `${scope} ${s.trim()}`).join(', ');
    return `@media all{${sel}{${decl}}}`;
  }).filter(Boolean).join('\n');
}

export function withLayout(design, meta, handle, device) {
  const css = layoutCss(meta, design?.layout, handle, device);
  return css ? { ...design, custom_css: `${design?.custom_css || ''}\n${css}` } : design;
}

export default function TextLayout({ meta, design, device, onDevice, onChange }) {
  const list = Object.entries(meta || {});
  const [sel, setSel] = useState(list[0]?.[0]);
  if (!list.length) return null;
  const layout = design?.layout || {};
  const el = meta[sel] || list[0][1];
  const key = meta[sel] ? sel : list[0][0];
  const own = layout[device]?.[key] || {};
  const inherited = device === 'desktop' ? {} : valuesFor(layout, device === 'mobile' ? 'tablet' : 'desktop', key);
  const changed = (k) => DEVICES.some(([d]) => Object.keys(layout[d]?.[k] || {}).length);

  const put = (setting, value) => {
    const values = { ...own };
    if (value === '' || value == null) delete values[setting]; else values[setting] = value;
    const forDevice = { ...(layout[device] || {}) };
    if (Object.keys(values).length) forDevice[key] = values; else delete forDevice[key];
    onChange({ ...design, layout: { ...layout, [device]: forDevice } });
  };
  const reset = () => {
    const forDevice = { ...(layout[device] || {}) };
    delete forDevice[key];
    onChange({ ...design, layout: { ...layout, [device]: forDevice } });
  };

  const fields = (el.kind === 'text' ? TEXT : BOX).filter((f) => !f[4] || f[4] === el.kind);
  const texts = list.filter(([, e]) => e.kind === 'text');
  const boxes = list.filter(([, e]) => e.kind !== 'text');

  return (
    <div className="tl">
      <h3>Text and layout</h3>
      <p className="b-muted">Pick a part, then set its text or spacing. Tablet follows desktop and mobile follows tablet until you change them. Empty = today's look.</p>
      <div className="b-seg tl-devices" role="group" aria-label="Screen size">
        {DEVICES.map(([d, label]) => <button type="button" key={d} aria-pressed={device === d} onClick={() => onDevice(d)}>{label}</button>)}
      </div>
      <div className="b-field">
        <label htmlFor="tl-part">Part</label>
        <select id="tl-part" value={key} onChange={(e) => setSel(e.target.value)}>
          <optgroup label="Text">{texts.map(([k, e]) => <option key={k} value={k}>{e.label}{changed(k) ? ' •' : ''}</option>)}</optgroup>
          {boxes.length > 0 && <optgroup label="Layout">{boxes.map(([k, e]) => <option key={k} value={k}>{e.label}{changed(k) ? ' •' : ''}</option>)}</optgroup>}
        </select>
      </div>
      {device !== 'desktop' && (
        <p className="tl-note">
          {Object.keys(own).length ? <>Own {device} values. <button type="button" className="b-link" onClick={reset}>{device === 'tablet' ? 'Use desktop values' : 'Use tablet values'}</button></> : `Follows ${device === 'tablet' ? 'desktop' : 'tablet'} until you change a setting.`}
        </p>
      )}
      <div className="tl-grid">
        {fields.map(([k, label, kind, opts]) => (
          <div className="b-field" key={k}>
            <label htmlFor={`tl-${k}`}>{label}</label>
            {kind === 'select' ? (
              <select id={`tl-${k}`} value={own[k] ?? ''} onChange={(e) => put(k, e.target.value === '' ? '' : (k === 'weight' ? Number(e.target.value) : e.target.value))}>
                <option value="">{inherited[k] != null ? `Same as larger screens (${opts[inherited[k]] || inherited[k]})` : 'Today\'s look'}</option>
                {Object.entries(opts).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
              </select>
            ) : (
              <input id={`tl-${k}`} type="number" min={opts.min} max={opts.max} step={opts.step} value={own[k] ?? ''}
                placeholder={inherited[k] != null ? String(inherited[k]) : 'Today'} onChange={(e) => put(k, e.target.value === '' ? '' : Number(e.target.value))} />
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
