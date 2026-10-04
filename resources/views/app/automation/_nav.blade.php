{{-- Automation tabs, and the plan note when workflows can't run yet. --}}
<nav class="oo-tabs" aria-label="Automation">
    @foreach ([['app.automation.index', 'Workflows'], ['app.automation.templates', 'Templates'], ['app.automation.runs', 'Runs'], ['app.automation.inbox', 'Inbox'.($openTasks ? ' ('.$openTasks.')' : '')], ['app.automation.emails', 'Emails']] as [$route, $label])
        <a href="{{ app_route($route) }}" @if (request()->routeIs($route, $route.'.*') || ($route === 'app.automation.index' && request()->routeIs('app.automation.edit'))) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@unless ($automationOn)
    <s-banner tone="info" heading="Workflows run on the Scale plan">
        <s-paragraph>You can build and test workflows now. Publishing them, so they run on real orders, needs the Scale plan.</s-paragraph>
        <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
    </s-banner>
@endunless
