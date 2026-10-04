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
    <link rel="stylesheet" href="{{ asset('css/app-base.css') }}?v={{ filemtime(public_path('css/app-base.css')) }}">
    @stack('head')
    {{-- Illustrated sample product for previews in the admin. --}}
    <script>window.OO_SAMPLE_IMAGE = @json(\App\Services\Experiences\TemplateLibrary::samples()[0]['image']);</script>
</head>
<body>
    @php($navUser = request()->attributes->get('storeUser'))
    @include('app._appnav')

    @php($section = request()->routeIs('app.automation.*') ? 'automation' : (request()->routeIs('app.experiments.*') ? 'experiments' : (request()->routeIs('app.audiences.*') ? 'audiences' : (request()->routeIs('app.analytics', 'app.analytics.*') ? 'analytics' : (request()->routeIs('app.settings.*') ? 'settings' : null)))))
    @if (request()->routeIs('app.cro.*', 'app.bundles.*', 'app.gifts.*', 'app.features.*'))
        <div class="ob-shell">
            @include('app.cro._subnav')
            <div class="ob-shell-main">@include('app._sales_limit')@yield('content')</div>
        </div>
    @elseif ($section && request()->attributes->get('store')?->hasPlanAccess())
        <div class="ob-shell">
            @include('app._modnav', ['section' => $section])
            <div class="ob-shell-main">@include('app._sales_limit')@yield('content')</div>
        </div>
    @else
        @include('app._sales_limit')
        @yield('content')
    @endif

    <script>
        // Session tokens live for one minute, so attach a fresh one to every form post.
        // Forms marked data-confirm ask first (section 2: destructive actions need confirmation).
        // Browsers silently refuse to submit when an invalid field is hidden (another tab or step),
        // so forms skip built-in validation and are checked here instead, with a visible message.
        document.querySelectorAll('form').forEach((f) => { f.noValidate = true; });
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            event.preventDefault();
            const bad = [...form.elements].find((el) => el.willValidate && !el.checkValidity());
            if (bad) {
                if (bad.offsetParent !== null) {
                    bad.reportValidity();
                } else {
                    const label = (bad.labels && bad.labels[0] ? bad.labels[0].textContent : bad.name || 'a field').trim().split('\n')[0];
                    shopify.toast.show('Check “' + label + '”: ' + bad.validationMessage, { isError: true });
                }
                return;
            }
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
