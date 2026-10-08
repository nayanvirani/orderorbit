import { ActionButton, Field, Form, useForm } from '../../components/form.jsx';
import { date, KeyValue, number, Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';

export default function Privacy({ privacy, events, oldest, policies = [] }) {
  const { can } = useShared();
  const form = useForm(privacy);
  const disabled = !can.manage_settings;

  return (
    <Page heading="Settings">
      <s-section heading="Consent">
        <s-paragraph>Growvia analytics run in a Shopify web pixel, which follows your store's cookie banner and Shopify's Customer Privacy settings: events are only recorded for shoppers who allow analytics. Storefront widgets still show to everyone.</s-paragraph>
        <s-paragraph>No names, emails or addresses are stored. Shoppers are an anonymous visitor id and, when signed in or after buying, a customer number. Shopify's customer data requests and deletion requests are handled automatically.</s-paragraph>
      </s-section>

      <Form onSubmit={() => form.post(route('app.settings.privacy.update'))}>
        <s-section heading="Retention and collection">
          <Field label="Keep analytics events for" help="Older events are deleted every night. Shorter retention also shortens what funnels, journeys and attribution can look back on." className="narrow">
            <select value={form.data.retention_months} onChange={(e) => form.set('retention_months', Number(e.target.value))} disabled={disabled} style={{ maxWidth: 320 }}>
              <option value={3}>3 months</option><option value={6}>6 months</option><option value={13}>13 months (default)</option>
            </select>
          </Field>
          <div className="ui-stack" style={{ marginTop: 12 }}>
            <label className="oo-radio"><input type="checkbox" {...form.bind('browsing_events', { type: 'checkbox' })} disabled={disabled} /><span><strong>Record browsing events</strong><small>Page, product, collection, search, cart and checkout steps, used by Event Explorer, funnels and journeys. Offer views, clicks and orders are always recorded for offer analytics and A/B tests.</small></span></label>
            <label className="oo-radio"><input type="checkbox" {...form.bind('journeys', { type: 'checkbox' })} disabled={disabled} /><span><strong>Link visits to customer numbers</strong><small>Lets customer journeys follow a customer across devices and repeat purchases. When off, no customer numbers are kept.</small></span></label>
          </div>
          {!disabled && <s-button type="submit" variant="primary" loading={form.processing || undefined}>Save</s-button>}
        </s-section>
      </Form>

      <s-section heading="Export and deletion">
        <KeyValue rows={[['Analytics events stored', number(events)], ['Oldest event', date(oldest)]]} />
        {!disabled && (
          <div className="oo-inline" style={{ marginTop: 12 }}>
            <s-button href={appUrl(route('app.settings.privacy.export'))} target="_blank">Export analytics (CSV)</s-button>
            <ActionButton url={route('app.settings.privacy.delete')} tone="critical" variant="tertiary" confirm="Delete every analytics event for your store? Reports, funnels, journeys and A/B test results start again from zero. This can't be undone.">Delete all analytics data</ActionButton>
          </div>
        )}
        <p className="oo-muted oo-small">When you uninstall, Shopify asks apps to delete store data 48 hours later; Growvia deletes it then.</p>
      </s-section>

      {policies.length > 0 && (
        <s-section heading="Terms and policies">
          <KeyValue rows={policies.map((p) => [
            p.title,
            <span key={p.url}>
              {p.accepted ? `Version ${p.accepted.version}, ${p.accepted.via === 'install' ? 'accepted at install' : 'reviewed'} ${date(p.accepted.at)}` : `Version ${p.version}`}
              {' · '}<a href={p.url} target="_blank" rel="noopener noreferrer">Read</a>
            </span>,
          ])} />
        </s-section>
      )}
    </Page>
  );
}
