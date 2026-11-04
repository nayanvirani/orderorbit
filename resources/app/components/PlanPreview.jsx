// Free read-only preview (G29): shown in an editor when the plan can't publish what's being built.
// The draft saves as usual; the Publish button names the plan, and a card lists what that plan adds.
import { appUrl, route } from '../router.jsx';

const price = (v) => (v > 0 ? `$${Number(v).toFixed(2)} a month` : 'free');

/** The banner at the top of the editor. */
export function PreviewBanner({ preview }) {
  if (!preview) return null;
  if (preview.off) {
    return <s-banner tone="warning">{preview.feature} is switched off for your store. Your draft is saved; contact Growvia support to turn it on.</s-banner>;
  }
  return (
    <s-banner tone="info" heading="Preview with sample data">
      <s-paragraph>Build and save this as a draft. Turning it on needs the {preview.plan || 'right'} plan.</s-paragraph>
      {preview.plan && <s-button slot="secondary-actions" href={appUrl(route('app.settings.billing'))}>See {preview.plan}, {price(preview.price)}</s-button>}
    </s-banner>
  );
}

/** The Publish button's text while previewing. */
export function previewLabel(preview, label) {
  return preview ? (preview.plan ? `Turn on · needs ${preview.plan}` : 'Turn on · switched off') : label;
}

/** "Growth also unlocks …" next to the Publish step. */
export function UnlockCard({ preview }) {
  if (!preview || !preview.plan) return null;
  return (
    <div className="pv-card">
      <b>{preview.plan} also unlocks</b>
      <ul>{[preview.feature, ...preview.also].map((l) => <li key={l}>{l}</li>)}</ul>
      <s-button variant="primary" href={appUrl(route('app.settings.billing'))}>Upgrade to {preview.plan}</s-button>
      <p className="b-muted">Your draft is kept and goes live when you turn it on.</p>
    </div>
  );
}
