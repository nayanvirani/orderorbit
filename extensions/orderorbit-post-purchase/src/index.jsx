/**
 * Growvia post-purchase funnel. Shown after payment and before the Thank You page:
 * one offer at a time, added to the paid order in one click. Growvia decides the offer
 * and signs the order change, using the token Shopify signs for each purchase.
 */
import React, { useEffect, useState } from 'react';
import {
  extend,
  render,
  useExtensionInput,
  BlockStack,
  Button,
  CalloutBanner,
  Heading,
  Image,
  Layout,
  Separator,
  Text,
  TextBlock,
  TextContainer,
  View,
} from '@shopify/post-purchase-ui-extensions-react';

const APP_URL = 'https://growvia.orderorbit.space';

function purchaseFacts(inputData) {
  const purchase = inputData.initialPurchase || {};
  return {
    token: inputData.token,
    shop: inputData.shop && inputData.shop.domain,
    reference_id: purchase.referenceId,
    total: Number((purchase.totalPriceSet && purchase.totalPriceSet.shopMoney && purchase.totalPriceSet.shopMoney.amount) || 0),
    products: (purchase.lineItems || []).map((l) => l.product && l.product.id).filter(Boolean),
  };
}

function post(path, body) {
  return fetch(APP_URL + '/api/post-purchase/' + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).then((r) => r.json());
}

// Decide before payment completes whether there is an offer, and keep it for the page.
extend('Checkout::PostPurchase::ShouldRender', async ({ inputData, storage }) => {
  try {
    const { funnel } = await post('offer', purchaseFacts(inputData));
    if (!funnel || !funnel.offers || !funnel.offers.length) return { render: false };
    await storage.update({ funnel });
    return { render: true };
  } catch (e) {
    return { render: false };
  }
});

render('Checkout::PostPurchase::Render', () => <App />);

function money(amount, currency) {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(amount || 0));
  } catch (e) {
    return String(amount);
  }
}

function App() {
  const { storage, inputData, calculateChangeset, applyChangeset, done } = useExtensionInput();
  const funnel = storage.initialData && storage.initialData.funnel;
  const [step, setStep] = useState(0);
  const [calculated, setCalculated] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const offer = funnel && funnel.offers[step];
  const facts = purchaseFacts(inputData);

  // Ask Shopify what accepting would cost (taxes and shipping included) before showing the button.
  useEffect(() => {
    if (!offer) return;
    setCalculated(null);
    const change = { type: 'add_variant', variantId: Number(offer.variant_id), quantity: 1 };
    if (offer.discount_percent > 0) change.discount = { value: offer.discount_percent, valueType: 'percentage', title: offer.discount_percent + '% off' };
    calculateChangeset({ changes: [change] }).then((result) => setCalculated(result.calculatedPurchase || null)).catch(() => setCalculated(null));
  }, [step]);

  if (!offer) {
    done();
    return null;
  }

  const currency = calculated && calculated.totalOutstandingSet ? calculated.totalOutstandingSet.presentmentMoney.currencyCode : undefined;
  const due = calculated && calculated.totalOutstandingSet ? calculated.totalOutstandingSet.presentmentMoney.amount : null;
  const line = calculated && calculated.updatedLineItems && calculated.updatedLineItems[0];
  const original = line && line.totalPriceSet ? line.totalPriceSet.presentmentMoney.amount : null;
  const discounted = line && line.totalPriceSet && line.discountedTotalPriceSet ? line.discountedTotalPriceSet.presentmentMoney.amount : original;

  async function accept() {
    setBusy(true);
    setError('');
    try {
      const { token, error: problem } = await post('sign', Object.assign({}, facts, { experience_id: funnel.experience_id, step: offer.step }));
      if (!token) throw new Error(problem || 'unavailable');
      await applyChangeset(token);
      done();
    } catch (e) {
      setError('This offer could not be added. Your original order is complete.');
      setBusy(false);
    }
  }

  function decline() {
    post('decline', Object.assign({}, facts, { experience_id: funnel.experience_id, step: offer.step })).catch(() => {});
    if (step + 1 < funnel.offers.length) setStep(step + 1); else done();
  }

  const premium = funnel.style === 'premium';
  const minimal = funnel.style === 'minimal';

  return (
    <BlockStack spacing="loose">
      {premium ? <CalloutBanner title={offer.headline}>{funnel.message}</CalloutBanner> : null}
      <Layout
        maxInlineSize={0.95}
        media={[
          { viewportSize: 'small', sizes: [1, 30, 1] },
          { viewportSize: 'medium', sizes: [300, 30, 0.5] },
          { viewportSize: 'large', sizes: [400, 30, 0.33] },
        ]}
      >
        <View>{offer.image && !minimal ? <Image source={offer.image} description={offer.product_title} /> : null}</View>
        <View />
        <BlockStack spacing="xloose">
          <TextContainer>
            {!premium ? <Heading>{offer.headline}</Heading> : null}
            <Heading level={2}>{offer.product_title}{offer.variant_title ? ' · ' + offer.variant_title : ''}</Heading>
            {!premium && funnel.message ? <TextBlock>{funnel.message}</TextBlock> : null}
          </TextContainer>
          <BlockStack spacing="tight">
            {original != null && discounted != null && Number(discounted) < Number(original) ? (
              <TextBlock>
                <Text role="deletion">{money(original, currency)}</Text> <Text emphasized>{money(discounted, currency)}</Text> · {offer.discount_percent}% off
              </TextBlock>
            ) : discounted != null ? <TextBlock emphasized>{money(discounted, currency)}</TextBlock> : null}
            <Separator />
            {due != null ? <TextBlock>Charged now to your original payment: {money(due, currency)}</TextBlock> : null}
          </BlockStack>
          {error ? <TextBlock appearance="critical">{error}</TextBlock> : null}
          <BlockStack spacing="tight">
            <Button submit onPress={accept} loading={busy} disabled={calculated == null}>
              {funnel.accept_text}{due != null ? ' · ' + money(due, currency) : ''}
            </Button>
            <Button subdued onPress={decline} disabled={busy}>{funnel.decline_text}</Button>
          </BlockStack>
        </BlockStack>
      </Layout>
    </BlockStack>
  );
}
