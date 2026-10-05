import { ActionButton } from '../../components/form.jsx';
import { Page } from '../../components/ui.jsx';
import { appUrl, route, useShared } from '../../router.jsx';
import AnalyticsNav from './_nav.jsx';
import FunnelForm from './_funnelForm.jsx';

export default function FunnelList(props) {
  const { funnels, presets, days, locked } = props;
  const { can } = useShared();
  return (
    <Page heading="Funnels" back={route('app.analytics', { days })} backLabel="Analytics">
      <AnalyticsNav {...props} hideRange />
      <s-section heading="Your funnels">
        {!funnels.length ? (
          <s-paragraph>A funnel shows how many shoppers go from one step to the next, such as product view → bundle view → add to cart → checkout → purchase, and where they drop off.</s-paragraph>
        ) : (
          <table className="oo-table stack">
            <thead><tr><th>Funnel</th><th>Steps</th><th>Window</th></tr></thead>
            <tbody>
              {funnels.map((f) => (
                <tr key={f.id}>
                  <td data-label="Funnel"><s-link href={appUrl(route('app.analytics.funnel', { funnel: f.id, days }))}>{f.name}</s-link></td>
                  <td data-label="Steps">{f.steps}</td>
                  <td data-label="Window">{f.window}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </s-section>

      {can.manage_experiences && !locked && (
        <>
          <s-section heading="Start from a ready-made funnel">
            <div className="an-presets">
              {presets.map((p) => (
                <div className="an-preset" key={p.key}>
                  <strong>{p.name}</strong>
                  <span className="oo-muted oo-small">{p.steps}</span>
                  <ActionButton url={route('app.analytics.funnels.store')} data={{ preset: p.key }}>Add</ActionButton>
                </div>
              ))}
            </div>
          </s-section>
          <s-section heading="Build your own">
            <FunnelForm {...props} funnel={null} action={route('app.analytics.funnels.store')} />
          </s-section>
        </>
      )}
    </Page>
  );
}
