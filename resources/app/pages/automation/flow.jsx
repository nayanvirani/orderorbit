// Flow-style workflow editor (automation redesign): the trigger and its steps drawn top to bottom on a
// canvas, conditions split into Then / Otherwise columns, and the chosen step's settings in a side
// panel. It edits the same definition as the classic editor; the server validates it and reports
// errors by path ("steps.1.then.0.params.url").
import { useMemo, useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { ago, Page } from '../../components/ui.jsx';
import SaveBar from '../../components/SaveBar.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { PlanNote, RunStatus } from './_shared.jsx';
import { confirmDialog } from '../../components/Confirm.jsx';

const keyOf = (k) => (/^\d+$/.test(k) ? Number(k) : k);
const getIn = (obj, path) => path.split('.').reduce((o, k) => (o == null ? o : o[keyOf(k)]), obj);
function setIn(obj, path, value) {
  const [head, ...rest] = path.split('.');
  const k = keyOf(head);
  const copy = Array.isArray(obj) ? [...obj] : { ...obj };
  copy[k] = rest.length ? setIn(obj?.[k] ?? (/^\d+$/.test(rest[0]) ? [] : {}), rest.join('.'), value) : value;
  return copy;
}
const parentOf = (path) => {
  const keys = path.split('.');
  const index = Number(keys.pop());
  return [keys.join('.'), index];
};
const grouped = (items) => Object.entries(items).reduce((g, [k, v]) => ({ ...g, [v.group || 'Other']: [...(g[v.group || 'Other'] || []), [k, v]] }), {});

const KIND = {
  trigger: { label: 'Trigger', icon: '⚡' },
  condition: { label: 'Condition', icon: '⑂' },
  wait: { label: 'Wait', icon: '⏳' },
  action: { label: 'Action', icon: '▶' },
};

// What each step does, for the step picker.
const ABOUT = {
  send_email: ['Email the customer.', 'Subject "Thanks for your order {{order_name}}" with a discount code from an earlier step.'],
  notify: ['Email your team and add it to the Team inbox.', '"Big order {{order_name}}: {{order_total}}".'],
  create_task: ['Add a task to the Team inbox with a due date.', '"Call the customer about {{order_name}}", due in 1 day.'],
  add_order_tag: ['Add tags to the order in Shopify.', 'Tag express orders "priority".'],
  remove_order_tag: ['Remove tags from the order in Shopify.', 'Remove "awaiting-payment" once paid.'],
  add_customer_tag: ['Add tags to the customer in Shopify.', 'Tag big spenders "VIP".'],
  remove_customer_tag: ['Remove tags from the customer in Shopify.', 'Remove "at-risk" when they order again.'],
  create_discount: ['Create a unique, single-use discount code. Use {{discount_code}} in a later email.', '15% off, expires after 30 days.'],
  webhook: ['Send the order details as JSON to another tool.', 'Zapier, Klaviyo or Shopify Flow.'],
  trigger_workflow: ['Start another of your workflows with the same order or customer.', 'Hand VIP orders to a "VIP welcome" workflow.'],
  wait: ['Pause, then continue with the next step.', 'Wait 7 days after delivery before asking for a review.'],
  window: ['Pause, then continue only during set hours in your store\'s time zone.', 'Wait 1 day, then send between 09:00 and 20:00.'],
};

const VARIABLES = ['order_name', 'order_total', 'product_titles', 'shop_name', 'discount_code', 'event_name', 'event_label'];

function blank(kind, extra) {
  if (kind === 'wait') return { type: 'wait', amount: 1, unit: 'days' };
  if (kind === 'window') return { type: 'wait', amount: 1, unit: 'days', window: { from: '09:00', to: '20:00' } };
  if (kind === 'condition') return { type: 'condition', match: 'all', rules: [extra], then: [], else: [] };
  return { type: 'action', action: kind, params: {} };
}

function ruleText(catalog, r) {
  const field = catalog.conditions[r.field]?.label || r.field;
  const op = catalog.operators[r.op] || r.op;
  const def = catalog.conditions[r.field] || {};
  const value = Array.isArray(r.value) ? r.value.map((v) => v.title || v.id).join(', ') : def.type === 'select' ? def.options?.[r.value] ?? r.value : r.value;
  return `${field} ${op} ${value === '' || value == null ? '…' : value}`;
}

/** Title and one-line summary for a node on the canvas. */
function summary(catalog, step, workflows) {
  if (step.type === 'wait') {
    return [step.label || `Wait ${step.amount} ${Number(step.amount) === 1 ? step.unit.replace(/s$/, '') : step.unit}`, step.window ? `Then continue between ${step.window.from} and ${step.window.to}` : 'Then continue'];
  }
  if (step.type === 'condition') {
    const rules = (step.rules || []).map((r) => ruleText(catalog, r));
    return [step.label || 'Check the order', rules.join(step.match === 'any' ? ' or ' : ' and ') || 'Add a rule'];
  }
  const action = catalog.actions[step.action] || {};
  const p = step.params || {};
  const line = {
    send_email: p.subject ? `"${p.subject}"` : 'Write the email',
    notify: p.title || 'Write the note',
    create_task: p.title || 'Write the task',
    add_order_tag: p.tags, remove_order_tag: p.tags, add_customer_tag: p.tags, remove_customer_tag: p.tags,
    create_discount: `${p.value ?? 10}${(p.kind || 'percent') === 'percent' ? '%' : ''} off, single use, ${p.expires_days ?? 30} days`,
    webhook: p.url,
    trigger_workflow: workflows.find((w) => w.handle === p.workflow)?.name,
  }[step.action];
  return [step.label || action.label || 'Action', line || 'Fill in the details'];
}

export default function FlowEditor({ workflow, catalog, errors, banner, versions, recentRuns, otherWorkflows, automationOn, live, savedAt, gates, stats }) {
  const { submit } = useRouter();
  const [name, setName] = useState(workflow.name);
  const initial = useMemo(() => ({ trigger: 'order_paid', trigger_config: {}, steps: [], ...workflow.draft }), [workflow.draft]);
  const [state, setState] = useState(initial);
  const [selected, setSelected] = useState(() => Object.keys(errors).find((k) => k.startsWith('steps.'))?.match(/^steps(\.\d+(\.(then|else)\.\d+)*)/)?.[0] || 'trigger');
  const [picker, setPicker] = useState(null);
  const [zoom, setZoom] = useState(1);
  const [busy, setBusy] = useState(null);
  const dirty = name !== workflow.name || JSON.stringify(state) !== JSON.stringify(initial);
  const update = (path, value) => setState((s) => setIn(s, path, value));
  const hasError = (prefix) => Object.keys(errors).some((k) => k === prefix || k.startsWith(`${prefix}.`));
  const errorsFor = (prefix) => [...new Set(Object.keys(errors).filter((k) => k === prefix || k.startsWith(`${prefix}.`)).map((k) => errors[k]))];

  const save = async (action) => {
    setBusy(action);
    await submit(route('app.automation.update', { workflow: workflow.id }), { name, definition: state, action });
    setBusy(null);
  };
  // Discard (save bar): back to the saved draft.
  const discard = () => { setName(workflow.name); setState(initial); };
  const insert = (listPath, index, step) => {
    const list = [...(getIn(state, listPath) || [])];
    list.splice(index, 0, step);
    setState((s) => setIn(s, listPath, list));
    setSelected(`${listPath}.${index}`);
    setPicker(null);
  };
  const remove = (path) => {
    const [listPath, i] = parentOf(path);
    setState((s) => setIn(s, listPath, (getIn(s, listPath) || []).filter((_, j) => j !== i)));
    setSelected('trigger');
  };
  const move = (path, dir) => {
    const [listPath, i] = parentOf(path);
    const list = [...(getIn(state, listPath) || [])];
    if (i + dir < 0 || i + dir >= list.length) return;
    [list[i], list[i + dir]] = [list[i + dir], list[i]];
    setState((s) => setIn(s, listPath, list));
    setSelected(`${listPath}.${i + dir}`);
  };

  const ctx = { catalog, state, update, selected, setSelected, setPicker, hasError, errorsFor, workflows: otherWorkflows, gates };
  const errorCount = Object.keys(errors).length;

  return (
    <Page heading={name || 'Workflow'} back={route('app.automation.index')} backLabel="Workflows">
      <SaveBar dirty={dirty} saving={busy === 'save'} onSave={() => save('save')} onDiscard={discard} />
      <PlanNote automationOn={automationOn} />
      {banner && <s-banner tone={errorCount ? 'warning' : 'info'}>{banner}</s-banner>}

      <div className="fl-shell">
        <header className="fl-top">
          <div className="fl-title">
            <div className="fl-title-row">
              <input className="fl-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={120} aria-label="Workflow name" />
              <span className={`fl-chip ${workflow.status === 'enabled' ? 'on' : ''}`}>{workflow.status === 'enabled' ? 'On' : workflow.status === 'disabled' ? 'Off' : 'Draft'}</span>
              {(workflow.unpublished || dirty) && <span className="fl-chip warn">{dirty ? 'Unsaved changes' : 'Unpublished changes'}</span>}
              {errorCount > 0 && <span className="fl-chip bad">{errorCount} to fix</span>}
            </div>
            <p className="fl-meta">
              {live ? `Version ${live.version} published ${ago(live.published_at)}` : 'Not published yet'} · draft saved {ago(savedAt)}
            </p>
          </div>
          <div className="fl-buttons">
            <s-button onClick={() => setSelected('workflow')}>Settings</s-button>
            <s-button href={appUrl(route('app.automation.runs', {}) + `?workflow=${workflow.id}`)}>Runs</s-button>
            <s-button onClick={() => save('test')} loading={busy === 'test' || undefined}>Test with an order</s-button>
            <s-button onClick={() => save('save')} loading={busy === 'save' || undefined}>Save draft</s-button>
            <s-button variant="primary" onClick={() => save('publish')} loading={busy === 'publish' || undefined}>Publish changes</s-button>
          </div>
        </header>

        <div className="fl-body">
          <section className="fl-canvas" aria-label="Workflow canvas">
            <div className="fl-zoomed" style={{ transform: `scale(${zoom})` }}>
              <Node ctx={ctx} kind="trigger" path="trigger" title={catalog.triggers[state.trigger]?.label || 'Choose a trigger'}
                text={catalog.triggers[state.trigger]?.help || ''} error={hasError('trigger') || hasError('trigger_config')} />
              <Flow ctx={ctx} list={state.steps || []} path="steps" root />
            </div>
            <div className="fl-zoom" role="group" aria-label="Zoom">
              <button type="button" aria-label="Zoom out" onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.1).toFixed(1)))}>−</button>
              <span>{Math.round(zoom * 100)}%</span>
              <button type="button" aria-label="Zoom in" onClick={() => setZoom((z) => Math.min(1.5, +(z + 0.1).toFixed(1)))}>+</button>
            </div>
          </section>

          <aside className="fl-panel" aria-label="Step settings">
            {selected === 'workflow'
              ? <WorkflowPanel workflow={workflow} versions={versions} recentRuns={recentRuns} />
              : selected === 'trigger'
                ? <TriggerPanel ctx={ctx} />
                : getIn(state, selected)
                  ? <StepPanel ctx={ctx} path={selected} onRemove={() => remove(selected)} onMove={(dir) => move(selected, dir)} />
                  : <TriggerPanel ctx={ctx} />}
            {stats && selected !== 'workflow' && (
              <p className="fl-stats">Last 30 days: {stats.runs} {stats.runs === 1 ? 'run' : 'runs'}, {stats.matched} matched a condition, {stats.failed} {stats.failed === 1 ? 'error' : 'errors'}.</p>
            )}
          </aside>
        </div>
      </div>

      {picker && <StepPicker catalog={catalog} gates={gates} onCancel={() => setPicker(null)} onAdd={(step) => insert(picker.path, picker.index, step)} />}
    </Page>
  );
}

