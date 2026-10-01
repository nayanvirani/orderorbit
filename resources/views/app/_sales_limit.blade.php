{{-- Plan limit notice: the store's sales against its plan, shown when it's close, over or paused. --}}
@php
    $limitStore = request()->attributes->get('store');
    $limitStatus = $limitStore && $limitStore->hasPlanAccess() ? app(\App\Services\Billing\SalesMeter::class)->status($limitStore) : null;
@endphp
@if ($limitStatus && $limitStatus['state'] !== 'ok' && ! request()->routeIs('app.settings.billing'))
    @php
        $money = fn ($v) => '$'.number_format((float) $v);
        $planName = config('shopify.billing.plans.'.$limitStore->effectivePlan().'.name');
    @endphp
    <div class="ob-limit ob-limit-{{ $limitStatus['state'] }}" role="status">
        <div>
            @if ($limitStatus['state'] === 'paused')
                <strong>Your offers are paused.</strong>
                Your store sold {{ $money($limitStatus['sales']) }} in the last 30 days, over the {{ $planName }} plan's {{ $money($limitStatus['limit']) }}. Upgrade and they go live again straight away. Nothing was deleted.
            @elseif ($limitStatus['state'] === 'over')
                <strong>You've passed your plan's sales limit.</strong>
                Your store sold {{ $money($limitStatus['sales']) }} in the last 30 days; {{ $planName }} covers up to {{ $money($limitStatus['limit']) }}. Upgrade by {{ $limitStatus['deadline']->toFormattedDateString() }} to keep your offers live.
            @else
                <strong>You're close to your plan's sales limit.</strong>
                Your store sold {{ $money($limitStatus['sales']) }} of {{ $money($limitStatus['limit']) }} in the last 30 days ({{ $limitStatus['percent'] }}%).
            @endif
        </div>
        <a href="{{ app_route('app.settings.billing') }}">{{ $limitStatus['state'] === 'near' ? 'See plans' : 'Upgrade plan' }}</a>
    </div>
@endif
