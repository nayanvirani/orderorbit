/*
 * OrderOrbit Space workflow builder: a vertical canvas of the trigger and its steps (actions,
 * waits and if/else conditions with nested branches). The workflow is kept as JSON in a hidden
 * field and sent with the form; the server validates it and reports errors by path.
 */
(() => {
  const form = document.querySelector('[data-workflow-form]');
  if (!form) return;

  const read = (sel, fallback) => { try { return JSON.parse(document.querySelector(sel).textContent); } catch (e) { return fallback; } };
  const catalog = read('[data-wf-catalog]', {});
  const errors = read('[data-wf-errors]', {});
  const workflows = read('[data-wf-workflows]', []);
  const hidden = form.querySelector('[data-definition]');
  const canvas = form.querySelector('[data-canvas]');
  let state;
  try { state = JSON.parse(hidden.value); } catch (e) { state = {}; }
  state.trigger = state.trigger || 'order_paid';
  state.trigger_config = state.trigger_config || {};
  state.steps = state.steps || [];

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const el = (html) => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; };

  // ------------------------------------------------------------- state paths ("steps.1.then.0")
  function at(path) {
    return path.split('.').reduce((o, k) => (o == null ? o : o[/^\d+$/.test(k) ? Number(k) : k]), state);
  }
  function set(path, value) {
    const keys = path.split('.');
    const last = keys.pop();
    const parent = keys.reduce((o, k) => o[/^\d+$/.test(k) ? Number(k) : k], state);
    parent[/^\d+$/.test(last) ? Number(last) : last] = value;
  }
  function listAndIndex(path) {
    const keys = path.split('.');
    const index = Number(keys.pop());
    return [at(keys.join('.')), index];
  }
  function errorsFor(prefix) {
    return Object.keys(errors).filter((k) => k === prefix || k.startsWith(prefix + '.')).map((k) => errors[k]);
  }

  // ------------------------------------------------------------- fields
  function field(def, value, bind) {
    const label = '<span>' + esc(def.label) + (def.required ? ' *' : '') + '</span>';
    const help = def.help ? '<small>' + esc(def.help) + '</small>' : '';
    if (def.type === 'select') {
      return '<label class="wf-field">' + label + '<select data-bind="' + bind + '">' + Object.entries(def.options).map(([k, v]) => '<option value="' + esc(k) + '"' + (String(value) === k ? ' selected' : '') + '>' + esc(v) + '</option>').join('') + '</select>' + help + '</label>';
    }
    if (def.type === 'textarea') {
      return '<label class="wf-field wf-wide">' + label + '<textarea rows="6" data-bind="' + bind + '">' + esc(value) + '</textarea>' + help + '</label>';
    }
    if (def.type === 'products' || def.type === 'collections') {
      const items = Array.isArray(value) ? value : [];
      return '<div class="wf-field wf-wide">' + label + '<div class="wf-picked">' + (items.length ? items.map((p) => '<span class="wf-chip">' + esc(p.title || p.id) + '</span>').join('') : '<span class="oo-muted">None chosen</span>') +
        '</div><button type="button" class="b-btn" data-pick="' + (def.type === 'collections' ? 'collection' : 'product') + '" data-bind-path="' + bind + '">Choose ' + (def.type === 'collections' ? 'collections' : 'products') + '</button>' + help + '</div>';
    }
    if (def.type === 'workflow') {
      return '<label class="wf-field">' + label + '<select data-bind="' + bind + '"><option value="">Choose…</option>' + workflows.map((w) => '<option value="' + esc(w.handle) + '"' + (value === w.handle ? ' selected' : '') + '>' + esc(w.name) + '</option>').join('') + '</select>' + help + '</label>';
    }
    return '<label class="wf-field">' + label + '<input type="' + (def.type === 'number' ? 'number' : 'text') + '" value="' + esc(value) + '" data-bind="' + bind + '"' + (def.min != null ? ' min="' + def.min + '"' : '') + (def.max != null && def.type === 'number' ? ' max="' + def.max + '"' : '') + (def.type === 'number' ? ' step="any"' : '') + '>' + help + '</label>';
  }

  // ------------------------------------------------------------- rendering
  function render() {
    canvas.innerHTML = '';
    canvas.appendChild(triggerCard());
    canvas.appendChild(stepList(state.steps, 'steps'));
  }

  function triggerCard() {
    const t = catalog.triggers[state.trigger] || {};
    const groups = {};
    Object.entries(catalog.triggers).forEach(([k, v]) => { (groups[v.group] = groups[v.group] || []).push([k, v]); });
    const config = Object.entries(t.config || {}).map(([k, def]) => field(def, state.trigger_config[k] != null ? state.trigger_config[k] : '', 'trigger_config.' + k)).join('');
    const errs = errorsFor('trigger').concat(errorsFor('trigger_config'));
    return el('<section class="wf-card wf-trigger' + (errs.length ? ' wf-has-error' : '') + '"><header><span class="wf-icon">⚡</span><strong>Starts when</strong></header>' +
      '<div class="wf-body"><label class="wf-field"><span>Trigger</span><select data-trigger>' + Object.entries(groups).map(([g, items]) => '<optgroup label="' + esc(g) + '">' + items.map(([k, v]) => '<option value="' + k + '"' + (k === state.trigger ? ' selected' : '') + '>' + esc(v.label) + '</option>').join('') + '</optgroup>').join('') + '</select><small>' + esc(t.help || '') + '</small></label>' + config +
      errs.map((m) => '<p class="wf-error">' + esc(m) + '</p>').join('') + '</div></section>');
  }

  function stepList(list, path) {
    const wrap = el('<div class="wf-list"></div>');
    list.forEach((step, i) => {
      wrap.appendChild(addButton(path, i));
      wrap.appendChild(stepCard(step, path + '.' + i, i, list.length));
    });
    wrap.appendChild(addButton(path, list.length));
    if (!list.length && path === 'steps') wrap.appendChild(el('<p class="oo-muted wf-empty">Add the first step: an action, a wait or a condition.</p>'));
    return wrap;
  }

  function addButton(path, index) {
    return el('<div class="wf-add"><button type="button" class="wf-plus" data-add="' + path + '" data-index="' + index + '" aria-label="Add a step here">+</button></div>');
  }

  function controls(path, i, count) {
    return '<span class="wf-tools">' + (i > 0 ? '<button type="button" data-move="' + path + '" data-dir="-1" aria-label="Move up">↑</button>' : '') + (i < count - 1 ? '<button type="button" data-move="' + path + '" data-dir="1" aria-label="Move down">↓</button>' : '') + '<button type="button" data-remove="' + path + '" aria-label="Remove step">✕</button></span>';
  }

  function stepCard(step, path, i, count) {
    const errs = errorsFor(path).filter((m, idx, all) => all.indexOf(m) === idx);
    const errorHtml = (step.type === 'condition' ? errorsFor(path + '.rules') : errs).map((m) => '<p class="wf-error">' + esc(m) + '</p>').join('');
    let head, body;
    if (step.type === 'wait') {
      head = '<span class="wf-icon">⏳</span><strong>Wait</strong>';
      body = '<div class="wf-row">' + field({ type: 'number', label: 'For', min: 1 }, step.amount, path + '.amount') + field({ type: 'select', label: 'Unit', options: catalog.wait_units }, step.unit, path + '.unit') + '</div>';
    } else if (step.type === 'condition') {
      head = '<span class="wf-icon">⑂</span><strong>If</strong>';
      const rules = (step.rules || []).map((r, ri) => ruleRow(r, path + '.rules.' + ri)).join('');
      body = field({ type: 'select', label: 'Match', options: { all: 'All of these rules', any: 'Any of these rules' } }, step.match || 'all', path + '.match') +
        '<div class="wf-rules">' + rules + '</div><button type="button" class="b-btn" data-add-rule="' + path + '">Add rule</button>';
    } else {
      const action = catalog.actions[step.action] || {};
      head = '<span class="wf-icon">▶</span><strong>' + esc(action.label || 'Action') + '</strong>';
      const groups = {};
      Object.entries(catalog.actions).forEach(([k, v]) => { (groups[v.group] = groups[v.group] || []).push([k, v]); });
      body = '<label class="wf-field"><span>Action</span><select data-action-pick="' + path + '">' + Object.entries(groups).map(([g, items]) => '<optgroup label="' + esc(g) + '">' + items.map(([k, v]) => '<option value="' + k + '"' + (k === step.action ? ' selected' : '') + '>' + esc(v.label) + '</option>').join('') + '</optgroup>').join('') + '</select>' + (action.note ? '<small>' + esc(action.note) + '</small>' : '') + '</label>' +
        Object.entries(action.params || {}).map(([k, def]) => field(def, (step.params || {})[k] != null ? step.params[k] : (def.default != null ? def.default : ''), path + '.params.' + k)).join('');
    }
    const card = el('<section class="wf-card wf-' + step.type + (errs.length ? ' wf-has-error' : '') + '"><header>' + head + controls(path, i, count) + '</header><div class="wf-body">' + body + errorHtml + '</div></section>');
    if (step.type === 'condition') {
      const branches = el('<div class="wf-branches"></div>');
      [['then', 'Yes'], ['else', 'No']].forEach(([key, label]) => {
        const b = el('<div class="wf-branch wf-branch-' + key + '"><p class="wf-branch-label">' + label + '</p></div>');
        b.appendChild(stepList(step[key] || [], path + '.' + key));
        branches.appendChild(b);
      });
      card.appendChild(branches);
    }
    return card;
  }

  function ruleRow(rule, path) {
    const def = catalog.conditions[rule.field] || catalog.conditions[Object.keys(catalog.conditions)[0]];
    const ops = Object.fromEntries((def.ops || []).map((o) => [o, catalog.operators[o] || o]));
    const valueField = def.type === 'select'
      ? field({ type: 'select', label: 'Value', options: def.options }, rule.value, path + '.value')
      : field({ type: def.type === 'number' ? 'number' : (def.type === 'products' || def.type === 'collections' ? def.type : 'text'), label: 'Value', help: def.help }, rule.value, path + '.value');
    return '<div class="wf-rule"><label class="wf-field"><span>Check</span><select data-rule-field="' + path + '">' + Object.entries(catalog.conditions).map(([k, v]) => '<option value="' + k + '"' + (k === rule.field ? ' selected' : '') + '>' + esc(v.label) + '</option>').join('') + '</select></label>' +
      field({ type: 'select', label: 'Is', options: ops }, rule.op, path + '.op') + valueField + '<button type="button" class="wf-x" data-remove-rule="' + path + '" aria-label="Remove rule">✕</button></div>';
  }

  // ------------------------------------------------------------- new steps
  function blank(kind) {
    if (kind === 'wait') return { type: 'wait', amount: 1, unit: 'days' };
    if (kind === 'condition') return { type: 'condition', match: 'all', rules: [{ field: 'order_total', op: 'gte', value: '' }], then: [], else: [] };
    return { type: 'action', action: kind, params: {} };
  }

  function menu(button) {
    document.querySelectorAll('.wf-menu').forEach((m) => m.remove());
    const groups = {};
    Object.entries(catalog.actions).forEach(([k, v]) => { (groups[v.group] = groups[v.group] || []).push([k, v]); });
    const m = el('<div class="wf-menu" role="menu"><p>Flow</p><button type="button" data-kind="wait">⏳ Wait</button><button type="button" data-kind="condition">⑂ If / else</button>' +
      Object.entries(groups).map(([g, items]) => '<p>' + esc(g) + '</p>' + items.map(([k, v]) => '<button type="button" data-kind="' + k + '">' + esc(v.label) + '</button>').join('')).join('') + '</div>');
    m.addEventListener('click', (e) => {
      const kind = e.target.closest('[data-kind]');
      if (!kind) return;
      at(button.dataset.add).splice(Number(button.dataset.index), 0, blank(kind.dataset.kind));
      render();
    });
    button.parentNode.appendChild(m);
  }

  // ------------------------------------------------------------- events
  canvas.addEventListener('input', (e) => {
    const bind = e.target.dataset.bind;
    if (bind) set(bind, e.target.type === 'number' ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value);
  });
  canvas.addEventListener('change', (e) => {
    const t = e.target;
    if (t.matches('[data-trigger]')) { state.trigger = t.value; state.trigger_config = {}; render(); return; }
    if (t.dataset.actionPick) { set(t.dataset.actionPick, { type: 'action', action: t.value, params: {} }); render(); return; }
    if (t.dataset.ruleField) { const def = catalog.conditions[t.value]; set(t.dataset.ruleField, { field: t.value, op: def.ops[0], value: def.type === 'select' ? Object.keys(def.options)[0] : '' }); render(); return; }
    if (t.dataset.bind && t.tagName === 'SELECT' && /\.(match|unit)$/.test(t.dataset.bind) === false) { /* plain value select */ }
  });
  canvas.addEventListener('click', async (e) => {
    const t = e.target.closest('button');
    if (!t) return;
    if (t.dataset.add != null) { menu(t); return; }
    if (t.dataset.remove) { const [list, i] = listAndIndex(t.dataset.remove); list.splice(i, 1); render(); return; }
    if (t.dataset.move) { const [list, i] = listAndIndex(t.dataset.move); const j = i + Number(t.dataset.dir); [list[i], list[j]] = [list[j], list[i]]; render(); return; }
    if (t.dataset.addRule) { at(t.dataset.addRule).rules.push({ field: 'order_total', op: 'gte', value: '' }); render(); return; }
    if (t.dataset.removeRule) { const [list, i] = listAndIndex(t.dataset.removeRule); list.splice(i, 1); render(); return; }
    if (t.dataset.pick) {
      if (!window.shopify || !shopify.resourcePicker) return;
      const current = at(t.dataset.bindPath) || [];
      const picked = await shopify.resourcePicker({ type: t.dataset.pick, multiple: true, selectionIds: (Array.isArray(current) ? current : []).map((c) => ({ id: c.id })) });
      if (picked) { set(t.dataset.bindPath, picked.map((r) => ({ id: r.id, title: r.title }))); render(); }
    }
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.wf-menu, [data-add]')) document.querySelectorAll('.wf-menu').forEach((m) => m.remove()); });
  form.addEventListener('submit', () => { hidden.value = JSON.stringify(state); });

  render();
})();