function Node({ ctx, kind, path, title, text, error }) {
  const on = ctx.selected === path;
  return (
    <button type="button" className={`fl-node fl-${kind}${on ? ' on' : ''}${error ? ' error' : ''}`} onClick={() => ctx.setSelected(path)} aria-pressed={on}>
      <span className="fl-tile" aria-hidden="true">{KIND[kind].icon}</span>
      <span className="fl-node-text">
        <span className="fl-type">{KIND[kind].label}{error ? ' · needs a fix' : ''}</span>
        <span className="fl-node-title">{title}</span>
        {text && <span className="fl-node-sub">{text}</span>}
      </span>
    </button>
  );
}

function Plus({ ctx, path, index, label }) {
  return <button type="button" className="fl-add" aria-label={label} onClick={() => ctx.setPicker({ path, index })}>+</button>;
}

/** A column of steps; conditions open two columns underneath. */
function Flow({ ctx, list, path, root, emptyText }) {
  if (!list.length && !root) {
    return (
      <>
        <div className="fl-wire" />
        <button type="button" className="fl-empty" onClick={() => ctx.setPicker({ path, index: 0 })}><span aria-hidden="true">+</span>Add a step</button>
        {emptyText && <p className="fl-empty-note">{emptyText}</p>}
      </>
    );
  }
  return (
    <>
      {list.map((step, i) => {
        const p = `${path}.${i}`;
        const [title, text] = summary(ctx.catalog, step, ctx.workflows);
        return (
          <div className="fl-col" key={p}>
            <div className="fl-wire" />
            <Plus ctx={ctx} path={path} index={i} label={`Add a step before ${title}`} />
            <div className="fl-wire" />
            <Node ctx={ctx} kind={step.type} path={p} title={title} text={text} error={ctx.hasError(p)} />
            {step.type === 'condition' && (
              <>
                <div className="fl-wire" />
                <div className="fl-branches">
                  <div className="fl-split" />
                  <div className="fl-col">
                    <span className="fl-branch then">Then</span>
                    <Flow ctx={ctx} list={step.then || []} path={`${p}.then`} />
                  </div>
                  <div className="fl-col">
                    <span className="fl-branch else">Otherwise</span>
                    <Flow ctx={ctx} list={step.else || []} path={`${p}.else`} emptyText="Nothing happens for the others." />
                  </div>
                  {/* The two branches meet again, also after the last step, where the + adds a step for everyone. */}
                  <div className="fl-join" />
                </div>
              </>
            )}
          </div>
        );
      })}
      <div className="fl-wire" />
      <Plus ctx={ctx} path={path} index={list.length} label={list[list.length - 1]?.type === 'condition' ? 'Add a step after both branches' : 'Add a step at the end'} />
      {root && !list.length && <p className="fl-empty-note">Add the first step: an action, a condition or a wait.</p>}
      {list[list.length - 1]?.type === 'condition' && <p className="fl-empty-note">Then and Otherwise meet here: a step added here runs for everyone.</p>}
    </>
  );
}

