@extends('layouts.embedded')

@section('title', $segment->name)

@php
    use App\Services\Audiences\Audiences;
    $err = fn ($k) => $fieldErrors[$k] ?? null;
    $rows = $segment->rules ?? [];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/audiences.css') }}?v={{ filemtime(public_path('css/audiences.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="{{ $segment->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.audiences.segments') }}">Audiences</s-link>
    @include('app.audiences._nav')
    @if ($fieldErrors)<s-banner tone="warning">Saved, but some rules need attention before the segment can be used.</s-banner>@endif
    @if ($segment->archived_at)<s-banner tone="info">This segment is archived. Restore it to use it again.</s-banner>@endif

    <form method="POST" action="{{ app_route('app.audiences.segments.update', ['segment' => $segment->id]) }}" data-segment-form>
        <s-section heading="Segment">
            <div class="b-field {{ $err('name') ? 'b-has-error' : '' }}"><label for="sg-name">Name</label><input id="sg-name" type="text" name="name" value="{{ $segment->name }}" maxlength="80" required>@if ($err('name'))<p class="b-error">{{ $err('name') }}</p>@endif</div>
            <div class="b-field"><label for="sg-desc">Description (optional)</label><input id="sg-desc" type="text" name="description" value="{{ $segment->description }}" maxlength="300"></div>
        </s-section>

        <s-section heading="Who's in it">
            <div class="b-field">
                <label for="sg-match">Shoppers who match</label>
                <select id="sg-match" name="match"><option value="all" @selected($segment->match === 'all')>All of these rules</option><option value="any" @selected($segment->match === 'any')>Any of these rules</option></select>
            </div>
            @if ($err('rules'))<p class="b-error">{{ $err('rules') }}</p>@endif
            <div class="au-rules" data-rules>
                @foreach ($rows as $i => $r)
                    @include('app.audiences._rule_row', ['i' => $i, 'r' => $r, 'error' => $err("rules.{$i}")])
                @endforeach
            </div>
            <template data-rule-template>@include('app.audiences._rule_row', ['i' => '__i__', 'r' => ['field' => 'orders_count', 'op' => 'gte', 'value' => ''], 'error' => null])</template>
            <s-button type="button" data-add-rule>Add rule</s-button>
            <details class="au-help"><summary>Where the data comes from</summary>
                <p class="oo-small">Orders, total spent, average order, days since last order, tags and purchased products come from the signed-in customer's account (guests have 0 orders). Market and device come from the visit. "Used an experience" is remembered on the shopper's device. Everything is worked out on your store; nothing about the shopper is sent to OrderOrbit Space.</p>
            </details>
        </s-section>

        <s-section heading="Members">
            @if (Audiences::countable($segment))
                <p>{{ $segment->member_count !== null ? number_format($segment->member_count).' customers in Shopify match' : 'Not counted yet.' }}@if ($segment->counted_at) <span class="oo-muted oo-small">· counted {{ $segment->counted_at->diffForHumans() }}</span>@endif</p>
            @else
                <p class="oo-muted">Count unavailable: this segment uses browsing or purchase rules that are only known during a visit.</p>
            @endif
        </s-section>

        <div class="oo-inline" style="margin:0 0 16px">
            <s-button type="submit" variant="primary">Save segment</s-button>
        </div>
    </form>

    <s-section heading="Used by">
        @php($any = array_filter($usage))
        @if (! $any)
            <s-paragraph><span class="oo-muted">Not used yet. Target an experience with it (Targeting → Only these segments), add a personalization rule, use it as an A/B test audience, or check it in a workflow condition.</span></s-paragraph>
        @else
            <ul class="au-usage">
                @foreach ($usage['experiences'] as $e)<li>Experience · <s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $e->id]) }}">{{ $e->name }}</s-link></li>@endforeach
                @foreach ($usage['rules'] as $r)<li>Rule · <s-link href="{{ app_route('app.audiences.rules.edit', ['rule' => $r->id]) }}">{{ $r->name }}</s-link></li>@endforeach
                @foreach ($usage['experiments'] as $x)<li>A/B test · <s-link href="{{ app_route('app.experiments.show', ['experiment' => $x->id]) }}">{{ $x->name }}</s-link></li>@endforeach
                @foreach ($usage['workflows'] as $w)<li>Workflow · <s-link href="{{ app_route('app.automation.edit', ['workflow' => $w->id]) }}">{{ $w->name }}</s-link></li>@endforeach
            </ul>
        @endif
        <div class="oo-inline" style="margin-top:12px">
            @if (Audiences::countable($segment))
                <form method="POST" action="{{ app_route('app.audiences.segments.count', ['segment' => $segment->id]) }}"><s-button type="submit" variant="tertiary">Recount members</s-button></form>
            @endif
            <form method="POST" action="{{ app_route('app.audiences.segments.duplicate', ['segment' => $segment->id]) }}"><s-button type="submit" variant="tertiary">Duplicate</s-button></form>
            <form method="POST" action="{{ app_route('app.audiences.segments.archive', ['segment' => $segment->id]) }}" @unless ($segment->archived_at) data-confirm="Archive this segment?" @endunless>
                <s-button type="submit" variant="tertiary" @unless ($segment->archived_at) tone="critical" @endunless>{{ $segment->archived_at ? 'Restore' : 'Archive' }}</s-button>
            </form>
        </div>
    </s-section>
