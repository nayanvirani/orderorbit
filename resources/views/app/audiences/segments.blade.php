@extends('layouts.embedded')

@section('title', 'Segments')

@php
    use App\Services\Audiences\Audiences;
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    $describe = fn ($s) => collect($s->rules)->map(fn ($r) => (Audiences::FIELDS[$r['field']]['label'] ?? $r['field']).' '.(Audiences::FIELDS[$r['field']]['ops'][$r['op']] ?? $r['op']).' '.(is_array($r['value']) ? (collect($r['value'])->pluck('title')->filter()->implode(', ') ?: count($r['value']).' products') : (Audiences::FIELDS[$r['field']]['options'][$r['value']] ?? $r['value'])))->implode($s->match === 'any' ? ' or ' : ' and ');
@endphp

@section('content')
<s-page inlineSize="large" heading="Audiences">
    <x-app.hero eyebrow="Audiences & Personalization" icon="users" tone="analytics" title="The right offer for <em>each shopper.</em>"
        lead="Segments group shoppers by what they've bought, spent and done. Use them to target experiences, run rules that show, swap or hide experiences, and in A/B tests and workflows.">
        <s-button href="{{ route('site.docs', 'personalization') }}" target="_blank" variant="tertiary">View documentation</s-button>
    </x-app.hero>
    @include('app.audiences._nav')

    @if ($canManage && ! $archived)
        <s-section heading="Start from a ready-made segment">
            <div class="au-templates">
                @foreach (Audiences::TEMPLATES as $key => $t)
                    <form method="POST" action="{{ app_route('app.audiences.segments.store') }}" class="au-template">
                        <input type="hidden" name="template" value="{{ $key }}">
                        <strong>{{ $t['name'] }}</strong>
                        <span class="oo-muted oo-small">{{ $t['description'] }}</span>
                        <s-button type="submit">Add</s-button>
                    </form>
                @endforeach
                <form method="POST" action="{{ app_route('app.audiences.segments.store') }}" class="au-template au-blank">
                    <strong>Custom segment</strong>
                    <span class="oo-muted oo-small">Combine orders, spend, tags, products, market, device and experiences.</span>
                    <s-button type="submit" variant="primary">Create</s-button>
                </form>
            </div>
        </s-section>
    @endif

    <s-section heading="{{ $archived ? 'Archived segments' : 'Your segments' }}">
        <p class="oo-small"><a href="{{ app_route('app.audiences.segments', ['archived' => $archived ? null : 1]) }}">{{ $archived ? '← Back to active segments' : 'Show archived segments' }}</a></p>
        @if ($segments->isEmpty())
            <s-paragraph><span class="oo-muted">{{ $archived ? 'Nothing archived.' : 'No segments yet. Add a ready-made one above.' }}</span></s-paragraph>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack">
                    <thead><tr><th>Segment</th><th>Definition</th><th>Members</th><th>Used by</th><th>Updated</th></tr></thead>
                    <tbody>
                        @foreach ($segments as $s)
                            @php($u = $usage[$s->id])
                            <tr>
                                <td data-label="Segment">@if ($canManage)<s-link href="{{ app_route('app.audiences.segments.edit', ['segment' => $s->id]) }}">{{ $s->name }}</s-link>@else{{ $s->name }}@endif</td>
                                <td data-label="Definition" class="oo-small">{{ $describe($s) }}</td>
                                <td data-label="Members">{{ $s->member_count !== null ? number_format($s->member_count).' customers' : (Audiences::countable($s) ? 'Not counted yet' : 'Unavailable') }}</td>
                                <td data-label="Used by">{{ collect(['experiences' => 'experience', 'rules' => 'rule', 'experiments' => 'A/B test', 'workflows' => 'workflow'])->map(fn ($label, $k) => count($u[$k]) ? count($u[$k]).' '.\Illuminate\Support\Str::plural($label, count($u[$k])) : null)->filter()->implode(', ') ?: '—' }}</td>
                                <td data-label="Updated">{{ $s->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="oo-muted oo-small">Member counts come from Shopify when every rule is a customer field (orders, total spent, tags). Segments with browsing rules (device, experiences, products, market) are worked out live on your store, so there's no count.</p>
        @endif
    </s-section>
</s-page>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('css/audiences.css') }}?v={{ filemtime(public_path('css/audiences.css')) }}">
@endpush
