/* Website content editor: turns a page's copy (nested groups, lists and text) into form fields.
   Lists can be added to, reordered and trimmed; the whole copy is sent back as JSON. */
(() => {
    const cfg = JSON.parse(document.getElementById('content-config').textContent);
    const root = document.getElementById('content-editor');
    const form = document.getElementById('content-form');
    const dirty = document.getElementById('content-dirty');
    let model = structuredClone(cfg.values);

    const isScalar = (v) => v === null || ['string', 'number', 'boolean'].includes(typeof v);
    const isObject = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
    const kind = (v) => (Array.isArray(v) ? 'list' : isObject(v) ? 'group' : 'text');
    const words = (k) => String(k).replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
    const human = (k) => cfg.labels[k] || words(k);
    const groupName = (k) => cfg.groups[k] || cfg.labels[k] || words(k);
    // A "row" is a fixed set of parts ([question, answer], [title, text], [type, content…]): an array
    // inside a list whose parts are all text, or whose parts mix text with lists.
    const isRow = (arr, inList) => Array.isArray(arr) && arr.length > 0
        && ((inList && arr.every(isScalar)) || (new Set(arr.map(kind)).size > 1));

    // A new, empty item shaped like the list's first item.
    const blank = (tpl) => {
        if (Array.isArray(tpl)) return tpl.map((x) => (Array.isArray(x) ? (x.length ? [blank(x[0])] : []) : blank(x)));
        if (isObject(tpl)) return Object.fromEntries(Object.entries(tpl).map(([k, v]) => [k, blank(v)]));
        return typeof tpl === 'number' ? 0 : typeof tpl === 'boolean' ? false : '';
    };

    const el = (tag, attrs = {}, ...kids) => {
        const n = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => (k === 'class' ? (n.className = v) : k.startsWith('on') ? n.addEventListener(k.slice(2), v) : n.setAttribute(k, v)));
        kids.flat().forEach((c) => c != null && n.append(c));
        return n;
    };
    const changed = () => { dirty.hidden = false; };

    function textField(label, value, set, key) {
        const long = typeof value === 'string' && (value.length > 70 || /text|lead|hero|answer|description|excerpt|summary|paragraph|content|note|message|body/i.test(String(label) + key));
        let input;
        if (typeof value === 'boolean') {
            input = el('input', { type: 'checkbox' });
            input.checked = value;
            input.addEventListener('change', () => { set(input.checked); changed(); });
        } else if (typeof value === 'number') {
            input = el('input', { type: 'number', step: 'any' });
            input.value = value;
            input.addEventListener('input', () => { set(Number(input.value)); changed(); });
        } else {
            input = long ? el('textarea', { rows: Math.min(8, Math.max(2, Math.ceil(String(value ?? '').length / 90))) }) : el('input', { type: 'text' });
            input.value = value ?? '';
            input.addEventListener('input', () => { set(input.value); changed(); });
        }
        return label === null ? input : el('label', { class: 'ce-field' }, el('span', {}, String(label)), input);
    }

    // Renders value at its place in the model; set(v) writes it back.
    function render(value, set, key, def, ctx = {}) {
        if (Array.isArray(value) && isRow(value, ctx.inList)) return renderRow(value, set, key, def, ctx);
        if (Array.isArray(value)) return renderList(value, set, key, def, ctx);
        if (isObject(value)) return renderGroup(value, set, key, def, ctx.depth || 0, ctx.inList);
        return textField(ctx.label === undefined ? human(key) : ctx.label, value, set, String(key));
    }

    function renderGroup(obj, set, key, def, depth, inList = false) {
        const keys = Object.keys(def && isObject(def) ? def : obj).filter((k) => !cfg.hidden.includes(k) && k in obj);
        const kids = keys.map((k) => render(obj[k], (v) => { obj[k] = v; }, k, def ? def[k] : undefined, { depth: depth + 1, parentKey: key }));
        if (depth === 0) return el('div', { class: 'ce' }, kids);
        if (depth === 1 && keys.some((k) => !isScalar(obj[k]))) {
            return el('details', { class: 'ce-group', open: '' }, el('summary', {}, groupName(key)), el('div', { class: 'ce-body' }, kids));
        }
        return el('div', { class: 'ce-sub' }, inList ? null : el('div', { class: 'ce-sub-title' }, groupName(key)), kids);
    }

    function renderRow(row, set, key, def, ctx) {
        const names = cfg.rows[ctx.rowsKey ?? key] || cfg.rows[ctx.parentKey] || [];
        return el('div', { class: 'ce-sub' }, row.map((part, i) => render(part, (v) => { row[i] = v; }, i, def ? def[i] : undefined, { label: names[i] || `Part ${i + 1}`, rowsKey: names[i], depth: 2 })));
    }

    function renderList(list, set, key, def, ctx = {}) {
        const wrap = el('div', { class: 'ce-list' });
        const rowsKey = ctx.rowsKey ?? key;
        const tpl = (def && def[0] !== undefined) ? def[0] : list[0];
        const draw = () => {
            wrap.replaceChildren();
            list.forEach((item, i) => {
                const move = (d) => { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; changed(); draw(); };
                const tools = el('span', { class: 'ce-tools' },
                    el('button', { type: 'button', class: 'ad-btn small', title: 'Move up', onclick: () => move(-1) }, '↑'),
                    el('button', { type: 'button', class: 'ad-btn small', title: 'Move down', onclick: () => move(1) }, '↓'),
                    el('button', { type: 'button', class: 'ad-btn small danger', title: 'Remove', onclick: () => { list.splice(i, 1); changed(); draw(); } }, '✕'));
                if (isScalar(item)) {
                    wrap.append(el('div', { class: 'ce-row' }, textField(null, item, (v) => { list[i] = v; }, String(key)), tools));
                } else {
                    const title = Array.isArray(item) ? (typeof item[0] === 'string' && item[0] ? item[0].slice(0, 60) : `Item ${i + 1}`)
                        : (item.title || item.label || item.name || item.question || `Item ${i + 1}`);
                    wrap.append(el('div', { class: 'ce-item' }, el('div', { class: 'ce-item-head' }, el('b', {}, `${i + 1}. ${String(title).replace(/\*/g, '')}`), tools),
                        render(item, (v) => { list[i] = v; }, key, def ? def[i] ?? tpl : tpl, { inList: true, depth: 2, rowsKey })));
                }
            });
            if (tpl !== undefined) {
                wrap.append(el('button', { type: 'button', class: 'ad-btn small ce-add', onclick: () => { list.push(blank(tpl)); changed(); draw(); } }, '+ Add'));
            }
        };
        draw();
        return el('div', { class: 'ce-field' }, el('span', {}, ctx.label ?? groupName(key)), wrap);
    }

    root.replaceChildren(render(model, (v) => { model = v; }, '', cfg.defaults, { depth: 0 }));
    form.addEventListener('submit', () => { document.getElementById('content-data').value = JSON.stringify(model); });
    addEventListener('beforeunload', (e) => { if (!dirty.hidden && !form.dataset.sending) e.preventDefault(); });
    form.addEventListener('submit', () => { form.dataset.sending = '1'; });
})();
