// Workflow builder: a vertical canvas of the trigger and its steps (actions, waits and if/else
// conditions with nested branches). The server validates the definition and reports errors by
// path ("steps.1.then.0.params.url").
import { createContext, useContext, useEffect, useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { ago, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter } from '../../router.jsx';
import { PlanNote, RunStatus, WorkflowStatus } from './_shared.jsx';

const Ctx = createContext(null);

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
const grouped = (items) => Object.entries(items).reduce((g, [k, v]) => ({ ...g, [v.group]: [...(g[v.group] || []), [k, v]] }), {});

function blank(kind) {
  if (kind === 'wait') return { type: 'wait', amount: 1, unit: 'days' };
  if (kind === 'condition') return { type: 'condition', match: 'all', rules: [{ field: 'order_total', op: 'gte', value: '' }], then: [], else: [] };
  return { type: 'action', action: kind, params: {} };
}

export default function WorkflowEditor({ workflow, catalog, errors, banner, versions, recentRuns, otherWorkflows, automationOn }) {
  const { submit } = useRouter();
  const [name, setName] = useState(workflow.name);
  const [state, setState] = useState(() => ({ trigger: 'order_paid', trigger_config: {}, steps: [], ...workflow.draft }));
  const [busy, setBusy] = useState(null);
  const [menu, setMenu] = useState(null);
  const update = (path, value) => setState((s) => setIn(s, path, value));
  const errorsFor = (prefix) => [...new Set(Object.keys(errors).filter((k) => k === prefix || k.startsWith(`${prefix}.`)).map((k) => errors[k]))];

  useEffect(() => {
    const close = (e) => { if (!e.target.closest?.('.wf-menu, .wf-plus')) setMenu(null); };
    document.addEventListener('click', close);
    return () => document.removeEventListener('click', close);
  }, []);

  const save = async (action) => {
    setBusy(action);
    await submit(route('app.automation.update', { workflow: workflow.id }), { name, definition: state, action });
    setBusy(null);
  };

  const ctx = { catalog, state, update, setState, errorsFor, menu, setMenu, workflows: otherWorkflows };

  return (
    <Page heading={workflow.name} back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      {banner && <s-banner tone={Object.keys(errors).length ? 'warning' : 'info'}>{banner}</s-banner>}

      <div className="wf-layout">
        <div className="wf-main">
          <div className="wf-head">
            <label className="wf-name"><span>Workflow name</span><input value={name} onChange={(e) => setName(e.target.value)} maxLength={120} /></label>
            <div className="wf-badges">
              <WorkflowStatus status={workflow.status} />
              {workflow.unpublished && <s-badge tone="warning">Unpublished changes</s-badge>}
            </div>
          </div>

          <Ctx.Provider value={ctx}>
            <div className="wf-canvas">
              <TriggerCard />
              <StepList list={state.steps || []} path="steps" />
            </div>
          </Ctx.Provider>

          <div className="wf-actions">
            <s-button onClick={() => save('save')} loading={busy === 'save' || undefined}>Save draft</s-button>
            <s-button onClick={() => save('test')} loading={busy === 'test' || undefined}>Test with a sample order</s-button>
            <s-button variant="primary" onClick={() => save('publish')} loading={busy === 'publish' || undefined}>Publish</s-button>
          </div>
          <p className="oo-muted wf-foot">Publishing creates a new version and enables the workflow. Runs already in progress finish on the version they started with.</p>
        </div>

        <aside className="wf-side">
          {workflow.published && (
            <s-section heading="Status">
              <s-paragraph>{workflow.status === 'enabled' ? 'Running on new triggers.' : 'Paused: new triggers are ignored. Runs in progress continue.'}</s-paragraph>
              <ActionButton url={route('app.automation.toggle', { workflow: workflow.id })} confirm={workflow.status === 'enabled' ? 'Disable this workflow? New orders won\'t start it until you enable it again.' : undefined}>
                {workflow.status === 'enabled' ? 'Disable' : 'Enable'}
              </ActionButton>
            </s-section>
          )}
          <s-section heading="Recent runs">
            {!recentRuns.length ? <s-paragraph><span className="oo-muted">No runs yet. Try "Test with a sample order".</span></s-paragraph> : recentRuns.map((r) => (
              <s-paragraph key={r.id}>
                <s-link href={appUrl(route('app.automation.runs.show', { run: r.id }))}>#{r.id}</s-link> {r.subject} · <RunStatus status={r.status} />{r.test && <> <s-badge>Test</s-badge></>}
              </s-paragraph>
            ))}
          </s-section>
          <s-section heading="Versions">
            {!versions.length ? <s-paragraph><span className="oo-muted">Not published yet.</span></s-paragraph> : versions.map((v) => (
              <div className="wf-version" key={v.id}>
                <span>Version {v.version} · {ago(v.published_at)}{v.live && <> <s-badge tone="success">Live</s-badge></>}</span>
                <ActionButton variant="tertiary" url={route('app.automation.restore', { workflow: workflow.id, version: v.id })} confirm={`Load version ${v.version} into the draft? Your unpublished changes are replaced.`}>Load into draft</ActionButton>
              </div>
            ))}
          </s-section>
          <s-section>
            <ActionButton tone="critical" variant="tertiary" url={route('app.automation.destroy', { workflow: workflow.id })} confirm="Delete this workflow and its run history? This can't be undone.">Delete workflow</ActionButton>
          </s-section>
        </aside>
      </div>
    </Page>
  );
}

function Field({ def, path, wide }) {
  const { state, update, workflows } = useContext(Ctx);
  const raw = getIn(state, path);
  const value = raw ?? (def.default ?? '');
  const label = <span>{def.label}{def.required ? ' *' : ''}</span>;
  const help = def.help ? <small>{def.help}</small> : null;
  const set = (v) => update(path, v);

  if (def.type === 'select') {
    return <label className="wf-field">{label}<select value={String(value)} onChange={(e) => set(e.target.value)}>{Object.entries(def.options || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>{help}</label>;
  }
  if (def.type === 'textarea') {
    return <label className="wf-field wf-wide">{label}<textarea rows={6} value={value} onChange={(e) => set(e.target.value)} />{help}</label>;
  }
  if (def.type === 'products' || def.type === 'collections') {
    const items = Array.isArray(raw) ? raw : [];
    const type = def.type === 'collections' ? 'collection' : 'product';
    const pick = async () => {
      if (!window.shopify?.resourcePicker) return;
      const picked = await window.shopify.resourcePicker({ type, multiple: true, selectionIds: items.map((c) => ({ id: c.id })) });
      if (picked) set(picked.map((r) => ({ id: r.id, title: r.title })));
    };
    return (
      <div className="wf-field wf-wide">
        {label}
        <div className="wf-picked">{items.length ? items.map((p) => <span className="wf-chip" key={p.id}>{p.title || p.id}</span>) : <span className="oo-muted">None chosen</span>}</div>
        <button type="button" className="b-btn" onClick={pick}>Choose {def.type}</button>
        {help}
      </div>
    );
  }
  if (def.type === 'workflow') {
    return <label className="wf-field">{label}<select value={value} onChange={(e) => set(e.target.value)}><option value="">Choose…</option>{workflows.map((w) => <option key={w.handle} value={w.handle}>{w.name}</option>)}</select>{help}</label>;
  }
  const number = def.type === 'number';
  return (
    <label className={`wf-field${wide ? ' wf-wide' : ''}`}>
      {label}
      <input type={number ? 'number' : 'text'} value={value} min={def.min ?? undefined} max={number ? def.max ?? undefined : undefined} step={number ? 'any' : undefined}
        onChange={(e) => set(number ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value)} />
      {help}
    </label>
  );
}

function Errors({ list }) {
  return list.map((m) => <p className="wf-error" key={m}>{m}</p>);
}

function TriggerCard() {
  const { catalog, state, setState, errorsFor } = useContext(Ctx);
  const t = catalog.triggers[state.trigger] || {};
  const errs = [...errorsFor('trigger'), ...errorsFor('trigger_config')];
  return (
    <section className={`wf-card wf-trigger${errs.length ? ' wf-has-error' : ''}`}>
      <header><span className="wf-icon">⚡</span><strong>Starts when</strong></header>
      <div className="wf-body">
        <label className="wf-field">
          <span>Trigger</span>
          <select value={state.trigger} onChange={(e) => setState((s) => ({ ...s, trigger: e.target.value, trigger_config: {} }))}>
            {Object.entries(grouped(catalog.triggers)).map(([g, items]) => <optgroup key={g} label={g}>{items.map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</optgroup>)}
          </select>
          <small>{t.help || ''}</small>
        </label>
        {Object.entries(t.config || {}).map(([k, def]) => <Field key={k} def={def} path={`trigger_config.${k}`} />)}
        <Errors list={errs} />
      </div>
    </section>
  );
}

function StepList({ list, path }) {
  return (
    <div className="wf-list">
      {list.map((step, i) => [
        <AddButton key={`a${i}`} path={path} index={i} />,
        <StepCard key={`s${i}`} step={step} path={`${path}.${i}`} index={i} count={list.length} />,
      ])}
      <AddButton path={path} index={list.length} />
      {!list.length && path === 'steps' && <p className="oo-muted wf-empty">Add the first step: an action, a wait or a condition.</p>}
    </div>
  );
}

function AddButton({ path, index }) {
  const { catalog, state, setState, menu, setMenu } = useContext(Ctx);
  const id = `${path}:${index}`;
  const add = (kind) => {
    const list = [...(getIn(state, path) || [])];
    list.splice(index, 0, blank(kind));
    setState((s) => setIn(s, path, list));
    setMenu(null);
  };
  return (
    <div className="wf-add">
      <button type="button" className="wf-plus" aria-label="Add a step here" onClick={() => setMenu(menu === id ? null : id)}>+</button>
      {menu === id && (
        <div className="wf-menu" role="menu">
          <p>Flow</p>
          <button type="button" onClick={() => add('wait')}>⏳ Wait</button>
          <button type="button" onClick={() => add('condition')}>⑂ If / else</button>
          {Object.entries(grouped(catalog.actions)).map(([g, items]) => [
            <p key={g}>{g}</p>,
            ...items.map(([k, v]) => <button type="button" key={k} onClick={() => add(k)}>{v.label}</button>),
          ])}
        </div>
      )}
    </div>
  );
}

function StepCard({ step, path, index, count }) {
  const { catalog, state, setState, update, errorsFor } = useContext(Ctx);
  const errs = errorsFor(path);
  const [listPath, i] = parentOf(path);
  const list = getIn(state, listPath) || [];
  const setList = (next) => setState((s) => setIn(s, listPath, next));
  const move = (dir) => {
    const next = [...list];
    [next[i], next[i + dir]] = [next[i + dir], next[i]];
    setList(next);
  };
  const tools = (
    <span className="wf-tools">
      {index > 0 && <button type="button" aria-label="Move up" onClick={() => move(-1)}>↑</button>}
      {index < count - 1 && <button type="button" aria-label="Move down" onClick={() => move(1)}>↓</button>}
      <button type="button" aria-label="Remove step" onClick={() => setList(list.filter((_, j) => j !== i))}>✕</button>
    </span>
  );

  let head;
  let body;
  if (step.type === 'wait') {
    head = <><span className="wf-icon">⏳</span><strong>Wait</strong></>;
    body = <div className="wf-row"><Field def={{ type: 'number', label: 'For', min: 1 }} path={`${path}.amount`} /><Field def={{ type: 'select', label: 'Unit', options: catalog.wait_units }} path={`${path}.unit`} /></div>;
  } else if (step.type === 'condition') {
    head = <><span className="wf-icon">⑂</span><strong>If</strong></>;
    body = (
      <>
        <Field def={{ type: 'select', label: 'Match', options: { all: 'All of these rules', any: 'Any of these rules' } }} path={`${path}.match`} />
        <div className="wf-rules">{(step.rules || []).map((r, ri) => <RuleRow key={ri} rule={r} path={`${path}.rules.${ri}`} />)}</div>
        <button type="button" className="b-btn" onClick={() => update(`${path}.rules`, [...(step.rules || []), { field: 'order_total', op: 'gte', value: '' }])}>Add rule</button>
      </>
    );
  } else {
    const action = catalog.actions[step.action] || {};
    head = <><span className="wf-icon">▶</span><strong>{action.label || 'Action'}</strong></>;
    body = (
      <>
        <label className="wf-field">
          <span>Action</span>
          <select value={step.action} onChange={(e) => update(path, { type: 'action', action: e.target.value, params: {} })}>
            {Object.entries(grouped(catalog.actions)).map(([g, items]) => <optgroup key={g} label={g}>{items.map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</optgroup>)}
          </select>
          {action.note && <small>{action.note}</small>}
        </label>
        {Object.entries(action.params || {}).map(([k, def]) => <Field key={k} def={def} path={`${path}.params.${k}`} />)}
      </>
    );
  }

  return (
    <section className={`wf-card wf-${step.type}${errs.length ? ' wf-has-error' : ''}`}>
      <header>{head}{tools}</header>
      <div className="wf-body">
        {body}
        <Errors list={step.type === 'condition' ? errorsFor(`${path}.rules`) : errs} />
      </div>
      {step.type === 'condition' && (
        <div className="wf-branches">
          {[['then', 'Yes'], ['else', 'No']].map(([key, label]) => (
            <div key={key} className={`wf-branch wf-branch-${key}`}>
              <p className="wf-branch-label">{label}</p>
              <StepList list={step[key] || []} path={`${path}.${key}`} />
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function RuleRow({ rule, path }) {
  const { catalog, state, setState, update } = useContext(Ctx);
  const def = catalog.conditions[rule.field] || catalog.conditions[Object.keys(catalog.conditions)[0]];
  const ops = Object.fromEntries((def.ops || []).map((o) => [o, catalog.operators[o] || o]));
  const [listPath, i] = parentOf(path);
  const valueType = def.type === 'number' ? 'number' : def.type === 'products' || def.type === 'collections' ? def.type : 'text';
  const changeField = (field) => {
    const d = catalog.conditions[field];
    update(path, { field, op: d.ops[0], value: d.type === 'select' ? Object.keys(d.options)[0] : '' });
  };
  return (
    <div className="wf-rule">
      <label className="wf-field">
        <span>Check</span>
        <select value={rule.field} onChange={(e) => changeField(e.target.value)}>{Object.entries(catalog.conditions).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}</select>
      </label>
      <Field def={{ type: 'select', label: 'Is', options: ops }} path={`${path}.op`} />
      {def.type === 'select'
        ? <Field def={{ type: 'select', label: 'Value', options: def.options }} path={`${path}.value`} />
        : <Field def={{ type: valueType, label: 'Value', help: def.help }} path={`${path}.value`} />}
      <button type="button" className="wf-x" aria-label="Remove rule" onClick={() => setState((s) => setIn(s, listPath, (getIn(s, listPath) || []).filter((_, j) => j !== i)))}>✕</button>
    </div>
  );
}
