/* OrderOrbit experience builder (section 16 / D2). */
(() => {
  const form = document.querySelector('[data-builder]');
  if (!form) return;

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const styles = JSON.parse(form.dataset.styles || '{}');
  const steps = $$('[data-step]', form).map((el) => el.dataset.step);
  let current = steps.includes(form.dataset.startStep) ? form.dataset.startStep : 'content';
  let dirty = false;

  // ---------------------------------------------------------------- steps
  function show(step) {
    current = step;
    $$('[data-step]', form).forEach((el) => { el.hidden = el.dataset.step !== step; });
    $$('[data-step-link]', form).forEach((b) => b.setAttribute('aria-current', b.dataset.stepLink === step ? 'step' : 'false'));
    const i = steps.indexOf(step);
    $('[data-step-prev]').disabled = i === 0;
    $('[data-step-next]').hidden = i === steps.length - 1;
    if (step === 'template') renderTemplatePreviews();
    const first = $(`[data-step="${step}"] .b-has-error input, [data-step="${step}"] .b-has-error select, [data-step="${step}"] .b-has-error textarea`, form);
    if (first) first.focus({ preventScroll: false });
  }
  $$('[data-step-link]', form).forEach((b) => b.addEventListener('click', () => show(b.dataset.stepLink)));
  $('[data-step-prev]').addEventListener('click', () => show(steps[Math.max(0, steps.indexOf(current) - 1)]));
  $('[data-step-next]').addEventListener('click', () => show(steps[Math.min(steps.length - 1, steps.indexOf(current) + 1)]));

  // ---------------------------------------------------------------- form → config
  function setPath(obj, path, value, isArray) {
    let node = obj;
    path.forEach((key, i) => {
      if (i === path.length - 1) {
        if (isArray) (node[key] = node[key] || []).push(value);
        else node[key] = value;
      } else {
        node = node[key] = node[key] || {};
      }
    });
  }

  function readConfig() {
    const config = {};
    const data = new FormData(form);
    const seenToggles = new Set();
    for (const [name, raw] of data.entries()) {
      if (!name.startsWith('config[')) continue;
      const isArray = name.endsWith('[]');
      const path = name.replace(/\[\]$/, '').slice(7, -1).split('][');
      const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
      let value = raw;
      if (input && (input.dataset.resourceInput !== undefined || input.dataset.listInput !== undefined)) {
        try { value = JSON.parse(raw || '[]'); } catch (e) { value = []; }
      } else if (input && input.type === 'hidden' && raw === '0') {
        value = false; // toggle's hidden fallback; the checkbox (if checked) follows with "1"
        seenToggles.add(name);
      } else if (seenToggles.has(name) && raw === '1') {
        value = true;
      } else if (input && input.type === 'number') {
        value = raw === '' ? null : Number(raw);
      } else if (input && input.type === 'datetime-local') {
        value = raw ? `${raw}:00${input.dataset.tzOffset || ''}` : null;
      }
      setPath(config, path, value, isArray);
    }
    config.targeting = config.targeting || {};
    config.targeting.page_types = config.targeting.page_types || [];
    return config;
  }

  // ---------------------------------------------------------------- preview
  const frame = $('[data-preview-frame]');
  const target = $('[data-preview]');
  const cartInput = $('[data-preview-cart]');

  function experience(templateKey) {
    const key = templateKey || ($('input[name="template_key"]:checked', form) || {}).value;
    const config = readConfig();
    return {
      id: form.dataset.handle, type: form.dataset.type, template: key, style: styles[key] || 'card', version: 0,
      priority: 50, content: config.content || {}, design: config.design || {}, behavior: config.behavior || {},
      targeting: config.targeting || {}, analytics: config.analytics || {},
    };
  }

  function context() {
    return { preview: true, currency: form.dataset.currency, cartTotal: Math.round(Number(cartInput.value || 0) * 100), productPrice: 2900, productTitle: 'Sample product', page: 'product' };
  }

  let pending = null;
  function renderPreview() {
    clearTimeout(pending);
    pending = setTimeout(() => {
      if (!window.OrderOrbit) return;
      window.OrderOrbit.render(target, experience(), context()).then((shown) => {
        if (shown) return;
        target.hidden = false;
        target.innerHTML = '<p class="b-muted b-empty-preview">Nothing to show with these settings (for example, the countdown has ended and is set to hide).</p>';
      });
    }, 120);
  }

  function renderTemplatePreviews() {
    if (!window.OrderOrbit) return;
    $$('[data-template-preview]', form).forEach((el) => {
      window.OrderOrbit.render(el, experience(el.dataset.templatePreview), context());
    });
  }

  $$('[data-device]').forEach((b) => b.addEventListener('click', () => {
    $$('[data-device]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    frame.classList.toggle('oo-preview-mobile', b.dataset.device === 'mobile');
  }));
  cartInput.addEventListener('input', renderPreview);

  // ---------------------------------------------------------------- colour inputs
  $$('[data-color-for]', form).forEach((picker) => {
    const text = document.getElementById(picker.dataset.colorFor);
    picker.addEventListener('input', () => { text.value = picker.value; text.dispatchEvent(new Event('input', { bubbles: true })); });
    text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
  });

  // ---------------------------------------------------------------- list rows
  $$('[data-list-input]', form).forEach((input) => {
    const fields = JSON.parse(input.dataset.listFields);
    const max = Number(input.dataset.listMax || 10);
    const box = input.parentElement.querySelector('[data-list]');
    let rows = [];
    try { rows = JSON.parse(input.value || '[]'); } catch (e) { rows = []; }

    function sync() { input.value = JSON.stringify(rows); input.dispatchEvent(new Event('input', { bubbles: true })); }

    function draw() {
      box.innerHTML = '';
      rows.forEach((row, i) => {
        const line = document.createElement('div');
        line.className = 'b-row';
        Object.entries(fields).forEach(([key, f]) => {
          const label = document.createElement('label');
          label.className = 'b-row-field';
          label.textContent = f.label;
          let control;
          if (f.type === 'select') {
            control = document.createElement('select');
            Object.entries(f.options).forEach(([v, l]) => control.add(new Option(l, v, false, String(row[key]) === v)));
          } else {
            control = document.createElement('input');
            control.type = f.type === 'number' || f.type === 'money' ? 'number' : 'text';
            if (f.type === 'money') control.step = '0.01';
            if (f.min != null) control.min = f.min;
            if (f.max != null && control.type === 'number') control.max = f.max;
            if (f.max != null && control.type === 'text') control.maxLength = f.max;
            control.value = row[key] == null ? '' : row[key];
          }
          control.addEventListener('input', () => {
            rows[i][key] = control.type === 'number' ? (control.value === '' ? null : Number(control.value)) : control.value;
            sync();
          });
          label.appendChild(control);
          line.appendChild(label);
        });
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'b-icon-btn';
        remove.setAttribute('aria-label', 'Remove row');
        remove.textContent = '×';
        remove.addEventListener('click', () => { rows.splice(i, 1); sync(); draw(); });
        line.appendChild(remove);
        box.appendChild(line);
      });
      if (rows.length < max) {
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'b-btn';
        add.textContent = 'Add row';
        add.addEventListener('click', () => {
          rows.push(Object.fromEntries(Object.keys(fields).map((k) => [k, fields[k].type === 'select' ? Object.keys(fields[k].options)[0] : ''])));
          sync(); draw();
        });
        box.appendChild(add);
      }
    }
    draw();
  });

  // ---------------------------------------------------------------- Shopify resource pickers
  $$('[data-picker]', form).forEach((button) => {
    const field = button.closest('.b-field');
    const input = $('[data-resource-input]', field);
    const list = $('[data-resource-list]', field);

    function items() { try { return JSON.parse(input.value || '[]'); } catch (e) { return []; } }

    function draw() {
      list.innerHTML = '';
      items().forEach((item, i) => {
        const li = document.createElement('li');
        li.className = 'b-chip';
        if (item.image) { const img = new Image(24, 24); img.src = item.image; img.alt = ''; li.appendChild(img); }
        const label = document.createElement('span');
        label.className = 'b-chip-label';
        label.textContent = item.title || item.id;
        if (item.variants && item.variants.length) {
          const v = document.createElement('small');
          v.textContent = item.variants.length + ' variant' + (item.variants.length > 1 ? 's' : '') + ': ' + item.variants.map((x) => x.title).join(', ');
          v.title = v.textContent;
          label.appendChild(v);
        }
        li.appendChild(label);
        if (button.hasAttribute('data-quantities')) {
          const qty = document.createElement('input');
          Object.assign(qty, { type: 'number', min: 1, max: 20, value: item.quantity || 1, className: 'b-chip-qty' });
          qty.setAttribute('aria-label', `Quantity of ${item.title || 'item'} in the bundle`);
          qty.addEventListener('change', () => {
            const next = items();
            next[i].quantity = Math.max(1, Math.min(20, Number(qty.value) || 1));
            input.value = JSON.stringify(next);
            input.dispatchEvent(new Event('input', { bubbles: true }));
          });
          li.appendChild(qty);
        }
        const x = document.createElement('button');
        x.type = 'button';
        x.setAttribute('aria-label', `Remove ${item.title || 'item'}`);
        x.textContent = '×';
        x.addEventListener('click', () => { const next = items(); next.splice(i, 1); input.value = JSON.stringify(next); draw(); input.dispatchEvent(new Event('input', { bubbles: true })); });
        li.appendChild(x);
        list.appendChild(li);
      });
    }

    button.addEventListener('click', async () => {
      if (!window.shopify || !shopify.resourcePicker) return;
      const current = items();
      const picked = await shopify.resourcePicker({
        type: button.dataset.picker,
        multiple: Number(button.dataset.max) > 1 ? Number(button.dataset.max) : false,
        // Re-opening keeps the variants already mapped for each product.
        selectionIds: current.map((c) => (c.variants && c.variants.length ? { id: c.id, variants: c.variants.map((v) => ({ id: v.id })) } : { id: c.id })),
        filter: { variants: true },
      });
      if (!picked) return;
      const mapped = picked.map((r) => {
        const chosen = (r.variants || []).filter((v) => v && v.id);
        const variant = chosen[0] || {};
        const before = current.find((c) => c.id === r.id) || {};
        return {
          variants: r.hasOnlyDefaultVariant === false || chosen.length > 1
            ? chosen.map((v) => ({ id: v.id, title: v.title || v.displayName, price: v.price != null ? Number(v.price) : null }))
            : undefined,
          quantity: before.quantity,
          id: r.id, title: r.title, handle: r.handle,
          image: (r.images && r.images[0] && (r.images[0].originalSrc || r.images[0].url)) || (r.image && (r.image.originalSrc || r.image.url)) || null,
          price: variant.price != null ? Number(variant.price) : null,
          compare_at: variant.compareAtPrice != null ? Number(variant.compareAtPrice) : null,
          variant_id: variant.id || null,
        };
      });
      input.value = JSON.stringify(mapped);
      draw();
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    draw();
  });

  // ---------------------------------------------------------------- dirty state
  form.addEventListener('input', () => { dirty = true; $('[data-dirty]').hidden = false; renderPreview(); });
  form.addEventListener('change', () => { dirty = true; $('[data-dirty]').hidden = false; renderPreview(); });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  show(current);
  renderPreview();
})();
