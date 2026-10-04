@extends('layouts.embedded')

@section('title', $experiment->name)

@php
    use App\Services\Experiments\ExperimentManager;
    $config = $experience->publishedVersion?->config ?? $experience->draft_config ?? [];
    $variants = $experiment->variants->keyBy('key');
    $controlTemplate = $variants['A']->template_key ?? $experience->template_key;
    $err = fn ($key) => $fieldErrors[$key] ?? null;
    $guardrails = collect($experiment->guardrails ?? [])->keyBy('metric');
    $steps = ['experience' => 'Experience', 'variants' => 'Variants', 'traffic' => 'Traffic', 'audience' => 'Audience', 'primary' => 'Primary metric', 'secondary' => 'Secondary metrics', 'guardrails' => 'Guardrails', 'duration' => 'Duration', 'launch' => 'Preview & launch'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/experiments.css') }}?v={{ filemtime(public_path('css/experiments.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $experiment->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.experiments.index', ['tab' => 'drafts']) }}">A/B tests</s-link>
    @if ($banner)<s-banner tone="{{ $fieldErrors ? 'warning' : 'critical' }}">{{ $banner }}</s-banner>@endif
    @if ($experiment->status === 'paused')
        <s-banner tone="warning" heading="This test is paused">
            <s-paragraph>Everyone sees the control. You can change the setup, then resume. Visitors keep their variant.</s-paragraph>
        </s-banner>
    @endif

    <nav class="xp-steps" aria-label="Setup steps">
        @foreach ($steps as $id => $label)<a href="#step-{{ $id }}">{{ $loop->iteration }}. {{ $label }}</a>@endforeach
    </nav>

    <form method="POST" action="{{ app_route('app.experiments.update', ['experiment' => $experiment->id]) }}" class="xp-form" data-xp-form>
        <s-section heading="1. Experience" id="step-experience">
            <p class="oo-muted">Testing <strong>{{ $experience->name }}</strong> ({{ $type['label'] ?? $experience->type }}). Variants can change its template, design and text; products, prices and discounts stay as published, so checkout always matches what shoppers saw.@if ($checkoutBlock) The split happens in Shopify's checkout, so it shows wherever the OrderOrbit Space block for this type is placed.@endif</p>
            @if ($experience->status !== 'published')<s-banner tone="warning">This experience isn't published. Publish it before launching the test.</s-banner>@endif
            <div class="b-field"><label for="xp-name">Test name</label><input id="xp-name" type="text" name="name" value="{{ $experiment->name }}" maxlength="120" required></div>
            <div class="b-field"><label for="xp-hypothesis">Hypothesis (optional)</label><textarea id="xp-hypothesis" name="hypothesis" rows="2" maxlength="1000" placeholder="Showing the upsell as a slider instead of cards will raise add to cart, because…">{{ $experiment->hypothesis }}</textarea></div>
        </s-section>

        <s-section heading="2. Variants" id="step-variants">
            @if ($err('variants'))<p class="b-error">{{ $err('variants') }}</p>@endif
            <div class="xp-variants">
                @foreach (['A', 'B', 'C'] as $key)
                    @php($v = $variants[$key] ?? null)
                    <fieldset class="xp-variant {{ $v ? '' : 'is-off' }}" data-variant="{{ $key }}">
                        <legend>{{ $key === 'A' ? 'A · Control' : 'Variant '.$key }}</legend>
                        @if ($key === 'C')
                            <label class="xp-toggle"><input type="checkbox" data-variant-toggle @checked($v)> Add a third variant (A/B/C)</label>
                        @endif
                        <div class="xp-variant-body" @if (! $v && $key === 'C') hidden @endif>
                            <input type="hidden" name="variants[{{ $key }}][key]" value="{{ $key }}" @disabled(! $v && $key === 'C')>
                            <div class="b-field"><label>Name</label><input type="text" name="variants[{{ $key }}][name]" value="{{ $v->name ?? ($key === 'C' ? 'Variant C' : '') }}" maxlength="80" @disabled(! $v && $key === 'C')></div>
                            @if ($key === 'A')
                                <p class="oo-muted oo-small">The experience exactly as published ({{ $templates[$controlTemplate]['name'] ?? $controlTemplate }}).</p>
                            @else
                                @if ($canHoldout)
                                    <label class="xp-toggle"><input type="checkbox" name="variants[{{ $key }}][hidden]" value="1" @checked($v?->hidden) @disabled(! $v && $key === 'C')> Holdout: don't show the experience to this group (measures its overall effect)</label>
                                @endif
                                <div class="b-field"><label>Template</label>
                                    <select name="variants[{{ $key }}][template_key]" @disabled(! $v && $key === 'C')>
                                        @foreach ($templates as $tKey => $t)<option value="{{ $tKey }}" @selected(($v->template_key ?? $controlTemplate) === $tKey)>{{ $t['name'] }}{{ $tKey === $controlTemplate ? ' (control)' : '' }}</option>@endforeach
                                    </select>
                                </div>
                                @if ($textFields)
                                    <details @if ($v?->content) open @endif><summary>Text{{ $v?->content ? ' · '.count($v->content).' changed' : '' }}</summary>
                                        @foreach ($textFields as $fKey => $f)
                                            <div class="b-field"><label>{{ $f['label'] }}</label>
                                                @if ($f['type'] === 'textarea')
                                                    <textarea name="variants[{{ $key }}][content][{{ $fKey }}]" rows="2" maxlength="{{ $f['max'] ?? 1000 }}" @disabled(! $v && $key === 'C')>{{ $v->content[$fKey] ?? ($config['content'][$fKey] ?? '') }}</textarea>
                                                @else
                                                    <input type="text" name="variants[{{ $key }}][content][{{ $fKey }}]" value="{{ $v->content[$fKey] ?? ($config['content'][$fKey] ?? '') }}" maxlength="{{ $f['max'] ?? 255 }}" @disabled(! $v && $key === 'C')>
                                                @endif
                                            </div>
                                        @endforeach
                                    </details>
                                @endif
                                @if ($designFields)
                                    <details @if ($v?->design) open @endif><summary>Design{{ $v?->design ? ' · '.count($v->design).' changed' : '' }}</summary>
                                        @foreach ($designFields as $fKey => $f)
                                            @include('app.cro._field', ['section' => "variants.{$key}.design", 'key' => $fKey, 'field' => $f, 'value' => $v->design[$fKey] ?? ($config['design'][$fKey] ?? ($f['default'] ?? null)), 'namePrefix' => "variants[{$key}][design]", 'timezone' => 'UTC'])
                                        @endforeach
                                    </details>
                                @endif
                            @endif
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </s-section>

        <s-section heading="3. Traffic allocation" id="step-traffic">
            <p class="oo-muted">The share of visitors who see each variant. Each visitor always sees the same one.</p>
            <div class="xp-alloc">
                @foreach (['A', 'B', 'C'] as $key)
                    <label class="oo-field" data-alloc="{{ $key }}" @if (! isset($variants[$key]) && $key === 'C') hidden @endif>{{ $key }}
                        <span class="xp-pct"><input type="number" name="variants[{{ $key }}][allocation]" value="{{ $variants[$key]->allocation ?? 33 }}" min="1" max="98" @disabled(! isset($variants[$key]) && $key === 'C')> %</span>
                        @if ($err("variants.{$key}.allocation"))<span class="b-error">{{ $err("variants.{$key}.allocation") }}</span>@endif
                    </label>
                @endforeach
                <p class="xp-total" data-alloc-total></p>
            </div>
            @if ($err('variants.allocation'))<p class="b-error">{{ $err('variants.allocation') }}</p>@endif
            <s-button type="button" variant="tertiary" data-alloc-even>Split evenly</s-button>
        </s-section>

        <s-section heading="4. Audience" id="step-audience">
            <p class="oo-muted">Who takes part. Visitors outside the audience see the experience as published and aren't counted. Leave empty for everyone who sees the experience.</p>
            @foreach ($audienceFields as $key => $field)
                @if (in_array($field['type'], ['products', 'collections'], true))
                    <div class="b-field" data-field="audience.{{ $key }}">
                        <span class="b-label">{{ $field['label'] }}</span>
                        <input type="hidden" name="audience[{{ $key }}]" value="{{ json_encode($experiment->audience[$key] ?? []) }}" data-pick-input>
                        <ul class="b-chips" data-pick-list></ul>
                        <button type="button" class="b-btn" data-pick="{{ $field['type'] === 'products' ? 'product' : 'collection' }}">Choose {{ $field['type'] }}</button>
                    </div>
                @else
                    @include('app.cro._field', ['section' => 'audience', 'key' => $key, 'field' => $field, 'value' => $experiment->audience[$key] ?? ($field['default'] ?? null), 'namePrefix' => 'audience', 'timezone' => 'UTC'])
                @endif
            @endforeach
            <p class="oo-muted oo-small">{{ $checkoutBlock ? 'Checkout and Thank You pages only know the cart value and the buyer\'s country.' : 'Customer segments arrive with Audiences & Personalization.' }}</p>
        </s-section>

        <s-section heading="5. Primary metric" id="step-primary">
            <p class="oo-muted">The one number that decides the winner.</p>
            @foreach (ExperimentManager::PRIMARY as $key => $label)
                <label class="oo-radio"><input type="radio" name="primary_metric" value="{{ $key }}" @checked($experiment->primary_metric === $key)><span><strong>{{ $label }}</strong>
                    <small>{{ ['conversion_rate' => 'Share of visitors who place an order after seeing the experience. Two-proportion z-test.', 'revenue_per_visitor' => 'Order revenue divided by visitors, so bigger orders count. Welch\'s t-test.', 'revenue' => 'Total revenue, compared per visitor so unequal splits stay fair. Welch\'s t-test.', 'click_rate' => 'Share of visitors who click the block (a button, link or accepting its offer). Best for Thank You and Order Status blocks, where the order is already placed. Two-proportion z-test.'][$key] }}</small></span></label>
            @endforeach
        </s-section>

        <s-section heading="6. Secondary metrics" id="step-secondary">
            <div class="b-checks">
                @foreach (ExperimentManager::SECONDARY as $key => $label)
                    <label><input type="checkbox" name="secondary_metrics[]" value="{{ $key }}" @checked(in_array($key, $experiment->secondary_metrics ?? [], true))> {{ $label }}</label>
                @endforeach
            </div>
        </s-section>

        <s-section heading="7. Guardrails" id="step-guardrails">
            <p class="oo-muted">A variant can't be declared the winner if it makes these worse by more than the threshold (percentage points vs control).</p>
            @foreach (ExperimentManager::GUARDRAILS as $key => $label)
                <div class="oo-form-row xp-guard">
                    <label class="xp-toggle"><input type="checkbox" name="guardrails[{{ $key }}][enabled]" value="1" @checked($guardrails->has($key))> {{ $label }}</label>
                    <input type="hidden" name="guardrails[{{ $key }}][metric]" value="{{ $key }}">
                    <label class="oo-field">Max increase<span class="xp-pct"><input type="number" name="guardrails[{{ $key }}][threshold]" value="{{ $guardrails[$key]['threshold'] ?? 5 }}" min="0.1" max="100" step="0.1"> pts</span></label>
                </div>
            @endforeach
            <p class="oo-muted oo-small">Refund rate isn't available as a guardrail: refunds can't be matched to test visitors reliably.</p>
        </s-section>

        <s-section heading="8. Duration and sample" id="step-duration">
            <p class="oo-muted">No winner is named before all three minimums are reached in every variant. These are the lowest values allowed.</p>
            <div class="oo-form-row">
                <label class="oo-field">Minimum days<input type="number" name="min_days" value="{{ $experiment->min_days }}" min="7" max="90"></label>
                <label class="oo-field">Visitors per variant<input type="number" name="min_visitors" value="{{ $experiment->min_visitors }}" min="1000"></label>
                <label class="oo-field">{{ $experiment->primary_metric === 'click_rate' ? 'Clicks' : 'Conversions' }} per variant<input type="number" name="min_conversions" value="{{ $experiment->min_conversions }}" min="100"></label>
                <label class="oo-field">End date (optional)<input type="date" name="ends_at" value="{{ $experiment->ends_at?->toDateString() }}"></label>
            </div>
            @if ($err('ends_at'))<p class="b-error">{{ $err('ends_at') }}</p>@endif
        </s-section>

        <s-section heading="9. Preview and launch" id="step-launch">
            <div class="xp-previews">
                @foreach ($experiment->variants as $v)
                    <div class="xp-preview">
                        <strong>{{ $v->key }} · {{ $v->name }}</strong>
                        @if ($previews[$v->key])
                            <div class="oo-preview" data-render="{{ json_encode($previews[$v->key]) }}"></div>
                        @else
                            <p class="oo-muted xp-holdout">Holdout: this group doesn't see the experience.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="oo-muted oo-small">Previews show the last saved setup. Save to refresh them.</p>
            @if ($problems)
                <s-banner tone="warning" heading="Before you can launch">
                    <s-unordered-list>@foreach ($problems as $p)<s-list-item>{{ $p }}</s-list-item>@endforeach</s-unordered-list>
                </s-banner>
            @endif
            <div class="oo-inline" style="margin-top:12px">
                <s-button type="submit" name="action" value="save">Save draft</s-button>
                <s-button type="submit" name="action" value="launch" variant="primary">{{ $experiment->status === 'paused' ? 'Save and resume' : 'Launch test' }}</s-button>
            </div>
        </s-section>
    </form>

    <s-section>
        <form method="POST" action="{{ app_route('app.experiments.destroy', ['experiment' => $experiment->id]) }}" data-confirm="Delete this test? This can't be undone.">
            @if ($experiment->status === 'draft')<s-button type="submit" tone="critical" variant="tertiary">Delete draft</s-button>@endif
        </form>
    </s-section>
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    @if ($checkoutBlock)
        <script src="{{ asset('js/checkout-preview.js') }}?v={{ filemtime(public_path('js/checkout-preview.js')) }}"></script>
        <link rel="stylesheet" href="{{ asset('css/checkout-preview.css') }}?v={{ filemtime(public_path('css/checkout-preview.css')) }}">
    @endif
    <script>
        (() => {
            const form = document.querySelector('[data-xp-form]');
            // Previews of each variant, drawn by the storefront runtime.
            document.querySelectorAll('[data-render]').forEach((el) => {
                window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });
            });
            // Variant C on/off.
            const c = form.querySelector('[data-variant="C"]');
            const toggle = c.querySelector('[data-variant-toggle]');
            const setC = () => {
                const on = toggle.checked;
                c.classList.toggle('is-off', !on);
                c.querySelector('.xp-variant-body').hidden = !on;
                form.querySelectorAll('[name^="variants[C]"]').forEach((el) => { el.disabled = !on; });
                form.querySelector('[data-alloc="C"]').hidden = !on;
                total();
            };
            toggle.addEventListener('change', setC);
            // Allocation total.
            const inputs = () => [...form.querySelectorAll('[data-alloc] input')].filter((i) => !i.disabled);
            const total = () => {
                const sum = inputs().reduce((n, i) => n + (Number(i.value) || 0), 0);
                const out = form.querySelector('[data-alloc-total]');
                out.textContent = 'Total: ' + sum + '%' + (sum === 100 ? '' : ' (must be 100%)');
                out.classList.toggle('bad', sum !== 100);
            };
            form.addEventListener('input', (e) => { if (e.target.closest('[data-alloc]')) total(); });
            form.querySelector('[data-alloc-even]').addEventListener('click', () => {
                const list = inputs();
                const each = Math.floor(100 / list.length);
                list.forEach((i, n) => { i.value = n === 0 ? 100 - each * (list.length - 1) : each; });
                total();
            });
            total();
            // Product and collection pickers for the audience.
            form.querySelectorAll('[data-pick]').forEach((button) => {
                const box = button.closest('.b-field');
                const input = box.querySelector('[data-pick-input]');
                const list = box.querySelector('[data-pick-list]');
                const draw = () => {
                    const items = JSON.parse(input.value || '[]');
                    list.innerHTML = '';
                    items.forEach((item, i) => {
                        const li = document.createElement('li');
                        li.textContent = item.title + ' ';
                        const x = Object.assign(document.createElement('button'), { type: 'button', textContent: '×', ariaLabel: 'Remove ' + item.title });
                        x.addEventListener('click', () => { items.splice(i, 1); input.value = JSON.stringify(items); draw(); });
                        li.appendChild(x);
                        list.appendChild(li);
                    });
                };
                button.addEventListener('click', async () => {
                    const picked = await shopify.resourcePicker({ type: button.dataset.pick, multiple: true, selectionIds: JSON.parse(input.value || '[]').map((p) => ({ id: p.id })) });
                    if (!picked) return;
                    input.value = JSON.stringify(picked.map((p) => ({ id: p.id, title: p.title })));
                    draw();
                });
                draw();
            });
        })();
    </script>
@endpush
