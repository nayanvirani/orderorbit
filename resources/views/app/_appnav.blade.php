{{-- Shopify's app menu (one level; each section has its own sidebar). Without a plan, only Billing. --}}
@if (! request()->attributes->get('store')?->hasPlanAccess())
<s-app-nav>
    <s-link href="{{ app_route('app.settings.billing') }}" rel="home">Choose a plan</s-link>
</s-app-nav>
@else
<s-app-nav>
    <s-link href="{{ app_route('app.dashboard') }}" rel="home">Home</s-link>
    @if (! request()->attributes->get('store')?->goal)
        <s-link href="{{ app_route('app.onboarding') }}">Get started</s-link>
    @endif
    @if (\App\Experiences\Registry::newMenu())
        {{-- The new menu (flag new_menu): same pages, new names; A/B tests and Audiences are one item. --}}
        <s-link href="{{ app_route('app.cro.overview') }}">Offers</s-link>
        @if (\App\Support\Features::enabled('profit', request()->attributes->get('store')))<s-link href="{{ app_route('app.profit.dashboard') }}">Profit</s-link>@endif
        <s-link href="{{ app_route('app.analytics') }}">Insights</s-link>
        <s-link href="{{ app_route('app.automation.index') }}">Automation</s-link>
        <s-link href="{{ app_route('app.experiments.index') }}">Audiences and tests</s-link>
    @else
        <s-link href="{{ app_route('app.cro.overview') }}">CRO</s-link>
        @if (\App\Support\Features::enabled('profit', request()->attributes->get('store')))<s-link href="{{ app_route('app.profit.dashboard') }}">Profit</s-link>@endif
        <s-link href="{{ app_route('app.automation.index') }}">Automation</s-link>
        <s-link href="{{ app_route('app.experiments.index') }}">A/B tests</s-link>
        <s-link href="{{ app_route('app.audiences.segments') }}">Audiences</s-link>
        <s-link href="{{ app_route('app.analytics') }}">Analytics</s-link>
    @endif
    <s-link href="{{ app_route('app.settings.store') }}">Settings</s-link>
    <s-link href="{{ app_route('app.support.index') }}">Support</s-link>
</s-app-nav>
@endif
