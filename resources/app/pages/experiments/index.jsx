import { useState } from 'react';
import { ActionButton, Field, Form } from '../../components/form.jsx';
import { Hero, Page, Upgrade } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared, useUrl, withQuery } from '../../router.jsx';
import TestStatus from './_status.jsx';

const EMPTY = { active: 'No tests running.', drafts: 'No drafts.', completed: 'No finished tests yet.' };

export default function Experiments({ tab, q, experiments, offers = [], limit, abTesting, docsUrl, error }) {
  const { can } = useShared();
  const { visit } = useRouter();
  const url = useUrl();
  const [search, setSearch] = useState(q);

  return (
    <Page heading="A/B tests">
      <Hero eyebrow="A/B testing" icon="target" tone="analytics" title="Test before you <em>commit.</em>" lead="Test any offer: layout, text, design, price, rewards or products. Each shopper keeps one version from the product page to checkout, and a winner is only named once the test has enough days, visitors and conversions to be sure.">
        <s-button href={docsUrl} target="_blank">View documentation</s-button>
      </Hero>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {!abTesting && (
        <Upgrade feature="ab_testing">You can set up tests now and launch them after upgrading.</Upgrade>
      )}

      {limit && <p className="oo-muted">Live tests: <strong>{limit.live}</strong>{limit.max !== null && limit.max !== undefined ? ` of ${limit.max} on your plan` : ''}</p>}

      {can.manage_experiences && (
        <s-section heading="Test any offer">
          {!offers.length ? (
            <s-paragraph>Publish an offer first, such as a bundle, gift campaign, upsell or checkout block. Tests split the traffic of a live offer.</s-paragraph>
          ) : (
            <div className="xp-offers">
              {offers.map((o) => (
                <div className="xp-offer" key={o.id}>
                  <span className="oo-muted oo-small">{o.type}</span>
                  <strong>{o.name}</strong>
                  {o.test ? <s-button href={appUrl(route(o.test.status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', { experiment: o.test.id }))}>{o.test.status === 'draft' ? 'Open draft test' : 'View its test'}</s-button>
                    : o.why ? <span className="oo-muted oo-small">{o.why}</span>
                      : <ActionButton url={route('app.experiments.store')} data={{ experience_id: o.id }}>Test this offer</ActionButton>}
                </div>
              ))}
            </div>
          )}
          <p className="oo-muted oo-small">Every offer page also has a Test this offer button.</p>
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
              <thead><tr><th>Test</th><th>Offer</th><th>What changes</th><th>Variants</th><th>Primary metric</th><th>Visitors per variant</th><th>Progress</th><th>Days</th><th>Status</th><th>Leader</th><th>Result</th></tr></thead>
              <tbody>
                {experiments.map((x) => (
                  <tr key={x.id}>
                    <td data-label="Test"><s-link href={appUrl(route(x.status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', { experiment: x.id }))}>{x.name}</s-link></td>
                    <td data-label="Offer">{x.experience}</td>
                    <td data-label="What changes">{x.changes}</td>
                    <td data-label="Variants">{x.variants}</td>
                    <td data-label="Primary metric">{x.metric}</td>
                    <td data-label="Visitors per variant">{x.visitors ?? '—'}</td>
                    <td data-label="Progress">{x.progress !== null ? <span className="oo-meter" style={{ display: 'block', width: 90 }}><i style={{ width: `${x.progress}%` }} /></span> : '—'}</td>
                    <td data-label="Days">{x.days ?? '—'}</td>
                    <td data-label="Status"><TestStatus status={x.status} /></td>
                    <td data-label="Leader">{x.leader ? <>{x.leader.key} · {x.leader.chance}% to beat A</> : '—'}</td>
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
