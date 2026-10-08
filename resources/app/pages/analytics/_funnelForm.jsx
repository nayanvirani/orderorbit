import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { useRouter } from '../../router.jsx';

/** Funnel name, window and up to 8 event steps (empty steps are ignored). */
export default function FunnelForm({ funnel, action, eventGroups, windows, experiences }) {
  const { submit } = useRouter();
  const [name, setName] = useState(funnel?.name || '');
  const [within, setWithin] = useState(funnel?.within || '7d');
  const [steps, setSteps] = useState(Array.from({ length: 8 }, (_, i) => ({ event: funnel?.steps?.[i]?.event || '', experience: funnel?.steps?.[i]?.experience || '' })));
  const [busy, setBusy] = useState(false);
  const setStep = (i, key, value) => setSteps(steps.map((s, j) => (j === i ? { ...s, [key]: value } : s)));

  const save = async () => {
    setBusy(true);
    await submit(action, { name, within, steps: steps.filter((s) => s.event) });
    setBusy(false);
  };

  return (
    <Form className="an-funnel-form" onSubmit={save}>
      <div className="oo-form-row">
        <Field label="Name" className="grow"><input type="text" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} style={{ minWidth: 220 }} /></Field>
        <Field label="Steps must happen">
          <select value={within} onChange={(e) => setWithin(e.target.value)}>{Object.entries(windows).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
        </Field>
      </div>
      <ol className="an-steps-edit">
        {steps.map((s, i) => (
          <li key={i}>
            <select value={s.event} onChange={(e) => setStep(i, 'event', e.target.value)} aria-label={`Step ${i + 1} event`}>
              <option value="">{i < 2 ? 'Choose an event' : '— (optional step)'}</option>
              {Object.entries(eventGroups).map(([group, events]) => (
                <optgroup key={group} label={group}>{Object.entries(events).map(([n, l]) => <option key={n} value={n}>{l}</option>)}</optgroup>
              ))}
            </select>
            <select value={s.experience} onChange={(e) => setStep(i, 'experience', e.target.value)} aria-label={`Step ${i + 1} widget`}>
              <option value="">Any widget</option>
              {Object.entries(experiences).map(([h, e]) => <option key={h} value={h}>{e.name}</option>)}
            </select>
          </li>
        ))}
      </ol>
      <p className="oo-muted oo-small">"Any widget" applies to Growvia events; pick one to follow a single bundle, gift or upsell.</p>
      <s-button type="submit" variant="primary" loading={busy || undefined}>{funnel ? 'Save funnel' : 'Create funnel'}</s-button>
    </Form>
  );
}
