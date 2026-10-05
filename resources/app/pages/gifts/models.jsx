import { ActionButton } from '../../components/form.jsx';
import { Preview, useRuntime } from '../../components/runtime.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useShared } from '../../router.jsx';

export default function GiftModels({ groups }) {
  const { currency } = useShared();
  const ready = useRuntime();
  const context = { currency, cartTotal: 6000, page: 'product' };
  return (
    <Page heading="Choose a template" back={route('app.gifts.index')} backLabel="Progressive gifts">
      <p className="bx-lead">Start from a ready-made layout. Rewards, thresholds, colours and text are all yours to change.</p>
      {groups.map(({ group, models }) => (
        <div key={group}>
          <h2 className="bx-group">{group}</h2>
          <div className="bx-models">
            {models.map((m) => (
              <div className="bx-model" key={m.key}>
                <div className="bx-model-shot bx-pdp">
                  <span className="bx-pdp-line" /><span className="bx-pdp-line short" />
                  <span className="bx-fake-atc">Add to cart</span>
                  <Preview ready={ready} experience={m.preview} context={context} />
                </div>
                <div className="bx-model-head"><strong>{m.name}</strong></div>
                <p className="bx-muted">{m.description}</p>
                <ActionButton url={route('app.gifts.store')} data={{ model: m.key }}>Use this template</ActionButton>
              </div>
            ))}
          </div>
        </div>
      ))}
    </Page>
  );
}
