import { ActionButton } from '../../components/form.jsx';
import { Page } from '../../components/ui.jsx';
import { route } from '../../router.jsx';
import { PlanNote } from './_shared.jsx';

export default function Templates({ templates, automationOn }) {
  return (
    <Page heading="Automation templates" back={route('app.automation.index')} backLabel="Automation">
      <PlanNote automationOn={automationOn} />
      <s-section heading="Start from a template">
        <div className="ob-plans">
          {templates.map((t) => (
            <div className="ob-plan" key={t.key}>
              <h3>{t.name}</h3>
              <p className="oo-muted" style={{ margin: 0 }}>{t.description}</p>
              <ul>
                <li>Starts when: {t.trigger}</li>
                {t.steps.map((s, i) => <li key={i}>{s}</li>)}
              </ul>
              <ActionButton variant="primary" url={route('app.automation.store')} data={{ template: t.key }}>Use template</ActionButton>
            </div>
          ))}
        </div>
      </s-section>
    </Page>
  );
}
