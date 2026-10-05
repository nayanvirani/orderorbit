import { appUrl, route } from '../../router.jsx';

/** Errors passed back in the URL, and the plan note. */
export default function AudienceNotes({ enabled, error }) {
  return (
    <>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {!enabled && (
        <s-banner tone="info" heading="Personalization runs on the Scale plan">
          <s-paragraph>You can build segments and rules now. They start working on your store, in A/B tests and in workflows once you're on Scale.</s-paragraph>
          <s-button slot="secondary-actions" href={appUrl(route('app.settings.billing'))}>See plans</s-button>
        </s-banner>
      )}
    </>
  );
}
