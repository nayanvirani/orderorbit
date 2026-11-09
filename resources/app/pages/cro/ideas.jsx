// Offers › Offer ideas (G20): products your customers buy together (from orders, no AI) as bundle
// ideas, with offer text when you ask (Growvia's own, or AI when it's on). Nothing is created or saved for you.
import { Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';
import AiCopy from '../../components/AiCopy.jsx';

export default function OfferIdeas({ pairs, minOrders, locked }) {
  return (
    <Page heading="Offer ideas">
      <p className="oo-muted" style={{ marginTop: 0 }}>Products your customers buy together, from the last 90 days of orders. A bundle of a pair often lifts the average order.</p>
      {locked && <s-banner tone="info">{locked} <a href={appUrl(route('app.settings.billing'))}>See plans</a></s-banner>}
      {!locked && !pairs.length && <div className="pt-empty">No pairs bought together at least {minOrders} times yet.</div>}
      {pairs.map((p) => (
        <s-section key={`${p.a.id}:${p.b.id}`} heading={`${p.a.name} + ${p.b.name}`}>
          <p className="oo-muted" style={{ marginTop: 0 }}>Bought together in {p.orders} orders ({p.share}% of the orders with the less popular one).</p>
          <div className="pf-bar"><s-button variant="primary" href={appUrl(route('app.bundles.types'))}>Create a bundle</s-button></div>
          <AiCopy context={{ type: 'bundles', products: [p.a.name, p.b.name] }} fields={{ headline: true, subheadline: true, cta: true }}
            onPick={(o) => navigator.clipboard?.writeText(`${o.headline}\n${o.subheadline}\n${o.cta}`).then(() => window.shopify?.toast?.show('Copied. Paste it into the bundle.'))} />
        </s-section>
      ))}
    </Page>
  );
}
