// Automation › Product documents (G42): certificates made for each order, or the same PDF for
// everyone, sent with workflow emails for the products in the order.
import { useEffect, useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useUrl } from '../../router.jsx';

const BLANK = {
  id: null, name: '', kind: 'certificate', file_name: null, match: 'products', match_items: [], match_tag: '', per: 'item', active: true, has_logo: false,
  certificate: { heading: 'Certificate of Authenticity', intro: 'This certifies that', owns: 'owns', show_name: true, serial_prefix: '', signature: '', footer: '', show_qr: true, accent: '#1f2328' },
};
const MATCH = { products: 'These products and variants', collection: 'Products in a collection', tag: 'Products with a tag', all: 'Every order' };
const PER = { item: 'Item (2 rings = 2 certificates)', line: 'Product line', order: 'Order' };

function matchLabel(d) {
  if (d.match === 'all') return 'Every order';
  if (d.match === 'tag') return `Tag: ${d.match_tag}`;
  const items = d.match_items || [];
  if (d.match === 'collection') return `Collection: ${items[0]?.title || '—'}`;
  return items.length ? `${items.slice(0, 2).map((i) => i.title).join(', ')}${items.length > 2 ? ` +${items.length - 2} more` : ''}` : '—';
}

export default function ProductDocuments({ documents, linkDays }) {
  const { submit } = useRouter();
  const url = useUrl();
  const openId = Number(new URLSearchParams(url.split('?')[1] || '').get('open')) || null;
  const [doc, setDoc] = useState(() => structuredClone(documents.find((d) => d.id === openId) || documents[0] || BLANK));
  const [file, setFile] = useState(null);
  const [logo, setLogo] = useState(null);
  const [removeLogo, setRemoveLogo] = useState(false);
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(null);
  const [preview, setPreview] = useState(null);

  useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);
  const open = (d) => { setDoc(structuredClone(d)); setFile(null); setLogo(null); setRemoveLogo(false); setErrors({}); setPreview(null); };
  const set = (key, value) => setDoc((d) => ({ ...d, [key]: value }));
  const setCert = (key, value) => setDoc((d) => ({ ...d, certificate: { ...d.certificate, [key]: value } }));
  const cert = doc.certificate || BLANK.certificate;

  const pick = async () => {
    if (!window.shopify?.resourcePicker) return;
    const collection = doc.match === 'collection';
    const picked = await window.shopify.resourcePicker({
      type: collection ? 'collection' : 'product', multiple: !collection, filter: collection ? undefined : { variants: true },
      selectionIds: doc.match_items.map((i) => (collection ? { id: i.id } : { id: i.id, variants: (i.variants || []).map((v) => ({ id: v })) })),
    });
    if (!picked) return;
    set('match_items', picked.map((r) => {
      // Only some variants ticked: just those get the document; all ticked: every variant does.
      const ids = (r.variants || []).map((v) => v.id);
      const all = !r.totalVariants || ids.length >= r.totalVariants;
      return collection ? { id: r.id, title: r.title } : { id: r.id, title: r.title, ...(all ? {} : { variants: ids }) };
    }));
  };

  const save = async () => {
    setBusy('save');
    const body = new FormData();
    body.append('data', JSON.stringify({ ...doc, remove_logo: removeLogo }));
    if (file) body.append('file', file);
    if (logo) body.append('logo', logo);
    const r = await submit(doc.id ? route('app.automation.documents.update', { document: doc.id }) : route('app.automation.documents.store'), body);
    setBusy(null);
    setErrors(r.errors || {});
  };

  const showPreview = async () => {
    setBusy('preview');
    const res = await fetch(appUrl(route('app.automation.documents.preview', { document: doc.id })));
    setBusy(null);
    if (!res.ok) return window.shopify?.toast?.show('We couldn\'t make the preview. Please try again.', { isError: true });
    setPreview(URL.createObjectURL(await res.blob()));
  };

  const err = (k) => errors[k] && <p className="b-error">{errors[k]}</p>;

  return (
    <Page heading="Product documents" back={route('app.automation.index')} backLabel="Workflows"
      primary={<s-button slot="primary-action" variant="primary" onClick={() => open(BLANK)}>Add document</s-button>}>
      <p className="oo-muted" style={{ marginTop: 0 }}>Certificates, warranty cards and guides sent with your automation emails. Each order gets the documents for the products in it.</p>

      <div className="pd-layout">
        <section className="pd-list" aria-label="Documents">
          {documents.length === 0 && <p className="oo-muted" style={{ padding: 16 }}>No documents yet. Add one, then choose “Product documents” in a workflow's Send email step.</p>}
          {documents.length > 0 && (
            <table className="pd-table">
              <thead><tr><th>Document</th><th>Products</th><th>Kind</th><th>Sent (30 days)</th></tr></thead>
              <tbody>
                {documents.map((d) => (
                  <tr key={d.id} aria-selected={d.id === doc.id} onClick={() => open(d)}>
                    <td><button type="button" className="pd-name" onClick={() => open(d)}>{d.name}</button><small>{d.kind === 'file' ? d.file_name : 'Made for each order'}{d.active ? '' : ' · Off'}</small></td>
                    <td>{matchLabel(d)}</td>
                    <td><span className={`pd-chip ${d.kind === 'certificate' ? 'pd-chip-cert' : ''}`}>{d.kind === 'certificate' ? 'Certificate' : 'PDF file'}</span></td>
                    <td>{d.sent}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <div className="pd-how">
            <b>In a workflow</b>
            <p>Open a workflow, add <strong>Send email</strong> and set <strong>Product documents</strong> to attach them or add download links. Attachments over 10 MB in total switch to links; links work for {linkDays} days.</p>
            <s-button href={appUrl(route('app.automation.index'))}>Open workflows</s-button>
          </div>
        </section>

        <section className="pd-editor" aria-label={doc.id ? `Edit ${doc.name}` : 'New document'}>
          <h2>{doc.id ? doc.name : 'New document'}</h2>
          <div className="b-field"><label htmlFor="pd-name">Name</label><input id="pd-name" maxLength={120} value={doc.name} onChange={(e) => set('name', e.target.value)} placeholder="Certificate of Authenticity" />{err('name')}</div>

          <div className="b-field">
            <span className="pd-label">Kind</span>
            <div className="b-seg" role="group" aria-label="Kind">
              <button type="button" aria-pressed={doc.kind === 'certificate'} onClick={() => set('kind', 'certificate')}>Certificate made for each order</button>
              <button type="button" aria-pressed={doc.kind === 'file'} onClick={() => set('kind', 'file')}>Same PDF for everyone</button>
            </div>
          </div>

          {doc.kind === 'file' ? (
            <div className="b-field">
              <label htmlFor="pd-file">PDF file</label>
              {doc.file_name && !file && <p className="pd-file">Current: <b>{doc.file_name}</b> ({Math.round((doc.file_size || 0) / 1024)} KB)</p>}
              <input id="pd-file" type="file" accept="application/pdf,.pdf" onChange={(e) => setFile(e.target.files[0] || null)} />
              <small className="oo-muted">PDF up to 10 MB · the same file for every order (warranty card, care guide, manual).</small>
              {err('file')}
            </div>
          ) : (
            <fieldset className="pd-cert">
              <legend>Certificate</legend>
              <p className="oo-muted">Filled for each order: customer name, product and variant, order number and date, a serial number and a QR code to verify it online.</p>
              <div className="pd-grid">
                <div className="b-field"><label htmlFor="pd-h">Heading</label><input id="pd-h" maxLength={80} value={cert.heading} onChange={(e) => setCert('heading', e.target.value)} /></div>
                <div className="b-field"><label htmlFor="pd-i">Line above the name</label><input id="pd-i" maxLength={120} value={cert.intro} onChange={(e) => setCert('intro', e.target.value)} /></div>
                <div className="b-field"><label htmlFor="pd-o">Word before the product</label><input id="pd-o" maxLength={40} value={cert.owns} onChange={(e) => setCert('owns', e.target.value)} /></div>
                <div className="b-field"><label htmlFor="pd-p">Serial number prefix</label><input id="pd-p" maxLength={12} value={cert.serial_prefix} onChange={(e) => setCert('serial_prefix', e.target.value)} placeholder="e.g. LJ → LJ-4821-01" /></div>
                <div className="b-field"><label htmlFor="pd-s">Signature line</label><input id="pd-s" maxLength={80} value={cert.signature} onChange={(e) => setCert('signature', e.target.value)} placeholder="e.g. Maya Lumen, founder" /></div>
                <div className="b-field"><label htmlFor="pd-a">Colour</label><input id="pd-a" type="color" value={cert.accent || '#1f2328'} onChange={(e) => setCert('accent', e.target.value)} /></div>
              </div>
              <div className="b-field"><label htmlFor="pd-f">Small print</label><input id="pd-f" maxLength={200} value={cert.footer} onChange={(e) => setCert('footer', e.target.value)} placeholder="e.g. 18k gold, hallmarked." /></div>
              <label className="pd-check"><input type="checkbox" checked={!!cert.show_name} onChange={(e) => setCert('show_name', e.target.checked)} /> Print the customer's name (otherwise “the owner of order #1001”)</label>
              <label className="pd-check"><input type="checkbox" checked={!!cert.show_qr} onChange={(e) => setCert('show_qr', e.target.checked)} /> QR code to verify it online</label>
              <div className="b-field">
                <label htmlFor="pd-logo">Logo (optional)</label>
                {doc.has_logo && !removeLogo && !logo && <p className="pd-file">A logo is saved. <button type="button" className="pd-link" onClick={() => setRemoveLogo(true)}>Remove it</button></p>}
                <input id="pd-logo" type="file" accept="image/png,image/jpeg,image/gif" onChange={(e) => { setLogo(e.target.files[0] || null); setRemoveLogo(false); }} />
                <small className="oo-muted">PNG, JPG or GIF up to 500 KB.</small>
                {err('logo')}
              </div>
            </fieldset>
          )}

          <div className="b-field">
            <label htmlFor="pd-match">Send for</label>
            <select id="pd-match" value={doc.match} onChange={(e) => setDoc((d) => ({ ...d, match: e.target.value, match_items: [] }))}>
              {Object.entries(MATCH).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
            </select>
          </div>
          {(doc.match === 'products' || doc.match === 'collection') && (
            <div className="b-field">
              <div className="pd-chips">{doc.match_items.length ? doc.match_items.map((i) => <span className="pd-chip" key={i.id}>{i.title}{i.variants?.length ? ` (${i.variants.length} variants)` : ''}</span>) : <span className="oo-muted">None chosen</span>}</div>
              <s-button onClick={pick}>{doc.match === 'collection' ? 'Choose a collection' : 'Choose products'}</s-button>
              {err('match_items')}
            </div>
          )}
          {doc.match === 'tag' && <div className="b-field"><label htmlFor="pd-tag">Product tag</label><input id="pd-tag" maxLength={120} value={doc.match_tag || ''} onChange={(e) => set('match_tag', e.target.value)} placeholder="certified-gem" />{err('match_tag')}</div>}

          <div className="b-field">
            <label htmlFor="pd-per">One per</label>
            <select id="pd-per" value={doc.per} onChange={(e) => set('per', e.target.value)}>{Object.entries(PER).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
          </div>
          <label className="pd-check"><input type="checkbox" checked={!!doc.active} onChange={(e) => set('active', e.target.checked)} /> On (workflows send it)</label>

          <div className="pd-actions">
            <s-button variant="primary" onClick={save} loading={busy === 'save' || undefined}>Save</s-button>
            {doc.id && <s-button onClick={showPreview} loading={busy === 'preview' || undefined}>Preview</s-button>}
            {doc.id && <s-button onClick={() => submit(route('app.automation.documents.test', { document: doc.id }))}>Send a test</s-button>}
            {doc.id && <s-button tone="critical" variant="tertiary" onClick={() => submit(route('app.automation.documents.destroy', { document: doc.id }), {}, { confirm: `Delete “${doc.name}”? Links already emailed stop working.` })}>Delete</s-button>}
          </div>
          {err('test')}
          {preview && <iframe className="pd-preview" title="Document preview" src={preview} />}
          <p className="oo-muted pd-note">Documents are stored privately by Growvia, not in Shopify Files. Download links expire after {linkDays} days.</p>
        </section>
      </div>
    </Page>
  );
}
