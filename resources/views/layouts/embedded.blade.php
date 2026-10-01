<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <title>@yield('title', 'OrderOrbit Space')</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-brand.css') }}?v={{ filemtime(public_path('css/app-brand.css')) }}">
    <style>
        body { font: 14px/1.45 -apple-system, BlinkMacSystemFont, "San Francisco", "Segoe UI", Roboto, sans-serif; color: #303030; }
        .oo-radio { display: flex; gap: 12px; align-items: flex-start; padding: 12px; border: 1px solid #e3e3e3; border-radius: 10px; margin-bottom: 8px; cursor: pointer; background: #fff; }
        .oo-radio:has(input:checked) { border-color: #303030; box-shadow: 0 0 0 1px #303030; }
        .oo-radio input { margin-top: 3px; }
        .oo-radio small { display: block; color: #616161; }
        .oo-tabs { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 16px; }
        .oo-tabs a { padding: 6px 12px; border-radius: 8px; color: #303030; text-decoration: none; font-weight: 550; }
        .oo-tabs a:hover { background: #f1f1f1; }
        .oo-tabs a[aria-current="page"] { background: #303030; color: #fff; }
        .oo-table { width: 100%; border-collapse: collapse; }
        .oo-table th, .oo-table td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ebebeb; vertical-align: middle; }
        .oo-table th { font-weight: 600; color: #616161; font-size: 12px; }
        .oo-table tr:last-child td { border-bottom: 0; }
        .oo-scroll { overflow-x: auto; }
        .oo-muted { color: #616161; }
        .oo-small { font-size: 12px; }
        .oo-inline { display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .oo-field { display: flex; flex-direction: column; gap: 4px; font-weight: 550; }
        .oo-field input, .oo-field select, select.oo-select { font: inherit; line-height: 1.2; height: 34px; padding: 4px 10px; border: 1px solid #8a8a8a; border-radius: 8px; background: #fff; }
        .oo-form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .oo-meter { height: 8px; border-radius: 8px; background: #ebebeb; overflow: hidden; margin-top: 6px; }
        .oo-meter i { display: block; height: 100%; background: #303030; border-radius: inherit; }
        .oo-meter i.full { background: #c70a24; }
        .oo-steps { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
        .oo-steps span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #f1f1f1; color: #616161; font-size: 12px; font-weight: 550; }
        .oo-steps span.done { background: #cdfee1; color: #0c5132; }
        .oo-steps span.current { background: #303030; color: #fff; }
        .oo-kv { display: grid; grid-template-columns: minmax(140px, 220px) 1fr; gap: 8px 16px; }
        .oo-kv dt { color: #616161; }
        .oo-kv dd { margin: 0; }
        .oo-code { font: 12px ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f1f1; padding: 1px 6px; border-radius: 6px; }
        @media (max-width: 560px) {
            .oo-kv { grid-template-columns: 1fr; gap: 2px; } .oo-kv dd { margin-bottom: 8px; }
            .oo-table.stack thead { display: none; }
            .oo-table.stack tr { display: block; padding: 10px 0; border-bottom: 1px solid #ebebeb; }
            .oo-table.stack tr:last-child { border-bottom: 0; }
            .oo-table.stack td { display: block; border: 0; padding: 3px 0; text-align: left !important; }
            .oo-table.stack td[data-label]::before { content: attr(data-label) ": "; color: #616161; }
        }
    </style>
    @stack('head')
    {{-- Illustrated sample product for previews in the admin. --}}
    <script>window.OO_SAMPLE_IMAGE = @json(\App\Services\Experiences\TemplateLibrary::samples()[0]['image']);</script>
</head>
<body>
    @php($navUser = request()->attributes->get('storeUser'))
    @if (! request()->attributes->get('store')?->hasPlanAccess())
    <s-app-nav>
        <s-link href="{{ app_route('app.settings.billing') }}" rel="home">Choose a plan</s-link>
    </s-app-nav>
    @else
    <s-app-nav>
        <s-link href="{{ app_route('app.dashboard') }}" rel="home">Home</s-link>
        @if (! request()->attributes->get('store')?->goal)
            <s-link href="{{ app_route('app.onboarding') }}">Get started</s-link>
        @endif
        {{-- Shopify's app menu has one level; each section has its own sub-menu on its pages. --}}
        <s-link href="{{ app_route('app.cro.overview') }}">CRO</s-link>
        <s-link href="{{ app_route('app.analytics') }}">Analytics</s-link>
        <s-link href="{{ app_route('app.settings.store') }}">Settings</s-link>
    </s-app-nav>
    @endif

    @if (request()->routeIs('app.cro.*', 'app.bundles.*', 'app.gifts.*', 'app.features.*'))
        <div class="ob-shell">
            @include('app.cro._subnav')
            <div class="ob-shell-main">@include('app._sales_limit')@yield('content')</div>
        </div>
    @else
        @include('app._sales_limit')
        @yield('content')
    @endif

    <script>
        // Session tokens live for one minute, so attach a fresh one to every form post.
        // Forms marked data-confirm ask first (section 2: destructive actions need confirmation).
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            event.preventDefault();
            if (form.dataset.submitting) return;
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
            form.dataset.submitting = '1';
            let input = form.querySelector('input[name="id_token"]');
            if (!input) {
                input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'id_token' });
                form.appendChild(input);
            }
            // form.submit() ignores the clicked button, so carry its name/value (e.g. action=publish).
            form.querySelectorAll('input[data-submitter]').forEach((el) => el.remove());
            if (event.submitter && event.submitter.name) {
                form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: event.submitter.name, value: event.submitter.value }))
                    .setAttribute('data-submitter', '');
            }
            input.value = await shopify.idToken();
            form.querySelectorAll('[type="submit"]').forEach((b) => b.setAttribute('loading', ''));
            form.submit();
        });
        // Polaris buttons live in shadow DOM; make sure type="submit" submits the surrounding form.
        document.addEventListener('click', (event) => {
            const button = event.target.closest('s-button[type="submit"]');
            if (button && button.closest('form') && !button.hasAttribute('disabled')) button.closest('form').requestSubmit();
        });
        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-autosubmit]')) event.target.form.requestSubmit();
        });
    </script>
    @if ($notice = \App\Support\Notices::get(request('notice')))
        <script>
            shopify.toast.show(@json($notice[0]), { isError: @json($notice[1]) });
            (() => { const u = new URL(location.href); u.searchParams.delete('notice'); history.replaceState(null, '', u); })();
        </script>
    @endif
    @stack('scripts')
</body>
</html>
