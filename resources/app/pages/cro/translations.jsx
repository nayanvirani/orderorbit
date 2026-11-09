// Offers › Translations (G22): each live offer's text in the store's other languages. Shoppers see
// their language; anything not translated (or changed since) shows in the default language.
import { useState } from 'react';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useRouter, useShared } from '../../router.jsx';
import { ApproveButton } from '../profit/_profit.jsx';

function Offer({ offer, locale, canEdit }) {
  const { submit } = useRouter();
  const [texts, setTexts] = useState(Object.fromEntries(offer.fields.map((f) => [f.path, f.translation])));
  const [busy, setBusy] = useState(false);
  const done = offer.fields.filter((f) => f.translation && !f.outdated).length;
  const save = async () => { setBusy(true); await submit(route('app.cro.translations.save'), { locale, handle: offer.handle, texts }); setBusy(false); };
  return (
    <s-section heading={offer.name}>
      <p className="oo-muted pf-small" style={{ marginTop: 0 }}>{done} of {offer.fields.length} translated</p>
      <div className="tr-grid">
        {offer.fields.map((f) => (
          <label key={f.path} className="tr-row">
            <span className="tr-source">{f.source}{f.outdated && <s-badge tone="warning">Changed since translated</s-badge>}</span>
            {f.source.length > 80
              ? <textarea rows={3} value={texts[f.path]} disabled={!canEdit} onChange={(e) => setTexts({ ...texts, [f.path]: e.target.value })} />
              : <input value={texts[f.path]} disabled={!canEdit} onChange={(e) => setTexts({ ...texts, [f.path]: e.target.value })} />}
          </label>
        ))}
      </div>
      {canEdit && <s-button variant="primary" loading={busy ? '' : undefined} onClick={save}>Save and publish</s-button>}
    </s-section>
  );
}

export default function Translations({ access, languages, locale, offers, locked }) {
  const { visit } = useRouter();
  const { can } = useShared();
  const canEdit = can.manage_experiences && !locked;
  return (
    <Page heading="Translations">
      <p className="oo-muted" style={{ marginTop: 0 }}>Offer text in your store&apos;s other languages. Shoppers see their language; anything not translated shows in your default language.</p>
      {locked && <s-banner tone="info">{locked} <a href={appUrl(route('app.settings.billing'))}>See plans</a></s-banner>}
      {!access ? (
        <s-section>
          <s-paragraph>Growvia needs to see your store&apos;s languages to translate offers.</s-paragraph>
          <ApproveButton scopes={['read_locales']}>Approve Store languages</ApproveButton>
        </s-section>
      ) : !Object.keys(languages).length ? (
        <s-section><s-paragraph>Your store has one language. Add and publish another in Shopify (Settings › Languages) to translate offers.</s-paragraph></s-section>
      ) : (
        <>
          <div className="pf-bar">
            <label className="mr-month"><span>Language</span>
              <select value={locale} onChange={(e) => visit(route('app.cro.translations', { locale: e.target.value }))}>
                {Object.entries(languages).map(([code, name]) => <option key={code} value={code}>{name}</option>)}
              </select>
            </label>
          </div>
          {!offers.length && <div className="pt-empty">No live offers with text yet. Publish an offer to translate it.</div>}
          {offers.map((o) => <Offer key={`${locale}:${o.handle}`} offer={o} locale={locale} canEdit={canEdit} />)}
        </>
      )}
    </Page>
  );
}