function Panel({ kind, title, children, tools }) {
  return (
    <>
      <div className="fl-panel-head">
        <div><p className={`fl-type fl-${kind}`}>{KIND[kind]?.label || 'Workflow'}</p><h2>{title}</h2></div>
        {tools}
      </div>
      {children}
    </>
  );
}

function Errors({ list }) {
  return list.map((m) => <p className="fl-error" key={m} role="alert">{m}</p>);
}

function Field({ ctx, def, path }) {
  const raw = getIn(ctx.state, path);
  const value = raw ?? (def.default ?? '');
  const set = (v) => ctx.update(path, v);
  const label = <span>{def.label}{def.required ? ' *' : ''}</span>;
  const help = def.help ? <small>{def.help}</small> : null;

  if (def.type === 'select') {
    return <label className="fl-field">{label}<select value={String(value)} onChange={(e) => set(e.target.value)}>{Object.entries(def.options || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>{help}</label>;
  }
  if (def.type === 'textarea') {
    return <label className="fl-field">{label}<textarea rows={7} value={value} onChange={(e) => set(e.target.value)} data-path={path} />{help}</label>;
  }
  if (def.type === 'documents') {
    // Product documents (G42): tick which this email sends; none ticked = every matching one.
    const ids = Array.isArray(raw) ? raw.map(Number) : [];
    const list = def.options || [];
    const toggle = (id, on) => set(on ? [...ids, id] : ids.filter((x) => x !== id));
    return (
      <div className="fl-field">
        {label}
        {list.length ? list.map((d) => (
          <label className="fl-doc" key={d.id}>
            <input type="checkbox" checked={ids.includes(d.id)} onChange={(e) => toggle(d.id, e.target.checked)} />
            <span>{d.name} <small className="oo-muted">· {d.kind}{d.active ? '' : ' · off'}</small></span>
          </label>
        )) : <span className="oo-muted">No documents yet. Add them in Automation › Product documents.</span>}
        {list.length > 0 && <small>{ids.length ? `Sends ${ids.length} chosen ${ids.length === 1 ? 'document' : 'documents'}, each for the products it's set up for.` : 'None ticked: sends every document that matches the products in the order.'}</small>}
        {help}
      </div>
    );
  }
  if (def.type === 'products' || def.type === 'collections') {
    const items = Array.isArray(raw) ? raw : [];
    const pick = async () => {
      if (!window.shopify?.resourcePicker) return;
      const picked = await window.shopify.resourcePicker({ type: def.type === 'collections' ? 'collection' : 'product', multiple: true, selectionIds: items.map((c) => ({ id: c.id })) });
      if (picked) set(picked.map((r) => ({ id: r.id, title: r.title })));
    };
    return (
      <div className="fl-field">
        {label}
        <div className="fl-picked">{items.length ? items.map((p) => <span className="fl-chip" key={p.id}>{p.title || p.id}</span>) : <span className="oo-muted">None chosen</span>}</div>
        <s-button onClick={pick}>Choose {def.type}</s-button>
        {help}
      </div>
    );
  }
  if (def.type === 'workflow') {
    return <label className="fl-field">{label}<select value={value} onChange={(e) => set(e.target.value)}><option value="">Choose…</option>{ctx.workflows.map((w) => <option key={w.handle} value={w.handle}>{w.name}</option>)}</select>{help}</label>;
  }
  const number = def.type === 'number';
  return (
    <label className="fl-field">
      {label}
      <input type={number ? 'number' : 'text'} value={value} min={def.min ?? undefined} max={number ? def.max ?? undefined : undefined} step={number ? 'any' : undefined}
        onChange={(e) => set(number ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value)} />
      {help}
    </label>
  );
}

function TriggerPanel({ ctx }) {
  const { catalog, state } = ctx;
  const t = catalog.triggers[state.trigger] || {};
  return (
    <Panel kind="trigger" title={t.label || 'Trigger'}>
      <label className="fl-field">
        <span>Start this workflow when</span>
        <select value={state.trigger} onChange={(e) => { ctx.update('trigger', e.target.value); ctx.update('trigger_config', {}); }}>
          {Object.entries(grouped(catalog.triggers)).map(([g, items]) => <optgroup key={g} label={g}>{items.map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</optgroup>)}
        </select>
        {t.help && <small>{t.help}</small>}
      </label>
      {Object.entries(t.config || {}).map(([k, def]) => <Field key={k} ctx={ctx} def={def} path={`trigger_config.${k}`} />)}
      <p className="fl-note">Each order or customer starts this workflow once, even if Shopify sends the update again.</p>
      <Errors list={[...ctx.errorsFor('trigger'), ...ctx.errorsFor('trigger_config')]} />
    </Panel>
  );
}

function StepPanel({ ctx, path, onRemove, onMove }) {
  const { catalog } = ctx;
  const step = getIn(ctx.state, path);
  const [title] = summary(catalog, step, ctx.workflows);
  const [listPath, i] = parentOf(path);
  const count = (getIn(ctx.state, listPath) || []).length;
  const tools = (
    <div className="fl-panel-tools">
      {i > 0 && <button type="button" aria-label="Move up" title="Move up" onClick={() => onMove(-1)}>↑</button>}
      {i < count - 1 && <button type="button" aria-label="Move down" title="Move down" onClick={() => onMove(1)}>↓</button>}
      <s-button tone="critical" onClick={async () => (step.type !== 'condition' || (!(step.then || []).length && !(step.else || []).length) || (await confirmDialog('Delete this condition and every step in its Then and Otherwise branches?', { title: 'Delete condition', action: 'Delete' }))) && onRemove()}>Delete</s-button>
    </div>
  );

  let body;
  if (step.type === 'wait') {
    const windowOn = !!step.window;
    body = (
      <>
        <div className="fl-two">
          <Field ctx={ctx} def={{ type: 'number', label: 'Wait', min: 1 }} path={`${path}.amount`} />
          <Field ctx={ctx} def={{ type: 'select', label: 'Unit', options: catalog.wait_units }} path={`${path}.unit`} />
        </div>
        <label className="fl-check">
          <input type="checkbox" checked={windowOn} onChange={(e) => ctx.update(`${path}.window`, e.target.checked ? { from: '09:00', to: '20:00' } : undefined)} />
          Only continue between set hours
        </label>
        {windowOn && (
          <div className="fl-two">
            <label className="fl-field"><span>From</span><input type="time" value={step.window.from} onChange={(e) => ctx.update(`${path}.window.from`, e.target.value)} /></label>
            <label className="fl-field"><span>To</span><input type="time" value={step.window.to} onChange={(e) => ctx.update(`${path}.window.to`, e.target.value)} /></label>
          </div>
        )}
        {windowOn && <p className="fl-note">In your store's time zone. If the wait ends outside these hours, the next step runs when they start.</p>}
      </>
    );
  } else if (step.type === 'condition') {
    body = (
      <>
        {!ctx.gates.branching && <s-banner tone="warning">Conditions need a plan with workflow branching. You can build and test it now; publishing needs an upgrade.</s-banner>}
        <div className="fl-segment" role="group" aria-label="Match">
          {[['all', 'All rules (AND)'], ['any', 'Any rule (OR)']].map(([k, l]) => (
            <button type="button" key={k} aria-pressed={(step.match || 'all') === k} onClick={() => ctx.update(`${path}.match`, k)}>{l}</button>
          ))}
        </div>
        {(step.rules || []).map((r, ri) => <Rule key={ri} ctx={ctx} rule={r} path={`${path}.rules.${ri}`} />)}
        <s-button onClick={() => ctx.update(`${path}.rules`, [...(step.rules || []), { field: 'order_total', op: 'gte', value: '' }])}>Add rule</s-button>
        <p className="fl-note">Orders that match go down <b>Then</b>; the rest go down <b>Otherwise</b>.</p>
      </>
    );
  } else {
    const action = catalog.actions[step.action] || {};
    body = (
      <>
        <label className="fl-field">
          <span>Action</span>
          <select value={step.action} onChange={(e) => ctx.update(path, { type: 'action', action: e.target.value, params: {}, ...(step.label ? { label: step.label } : {}) })}>
            {Object.entries(grouped(catalog.actions)).map(([g, items]) => <optgroup key={g} label={g}>{items.map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</optgroup>)}
          </select>
        </label>
        {step.action === 'webhook' && !ctx.gates.webhooks && <s-banner tone="warning">Webhooks need a plan that includes them. You can build and test now; publishing needs an upgrade.</s-banner>}
        {Object.entries(action.params || {}).filter(([, def]) => !def.show_with || ![undefined, '', 'none'].includes(step.params?.[def.show_with])).map(([k, def]) => <Field key={k} ctx={ctx} def={def} path={`${path}.params.${k}`} />)}
        {['send_email', 'notify', 'create_task'].includes(step.action) && <Variables ctx={ctx} path={`${path}.params.body`} />}
        {action.note && <p className="fl-note">{action.note}</p>}
      </>
    );
  }

  return (
    <Panel kind={step.type} title={title} tools={tools}>
      <label className="fl-field">
        <span>Step name (optional)</span>
        <input value={step.label || ''} maxLength={80} placeholder={step.type === 'condition' ? 'e.g. Is this a big spender?' : 'Shown on the canvas'} onChange={(e) => ctx.update(`${path}.label`, e.target.value || undefined)} />
      </label>
      {body}
      <Errors list={ctx.errorsFor(path)} />
    </Panel>
  );
}

function Variables({ ctx, path }) {
  const add = (v) => ctx.update(path, `${getIn(ctx.state, path) || ''}{{${v}}}`);
  return (
    <div className="fl-vars">
      <span>Insert:</span>
      {VARIABLES.map((v) => <button type="button" key={v} onClick={() => add(v)}>{`{{${v}}}`}</button>)}
    </div>
  );
}

function Rule({ ctx, rule, path }) {
  const { catalog } = ctx;
  const def = catalog.conditions[rule.field] || catalog.conditions[Object.keys(catalog.conditions)[0]];
  const ops = Object.fromEntries((def.ops || []).map((o) => [o, catalog.operators[o] || o]));
  const [listPath, i] = parentOf(path);
  const valueType = def.type === 'number' ? 'number' : def.type === 'products' || def.type === 'collections' ? def.type : 'text';
  const changeField = (field) => {
    const d = catalog.conditions[field];
    ctx.update(path, { field, op: d.ops[0], value: d.type === 'select' ? Object.keys(d.options)[0] : '' });
  };
  const rules = getIn(ctx.state, listPath) || [];
  return (
    <div className="fl-rule">
      <div className="fl-rule-head">
        <select value={rule.field} onChange={(e) => changeField(e.target.value)} aria-label="Field">
          {Object.entries(catalog.conditions).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
        </select>
        {rules.length > 1 && <button type="button" className="fl-x" aria-label="Remove rule" onClick={() => ctx.update(listPath, rules.filter((_, j) => j !== i))}>✕</button>}
      </div>
      <div className="fl-two">
        <Field ctx={ctx} def={{ type: 'select', label: 'Is', options: ops }} path={`${path}.op`} />
        {def.type === 'select'
          ? <Field ctx={ctx} def={{ type: 'select', label: 'Value', options: def.options }} path={`${path}.value`} />
          : valueType === 'products' || valueType === 'collections' ? null : <Field ctx={ctx} def={{ type: valueType, label: 'Value' }} path={`${path}.value`} />}
      </div>
      {(valueType === 'products' || valueType === 'collections') && <Field ctx={ctx} def={{ type: valueType, label: 'Value' }} path={`${path}.value`} />}
      {def.help && <p className="fl-note">{def.help}</p>}
    </div>
  );
}

function WorkflowPanel({ workflow, versions, recentRuns }) {
  return (
    <Panel kind="workflow" title="Workflow settings">
      {workflow.published && (
        <div className="fl-group">
          <p>{workflow.status === 'enabled' ? 'On: runs for new orders and customers.' : 'Off: new triggers are ignored. Runs already going finish.'}</p>
          <ActionButton url={route('app.automation.toggle', { workflow: workflow.id })} confirm={workflow.status === 'enabled' ? 'Turn this workflow off? New orders won\'t start it until you turn it on again.' : undefined}>
            {workflow.status === 'enabled' ? 'Turn off' : 'Turn on'}
          </ActionButton>
        </div>
      )}
      <div className="fl-group">
        <h3>Recent runs</h3>
        {!recentRuns.length ? <p className="oo-muted">No runs yet. Try "Test with an order".</p> : recentRuns.map((r) => (
          <p key={r.id}><s-link href={appUrl(route('app.automation.runs.show', { run: r.id }))}>#{r.id}</s-link> {r.subject} · <RunStatus status={r.status} />{r.test && <> <s-badge>Test</s-badge></>}</p>
        ))}
      </div>
      <div className="fl-group">
        <h3>Versions</h3>
        {!versions.length ? <p className="oo-muted">Not published yet.</p> : versions.map((v) => (
          <div className="fl-version" key={v.id}>
            <span>Version {v.version} · {ago(v.published_at)}{v.live && <> <s-badge tone="success">Live</s-badge></>}</span>
            <ActionButton variant="tertiary" url={route('app.automation.restore', { workflow: workflow.id, version: v.id })} confirm={`Load version ${v.version} into the draft? Your unpublished changes are replaced.`}>Load into draft</ActionButton>
          </div>
        ))}
      </div>
      <p className="fl-note">Publishing creates a new version and turns the workflow on. Runs already going finish on the version they started with.</p>
      <ActionButton tone="critical" variant="tertiary" url={route('app.automation.destroy', { workflow: workflow.id })} confirm="Delete this workflow and its run history? This can't be undone.">Delete workflow</ActionButton>
    </Panel>
  );
}

/** "Add a step": Actions / Conditions / Waits, a search, the list, and what the chosen step does. */
function StepPicker({ catalog, gates, onAdd, onCancel }) {
  const [tab, setTab] = useState('actions');
  const [query, setQuery] = useState('');
  const [pick, setPick] = useState(null);
  const items = {
    actions: Object.entries(catalog.actions).map(([k, v]) => ({ key: k, label: v.label, group: v.group, about: ABOUT[k], make: () => blank(k), locked: k === 'webhook' && !gates.webhooks })),
    conditions: Object.entries(catalog.conditions).map(([k, v]) => ({
      key: k, label: v.label, group: /event/.test(k) ? 'Growvia' : /customer|segment|ltv|previous|ordered_again/.test(k) ? 'Customer' : 'Order',
      about: [`Check ${v.label.toLowerCase()} and split the workflow into Then and Otherwise.`, v.help || null], locked: !gates.branching,
      make: () => blank('condition', { field: k, op: v.ops[0], value: v.type === 'select' ? Object.keys(v.options)[0] : '' }),
    })),
    waits: [
      { key: 'wait', label: 'Wait for a time', group: 'Waits', about: ABOUT.wait, make: () => blank('wait') },
      { key: 'window', label: 'Wait, then continue during set hours', group: 'Waits', about: ABOUT.window, make: () => blank('window') },
    ],
  }[tab].filter((i) => !query || i.label.toLowerCase().includes(query.toLowerCase()));
  const chosen = items.find((i) => i.key === pick) || items[0];
  const groups = items.reduce((g, i) => ({ ...g, [i.group]: [...(g[i.group] || []), i] }), {});

  return (
    <div className="fl-modal" role="dialog" aria-modal="true" aria-label="Add a step" onClick={(e) => e.target === e.currentTarget && onCancel()}>
      <div className="fl-dialog">
        <header className="fl-dialog-head">
          <h2>Add a step</h2>
          <button type="button" className="fl-x" aria-label="Close" onClick={onCancel}>✕</button>
        </header>
        <div className="fl-tabs" role="tablist">
          {[['actions', 'Actions'], ['conditions', 'Conditions'], ['waits', 'Waits']].map(([k, l]) => (
            <button type="button" role="tab" key={k} aria-selected={tab === k} onClick={() => { setTab(k); setPick(null); }}>{l}</button>
          ))}
        </div>
        <input className="fl-search" type="search" placeholder="Search steps" value={query} onChange={(e) => setQuery(e.target.value)} aria-label="Search steps" autoFocus />
        <div className="fl-pick">
          <div className="fl-pick-list">
            {!items.length && <p className="oo-muted">Nothing matches "{query}".</p>}
            {Object.entries(groups).map(([g, list]) => (
              <div key={g}>
                <p className="fl-pick-group">{g}</p>
                {list.map((i) => (
                  <button type="button" key={i.key} className={chosen?.key === i.key ? 'on' : ''} onClick={() => setPick(i.key)} onDoubleClick={() => onAdd(i.make())}>
                    {i.label}{i.locked && <span className="fl-lock">Upgrade</span>}
                  </button>
                ))}
              </div>
            ))}
          </div>
          {chosen && (
            <div className="fl-pick-about">
              <h3>{chosen.label}</h3>
              <p>{chosen.about?.[0]}</p>
              {chosen.about?.[1] && <p className="fl-note">Example: {chosen.about[1]}</p>}
              {chosen.locked && <p className="fl-note">Not on your plan yet: you can build and test it, and publishing needs an upgrade.</p>}
            </div>
          )}
        </div>
        <footer className="fl-dialog-foot">
          <s-button onClick={onCancel}>Cancel</s-button>
          <s-button variant="primary" disabled={!chosen || undefined} onClick={() => chosen && onAdd(chosen.make())}>Add step</s-button>
        </footer>
      </div>
    </div>
  );
}
