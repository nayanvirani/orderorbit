@extends('layouts.embedded')

@section('title', 'Customer journey')

@php
    $money = fn ($v) => money($v, $journey['currency']);
    $icons = ['purchase' => '●', 'automation' => '⚙', 'experience' => '★', 'cart' => '🛒', 'browse' => '·'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $journey['customer'] ? 'Customer '.$journey['customer'] : 'Visitor '.substr($visitor, 0, 8) }}">
    @include('app.analytics._nav', ['hideRange' => true])

    <s-section heading="Summary">
        <dl class="oo-kv">
            <dt>First seen</dt><dd>{{ $journey['first_seen']?->toDayDateTimeString() }} UTC</dd>
            <dt>Orders</dt><dd>{{ $journey['orders'] }}{{ $journey['orders'] > 1 ? ' (repeat customer)' : '' }}</dd>
            <dt>Revenue</dt><dd>{{ $money($journey['revenue']) }}</dd>
            <dt>Sessions</dt><dd>{{ collect($journey['sessions'])->where('automation', false)->count() }}</dd>
            @if (count($journey['visitors']) > 1)<dt>Devices or browsers</dt><dd>{{ count($journey['visitors']) }}</dd>@endif
            @if ($journey['customer'])<dt>Customer</dt><dd><s-link href="shopify://admin/customers/{{ $journey['customer'] }}" target="_top">Open in Shopify</s-link></dd>@endif
        </dl>
    </s-section>

    <s-section heading="Journey">
        <ol class="an-journey">
            @foreach ($journey['sessions'] as $session)
                <li class="an-session {{ $session['automation'] ? 'is-auto' : '' }}">
                    <div class="an-session-head">
                        <strong>{{ $session['automation'] ? 'Automation' : 'Visit' }}</strong>
                        <span class="oo-muted">{{ $session['started']->toDayDateTimeString() }}@if ($session['source']) · from {{ $session['source'] }}@endif @if ($session['device']) · {{ $session['device'] }}@endif</span>
                    </div>
                    <ul>
                        @foreach ($session['events'] as $e)
                            <li class="k-{{ $e['kind'] }}">
                                <span class="an-dot" aria-hidden="true">{{ $icons[$e['kind']] }}</span>
                                <span class="an-time">{{ $e['at']->format('H:i') }}</span>
                                <span>
                                    @if ($e['order_number'])<b>{{ $e['order_number'] > 1 ? 'Repeat purchase (order '.$e['order_number'].')' : 'First purchase' }}</b> · {{ $money($e['value']) }}
                                    @else{{ $e['label'] }}@endif
                                    @if ($e['experience']) · <span class="oo-muted">{{ $experiences[$e['experience']]->name ?? $e['experience'] }}</span>@endif
                                    @if ($e['detail'] && ! $e['order_number']) · <span class="oo-muted">{{ $e['detail'] }}</span>@endif
                                    @if ($e['name'] === 'page_viewed' && $e['page_type']) · <span class="oo-muted">{{ \App\Services\Analytics\Events::PAGE_TYPES[$e['page_type']] ?? $e['page_type'] }}</span>@endif
                                    @if ($e['run_id']) · <s-link href="{{ app_route('app.automation.runs.show', ['run' => $e['run_id']]) }}">Run #{{ $e['run_id'] }}</s-link>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ol>
        <p class="oo-muted oo-small">Only visits where the shopper allowed analytics are shown. Times are UTC.</p>
    </s-section>
</s-page>
@endsection
