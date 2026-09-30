@extends('layouts.embedded')

@section('title', 'Progressive gifts')

@php($canManage = request()->attributes->get('storeUser')?->can('manage_experiences'))

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page heading="Progressive gifts">
    @if ($canManage)
        <s-button slot="primary-action" variant="primary" href="{{ app_route('app.gifts.models') }}">Create progressive gifts</s-button>
    @endif

    <x-app.hero eyebrow="Progressive gifts" title="Rewards that grow <em>with the cart.</em>"
        lead="Free gifts, free shipping and order discounts that unlock by cart value or item count, in one progress bar. Rewards apply automatically at checkout." />

    <s-section>
        @if ($items->isEmpty())
            <x-app.empty title="No progressive gifts yet" text="Pick a layout, set your milestones and publish. It shows under your add to cart button.">
                @if ($canManage)<s-button variant="primary" href="{{ app_route('app.gifts.models') }}">Create progressive gifts</s-button>@endif
            </x-app.empty>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack bx-table">
                    <thead><tr><th>Status</th><th>Title</th><th>Unlocks by</th><th>Rewards</th><th>Views</th><th>Orders</th><th>Revenue</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php($c = $item->draft_config)
                            @php($live = $item->status === 'published')
                            <tr>
                                <td data-label="Status">
                                    @if ($canManage)
                                        <form method="POST" action="{{ app_route('app.gifts.toggle', ['gift' => $item->id]) }}" @if ($live) data-confirm="Pause these rewards? They stop showing and stop applying at checkout." @endif>
                                            <button type="submit" class="bx-switch {{ $live ? 'on' : '' }}" role="switch" aria-checked="{{ $live ? 'true' : 'false' }}" aria-label="{{ $live ? 'Pause' : 'Publish' }} {{ $item->name }}"><i></i></button>
                                        </form>
                                    @else
                                        @include('app.cro._status', ['experience' => $item])
                                    @endif
                                </td>
                                <td data-label="Title">
                                    <a class="bx-title" href="{{ $canManage ? app_route('app.gifts.edit', ['gift' => $item->id]) : app_route('app.cro.experiences.show', ['experience' => $item->id]) }}">{{ $item->name }}</a>
                                    <span class="bx-sub">{{ \App\Experiences\GiftSchema::LAYOUTS[$c['settings']['layout'] ?? 'classic'] ?? '' }}</span>
                                </td>
                                <td data-label="Unlocks by">{{ ($c['settings']['unlock'] ?? 'value') === 'count' ? 'Item count' : 'Cart value' }}</td>
                                <td data-label="Rewards">{{ collect($c['milestones'] ?? [])->pluck('label')->implode(' · ') }}</td>
                                @php($st = $stats[$item->handle] ?? ['views' => 0, 'orders' => 0, 'revenue' => 0])
                                <td data-label="Views">{{ number_format($st['views']) }}</td>
                                <td data-label="Orders">{{ number_format($st['orders']) }}</td>
                                <td data-label="Revenue">{{ money($st['revenue'], $store->currency) }}</td>
                                <td data-label="Actions">
                                    <div class="bx-actions">
                                        @if ($canManage)
                                            <a class="bx-icon" href="{{ app_route('app.gifts.edit', ['gift' => $item->id]) }}" title="Edit" aria-label="Edit">
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m13.6 3.4 3 3-9.1 9.1-3.7.7.7-3.7 9.1-9.1Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                                            </a>
                                            <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $item->id, 'action' => 'archive']) }}" data-confirm="Archive these rewards? They stop showing and stop applying at checkout.">
                                                <button type="submit" class="bx-icon danger" title="Archive" aria-label="Archive">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14v3H3zM4.5 8v8h11V8M8 11h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </s-section>
</s-page>
@endsection
