// Templates (automation redesign): follow-ups Shopify and review apps don't already offer, filtered by category.
import { useState } from 'react';
import { ActionButton } from '../../components/form.jsx';
import { Page } from '../../components/ui.jsx';
import { route } from '../../router.jsx';
import { PlanNote, Pills } from './_shared.jsx';

const CATEGORIES = [['all', 'All'], ['vip', 'VIP and loyalty'], ['retention', 'Retention'], ['care', 'Customer care'], ['growvia', 'Growvia events']];

export default function FlowTemplates({ templates, automationOn }) {
  const [category, setCategory] = useState('all');
  const shown = templates.filter((t) => category === 'all' || t.category === category);
  return (
    <Page heading="Templates" back={route('app.automation.index')} backLabel="Workflows">
      <PlanNote automationOn={automationOn} />
      <div className="fl-list-head">
        <p className="oo-muted">Ready workflows for what Shopify Flow, Shopify Messaging and review apps don't do on their own. Change anything before you publish.</p>
        <ActionButton url={route('app.automation.store')}>Start from scratch</ActionButton>
      </div>
      <div className="fl-filters" role="group" aria-label="Category">
        {CATEGORIES.filter(([k]) => k === 'all' || templates.some((t) => t.category === k)).map(([k, l]) => (
          <button type="button" key={k} aria-pressed={category === k} onClick={() => setCategory(k)}>{l}</button>
        ))}
      </div>
      <div className="fl-cards">
        {shown.map((t) => (
          <div className="fl-card" key={t.key}>
            <div className="fl-card-top">
              <span className="fl-cat">{CATEGORIES.find(([k]) => k === t.category)?.[1]}</span>
              {t.in_use && <span className="fl-chip on">In use</span>}
            </div>
            <h3>{t.name}</h3>
            <p>{t.description}</p>
            <Pills pills={t.pills} max={5} />
            <div className="fl-card-foot">
              <ActionButton variant="primary" url={route('app.automation.store')} data={{ template: t.key }}>Use template</ActionButton>
            </div>
          </div>
        ))}
      </div>
    </Page>
  );
}
