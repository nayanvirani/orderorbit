/* OrderOrbit bundle editor: Settings / Offers / Design panels over one JSON config,
   with a live storefront preview. The server (BundleSchema) validates on save. */
(() => {
  const { config: state, errors, meta } = window.BundleEditor;
  const form = document.querySelector('[data-bundle-editor]');
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const open = new Set(['offer-0', 'visibility', 'mix', 'd-overall']);
  let dirty = false;

  // ------------------------------------------------------------------ state paths ("offers.1.title")
  function get(path) { return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), state); }
  function set(path, value) {
    const keys = path.split('.');
    const last = keys.pop();
    const target = keys.reduce((o, k) => (o[k] = o[k] ?? {}), state);
    target[last] = value;
  }
  function changed() { dirty = true; $('[data-dirty]').hidden = false; preview(); }

  // ------------------------------------------------------------------ field renderers
  const err = (path) => (errors[path] ? `<p class="b-error">${esc(errors[path])}</p>` : '');
  const help = (text) => (text ? `<p class="b-help">${text}</p>` : '');

  function field(path, label, type = 'text', opts = {}) {
    const v = get(path);
    const id = 'f-' + path.replace(/\./g, '-');
    const attrs = `id="${id}" data-path="${path}" data-type="${type}"`;
    let control;
    switch (type) {
      case 'textarea': control = `<textarea ${attrs} rows="${opts.rows || 3}" ${opts.code ? 'class="b-code"' : ''}>${esc(v)}</textarea>`; break;
      case 'number': control = `<input type="number" ${attrs} value="${esc(v)}" ${opts.min != null ? `min="${opts.min}"` : ''} ${opts.max != null ? `max="${opts.max}"` : ''} step="${opts.step || 1}">`; break;
      case 'select': control = `<select ${attrs}>${Object.entries(opts.options).map(([k, l]) => `<option value="${esc(k)}" ${String(v) === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`; break;
      case 'color': control = `<span class="b-color"><input type="color" ${attrs} value="${esc(v)}"><code>${esc(v)}</code></span>`; break;
      case 'datetime': control = `<input type="datetime-local" ${attrs} value="${esc(v ? String(v).slice(0, 16) : '')}">`; break;
      case 'toggle':
        return `<div class="b-field b-toggle ${errors[path] ? 'b-has-error' : ''}"><label><input type="checkbox" ${attrs} ${v ? 'checked' : ''}> ${esc(label)}</label>${help(opts.help)}${err(path)}</div>`;
      default: control = `<input type="text" ${attrs} value="${esc(v)}" ${opts.max ? `maxlength="${opts.max}"` : ''} ${opts.placeholder ? `placeholder="${esc(opts.placeholder)}"` : ''}>`;
    }
    return `<div class="b-field ${errors[path] ? 'b-has-error' : ''}"><label for="${id}">${esc(label)}</label>${control}${help(opts.help)}${err(path)}</div>`;
  }

  const row = (...cells) => `<div class="bx-row">${cells.join('')}</div>`;

  function section(key, title, body, toggle) {
    const on = toggle ? get(toggle) : true;
    return `<section class="bx-card bx-section ${open.has(key) ? 'open' : ''}" data-section="${key}">
      <header><button type="button" class="bx-section-toggle" data-toggle-section="${key}" aria-expanded="${open.has(key)}"><span class="bx-chevron"></span>${title}</button>
      ${toggle ? `<label class="bx-switch-lg"><input type="checkbox" data-path="${toggle}" data-type="toggle" data-rerender ${on ? 'checked' : ''}><i></i></label>` : ''}</header>
      <div class="bx-section-body">${body}</div></section>`;
  }

  // Product / collection pickers with variant mapping and quantities.
  function picker(path, label, opts = {}) {
    const items = get(path) || [];
    const chips = items.map((it, i) => `<li class="b-chip">${it.image ? `<img src="${esc(it.image)}" alt="" width="24" height="24">` : ''}
      <span class="b-chip-label">${esc(it.title || it.id)}${it.variants && it.variants.length ? `<small title="${esc(it.variants.map((v) => v.title).join(', '))}">${it.variants.length} variant${it.variants.length > 1 ? 's' : ''}: ${esc(it.variants.map((v) => v.title).join(', '))}</small>` : ''}</span>
      ${opts.quantities ? `<input type="number" class="b-chip-qty" min="1" max="20" value="${it.quantity || 1}" data-chip-qty="${path}" data-i="${i}" aria-label="Quantity">` : ''}
      <button type="button" data-chip-remove="${path}" data-i="${i}" aria-label="Remove">×</button></li>`).join('');
    return `<div class="b-field ${errors[path] ? 'b-has-error' : ''}"><span class="b-label">${esc(label)}</span>
      <ul class="b-chips">${chips}</ul>
      <button type="button" class="b-btn" data-pick="${path}" data-kind="${opts.kind || 'product'}" data-max="${opts.max || 20}">${items.length ? 'Change' : 'Select'} ${opts.kind === 'collection' ? 'collections' : (opts.max === 1 ? 'product' : 'products')}</button>
      ${help(opts.help || (opts.kind === 'collection' ? '' : 'For products with options, tick the variants to offer in the picker.'))}${err(path)}</div>`;
  }

  // ------------------------------------------------------------------ panels
  function schedulePanel() {
    return `<div class="bx-inline"><strong>Schedule</strong><span class="bx-muted">Optional start and end. Shopify applies the bundle pricing only in this window.</span></div>
      ${row(field('schedule.starts_at', `Start (${meta.timezone})`, 'datetime'), field('schedule.ends_at', `End (${meta.timezone})`, 'datetime'))}`;
  }

  function settingsPanel() {
    const s = state.settings;
    const vis = `<div class="b-field"><span class="b-label">Visibility</span><div class="bx-seg" role="group">
      ${[['all', 'All products'], ['collections', 'Collection(s)'], ['products', 'Product(s)']].map(([k, l]) => `<button type="button" data-set="settings.visibility" data-value="${k}" aria-pressed="${s.visibility === k}">${l}</button>`).join('')}</div>
      ${help('Which product pages show this bundle.')}</div>`;
    const layouts = [['vertical', 'Vertical'], ['horizontal', 'Horizontal'], ['grid', 'Grid']].map(([k, l]) => `<button type="button" class="bx-layout-tile ${s.layout === k ? 'on' : ''}" data-set="settings.layout" data-value="${k}" aria-pressed="${s.layout === k}"><span class="bx-lt bx-lt-${k}"><i></i><i></i><i></i>${k === 'grid' ? '<i></i>' : ''}</span>${l}</button>`).join('');

    return section('visibility', 'Visibility and market', vis +
        (s.visibility === 'products' ? picker('settings.products', 'Show on these products', { max: 100 }) : '') +
        (s.visibility === 'collections' ? picker('settings.collections', 'Show on products in these collections', { kind: 'collection', max: 50 }) : '') +
        picker('settings.excluded', 'Excluded products', { max: 100, help: 'The bundle won’t appear on these product pages.' }) +
        field('settings.countries', 'Markets (countries)', 'text', { placeholder: 'All markets', help: 'Two-letter country codes separated by commas, e.g. US, CA. Leave empty for all markets.' }))
      + section('titles', 'Titles', row(field('settings.title', 'Header title', 'text', { max: 80 }), field('settings.subtitle', 'Subtitle', 'text', { max: 160 })) + field('settings.hide_lines', 'Hide header lines', 'toggle'))
      + section('timer', 'Timer', field('settings.timer.enabled', 'Show bundle timer', 'toggle', { help: 'A real deadline: end of today in the shopper’s time, or a date you set. It never resets per visitor.' }) +
        (s.timer.enabled ? row(field('settings.timer.mode', 'Ends', 'select', { options: { end_of_day: 'At the end of each day', date: 'On a date' } }), field('settings.timer.text', 'Timer text', 'text', { max: 60 })) +
          (s.timer.mode === 'date' ? field('settings.timer.ends_at', `Ends at (${meta.timezone})`, 'datetime') : '') : ''))
      + section('layout', 'Layout and position', `<div class="b-field"><span class="b-label">Layout</span><div class="bx-layouts">${layouts}</div></div>` +
        row(field('settings.style', 'Style', 'select', { options: { cards: 'Cards', compact: 'Compact list', fbt: 'Frequently bought together', checklist: 'Checklist' } }),
          field('settings.position', 'Bundle position', 'select', { options: { above_atc: 'Above the add to cart button', below_atc: 'Below the add to cart button', block: 'Only where I place the block' }, help: 'Above/below needs the OrderOrbit app embed turned on in the Theme Editor.' })) +
        row(field('settings.button_text', 'Button text', 'text', { max: 40 }), field('settings.after_add', 'After adding to cart', 'select', { options: { cart: 'Go to the cart', stay: 'Stay on the page', checkout: 'Skip cart and go to checkout' } })) +
        field('settings.show_variants', 'Show product variant selection', 'toggle', { help: 'Shoppers choose a variant for each item (#1, #2 …).' }) +
        field('settings.hide_theme_form', 'Hide the theme’s product form', 'toggle', { help: 'Hides your theme’s variant picker, quantity, add to cart, buy-now and subscription options where the bundle shows, so they don’t conflict.' }) +
        (s.hide_theme_form ? field('settings.hide_selectors', 'Extra elements to hide (CSS selectors)', 'text', { placeholder: '.my-theme-variant-picker, .my-subscriptions', help: 'Only needed if your theme uses a custom product form.' }) : '') +
        field('behavior.priority', 'Priority', 'number', { min: 1, max: 100, help: 'When several bundles match a product, the highest priority shows.' }));
  }

  function offerTitle(o) {
    if (o.kind === 'multi') return `${(o.products || []).length} products`;
    if (o.kind === 'mono') return (o.product[0] || {}).title || 'Choose product';
    return `${o.quantity} product${o.quantity > 1 ? 's' : ''}`;
  }

  function offerBody(o, i) {
    const p = `offers.${i}`;
    const kinds = Object.keys(meta.offerKinds);
    let body = '';
    if (kinds.length > 1) body += field(`${p}.kind`, 'Offer type', 'select', { options: meta.offerKinds });
    body += row(field(`${p}.title`, 'Title', 'text', { max: 80 }), field(`${p}.subtitle`, 'Subtitle', 'text', { max: 120, help: 'Use {saving} for the amount saved and {percent} for the % off.' }));
    if (o.kind === 'quantity') body += field(`${p}.quantity`, 'Quantity of the product on the page', 'number', { min: 1, max: 50 });
    if (o.kind === 'mono') body += picker(`${p}.product`, 'Product (choose one variant for a pack size)', { max: 1 }) + field(`${p}.quantity`, 'Quantity', 'number', { min: 1, max: 50 });
    if (o.kind === 'multi') body += picker(`${p}.products`, 'Products in this pack', { max: 10, quantities: true });
    body += row(field(`${p}.discount_type`, 'Discount', 'select', { options: meta.discounts }),
      o.discount_type === 'none' ? '' : field(`${p}.discount_value`, o.discount_type === 'percentage' ? '% off' : `Value (${meta.currency})`, 'number', { min: 0, step: 0.01, help: o.discount_type === 'fixed_price' ? 'The total the shopper pays for this offer.' : '' }));
    body += row(field(`${p}.label`, 'Ribbon', 'text', { max: 40, placeholder: 'e.g. Most popular' }), field(`${p}.badge`, 'Badge', 'text', { max: 24, placeholder: 'Automatic: −20%' }));
    body += `<div class="b-field b-toggle"><label><input type="radio" name="preselected" data-preselect="${i}" ${o.preselected ? 'checked' : ''}> Selected by default</label></div>`;
    if (state.gifts.enabled) {
      body += `<div class="bx-sub-list"><span class="b-label">Free gifts with this offer</span>` + (o.gifts || []).map((g, j) =>
        `<div class="bx-sub-item">${picker(`${p}.gifts.${j}.product`, `Gift ${j + 1}`, { max: 1 })}${field(`${p}.gifts.${j}.quantity`, 'Quantity', 'number', { min: 1, max: 10 })}<button type="button" class="b-icon-btn" data-remove-gift="${i}:${j}" aria-label="Remove gift">×</button></div>`).join('') +
        `<button type="button" class="b-btn" data-add-gift="${i}">Add gift</button></div>`;
    }
    return body;
  }

  function offersPanel() {
    if (state.bundle_type === 'mix-match') {
      const m = state.mix;
      return section('mix', 'Products and slots', picker('mix.pool', 'Products shoppers can choose from', { max: 50 }) +
          row(field('mix.slots', 'Number of slots', 'number', { min: 2, max: 8 }), field('mix.slot_text', 'Empty slot text', 'text', { max: 24 })) +
          `<div class="b-field"><span class="b-label">Progressive discounts</span>${m.tiers.map((t, j) => `<div class="bx-tier">${field(`mix.tiers.${j}.count`, 'Items', 'number', { min: 1, max: 8 })}${field(`mix.tiers.${j}.discount`, '% off', 'number', { min: 0, max: 100, step: 0.01 })}<button type="button" class="b-icon-btn" data-remove-tier="${j}" aria-label="Remove">×</button></div>`).join('')}
          <button type="button" class="b-btn" data-add-tier>Add discount step</button>${help('The best step reached applies to the whole bundle.')}</div>`)
        + extrasPanel();
    }
    const list = state.offers.map((o, i) => `<div class="bx-offer ${open.has('offer-' + i) ? 'open' : ''} ${o.visible ? '' : 'hidden-offer'}">
        <div class="bx-offer-head">
          <button type="button" class="bx-section-toggle" data-toggle-section="offer-${i}"><span class="bx-chevron"></span>Offer ${i + 1} <span class="bx-muted">– ${esc(o.title || offerTitle(o))}</span></button>
          <div class="bx-offer-tools">
            <button type="button" class="bx-tool ${o.highlight ? 'on' : ''}" data-flip="offers.${i}.highlight" title="Highlight this offer" aria-label="Highlight">★</button>
            <button type="button" class="bx-tool ${o.visible ? 'on' : ''}" data-flip="offers.${i}.visible" title="${o.visible ? 'Hide' : 'Show'} offer" aria-label="Visible">👁</button>
            <button type="button" class="bx-tool" data-move="${i}:-1" title="Move up" aria-label="Move up" ${i ? '' : 'disabled'}>↑</button>
            <button type="button" class="bx-tool" data-move="${i}:1" title="Move down" aria-label="Move down" ${i < state.offers.length - 1 ? '' : 'disabled'}>↓</button>
            <button type="button" class="bx-tool" data-duplicate="${i}" title="Duplicate" aria-label="Duplicate">⧉</button>
            <button type="button" class="bx-tool danger" data-remove-offer="${i}" title="Delete" aria-label="Delete">🗑</button>
          </div>
        </div>
        <div class="bx-offer-body">${offerBody(o, i)}</div></div>`).join('');
    const add = Object.entries(meta.offerKinds).map(([k, l]) => `<button type="button" class="b-btn b-primary" data-add-offer="${k}">+ ${esc(l)}</button>`).join('');
    return `<section class="bx-card"><header class="bx-inline"><strong>Offers (${state.offers.length})</strong>${err('offers')}</header>${list}<div class="bx-add-offer"><span class="b-label">Add offer</span><div class="b-actions">${add}</div></div></section>` + extrasPanel();
  }

  function extrasPanel() {
    const u = state.upsells;
    return section('gifts', 'Gifts', field('gifts.title', 'Gifts title', 'text', { max: 80 }) + help('Add gift products inside each offer. Gifts are free at checkout when the offer is bought.'), 'gifts.enabled')
      + section('upsells', 'Upsells', field('upsells.title', 'Title', 'text', { max: 80 }) + picker('upsells.products', 'Add-on products', { max: 4 }) +
        field('upsells.discount_percent', 'Add-on discount (%)', 'number', { min: 0, max: 100, step: 0.01, help: 'Applies only to add-ons ticked in this bundle.' }), 'upsells.enabled')
      + section('summary', 'Savings summary', field('summary.text', 'Text', 'text', { max: 80, help: 'Use {saving} for the amount saved.' }), 'summary.enabled');
  }

  function designPanel() {
    const d = 'design.';
    const swatches = Object.entries(meta.presets).map(([k, p]) => `<button type="button" class="bx-swatch" style="--sw:${p.accent}" data-preset="${k}" aria-pressed="${state.design.preset === k}" aria-label="${k}"></button>`).join('');
    return section('d-overall', 'Overall design of the bundle', `<div class="b-field"><span class="b-label">Colour preset</span><div class="bx-swatches">${swatches}</div></div>` +
        row(field(d + 'accent', 'Accent (selected offer)', 'color'), field(d + 'selected_background', 'Selected background', 'color')) +
        row(field(d + 'background', 'Background', 'color'), field(d + 'border', 'Border', 'color')) +
        row(field(d + 'text', 'Text', 'color'), field(d + 'muted', 'Secondary text', 'color')) +
        row(field(d + 'radius', 'Corner radius (px)', 'number', { min: 0, max: 40 }), field(d + 'border_width', 'Border width (px)', 'number', { min: 0, max: 6 })) +
        row(field(d + 'spacing', 'Spacing', 'select', { options: { compact: 'Compact', comfortable: 'Comfortable', spacious: 'Spacious' } }), field(d + 'font', 'Font', 'select', { options: { theme: 'Match my theme', system: 'System font' } })))
      + section('d-type', 'Typography and images', row(field(d + 'title_size', 'Header size (px)', 'number', { min: 10, max: 40 }), field(d + 'offer_title_size', 'Offer title size (px)', 'number', { min: 10, max: 40 })) +
        row(field(d + 'price_size', 'Price size (px)', 'number', { min: 10, max: 40 }), field(d + 'image_size', 'Image size (px)', 'number', { min: 24, max: 80 })))
      + section('d-labels', 'Ribbons and badges', row(field(d + 'label_background', 'Ribbon background', 'color'), field(d + 'label_text', 'Ribbon text', 'color')) +
        row(field(d + 'badge_background', 'Badge background', 'color'), field(d + 'badge_text', 'Badge text', 'color')))
      + section('d-button', 'Button', row(field(d + 'button_background', 'Button background', 'color'), field(d + 'button_text', 'Button text', 'color')))
      + section('d-gift', 'Gift design', field(d + 'gift_background', 'Gift tile background', 'color'))
      + section('d-summary', 'Savings summary design', row(field(d + 'summary_background', 'Background', 'color'), field(d + 'summary_text', 'Text', 'color')))
      + section('d-css', 'Custom CSS', field(d + 'custom_css', 'CSS', 'textarea', { rows: 6, code: true, help: 'Scoped to this bundle. Classes start with .oo-b.' }));
  }

  // ------------------------------------------------------------------ rendering
  const panels = { settings: settingsPanel, offers: offersPanel, design: designPanel };
  function draw(name) {
    const el = $(`[data-panel="${name}"]`);
    el.innerHTML = panels[name]();
  }
  function drawAll() { Object.keys(panels).forEach(draw); $('[data-schedule]').innerHTML = schedulePanel(); markTabs(); preview(); }

  function markTabs() {
    const keys = Object.keys(errors);
    const tabOf = (k) => (k.startsWith('offers') || k.startsWith('mix') || k.startsWith('upsells') ? 'offers' : k.startsWith('design') ? 'design' : 'settings');
    $$('[data-tab]').forEach((t) => t.classList.toggle('has-error', keys.some((k) => tabOf(k) === t.dataset.tab)));
    return keys.length ? tabOf(keys[0]) : null;
  }

  function showTab(name) {
    $$('[data-tab]').forEach((t) => t.setAttribute('aria-selected', String(t.dataset.tab === name)));
    $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
  }

  // ------------------------------------------------------------------ preview (mirrors BundleSchema::payload)
  let device = 'desktop';
  const previewCtx = { preview: true, currency: meta.currency, cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
  const SAMPLES = meta.samples.slice(0, 3);

  function payload() {
    const c = JSON.parse(JSON.stringify(state));
    const d = c.design;
    // Empty pickers preview with sample products so the layout is visible.
    c.offers.forEach((o, i) => {
      o.index = i;
      if (o.kind === 'multi' && !o.products.length) o.products = SAMPLES.slice(0, 2);
      if (o.kind === 'mono' && !o.product.length) o.product = [SAMPLES[i % 3]];
      (o.gifts || []).forEach((g) => { if (!g.product.length) g.product = [meta.samples[3]]; });
    });
    if (!c.mix.pool.length) c.mix.pool = SAMPLES;
    return {
      id: meta.id, type: 'bundles', template: 'editor', style: c.settings.layout, version: 0, priority: 50,
      content: { bundle_type: c.bundle_type, settings: c.settings, offers: c.offers.filter((o) => o.visible), mix: c.mix, gifts: c.gifts, upsells: c.upsells, summary: c.summary },
      design: Object.assign({}, d, { primary_color: d.button_background, accent_color: d.accent, text_color: d.text, background_color: d.background, border: false }),
      behavior: { priority: 50, after_add: c.settings.after_add, position: c.settings.position, animation: 'none' },
      targeting: {},
    };
  }

  let queued = null;
  function preview() {
    clearTimeout(queued);
    queued = setTimeout(() => {
      const el = $('[data-preview]');
      window.OrderOrbit.render(el, payload(), Object.assign({}, previewCtx)).then((shown) => {
        if (!shown) el.innerHTML = '<p class="bx-muted" style="text-align:center;padding:24px">Add an offer to see the preview.</p>';
      });
    }, 60);
  }

  // ------------------------------------------------------------------ resource pickers
  async function pick(path, kind, max) {
    if (!window.shopify || !shopify.resourcePicker) return;
    const current = get(path) || [];
    const picked = await shopify.resourcePicker({
      type: kind,
      multiple: max > 1 ? max : false,
      selectionIds: current.map((c) => (c.variants && c.variants.length ? { id: c.id, variants: c.variants.map((v) => ({ id: v.id })) } : { id: c.id })),
      filter: kind === 'product' ? { variants: true } : undefined,
    });
    if (!picked) return;
    set(path, picked.map((r) => {
      const before = current.find((c) => c.id === r.id) || {};
      if (kind === 'collection') return { id: r.id, title: r.title, handle: r.handle };
      const chosen = (r.variants || []).filter((v) => v && v.id);
      const first = chosen[0] || {};
      const image = (r.images && r.images[0] && (r.images[0].originalSrc || r.images[0].url)) || null;
      return {
        id: r.id, title: r.title, handle: r.handle, image,
        price: first.price != null ? Number(first.price) : null,
        compare_at: first.compareAtPrice != null ? Number(first.compareAtPrice) : null,
        variant_id: first.id || null,
        variants: r.hasOnlyDefaultVariant === false || chosen.length > 1 ? chosen.map((v) => ({ id: v.id, title: v.title || v.displayName, price: v.price != null ? Number(v.price) : null })) : undefined,
        quantity: before.quantity,
      };
    }));
    rerender();
  }

  function rerender() {
    const active = ($('[data-tab][aria-selected="true"]') || {}).dataset?.tab || 'settings';
    draw(active);
    $('[data-schedule]').innerHTML = schedulePanel();
    changed();
  }

  // ------------------------------------------------------------------ events
  function read(el) {
    const type = el.dataset.type;
    if (type === 'toggle') return el.checked;
    if (type === 'number') return el.value === '' ? null : Number(el.value);
    return el.value;
  }

  form.addEventListener('input', (e) => {
    const el = e.target;
    if (el.dataset.path && el.dataset.type !== 'toggle' && el.dataset.type !== 'select') {
      set(el.dataset.path, read(el));
      if (el.dataset.type === 'color') el.nextElementSibling.textContent = el.value;
      changed();
    }
    if (el.dataset.chipQty) {
      const list = get(el.dataset.chipQty);
      list[Number(el.dataset.i)].quantity = Math.max(1, Math.min(20, Number(el.value) || 1));
      changed();
    }
    if (el.name === 'name') changed();
  });

  form.addEventListener('change', (e) => {
    const el = e.target;
    if (el.dataset.path && (el.dataset.type === 'toggle' || el.dataset.type === 'select')) {
      set(el.dataset.path, read(el));
      // Choices that change which fields show redraw the panel.
      if (el.dataset.rerender !== undefined || /kind|discount_type|timer|visibility|gifts\.enabled|hide_theme_form/.test(el.dataset.path)) rerender(); else changed();
    }
    if (el.dataset.preselect !== undefined) {
      state.offers.forEach((o, i) => { o.preselected = i === Number(el.dataset.preselect); });
      changed();
    }
  });

  form.addEventListener('click', (e) => {
    const t = e.target.closest('button');
    if (!t) return;
    const d = t.dataset;
    if (d.tab) { showTab(d.tab); return; }
    if (d.device) {
      device = d.device;
      $$('[data-device]').forEach((b) => b.setAttribute('aria-pressed', String(b === t)));
      $('[data-frame]').classList.toggle('oo-preview-mobile', device === 'mobile');
      return;
    }
    if (d.toggleSection) {
      open.has(d.toggleSection) ? open.delete(d.toggleSection) : open.add(d.toggleSection);
      t.closest('.bx-section, .bx-offer').classList.toggle('open');
      t.setAttribute('aria-expanded', String(open.has(d.toggleSection)));
      return;
    }
    if (d.set) { set(d.set, d.value); rerender(); return; }
    if (d.pick) { pick(d.pick, d.kind, Number(d.max)); return; }
    if (d.chipRemove) { get(d.chipRemove).splice(Number(d.i), 1); rerender(); return; }
    if (d.flip) { set(d.flip, !get(d.flip)); rerender(); return; }
    if (d.addOffer) {
      const n = state.offers.length;
      state.offers.push({ id: 'o' + Date.now().toString(36), kind: d.addOffer, title: d.addOffer === 'multi' ? 'Bundle pack' : `${n + 1} Products`, subtitle: 'You save {saving}',
        quantity: d.addOffer === 'quantity' ? n + 1 : 1, product: [], products: [], discount_type: 'percentage', discount_value: 10, badge: '', label: '', highlight: false, preselected: false, visible: true, gifts: [] });
      open.add('offer-' + n);
      rerender();
      return;
    }
    if (d.removeOffer) {
      if (!confirm('Delete this offer?')) return;
      state.offers.splice(Number(d.removeOffer), 1);
      if (state.offers.length && !state.offers.some((o) => o.preselected)) state.offers[0].preselected = true;
      rerender();
      return;
    }
    if (d.duplicate) {
      const i = Number(d.duplicate);
      const copy = JSON.parse(JSON.stringify(state.offers[i]));
      copy.id = 'o' + Date.now().toString(36);
      copy.preselected = false;
      state.offers.splice(i + 1, 0, copy);
      rerender();
      return;
    }
    if (d.move) {
      const [i, step] = d.move.split(':').map(Number);
      const [o] = state.offers.splice(i, 1);
      state.offers.splice(i + step, 0, o);
      rerender();
      return;
    }
    if (d.addGift) { state.offers[Number(d.addGift)].gifts.push({ product: [], quantity: 1 }); rerender(); return; }
    if (d.removeGift) { const [i, j] = d.removeGift.split(':').map(Number); state.offers[i].gifts.splice(j, 1); rerender(); return; }
    if (d.addTier) { const last = state.mix.tiers[state.mix.tiers.length - 1]; state.mix.tiers.push({ count: last ? last.count + 1 : 2, discount: last ? last.discount + 5 : 10 }); rerender(); return; }
    if (d.removeTier) { state.mix.tiers.splice(Number(d.removeTier), 1); rerender(); return; }
    if (d.preset) {
      state.design = Object.assign(state.design, meta.presets[d.preset], { preset: d.preset });
      rerender();
      return;
    }
    if (d.previewProduct !== undefined) previewProduct(t);
  });

  async function previewProduct(button) {
    if (!window.shopify || !shopify.resourcePicker) return;
    const picked = await shopify.resourcePicker({ type: 'product', multiple: false });
    const p = picked && picked[0];
    if (!p) return;
    const first = (p.variants || [])[0] || {};
    const image = (p.images && p.images[0] && (p.images[0].originalSrc || p.images[0].url)) || null;
    const variants = (p.variants || []).map((v) => ({ id: v.id, title: v.title, price: Number(v.price), available: true }));
    Object.assign(previewCtx, { productTitle: p.title, productPrice: Math.round(Number(first.price || 29) * 100), productImage: image,
      pageProduct: { title: p.title, price: Number(first.price || 29), image, variants: variants.length > 1 ? variants : null } });
    button.textContent = p.title;
    preview();
  }

  // Runs before the layout's submit handler, which adds the session token and the clicked action.
  form.addEventListener('submit', () => {
    $('[data-config-json]').value = JSON.stringify(state);
    dirty = false;
  }, true);

  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  drawAll();
  showTab(markTabs() || 'settings');
})();
