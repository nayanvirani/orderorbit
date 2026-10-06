import { useRuntime } from '../../components/runtime.jsx';
import { TemplateCard, TemplateGrid } from '../../components/templates.jsx';
import { Page } from '../../components/ui.jsx';
import { route, useRouter, useShared } from '../../router.jsx';

export default function GiftModels({ groups }) {
  const { currency } = useShared();
  const { submit } = useRouter();
  const ready = useRuntime();
  const context = { currency, cartTotal: 6000, page: 'product' };
  return (
    <Page heading="Choose a template" back={route('app.gifts.index')} backLabel="Progressive gifts">
      <p className="bx-lead">Start from a ready-made layout. Rewards, thresholds, colours and text are all yours to change.</p>
      {groups.map(({ group, models }) => (
        <div key={group}>
          <h2 className="bx-group">{group}</h2>
          <TemplateGrid>
            {models.map((m) => (
              <TemplateCard key={m.key} preview={m.preview} context={context} ready={ready} name={m.name} description={m.description}
                onUse={() => submit(route('app.gifts.store'), { model: m.key })} />
            ))}
          </TemplateGrid>
        </div>
      ))}
    </Page>
  );
}
