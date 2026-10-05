import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { Hero, Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared, useUrl, withQuery } from '../../router.jsx';
import TestStatus from './_status.jsx';

const EMPTY = { active: 'No tests running.', drafts: 'No drafts.', completed: 'No finished tests yet.' };

export default function Experiments({ tab, q, experiments, testable, abTesting, docsUrl, error }) {
  const { can } = useShared();
  const { submit, visit } = useRouter();
  const url = useUrl();
  const [experience, setExperience] = useState(testable[0]?.id || '');
  const [busy, setBusy] = useState(false);
  const [search, setSearch] = useState(q);

  const create = async () => {
    setBusy(true);
    await submit(route('app.experiments.store'), { experience_id: experience });
    setBusy(false);
  };

  return (
    <Page heading="A/B tests">
      <Hero eyebrow="A/B testing" icon="target" tone="analytics" title="Test before you <em>commit.</em>" lead="Split visitors between versions of a live experience and see which earns more. A winner is only named once the test has enough days, visitors and conversions to be sure.">
        <s-button href={docsUrl} target="_blank">View documentation</s-button>
      </Hero>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {!abTesting && (
        <s-banner tone="info" heading="A/B tests run on Growth and Scale">
          <s-paragraph>You can set up tests now and launch them after upgrading.</s-paragraph>
          <s-button slot="secondary-actions" href={appUrl(route('app.settings.billing'))}>See plans</s-button>
        </s-banner>
      )}

      {can.manage_experiences && (
        <s-section heading="Create a test">
          {!testable.length ? (
            <s-paragraph>Publish an experience first, such as an upsell, countdown, sticky add to cart, trust or checkout block. Tests split the traffic of a live experience.</s-paragraph>
          ) : (
            <>
              <Form className="oo-form-row" onSubmit={create}>
                <Field label="Experience to test" className="grow">
                  <select value={experience} onChange={(e) => setExperience(e.target.value)} style={{ minWidth: 260 }}>
                    {testable.map((e) => <option key={e.id} value={e.id}>{e.label}</option>)}
                  </select>
                </Field>
                <s-button type="submit" variant="primary" loading={busy || undefined}>Create test</s-button>
              </Form>
              <p className="oo-muted oo-small">Storefront experiences and checkout, Thank You and Order Status blocks can be tested. Bundles, Progressive gifts, the post-purchase offer and customer account blocks can't yet.</p>
            </>
          )}
        </s-section>
      )}

      <s-section>
        <Form className="oo-form-row" onSubmit={() => visit(withQuery(url, { q: search || null }))}>
          <Field label="Search" className="grow"><input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Test name" /></Field>
        </Form>
        <div style={{ height: 12 }} />
        {!experiments.length ? <s-paragraph><span className="oo-muted">{EMPTY[tab]}</span></s-paragraph> : (
          <div className="oo-scroll">
            <table className="oo-table stack">
              <thead><tr><th>Test</th><th>Experience</th><th>Variants</th><th>Primary metric</th><th>Visitors per variant</th><th>Progress</th><th>Days</th><th>Status</th><th>Result</th></tr></thead>
              <tbody>
                {experiments.map((x) => (
                  <tr key={x.id}>
                    <td data-label="Test"><s-link href={appUrl(route(x.status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', { experiment: x.id }))}>{x.name}</s-link></td>
                    <td data-label="Experience">{x.experience}</td>
                    <td data-label="Variants">{x.variants}</td>
                    <td data-label="Primary metric">{x.metric}</td>
                    <td data-label="Visitors per variant">{x.visitors ?? '—'}</td>
                    <td data-label="Progress">{x.progress !== null ? <span className="oo-meter" style={{ display: 'block', width: 90 }}><i style={{ width: `${x.progress}%` }} /></span> : '—'}</td>
                    <td data-label="Days">{x.days ?? '—'}</td>
                    <td data-label="Status"><TestStatus status={x.status} /></td>
                    <td data-label="Result">{x.result ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </s-section>
    </Page>
  );
}
