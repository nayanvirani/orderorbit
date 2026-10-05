import { useState } from 'react';
import { ago, Hero, KeyValue, Page } from '../components/ui.jsx';
import { appUrl, route, useRouter } from '../router.jsx';

const TITLES = { 4: 'Choose a template', 5: 'Configure it', 6: 'Preview on desktop and mobile', 7: 'Publish and place it' };
const HELP = {
  4: 'Pick the layout that fits your store. You can switch templates any time without losing your content.',
  5: 'Add your products, text and offer, and match your brand in the Design step.',
  6: 'Use the preview on the right of the builder, and switch between desktop and mobile.',
  7: 'Publish, then place the block in the Theme Editor (or the checkout editor for checkout blocks). The builder shows exactly where.',
};

export default function Onboarding({ goals, goal, steps, step, done, store, missingScopes, recommended, first, pixelConnected, lastEvent }) {
  const { submit } = useRouter();
  const [choice, setChoice] = useState(goal);
  const [busy, setBusy] = useState(false);
  const to = (n) => appUrl(route('app.onboarding', { step: n }));

  return (
    <Page heading="Welcome to OrderOrbit Space">
      <Hero eyebrow="Get started" title="Welcome to <em>OrderOrbit Space.</em>" lead="Eight short steps from connecting your store to seeing your first results. You can leave at any time and pick up where you left off." />
      <div className="oo-steps" aria-label="Onboarding progress">
        {steps.map((label, i) => {
          const n = i + 1;
          return (
            <a key={label} href={to(n)} style={{ textDecoration: 'none' }}>
              <span className={n === step ? 'current' : done[n] ? 'done' : ''}>{done[n] ? '✓' : n}. {label}</span>
            </a>
          );
        })}
      </div>

      {step === 1 && (
        <s-section heading="Your store is connected">
          <KeyValue rows={[
            ['Store', store.name],
            ['Currency', store.currency],
            ['Selling currencies', store.currencies.join(', ') || '—'],
            ['Permissions', missingScopes.length ? <s-badge tone="critical">Missing: {missingScopes.join(', ')}</s-badge> : <s-badge tone="success">All granted</s-badge>],
          ]} />
          <s-stack direction="inline" gap="small-200" paddingBlockStart="base"><s-button variant="primary" href={to(2)}>Next</s-button></s-stack>
        </s-section>
      )}

      {step === 2 && (
        <s-section heading="What do you want to improve first?">
          <s-paragraph>We'll recommend widgets and templates for your goal. You can change this any time.</s-paragraph>
          {Object.entries(goals).map(([key, g]) => (
            <label className="oo-radio" key={key}>
              <input type="radio" name="goal" value={key} checked={choice === key} onChange={() => setChoice(key)} />
              <span><strong>{g.label}</strong><small>{g.help}</small></span>
            </label>
          ))}
          <s-stack direction="inline" gap="small-200">
            <s-button href={to(1)}>Back</s-button>
            <s-button variant="primary" disabled={!choice || undefined} loading={busy || undefined} onClick={async () => { setBusy(true); await submit(route('app.onboarding.update'), { goal: choice }); setBusy(false); }}>Next</s-button>
          </s-stack>
        </s-section>
      )}

      {step === 3 && (
        <s-section heading="3. Choose your first widget">
          <p className="oo-muted">Recommended for <strong>{goals[goal]?.label || 'your goal'}</strong>. You can add more later.</p>
          <div className="ob-types">
            {recommended.map((r) => <a className="ob-type" key={r.label} href={appUrl(r.href)}><h4>{r.label}</h4><p>{r.help}</p><div className="ob-row">Start →</div></a>)}
          </div>
          <p className="oo-small"><a href={appUrl(route('app.cro.experiences.create'))}>See every widget type</a> · <a href={to(2)}>Change goal</a></p>
        </s-section>
      )}

      {step >= 4 && step <= 7 && (
        <s-section heading={`${step}. ${TITLES[step]}`}>
          {!first ? (
            <>
              <s-paragraph>Start by choosing your first widget.</s-paragraph>
              <s-button variant="primary" href={to(3)}>Choose a widget</s-button>
            </>
          ) : (
            <>
              <s-paragraph>{HELP[step]}</s-paragraph>
              <KeyValue rows={[
                ['Your first widget', `${first.name} · ${first.type}`],
                ['Status', `${first.status[0].toUpperCase()}${first.status.slice(1)}${first.status === 'published' ? ` · placement: ${first.placement}` : ''}`],
              ]} />
              <div className="oo-inline" style={{ marginTop: 12 }}>
                <s-button variant="primary" href={appUrl(first.edit)}>Open the builder</s-button>
                {step === 7 && first.status === 'published' && <s-button href={first.editorUrl} target="_top">{first.editorLabel}</s-button>}
                <s-button variant="tertiary" href={to(step + 1)}>Next step</s-button>
              </div>
            </>
          )}
        </s-section>
      )}

      {step === 8 && (
        <s-section heading="8. Verify analytics">
          <KeyValue rows={[
            ['Analytics pixel', pixelConnected ? <s-badge tone="success">Connected</s-badge> : <><s-badge tone="warning">Not connected</s-badge> <s-link href={appUrl(route('app.analytics'))}>Connect</s-link></>],
            ['First event', lastEvent ? <><s-badge tone="success">Received</s-badge> {ago(lastEvent)}</> : <><s-badge>Waiting</s-badge> Open your store and view a product (with analytics cookies allowed), then check again.</>],
          ]} />
          <div className="oo-inline" style={{ marginTop: 12 }}>
            <s-button href={`https://${store.domain}`} target="_blank">Open your store</s-button>
            <s-button href={to(8)}>Check again</s-button>
            {lastEvent && <s-button variant="primary" href={appUrl(route('app.dashboard'))}>Go to your dashboard</s-button>}
          </div>
        </s-section>
      )}
    </Page>
  );
}
