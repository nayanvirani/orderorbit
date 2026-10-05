import { ActionButton } from '../../components/form.jsx';
import { ago, Hero, Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import AudienceNotes from './_nav.jsx';

export default function Segments(props) {
  const { segments, templates, archived, docsUrl } = props;
  const { can } = useShared();
  return (
    <Page heading="Audiences">
      <Hero eyebrow="Audiences & Personalization" icon="users" tone="analytics" title="The right offer for <em>each shopper.</em>" lead="Segments group shoppers by what they've bought, spent and done. Use them to target experiences, run rules that show, swap or hide experiences, and in A/B tests and workflows.">
        <s-button href={docsUrl} target="_blank" variant="tertiary">View documentation</s-button>
      </Hero>
      <AudienceNotes {...props} />

      {can.manage_experiences && !archived && (
        <s-section heading="Start from a ready-made segment">
          <div className="au-templates">
            {templates.map((t) => (
              <div className="au-template" key={t.key}>
                <strong>{t.name}</strong>
                <span className="oo-muted oo-small">{t.description}</span>
                <ActionButton url={route('app.audiences.segments.store')} data={{ template: t.key }}>Add</ActionButton>
              </div>
            ))}
            <div className="au-template au-blank">
              <strong>Custom segment</strong>
              <span className="oo-muted oo-small">Combine orders, spend, tags, products, market, device and experiences.</span>
              <ActionButton variant="primary" url={route('app.audiences.segments.store')}>Create</ActionButton>
            </div>
          </div>
        </s-section>
      )}

      <s-section heading={archived ? 'Archived segments' : 'Your segments'}>
        <p className="oo-small"><a href={appUrl(route('app.audiences.segments', { archived: archived ? null : 1 }))}>{archived ? '← Back to active segments' : 'Show archived segments'}</a></p>
        {!segments.length ? <s-paragraph><span className="oo-muted">{archived ? 'Nothing archived.' : 'No segments yet. Add a ready-made one above.'}</span></s-paragraph> : (
          <>
            <div className="oo-scroll">
              <table className="oo-table stack">
                <thead><tr><th>Segment</th><th>Definition</th><th>Members</th><th>Used by</th><th>Updated</th></tr></thead>
                <tbody>
                  {segments.map((s) => (
                    <tr key={s.id}>
                      <td data-label="Segment">{can.manage_experiences ? <s-link href={appUrl(route('app.audiences.segments.edit', { segment: s.id }))}>{s.name}</s-link> : s.name}</td>
                      <td data-label="Definition" className="oo-small">{s.definition}</td>
                      <td data-label="Members">{s.members}</td>
                      <td data-label="Used by">{s.used_by || '—'}</td>
                      <td data-label="Updated">{ago(s.updated_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="oo-muted oo-small">Member counts come from Shopify when every rule is a customer field (orders, total spent, tags). Segments with browsing rules (device, experiences, products, market) are worked out live on your store, so there's no count.</p>
          </>
        )}
      </s-section>
    </Page>
  );
}
