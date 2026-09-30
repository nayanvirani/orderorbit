/* OrderOrbit progressive gifts editor: Rewards / Settings / Design over one JSON config,
   with a live preview driven by a sample cart value. The server (GiftSchema) validates. */
(() => {
  const { config: state, errors, meta } = window.GiftEditor;
  const form = document.querySelector('[data-gift-editor]');
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let dirty = false;

  function get(path) { return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), state); }
  function set(path, value) {
    const keys = path.split('.');
    const last = keys.pop();
    keys.reduce((o, k) => (o[k] = o[k] ?? {}), state)[last] = value;
  }
  function changed() { dirty = true; $('[data-dirty]').hidden = false; preview(); }

  const err = (path) => (errors[path] ? `<p class="b-error">${esc(errors[path])}</p>` : '');
  const help = (text) => (text ? `<p class="b-help">${text}</p>` : '');
  const row = (...cells) => `<div class="bx-row">${cells.join('')}</div>`;

  function field(path, label, type = 'text', opts = {}) {
    const v = get(path);
    const id = 'g-' + path.replace(/\./g, '-');
    const attrs = `id="${id}" data-path="${path}" data-type="${type}"`;
    let control;
    if (type === 'select') control = `<select ${attrs}>${Object.entries(opts.options).map(([k, l]) => `<option value="${esc(k)}" ${String(v) === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
    else if (type === 'number') control = `<input type="number" ${attrs} value="${esc(v)}" min="${opts.min ?? 0}" ${opts.max != null ? `max="${opts.max}"` : ''} step="${opts.step || 1}">`;
    else if (type === 'color') control = `<span class="b-color"><input type="color" ${attrs} value="${esc(v)}"><code>${esc(v)}</code></span>`;
    else if (type === 'textarea') control = `<textarea ${attrs} rows="5" class="b-code">${esc(v)}</textarea>`;
    else if (type === 'datetime') control = `<input type="datetime-local" ${attrs} value="${esc(v ? String(v).slice(0, 16) : '')}">`;
    else if (type === 'toggle') return `<div class="b-field b-toggle"><label><input type="checkbox" ${attrs} ${v ? 'checked' : ''}> ${esc(label)}</label>${help(opts.help)}</div>`;
    else control = `<input type="text" ${attrs} value="${esc(v)}" maxlength="${opts.max || 160}">`;
    return `<div class="b-field ${errors[path] ? 'b-has-error' : ''}"><label for="${id}">${esc(label)}</label>${control}${help(opts.help)}${err(path)}</div>`;
  }

  function picker(path, label, max) {
    const items = get(path) || [];
    return `<div class="b-field ${errors[path] ? 'b-has-error' : ''}"><span class="b-label">${esc(label)}</span>
      <ul class="b-chips">${items.map((it, i) => `<li class="b-chip">${it.image ? `<img src="${esc(it.image)}" alt="" width="24" height="24">` : ''}<span class="b-chip-label">${esc(it.title)}${it.variants && it.variants.length ? `<small>${esc(it.variants.map((v) => v.title).join(', '))}</small>` : ''}</span><button type="button" data-chip-remove="${path}" data-i="${i}" aria-label="Remove">×</button></li>`).join('')}</ul>
      <button type="button" class="b-btn" data-pick="${path}" data-max="${max}">${items.length ? 'Change' : 'Select'} ${max > 1 ? 'products' : 'product'}</button>${err(path)}</div>`;
  }

  function card(title, body) { return `<section class="bx-card"><header class="bx-inline"><strong>${title}</strong></header>${body}</section>`; }

  // ------------------------------------------------------------------ panels
  function rewardsPanel() {
    const count = state.settings.unlock === 'count';
    const list = state.milestones.map((m, i) => {
      const p = `milestones.${i}`;
      let body = row(field(`${p}.threshold`, count ? 'Unlocks at (items)' : `Unlocks at (${meta.currency})`, 'number', { min: 0, step: count ? 1 : 0.01 }), field(`${p}.reward`, 'Reward', 'select', { options: meta.rewards }));
      body += field(`${p}.label`, 'Label shoppers see', 'text', { max: 40 });
      if (m.reward === 'gift') body += row(picker(`${p}.products`, 'Gift product', 1), field(`${p}.quantity`, 'Quantity', 'number', { min: 1, max: 5 }));
      if (m.reward === 'choice') body += picker(`${p}.products`, 'Gifts shoppers choose from', 8);
      if (m.reward === 'percent' || m.reward === 'amount') body += field(`${p}.value`, m.reward === 'percent' ? '% off the order' : `Amount off (${meta.currency})`, 'number', { min: 0, step: 0.01 });
      return `<div class="bx-milestone"><div class="bx-milestone-head"><strong>Reward ${i + 1}</strong><button type="button" class="bx-tool danger" data-remove="${i}" aria-label="Remove reward">🗑</button></div>${body}</div>`;
    }).join('');
    return card('Unlock rewards by', `<div class="bx-seg" role="group">${[['value', 'Cart value'], ['count', 'Item count']].map(([k, l]) => `<button type="button" data-set="settings.unlock" data-value="${k}" aria-pressed="${state.settings.unlock === k}">${l}</button>`).join('')}</div>`)
      + card(`Rewards (${state.milestones.length})`, list + err('milestones') + (state.milestones.length < 5 ? `<div class="b-actions" style="margin-top:12px"><button type="button" class="b-btn b-primary" data-add>+ Add reward</button></div>` : '')
        + help('Gifts are free at checkout while the cart qualifies; if it drops below, the gift is taken out of the cart. Free shipping and order discounts apply automatically.'));
  }

  function settingsPanel() {
    return card('Messages', field('settings.title', 'Title (optional)', 'text', { max: 80 }) +
        field('settings.progress_message', 'Progress message', 'text', { help: 'Use {remaining} and {reward}.' }) +
        field('settings.unlocked_message', 'When everything is unlocked', 'text'))
      + card('Where it shows', row(field('settings.placement', 'Pages', 'select', { options: { both: 'Product pages and cart', product: 'Product pages', cart: 'Cart page' } }),
          field('settings.position', 'On product pages', 'select', { options: { below_atc: 'Below the add to cart button', above_atc: 'Above the add to cart button', block: 'Only where I place the block' } })) +
        help('The cart page shows it where you add the OrderOrbit block in the Theme Editor.') +
        field('settings.claim', 'Single gifts', 'select', { options: { auto: 'Add to the cart automatically', claim: 'Shopper claims the gift' } }) +
        field('settings.show_empty', 'Show when the cart is empty', 'toggle'))
      + card('Schedule', row(field('schedule.starts_at', `Start (${meta.timezone})`, 'datetime'), field('schedule.ends_at', `End (${meta.timezone})`, 'datetime')));
  }

  function designPanel() {
    const tiles = Object.entries(meta.layouts).map(([k, l]) => `<button type="button" class="bx-layout-tile ${state.settings.layout === k ? 'on' : ''}" data-set="settings.layout" data-value="${k}" aria-pressed="${state.settings.layout === k}">${esc(l)}</button>`).join('');
    const d = 'design.';
    return card('Layout', `<div class="bx-layouts" style="grid-template-columns:repeat(auto-fit,minmax(110px,1fr))">${tiles}</div>`)
      + card('Colours', row(field(d + 'accent', 'Progress and unlocked', 'color'), field(d + 'track', 'Track', 'color')) +
        row(field(d + 'background', 'Background', 'color'), field(d + 'border', 'Border', 'color')) +
        row(field(d + 'text', 'Text', 'color'), field(d + 'muted', 'Locked rewards', 'color')))
      + card('Shape', row(field(d + 'radius', 'Corner radius (px)', 'number', { max: 40 }), field(d + 'bar_height', 'Bar height (px)', 'number', { min: 2, max: 24 })) + field(d + 'title_size', 'Message size (px)', 'number', { min: 10, max: 32 }))
      + card('Custom CSS', field(d + 'custom_css', 'CSS', 'textarea', { help: 'Scoped to these rewards. Classes start with .oo-pg.' }));
  }

  const panels = { rewards: rewardsPanel, settings: settingsPanel, design: designPanel };
  function draw(name) { $(`[data-panel="${name}"]`).innerHTML = panels[name](); }
  function active() { return ($('[data-tab][aria-selected="true"]') || {}).dataset?.tab || 'rewards'; }
  function rerender() { draw(active()); changed(); }
  function showTab(name) {
    $$('[data-tab]').forEach((t) => t.setAttribute('aria-selected', String(t.dataset.tab === name)));
    $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
  }

  // ------------------------------------------------------------------ preview (mirrors GiftSchema::payload)
  const range = $('[data-cart-range]');
  function preview() {
    const count = state.settings.unlock === 'count';
    const top = Math.max(...state.milestones.map((m) => Number(m.threshold) || 0), count ? 5 : 100);
    range.max = String(Math.ceil(top * 1.2));
    $('[data-cart-label]').textContent = count ? 'Items in cart' : 'Cart value';
    $('[data-cart-value]').textContent = count ? range.value : new Intl.NumberFormat(undefined, { style: 'currency', currency: meta.currency }).format(Number(range.value));
    const c = JSON.parse(JSON.stringify(state));
    c.milestones.sort((a, b) => a.threshold - b.threshold).forEach((m, i) => {
      m.index = i;
      if ((m.reward === 'gift' || m.reward === 'choice') && !m.products.length) m.products = [{ title: 'Free gift', price: 12 }];
    });
    const d = c.design;
    const exp = {
      id: meta.id, type: 'progressive-gifts', template: 'editor', style: c.settings.layout, version: 0, priority: 50,
      content: { settings: c.settings, milestones: c.milestones },
      design: Object.assign({}, d, { primary_color: d.accent, accent_color: d.accent, text_color: d.text, background_color: d.background, border: true }),
      behavior: {}, targeting: {},
    };
    // The slider is the preview cart: its value or item count.
    const ctx = { preview: true, currency: meta.currency, page: 'product', previewProgress: Number(range.value) };
    window.OrderOrbit.render($('[data-preview]'), exp, ctx);
  }

  // ------------------------------------------------------------------ pickers
  async function pick(path, max) {
    if (!window.shopify || !shopify.resourcePicker) return;
    const current = get(path) || [];
    const picked = await shopify.resourcePicker({
      type: 'product', multiple: max > 1 ? max : false, filter: { variants: true },
      selectionIds: current.map((c) => (c.variants && c.variants.length ? { id: c.id, variants: c.variants.map((v) => ({ id: v.id })) } : { id: c.id })),
    });
    if (!picked) return;
    set(path, picked.map((r) => {
      const chosen = (r.variants || []).filter((v) => v && v.id);
      const first = chosen[0] || {};
      return {
        id: r.id, title: r.title, handle: r.handle,
        image: (r.images && r.images[0] && (r.images[0].originalSrc || r.images[0].url)) || null,
        price: first.price != null ? Number(first.price) : null, variant_id: first.id || null,
        variants: r.hasOnlyDefaultVariant === false || chosen.length > 1 ? chosen.map((v) => ({ id: v.id, title: v.title || v.displayName, price: v.price != null ? Number(v.price) : null })) : undefined,
      };
    }));
    rerender();
  }

  // ------------------------------------------------------------------ events
  const read = (el) => (el.dataset.type === 'toggle' ? el.checked : el.dataset.type === 'number' ? (el.value === '' ? null : Number(el.value)) : el.value);

  form.addEventListener('input', (e) => {
    const el = e.target;
    if (el === range) { preview(); return; }
    if (el.dataset.path && !['toggle', 'select'].includes(el.dataset.type)) {
      set(el.dataset.path, read(el));
      if (el.dataset.type === 'color') el.nextElementSibling.textContent = el.value;
      changed();
    }
    if (el.name === 'name') changed();
  });
  form.addEventListener('change', (e) => {
    const el = e.target;
    if (el.dataset.path && ['toggle', 'select'].includes(el.dataset.type)) {
      set(el.dataset.path, read(el));
      if (/reward$/.test(el.dataset.path)) {
        const m = get(el.dataset.path.replace(/\.reward$/, ''));
        m.label = meta.rewards[m.reward];
        rerender();
      } else changed();
    }
  });
  form.addEventListener('click', (e) => {
    const t = e.target.closest('button');
    if (!t) return;
    const d = t.dataset;
    if (d.tab) showTab(d.tab);
    else if (d.device) {
      $$('[data-device]').forEach((b) => b.setAttribute('aria-pressed', String(b === t)));
      $('[data-frame]').classList.toggle('oo-preview-mobile', d.device === 'mobile');
    } else if (d.set) { set(d.set, d.value); rerender(); }
    else if (d.pick) pick(d.pick, Number(d.max));
    else if (d.chipRemove) { get(d.chipRemove).splice(Number(d.i), 1); rerender(); }
    else if (d.add !== undefined) {
      const last = state.milestones[state.milestones.length - 1];
      state.milestones.push({ threshold: last ? Number(last.threshold) + (state.settings.unlock === 'count' ? 1 : 25) : 50, reward: 'shipping', label: 'Free shipping', value: 0, products: [], quantity: 1 });
      rerender();
    } else if (d.remove !== undefined) { state.milestones.splice(Number(d.remove), 1); rerender(); }
  });
  form.addEventListener('submit', () => { $('[data-config-json]').value = JSON.stringify(state); dirty = false; }, true);
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  Object.keys(panels).forEach(draw);
  const firstError = Object.keys(errors)[0];
  showTab(firstError ? (firstError.startsWith('milestones') ? 'rewards' : firstError.startsWith('design') ? 'design' : 'settings') : 'rewards');
  preview();
})();
