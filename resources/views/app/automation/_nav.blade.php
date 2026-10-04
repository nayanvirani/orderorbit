{{-- Automation tabs, and the plan note when workflows can't run yet. --}}
@unless ($automationOn)
    <s-banner tone="info" heading="Workflows run on the Scale plan">
        <s-paragraph>You can build and test workflows now. Publishing them, so they run on real orders, needs the Scale plan.</s-paragraph>
        <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
    </s-banner>
@endunless
