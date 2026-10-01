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
                <strong>All features are stopped.</strong>
                Your store passed the {{ $planName }} plan's {{ $money($limitStatus['limit']) }} sales limit. Upgrade and everything goes live again straight away. Nothing was deleted.
            @elseif ($limitStatus['state'] === 'over')
                <strong>Upgrade required: you've passed your plan's sales limit.</strong>
                {{ $planName }} covers up to {{ $money($limitStatus['limit']) }} per cycle. Upgrade by {{ $limitStatus['deadline']->toFormattedDateString() }} or every feature stops on your store.
            @else
                <strong>You're close to your plan's sales limit.</strong>
                Your store sold {{ $money($limitStatus['sales']) }} of {{ $money($limitStatus['limit']) }} this cycle ({{ $limitStatus['percent'] }}%). Past the limit you'll have {{ config('shopify.billing.grace_days') }} days to upgrade.
            @endif
        </div>
        <a href="{{ app_route('app.settings.billing') }}">{{ $limitStatus['state'] === 'near' ? 'See plans' : 'Upgrade plan' }}</a>
    </div>
@endif
