@extends('layouts.embedded')

@section('title', $experience->name)

@php
    $steps = [
        'type' => 'Type', 'template' => 'Template', 'content' => 'Content', 'design' => 'Design',
        'behavior' => 'Behavior', 'targeting' => 'Targeting', 'analytics' => 'Analytics', 'preview' => 'Preview', 'publish' => 'Publish',
    ];
    $sectionFor = ['content' => ['content'], 'design' => ['design'], 'behavior' => ['behavior'], 'targeting' => ['targeting', 'schedule'], 'analytics' => ['analytics']];
    $stepErrors = collect($fieldErrors)->keys()->map(fn ($k) => explode('.', $k)[0])->map(fn ($s) => $s === 'schedule' ? 'targeting' : $s)->unique()->values()->all();
    $firstErrorStep = $stepErrors[0] ?? null;
@endphp

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $experience->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.cro.experiences.show', ['experience' => $experience->id]) }}">Experience</s-link>

    @if ($banner)
        <s-banner tone="{{ $fieldErrors ? 'warning' : 'info' }}">{{ $banner }}</s-banner>
    @endif

    <form method="POST" action="{{ app_route('app.cro.experiences.update', ['experience' => $experience->id]) }}" id="builder" data-builder
          data-type="{{ $experience->type }}" data-handle="{{ $experience->handle }}" data-currency="{{ $store->currency ?? 'USD' }}"
          data-styles="{{ json_encode(collect($templates)->map(fn ($t) => $t['style'])) }}" data-start-step="{{ $firstErrorStep ?? 'content' }}">

        <nav class="b-steps" aria-label="Builder steps">
            @foreach ($steps as $key => $label)
                <button type="button" class="b-step {{ in_array($key, $stepErrors, true) ? 'b-step-error' : '' }}" data-step-link="{{ $key }}">
                    <span>{{ $loop->iteration }}</span>{{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="b-layout">
            <div class="b-panel">
                {{-- 1. Type --}}
                <section class="b-card" data-step="type" hidden>
                    <h2>Type</h2>
                    <p><strong>{{ $type['label'] }}</strong> — {{ $type['description'] }}</p>
                    <p class="b-muted">The type is set when an experience is created. Duplicate from another type to switch.</p>
                </section>

                {{-- 2. Template --}}
                <section class="b-card" data-step="template" hidden>
                    <h2>Template</h2>
                    <p class="b-muted">Switching templates keeps your content and design settings.</p>
                    <div class="b-templates">
                        @foreach ($templates as $key => $template)
                            <label class="b-template">
                                <input type="radio" name="template_key" value="{{ $key }}" @checked($experience->template_key === $key)>
                                <span class="b-template-preview oo-preview" data-template-preview="{{ $key }}"></span>
                                <span class="b-template-name">{{ $template['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                {{-- 3–7. Schema sections --}}
                @foreach ($sectionFor as $step => $sections)
                    <section class="b-card" data-step="{{ $step }}" hidden>
                        @foreach ($sections as $section)
                            <h2>{{ ['content' => 'Content', 'design' => 'Design', 'behavior' => 'Behavior', 'targeting' => 'Targeting', 'schedule' => 'Schedule', 'analytics' => 'Analytics'][$section] }}</h2>
                            @if ($section === 'design')<p class="b-muted">Defaults come from <a href="{{ app_route('app.settings.branding') }}">Settings → Branding</a>.</p>@endif
                            @if ($section === 'schedule')<p class="b-muted">Optional. Leave empty to go live as soon as you publish.</p>@endif
                            @foreach ($fields[$section] as $key => $field)
                                @include('app.cro._field', ['section' => $section, 'key' => $key, 'field' => $field, 'value' => $config[$section][$key] ?? null])
                            @endforeach
                        @endforeach
                        @if ($step === 'analytics')
                            <h2>Experiment</h2>
                            <p class="b-muted">A/B tests on this experience arrive with Experiments. Views and clicks are measured once analytics is connected.</p>
                        @endif
                    </section>
                @endforeach

                {{-- 8. Preview --}}
                <section class="b-card" data-step="preview" hidden>
                    <h2>Preview</h2>
                    <p class="b-muted">Use the preview on the right. Try a different cart value to see progress states.</p>
                    <dl class="b-summary">
                        <dt>Template</dt><dd data-summary="template">{{ $experience->templateName() }}</dd>
                        <dt>Shows on</dt><dd>{{ collect($config['targeting']['page_types'] ?? [])->map(fn ($p) => \App\Experiences\Schema::PAGE_TYPES[$p] ?? $p)->implode(', ') ?: 'Wherever you place the block' }}</dd>
                        <dt>Shoppers</dt><dd>{{ ['all' => 'Everyone', 'new' => 'New shoppers', 'returning' => 'Returning customers'][$config['targeting']['customer'] ?? 'all'] }} · {{ ['all' => 'All devices', 'mobile' => 'Mobile', 'desktop' => 'Desktop'][$config['targeting']['device'] ?? 'all'] }}</dd>
                    </dl>
                </section>

                {{-- 9. Publish --}}
                <section class="b-card" data-step="publish" hidden>
                    <h2>Publish</h2>
                    <div class="b-field"><label for="f-name">Internal name</label><input id="f-name" name="name" value="{{ $experience->name }}" maxlength="120" required></div>
                    <div class="b-field"><label for="f-description">Description</label><textarea id="f-description" name="description" rows="2" maxlength="500">{{ $experience->description }}</textarea></div>
                    <div class="b-field"><label for="f-note">Change note</label><input id="f-note" name="change_note" maxlength="190" placeholder="What changed in this version?"></div>
                    @if ($type['discount'] ?? false)
                        <p class="b-muted">Savings apply automatically in cart and checkout. Publishing creates a Shopify automatic discount for this {{ lower_label($type['singular']) }}; pausing or archiving it removes the discount. You'll see it under <strong>Discounts</strong> in Shopify admin.</p>
                    @endif
                    <p class="b-muted">After publishing, add the <strong>OrderOrbit experience</strong> block in the Theme Editor and pick “{{ $type['singular'] }}”, or pin it with ID <code class="b-code-inline">{{ $experience->handle }}</code>.</p>
                    <div class="b-actions">
                        <button type="submit" name="action" value="publish" class="b-btn b-primary">
                            {{ ! empty($config['schedule']['starts_at']) ? 'Schedule' : 'Publish' }}
                        </button>
                        <a class="b-btn" target="_top" href="{{ $store->adminUrl('themes/current/editor?template='.($type['surface'] === 'product' ? 'product' : ($type['surface'] === 'cart' ? 'cart' : 'index')).'&addAppBlockId='.config('shopify.api_key').'/experience&target=newAppsSection') }}">Open Theme Editor</a>
                    </div>
                </section>

                <div class="b-savebar">
                    <button type="button" class="b-btn" data-step-prev>Back</button>
                    <span class="b-dirty" data-dirty hidden>Unsaved changes</span>
                    <button type="submit" name="action" value="save" class="b-btn">Save draft</button>
                    <button type="button" class="b-btn b-primary" data-step-next>Next</button>
                </div>
            </div>

            <aside class="b-preview-col">
                <div class="b-preview-bar">
                    <div class="b-seg" role="group" aria-label="Preview device">
                        <button type="button" aria-pressed="true" data-device="desktop">Desktop</button>
                        <button type="button" aria-pressed="false" data-device="mobile">Mobile</button>
                    </div>
                    <label class="b-cart">Cart value <input type="number" min="0" step="1" value="45" data-preview-cart aria-label="Preview cart value"></label>
                </div>
                <div class="b-stage">
                    <div class="b-frame oo-preview" data-preview-frame>
                        <div class="b-fake-page" aria-hidden="true"><i></i><i></i><i class="short"></i></div>
                        <div class="oo-root" data-preview></div>
                        <div class="b-fake-page" aria-hidden="true"><i class="short"></i><i></i></div>
                    </div>
                </div>
                <p class="b-muted b-small">Preview uses sample prices where the storefront would show live ones.</p>
            </aside>
        </div>
    </form>

    @if ($experience->published_version_id && $experience->has_unpublished_changes)
        <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $experience->id, 'action' => 'discard']) }}" data-confirm="Discard all changes since the last publish?" style="margin-top:12px">
            <s-button type="submit" variant="tertiary">Discard changes since last publish</s-button>
        </form>
    @endif
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script src="{{ asset('js/builder.js') }}?v={{ filemtime(public_path('js/builder.js')) }}"></script>
@endpush
