@extends('layouts.embedded')

@section('title', 'Settings · Branding')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
@endpush

@section('content')
@php($canManage = request()->attributes->get('storeUser')?->can('manage_settings'))
<s-page heading="Settings">
    @include('app.settings._tabs')

    <s-section heading="Branding">
        <s-paragraph>New experiences start from these colours and styles. Existing experiences keep their own design.</s-paragraph>
        <form method="POST" action="{{ app_route('app.settings.branding.update') }}" data-branding>
            <div class="oo-form-row" style="align-items:flex-start">
                @foreach (['primary_color' => 'Primary (buttons)', 'accent_color' => 'Accent (progress, badges)', 'text_color' => 'Text', 'background_color' => 'Background'] as $key => $label)
                    <label class="oo-field">{{ $label }}
                        <span class="oo-inline"><input type="color" name="{{ $key }}" value="{{ $branding[$key] }}" style="width:48px;padding:2px" @disabled(! $canManage)><span class="oo-code">{{ $branding[$key] }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="oo-form-row" style="margin-top:12px">
                <label class="oo-field">Button style
                    <select name="button_style" @disabled(! $canManage)><option value="filled" @selected($branding['button_style'] === 'filled')>Filled</option><option value="outline" @selected($branding['button_style'] === 'outline')>Outline</option></select>
                </label>
                <label class="oo-field">Corner radius (px)<input type="number" name="radius" min="0" max="32" value="{{ $branding['radius'] }}" @disabled(! $canManage)></label>
                <label class="oo-field">Font
                    <select name="font" @disabled(! $canManage)><option value="theme" @selected($branding['font'] === 'theme')>Match my theme</option><option value="system" @selected($branding['font'] === 'system')>System font</option></select>
                </label>
            </div>
            @if ($canManage)
                <s-stack direction="inline" gap="small-200" style="margin-top:16px">
                    <s-button type="submit" variant="primary">Save</s-button>
                    <button type="submit" name="action" value="reset" hidden data-reset></button>
                    <s-button data-reset-click>Reset to defaults</s-button>
                </s-stack>
            @endif
        </form>
    </s-section>

    <s-section heading="Preview">
        <div class="oo-preview" style="max-width:520px"><div class="oo-root" data-branding-preview></div></div>
    </s-section>
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script>
        (() => {
            const form = document.querySelector('[data-branding]');
            const target = document.querySelector('[data-branding-preview]');
            const draw = () => {
                const v = (n) => form.elements[n] && form.elements[n].value;
                window.OrderOrbit.render(target, {
                    id: 'branding', type: 'shipping-bar', style: 'card', version: 0,
                    content: { thresholds: [{ amount: 60, reward: 'free shipping' }], progress_message: "You're {remaining} away from {reward}", unlocked_message: '', empty_message: '' },
                    design: { primary_color: v('primary_color'), accent_color: v('accent_color'), text_color: v('text_color'), background_color: v('background_color'), button_style: v('button_style'), radius: Number(v('radius')), border: true, font: v('font') },
                    behavior: {}, targeting: {}, analytics: {},
                }, { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500 });
                form.querySelectorAll('input[type=color]').forEach((c) => { c.nextElementSibling.textContent = c.value; });
            };
            form.addEventListener('input', draw);
            form.addEventListener('change', draw);
            const reset = form.querySelector('[data-reset-click]');
            if (reset) reset.addEventListener('click', () => { if (confirm('Reset branding to the defaults?')) form.querySelector('[data-reset]').click(); });
            draw();
        })();
    </script>
@endpush
