import { Upgrade } from '../../components/ui.jsx';

/** Errors passed back in the URL, and the plan note. */
export default function AudienceNotes({ enabled, advanced, error }) {
  return (
    <>
      {error && <s-banner tone="critical">{error}</s-banner>}
      {!enabled ? (
        <Upgrade feature="personalization">You can build segments and rules now. They start working on your store once your plan includes personalization.</Upgrade>
      ) : advanced === false && (
        <Upgrade feature="personalization_advanced">Your plan targets by country, sign-in, customer tags, traffic source and offer activity. Upgrade to also target by order history and spend (VIP, returning, lifecycle), products bought, device and cart value.</Upgrade>
      )}
    </>
  );
}
