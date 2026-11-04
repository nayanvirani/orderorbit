// Offers › Create an offer (new menu): pick a step of the shopper's visit, then an offer, then its kind.
// Each kind opens today's create page for it.
import { useState } from 'react';
import { Hero, Icon, Page } from '../../components/ui.jsx';
import { appUrl, route } from '../../router.jsx';

export default function CreateOffer({ steps }) {
  const [stepKey, setStep] = useState(steps[0]?.key);
  const step = steps.find((s) => s.key === stepKey) || steps[0];
  const [offerKey, setOffer] = useState(step?.offers[0]?.key);
  const offer = step?.offers.find((o) => o.key === offerKey) || step?.offers[0];
  const pickStep = (s) => { setStep(s.key); setOffer(s.offers[0]?.key); };

  return (
    <Page heading="Create an offer" back={route('app.cro.overview')} backLabel="Offers overview">
      <Hero eyebrow="Offers" icon="plus" title="Pick a step, <em>then an offer.</em>"
        lead="Growvia adds offers at each step of the shopper's visit, from the product page to after they've paid. Each one is explained before you create it.">
        <s-button href={appUrl(route('app.cro.experiences.index'))}>See my offers</s-button>
      </Hero>

      <nav className="co-steps" aria-label="Shopper steps">
        {steps.map((s, i) => (
          <button type="button" key={s.key} className="co-step" aria-pressed={s.key === step.key} onClick={() => pickStep(s)}>
            <span className="co-n">{i + 1}</span>
            <span><b>{s.name}</b><small>{s.goal}</small></span>
          </button>
        ))}
      </nav>

      <div className="co-layout">
        <section className="co-offers" aria-label={`Offers for the ${step.name.toLowerCase()}`}>
          {step.offers.map((o) => (
            <button type="button" key={o.key} className="co-offer" aria-pressed={o.key === offer?.key} onClick={() => setOffer(o.key)}>
              <Icon name={o.icon} tone={o.tone} />
              <span className="co-offer-text">
                <b>{o.label}</b>
                <small>{o.lead}</small>
                {o.was && <em>{o.was}</em>}
              </span>
              <span className="co-count">{o.kinds.length} {o.kinds.length === 1 ? 'kind' : 'kinds'}</span>
            </button>
          ))}
        </section>

        {offer && (
          <section className="co-detail" aria-labelledby="co-title">
            <p className="co-where">Shows on: {offer.where}</p>
            <h2 id="co-title">{offer.label}</h2>
            <p className="co-lead">{offer.lead}</p>
            <h3>{offer.kinds.length > 1 ? 'Choose a kind' : 'What you get'}</h3>
            <ul className="co-kinds">
              {offer.kinds.map((k) => (
                <li key={k.href}>
                  <div>
                    <b>{k.name}</b>
                    <p>{k.what}</p>
                    {k.example && <small>Example: {k.example}</small>}
                  </div>
                  <s-button variant="primary" href={appUrl(k.href)}>Create</s-button>
                </li>
              ))}
            </ul>
          </section>
        )}
      </div>
    </Page>
  );
}
