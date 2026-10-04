@extends('layouts.embedded')

@section('title', 'Funnels')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page heading="Funnels">
    @include('app.analytics._nav', ['hideRange' => true, 'lockedTitle' => 'Funnels are on Growth and Scale'])

    <s-section heading="Your funnels">
        @if ($funnels->isEmpty())
            <s-paragraph>A funnel shows how many shoppers go from one step to the next, such as product view → bundle view → add to cart → checkout → purchase, and where they drop off.</s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Funnel</th><th>Steps</th><th>Window</th></tr></thead>
                <tbody>
                    @foreach ($funnels as $f)
                        <tr>
                            <td data-label="Funnel"><s-link href="{{ app_route('app.analytics.funnel', ['funnel' => $f->id, 'days' => $days]) }}">{{ $f->name }}</s-link></td>
                            <td data-label="Steps">{{ collect($f->steps)->map(fn ($s) => \App\Services\Analytics\Events::label($s['event']))->implode(' → ') }}</td>
                            <td data-label="Window">{{ \App\Services\Analytics\Funnels::WINDOWS[$f->within] ?? $f->within }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>

    @if (request()->attributes->get('storeUser')?->can('manage_experiences') && ! $locked)
        <s-section heading="Start from a ready-made funnel">
            <div class="an-presets">
                @foreach (\App\Services\Analytics\Funnels::PRESETS as $key => $preset)
                    <form method="POST" action="{{ app_route('app.analytics.funnels.store') }}" class="an-preset">
                        <input type="hidden" name="preset" value="{{ $key }}">
                        <strong>{{ $preset['name'] }}</strong>
                        <span class="oo-muted oo-small">{{ collect($preset['steps'])->map(fn ($e) => \App\Services\Analytics\Events::label($e))->implode(' → ') }}</span>
                        <s-button type="submit">Add</s-button>
                    </form>
                @endforeach
            </div>
        </s-section>

        <s-section heading="Build your own">
            @include('app.analytics._funnel_form', ['funnel' => null, 'action' => app_route('app.analytics.funnels.store')])
        </s-section>
    @endif
</s-page>
@endsection
