<s-app-nav>
    <s-link href="{{ app_route('app.dashboard') }}" rel="home">Home</s-link>
    @if (! request()->attributes->get('store')?->goal)
        <s-link href="{{ app_route('app.onboarding') }}">Get started</s-link>
    @endif
    {{-- Shopify's app menu has one level; each section has its own sub-menu on its pages. --}}
    <s-link href="{{ app_route('app.cro.overview') }}">CRO</s-link>
    <s-link href="{{ app_route('app.automation.index') }}">Automation</s-link>
    <s-link href="{{ app_route('app.experiments.index') }}">A/B tests</s-link>
    <s-link href="{{ app_route('app.audiences.segments') }}">Audiences</s-link>
    <s-link href="{{ app_route('app.analytics') }}">Analytics</s-link>
    <s-link href="{{ app_route('app.settings.store') }}">Settings</s-link>
    <s-link href="{{ app_route('app.support.index') }}">Support</s-link>
</s-app-nav>
