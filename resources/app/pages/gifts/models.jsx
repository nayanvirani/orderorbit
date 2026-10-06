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
                <div className="tpl-stage"><Preview ready={ready} experience={m.preview} context={context} /></div>
                <div className="tpl-body">
                  <strong className="b-template-name">{m.name}</strong>
                  <p className="bx-muted">{m.description}</p>
                  <div className="tpl-action"><ActionButton variant="primary" inlineSize="fill" url={route('app.gifts.store')} data={{ model: m.key }}>Use this template</ActionButton></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}
    </Page>
  );
}