</s-page>
@endsection

@push('scripts')
    <script>
        (() => {
            const FIELDS = @json(Audiences::FIELDS);
            const form = document.querySelector('[data-segment-form]');
            const box = form.querySelector('[data-rules]');
            const tpl = form.querySelector('[data-rule-template]');
            let next = box.children.length;

            // Rebuild a row's operator and value inputs for its field.
            const setField = (row) => {
                const def = FIELDS[row.querySelector('[data-field]').value];
                const op = row.querySelector('[data-op]');
                op.innerHTML = Object.entries(def.ops).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
                row.querySelectorAll('[data-value-type]').forEach((el) => {
                    const on = el.dataset.valueType === def.type || (el.dataset.valueType === 'number' && def.type === 'money');
                    el.hidden = !on;
                    el.querySelectorAll('input, select').forEach((i) => { i.disabled = !on; });
                });
                const select = row.querySelector('[data-value-type="select"] select');
                if (def.type === 'select') select.innerHTML = Object.entries(def.options).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
                row.querySelector('[data-help]').textContent = def.help || '';
            };
            const bindPicker = (row) => {
                const button = row.querySelector('[data-pick]');
                if (!button) return;
                const input = row.querySelector('[data-pick-input]');
                const list = row.querySelector('[data-pick-list]');
                const draw = () => {
                    const items = JSON.parse(input.value || '[]');
                    list.textContent = items.length ? items.map((p) => p.title || p.id).join(', ') : 'No products chosen';
                };
                button.addEventListener('click', async () => {
                    const picked = await shopify.resourcePicker({ type: 'product', multiple: true });
                    if (!picked) return;
                    input.value = JSON.stringify(picked.map((p) => ({ id: p.id, title: p.title })));
                    draw();
                });
                draw();
            };
            box.querySelectorAll('[data-rule]').forEach(bindPicker);
            box.addEventListener('change', (e) => { if (e.target.matches('[data-field]')) setField(e.target.closest('[data-rule]')); });
            box.addEventListener('click', (e) => { if (e.target.closest('[data-remove]')) e.target.closest('[data-rule]').remove(); });
            form.querySelector('[data-add-rule]').addEventListener('click', () => {
                const html = tpl.innerHTML.replaceAll('__i__', String(next++));
                box.insertAdjacentHTML('beforeend', html);
                const row = box.lastElementChild;
                setField(row);
                bindPicker(row);
            });
        })();
    </script>
@endpush
