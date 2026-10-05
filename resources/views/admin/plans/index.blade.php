@extends('admin.layout')
@section('title', 'Plans & modules')
@section('content')
@php($canManage = \App\Support\AdminRoles::can(auth()->user(), 'plans.manage'))
<div class="ad-head">
    <div><h1>Plans & modules</h1><p>Prices, limits and which modules each plan includes. Give single stores more (or less) on their Plan & access tab.</p></div>
    @if ($canManage)<div class="ad-actions"><a class="ad-btn primary" href="{{ route('admin.plans.create') }}">Add plan</a></div>@endif
</div>
<div class="ad-note warn">
    Shopify charges what's set for each plan under <b>Managed Pricing</b> in the Partner Dashboard. When you change a price here, change it there too:
    this page sets what merchants see in the app and on the pricing page, and what each plan unlocks.
    {{ $customized }} {{ \Illuminate\Support\Str::plural('store', $customized) }} also {{ $customized === 1 ? 'has' : 'have' }} special access set per store.
</div>

<div class="ad-plans">
    @foreach ($plans as $plan)
        <div class="ad-plan {{ $plan->is_active ? '' : 'off' }}">
            <h3>{{ $plan->name }}
                <span>
                    @unless ($plan->is_active)<span class="ad-badge">Off</span>@endunless
                    @unless ($plan->is_public)<span class="ad-badge warn">Hidden</span>@endunless
                </span>
            </h3>
            <div class="price">{{ $plan->price > 0 ? '$'.number_format($plan->price, 2) : 'Free' }} @if ($plan->price > 0)<small>/ month</small>@endif</div>
            <div class="ad-muted">
                Sales limit: {{ $plan->sales_limit === null ? 'unlimited' : '$'.number_format($plan->sales_limit) }}
                · {{ count($plan->modules ?? []) }} of {{ count(\App\Support\Modules::ALL) }} modules
                · {{ $storeCounts[$plan->key] ?? 0 }} {{ \Illuminate\Support\Str::plural('store', $storeCounts[$plan->key] ?? 0) }}
            </div>
            <div class="ad-muted ad-small">Shopify name: <code>{{ $plan->shopify_name }}</code> · key <code>{{ $plan->key }}</code></div>
            @if ($canManage)<div><a class="ad-btn small" href="{{ route('admin.plans.edit', $plan->id) }}">Edit plan</a></div>@endif
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('admin.plans.matrix') }}">
    @csrf
    <section class="ad-card flush">
        <header><h2>Modules by plan</h2><span class="ad-muted">Tick what each plan includes.</span></header>
        <div class="ad-scroll">
            <table class="ad-table ad-matrix">
                <thead><tr><th>Module</th>@foreach ($plans as $plan)<th>{{ $plan->name }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (\App\Support\Modules::grouped() as $group => $modules)
                        <tr class="group"><td colspan="{{ count($plans) + 1 }}">{{ $group }}</td></tr>
                        @foreach ($modules as $key => [$label, , $help])
                            <tr>
                                <td><b>{{ $label }}</b><small>{{ $help }}</small></td>
                                @foreach ($plans as $plan)
                                    <td><input type="checkbox" name="grid[{{ $plan->key }}][{{ $key }}]" value="1" @checked(in_array($key, $plan->modules ?? [], true)) @disabled(! $canManage) aria-label="{{ $label }} on {{ $plan->name }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @if ($canManage)<div class="ad-savebar"><button class="ad-btn primary" type="submit">Save modules</button></div>@endif
</form>
@endsection
