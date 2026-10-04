
@if (request('error'))<s-banner tone="critical">{{ request('error') }}</s-banner>@endif
@unless ($enabled)
    <s-banner tone="info" heading="Personalization runs on the Scale plan">
        <s-paragraph>You can build segments and rules now. They start working on your store, in A/B tests and in workflows once you're on Scale.</s-paragraph>
        <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
    </s-banner>
@endunless
