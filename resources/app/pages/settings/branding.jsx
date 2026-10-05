import { useEffect, useRef } from 'react';
import { Field, Form, useForm } from '../../components/form.jsx';
import { useRuntime } from '../../components/runtime.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useShared } from '../../router.jsx';

const COLORS = [['primary_color', 'Primary (buttons)'], ['accent_color', 'Accent (progress, badges)'], ['text_color', 'Text'], ['background_color', 'Background']];

export default function Branding({ branding, currency }) {
  const { can } = useShared();
  const form = useForm(branding);
  const ready = useRuntime();
  const preview = useRef(null);
  const disabled = !can.manage_settings;
  const d = form.data;

  // Live preview with the storefront runtime.
  useEffect(() => {
    if (!ready || !preview.current) return;
    window.OrderOrbit.render(preview.current, {
      id: 'branding', type: 'shipping-bar', style: 'card', version: 0,
      content: { thresholds: [{ amount: 60, reward: 'free shipping' }], progress_message: 'You\'re {remaining} away from {reward}', unlocked_message: '', empty_message: '' },
      design: { primary_color: d.primary_color, accent_color: d.accent_color, text_color: d.text_color, background_color: d.background_color, button_style: d.button_style, radius: Number(d.radius), border: true, font: d.font },
      behavior: {}, targeting: {}, analytics: {},
    }, { preview: true, currency, cartTotal: 4500 });
  }, [ready, d, currency]);

  return (
    <Page heading="Settings">
      <Form onSubmit={() => form.post(route('app.settings.branding.update'))}>
        <s-section heading="Branding">
          <s-paragraph>New experiences start from these colours and styles. Existing experiences keep their own design.</s-paragraph>
          <div className="ui-grid" style={{ marginTop: 12 }}>
            {COLORS.map(([key, label]) => (
              <Field key={key} label={label}>
                <span className="oo-inline"><input type="color" {...form.bind(key)} disabled={disabled} style={{ width: 48, padding: 2 }} /><span className="oo-code">{d[key]}</span></span>
              </Field>
            ))}
          </div>
          <div className="ui-grid" style={{ marginTop: 12 }}>
            <Field label="Button style">
              <select {...form.bind('button_style')} disabled={disabled}><option value="filled">Filled</option><option value="outline">Outline</option></select>
            </Field>
            <Field label="Corner radius (px)"><input type="number" min="0" max="32" {...form.bind('radius')} disabled={disabled} /></Field>
            <Field label="Font">
              <select {...form.bind('font')} disabled={disabled}><option value="theme">Match my theme</option><option value="system">System font</option></select>
            </Field>
          </div>
          {!disabled && (
            <s-stack direction="inline" gap="small-200" paddingBlockStart="base">
              <s-button type="submit" variant="primary" loading={form.processing || undefined}>Save</s-button>
              <s-button onClick={() => form.post(route('app.settings.branding.update'), { data: { action: 'reset' }, confirm: 'Reset branding to the defaults?' })}>Reset to defaults</s-button>
            </s-stack>
          )}
        </s-section>
      </Form>

      <s-section heading="Preview">
        <div className="oo-preview" style={{ maxWidth: 520 }}><div className="oo-root" ref={preview} /></div>
      </s-section>
    </Page>
  );
}
