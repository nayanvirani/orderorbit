// Custom plan request (G34): the owner ticks the modules they need (each shows the cheapest plan
// that has it) and the limits; a side box shows the closest plan. Sent as a support ticket.
import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { money } from '../../components/ui.jsx';
import { route, useRouter } from '../../router.jsx';

export default function CustomPlanForm({ options }) {
  const { submit } = useRouter();
  const [picked, setPicked] = useState([]);
  const [widgets, setWidgets] = useState('');
  const [runs, setRuns] = useState('');
  const [budget, setBudget] = useState('');
  const [note, setNote] = useState('');
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const toggle = (key, on) => setPicked((p) => (on ? [...p, key] : p.filter((k) => k !== key)));

  // The cheapest plan with every ticked module and room for the widgets and runs.
  const fits = (p) => picked.every((k) => p.includes.includes(k))
    && (p.widgets === null || !widgets || Number(widgets) <= p.widgets) && (p.runs === null || !runs || Number(runs) <= p.runs);
  const closest = picked.length ? options.plans.find(fits) : null;

  const send = async () => {
    setBusy(true);
    const r = await submit(route('app.support.store'), { category: 'custom_plan', modules: picked, widgets: widgets || null, runs: runs || null, budget: budget || null, note });
    setBusy(false);
    setErrors(r.errors || {});
  };

  return (
    <div className="cp-layout">
      <Form className="sp-form" onSubmit={send}>
        <p className="oo-muted" style={{ margin: 0 }}>Tick what you need. The Growvia team replies here, usually within one business day, with the plan that fits or a plan made for you.</p>
        {Object.entries(options.groups).map(([group, modules]) => (
          <fieldset className="cp-group" key={group}>
            <legend>{group}</legend>
            {modules.map((m) => (
              <label className="cp-module" key={m.key}>
                <input type="checkbox" checked={picked.includes(m.key)} onChange={(e) => toggle(m.key, e.target.checked)} />
                <span>{m.label}</span>
                <small>{m.plan}</small>
              </label>
            ))}
          </fieldset>
        ))}
        {errors.modules && <p className="b-error">{errors.modules}</p>}
        <div className="oo-form-row">
          <Field label="Live widgets you need (optional)" error={errors.widgets}><input type="number" min={0} value={widgets} onChange={(e) => setWidgets(e.target.value)} /></Field>
          <Field label="Automation runs a month (optional)" error={errors.runs}><input type="number" min={0} value={runs} onChange={(e) => setRuns(e.target.value)} /></Field>
          <Field label="Monthly budget in USD (optional)" error={errors.budget}><input type="number" min={0} step="0.01" value={budget} onChange={(e) => setBudget(e.target.value)} /></Field>
        </div>
        <Field label="Anything else? (optional)" error={errors.note}><textarea rows={3} maxLength={2000} value={note} onChange={(e) => setNote(e.target.value)} /></Field>
        <div><s-button type="submit" variant="primary" loading={busy || undefined}>Send request</s-button></div>
      </Form>
      <aside className="cp-side" aria-live="polite">
        <b>Closest plan</b>
        {closest ? <p><span className="cp-plan">{closest.name}</span> {money(closest.price, 'USD')}/month{closest.name === options.current ? ' · your plan already has these' : ''}</p>
          : <p className="oo-muted">{picked.length ? 'No plan has all of these: the team will suggest a plan made for you.' : 'Tick modules to see the plan that fits.'}</p>}
        {options.current && <p className="oo-muted">Your plan: {options.current}</p>}
      </aside>
    </div>
  );
}
