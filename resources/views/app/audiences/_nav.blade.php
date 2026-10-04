<nav class="oo-tabs" aria-label="Audiences">
    <a href="{{ app_route('app.audiences.segments') }}" @if (request()->routeIs('app.audiences.segments*')) aria-current="page" @endif>Segments</a>
    <a href="{{ app_route('app.audiences.rules') }}" @if (request()->routeIs('app.audiences.rules*')) aria-current="page" @endif>Personalization rules</a>
</nav>
@if (request('error'))<s-banner tone="critical">{{ request('error') }}</s-banner>@endif
@unless ($enabled)
    <s-banner tone="info" heading="Personalization runs on the Scale plan">
        <s-paragraph>You can build segments and rules now. They start working on your store, in A/B tests and in workflows once you're on Scale.</s-paragraph>
        <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
    </s-banner>
@endunless
