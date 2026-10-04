@extends('layouts.embedded')

@section('title', $rule->exists ? $rule->name : 'New rule')

@php
    use App\Services\Audiences\Audiences;
    $err = fn ($k) => $fieldErrors[$k] ?? null;
    $c = $rule->conditions ?? [];
    $action = $rule->exists ? app_route('app.audiences.rules.update', ['rule' => $rule->id]) : app_route('app.audiences.rules.store');
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/audiences.css') }}?v={{ filemtime(public_path('css/audiences.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $rule->exists ? $rule->name : 'New personalization rule' }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.audiences.rules') }}">Rules</s-link>
    @include('app.audiences._nav')
    @if ($fieldErrors)<s-banner tone="warning">Fix the highlighted fields.</s-banner>@endif

    <form method="POST" action="{{ $action }}" data-rule-form>
        <s-section heading="1. Which experience">
            <div class="b-field {{ $err('experience_id') ? 'b-has-error' : '' }}">
                <label for="ru-exp">Experience</label>
                <select id="ru-exp" name="experience_id" data-experience>
                    <option value="">Choose an experience</option>
                    @foreach ($experiences as $e)<option value="{{ $e->id }}" @selected($rule->experience_id === $e->id)>{{ $e->name }} · {{ \App\Experiences\Registry::type($e->type)['label'] }}{{ $e->status === 'published' ? '' : ' (not published)' }}</option>@endforeach
                </select>
                @if ($err('experience_id'))<p class="b-error">{{ $err('experience_id') }}</p>@endif
            </div>
        </s-section>

        <s-section heading="2. For whom">
            <p class="oo-muted">Shoppers in any of the chosen segments, who also match the live conditions you set.</p>
            <div class="b-field {{ $err('segments') ? 'b-has-error' : '' }}">
                <span class="b-label">Segments</span>
                @if ($segments->isEmpty())
                    <p class="b-help">No segments yet. <a href="{{ app_route('app.audiences.segments') }}">Create one</a>, or use only the conditions below.</p>
                @else
                    <div class="b-checks">
                        @foreach ($segments as $s)<label><input type="checkbox" name="segments[]" value="{{ $s->id }}" @checked(in_array($s->id, $rule->segments ?? [], true))> {{ $s->name }}</label>@endforeach
                    </div>
                @endif
                @if ($err('segments'))<p class="b-error">{{ $err('segments') }}</p>@endif
            </div>
            <div class="oo-form-row">
                <label class="oo-field">Device<select name="conditions[device]"><option value="">Any</option><option value="mobile" @selected(($c['device'] ?? '') === 'mobile')>Mobile</option><option value="desktop" @selected(($c['device'] ?? '') === 'desktop')>Desktop</option></select></label>
                <label class="oo-field">Cart value at least ({{ $store->currency }})<input type="number" step="0.01" min="0" name="conditions[cart_min]" value="{{ $c['cart_min'] ?? '' }}"></label>
                <label class="oo-field">Cart value at most<input type="number" step="0.01" min="0" name="conditions[cart_max]" value="{{ $c['cart_max'] ?? '' }}"></label>
                <label class="oo-field">UTM source<input type="text" name="conditions[utm_source]" value="{{ $c['utm_source'] ?? '' }}" maxlength="100"></label>
                <label class="oo-field">UTM campaign<input type="text" name="conditions[utm_campaign]" value="{{ $c['utm_campaign'] ?? '' }}" maxlength="100"></label>
            </div>
            @if ($err('conditions'))<p class="b-error">{{ $err('conditions') }}</p>@endif
        </s-section>

        <s-section heading="3. Then">
            @foreach (Audiences::OUTCOMES as $key => $label)
                <label class="oo-radio"><input type="radio" name="outcome" value="{{ $key }}" @checked(($rule->outcome ?? 'show') === $key) data-outcome><span><strong>{{ $label }}</strong>
                    <small>{{ ['show' => 'Everyone else doesn\'t see it. Example: show the VIP offer only to VIP customers.', 'swap' => 'Matching shoppers see the same experience in a different layout. Example: the compact bundle template on mobile.', 'hide' => 'Matching shoppers don\'t see it. Example: hide the first-order discount from returning customers.'][$key] }}</small></span></label>
            @endforeach
            <div class="b-field {{ $err('template_key') ? 'b-has-error' : '' }}" data-swap @if (($rule->outcome ?? 'show') !== 'swap') hidden @endif>
                <label for="ru-tpl">Template to show</label>
                <select id="ru-tpl" name="template_key" data-template data-selected="{{ $rule->template_key }}"></select>
                @if ($err('template_key'))<p class="b-error">{{ $err('template_key') }}</p>@endif
            </div>
        </s-section>

        <s-section heading="4. Name and status">
            <div class="b-field"><label for="ru-name">Rule name (optional)</label><input id="ru-name" type="text" name="name" value="{{ $rule->name }}" maxlength="80" placeholder="Returning + cart over $75 → premium upsell"></div>
            <div class="b-field b-toggle"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($rule->enabled ?? true)> <span>Enabled</span></label></div>
            <div class="oo-inline"><s-button type="submit" variant="primary">Save rule</s-button></div>
        </s-section>
    </form>
</s-page>
@endsection

@push('scripts')
    <script>
        (() => {
            const templates = @json($templates);
            const form = document.querySelector('[data-rule-form]');
            const exp = form.querySelector('[data-experience]');
            const tpl = form.querySelector('[data-template]');
            const fill = () => {
                const list = templates[exp.value] || {};
                tpl.innerHTML = Object.entries(list).map(([k, v]) => `<option value="${k}" ${k === tpl.dataset.selected ? 'selected' : ''}>${v}</option>`).join('');
            };
            exp.addEventListener('change', fill);
            fill();
            form.querySelectorAll('[data-outcome]').forEach((r) => r.addEventListener('change', () => {
                form.querySelector('[data-swap]').hidden = form.querySelector('[data-outcome]:checked').value !== 'swap';
            }));
        })();
    </script>
@endpush
