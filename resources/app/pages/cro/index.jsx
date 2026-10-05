import { useState } from 'react';
import { Field, Form } from '../../components/form.jsx';
import { ago, EmptyState, Page, Pagination } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useUrl, withQuery } from '../../router.jsx';
import ExperienceStatus from './_status.jsx';

const STATUSES = { draft: 'Draft', published: 'Published', scheduled: 'Scheduled', paused: 'Paused', not_placed: 'Not placed', archived: 'Archived' };

export default function Offers({ type, typeDef, filters, types, experiences }) {
  const { visit, submit } = useRouter();
  const url = useUrl();
  const [q, setQ] = useState(filters.q);
  const [selected, setSelected] = useState([]);
  const [bulk, setBulk] = useState('');
  const createHref = appUrl(route('app.cro.experiences.create', { type }));
  const createLabel = typeDef ? `Create ${typeDef.singular}` : 'Create widget';
  const filter = (k, v) => visit(withQuery(url, { [k]: v || null, page: null }));
  const all = experiences.data.length > 0 && selected.length === experiences.data.length;

  // Downloads need the session token, so fetch the file instead of opening a new tab.
  const download = async () => {
    const res = await fetch(appUrl(route('app.cro.experiences.export')));
    if (!res.ok) return window.shopify?.toast?.show('We couldn\'t complete that request. Please try again.', { isError: true });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await res.blob()), download: 'experiences.csv' });
    a.click();
    URL.revokeObjectURL(a.href);
  };

  return (
    <Page heading={typeDef?.label || 'All widgets'}
      primary={<s-button slot="primary-action" variant="primary" href={createHref}>{createLabel}</s-button>}
      secondary={<s-button slot="secondary-actions" onClick={download}>Export CSV</s-button>}>
      <s-section>
        <Form className="oo-form-row" onSubmit={() => filter('q', q)}>
          <Field label="Search" className="grow"><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name or ID" style={{ minWidth: 220 }} /></Field>
          {!type && (
            <Field label="Type">
              <select value={filters.type || ''} onChange={(e) => filter('type', e.target.value)}>
                <option value="">All types</option>
                {Object.entries(types).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
              </select>
            </Field>
          )}
          <Field label="Status">
            <select value={filters.status || ''} onChange={(e) => filter('status', e.target.value)}>
              <option value="">All except archived</option>
              {Object.entries(STATUSES).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
            </select>
          </Field>
          <s-button type="submit">Filter</s-button>
        </Form>
      </s-section>

      <s-section>
        {!experiences.data.length ? (
          filters.q || filters.status || (!type && filters.type) ? <s-paragraph>No widgets match those filters.</s-paragraph> : (
            <EmptyState title={typeDef ? `No ${typeDef.plural} yet` : 'No widgets yet'} text={typeDef?.empty || 'Create your first widget. Pick a template, customise it and publish it from the Theme Editor.'}>
              <s-button variant="primary" href={createHref}>{createLabel}</s-button>
            </EmptyState>
          )
        ) : (
          <>
            <div className="oo-form-row" style={{ marginBottom: 10 }}>
              <select className="oo-select" value={bulk} onChange={(e) => setBulk(e.target.value)} aria-label="Bulk action">
                <option value="">Bulk actions</option><option value="pause">Pause</option><option value="archive">Archive</option>
              </select>
              <s-button disabled={!bulk || !selected.length || undefined} onClick={async () => {
                const r = await submit(route('app.cro.experiences.bulk'), { ids: selected, bulk_action: bulk }, { confirm: 'Apply this action to the selected widgets?' });
                if (r.ok) setSelected([]);
              }}>Apply</s-button>
            </div>
            <div className="oo-scroll">
              <table className="oo-table stack">
                <thead><tr><th><input type="checkbox" aria-label="Select all" checked={all} onChange={(e) => setSelected(e.target.checked ? experiences.data.map((x) => x.id) : [])} /></th><th>Name</th>{!type && <th>Type</th>}<th>Status</th><th>Template</th><th>Updated</th></tr></thead>
                <tbody>
                  {experiences.data.map((e) => (
                    <tr key={e.id}>
                      <td><input type="checkbox" aria-label={`Select ${e.name}`} checked={selected.includes(e.id)} onChange={(ev) => setSelected(ev.target.checked ? [...selected, e.id] : selected.filter((x) => x !== e.id))} /></td>
                      <td><s-link href={appUrl(route('app.cro.experiences.show', { experience: e.id }))}>{e.name}</s-link><div className="oo-muted oo-small">{e.handle}</div></td>
                      {!type && <td data-label="Type">{e.type_label}</td>}
                      <td><ExperienceStatus experience={e} /></td>
                      <td data-label="Template">{e.template}</td>
                      <td className="oo-muted" data-label="Updated">{ago(e.updated_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination paginator={experiences} url={url} />
          </>
        )}
      </s-section>
    </Page>
  );
}
