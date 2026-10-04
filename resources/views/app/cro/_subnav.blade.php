{{-- CRO sidebar: every conversion feature in one place, grouped. --}}
@php
    $feature = request()->route('feature');
    // On an experience's own pages, highlight the feature it belongs to rather than "All offers".
    $experienceId = request()->route('experience');
    $experienceFeature = is_numeric($experienceId)
        ? \App\Experiences\Registry::featureFor((string) \App\Models\Experience::whereKey((int) $experienceId)->where('store_id', request()->attributes->get('store')?->id)->value('type'))
        : null;
    $feature ??= $experienceFeature;
    // Creating a new experience of a type: highlight that type's feature too.
    if (! $feature && request()->routeIs('app.cro.experiences.create') && \App\Experiences\Registry::has(request('type'))) {
        $feature = $experienceFeature = \App\Experiences\Registry::featureFor(request('type'));
    }
    $features = \App\Experiences\Registry::features();
    $link = fn (string $key) => [
        'label' => $features[$key]['label'],
        'icon' => $features[$key]['icon'] ?? 'sparkle',
        'tone' => $features[$key]['tone'] ?? null,
        'href' => isset($features[$key]['module'])
            ? app_route($features[$key]['module'])
            : app_route('app.features.show', ['feature' => $key]),
        'active' => match ($key) {
            'bundles' => request()->routeIs('app.bundles.*'),
            'progressive-gifts' => request()->routeIs('app.gifts.*'),
            default => $feature === $key,
        },
    ];
    $order = ['bundles', 'progressive-gifts', 'cart-upsells'];
    $checkout = ['checkout', 'post-purchase', 'thank-you', 'customer-accounts'];
    $groups = [
        'Order value' => array_map($link, array_values(array_filter($order, fn ($k) => isset($features[$k])))),
        'Conversion' => array_map($link, array_values(array_filter(array_keys($features), fn ($k) => ! in_array($k, array_merge($order, $checkout), true)))),
        'Checkout' => array_map($link, array_values(array_filter($checkout, fn ($k) => isset($features[$k])))),
        'Manage' => [
            ['label' => 'All offers', 'icon' => 'list', 'tone' => 'default', 'href' => app_route('app.cro.experiences.index'), 'active' => request()->routeIs('app.cro.experiences.*') && ! $experienceFeature],
            ['label' => 'Templates', 'icon' => 'palette', 'tone' => 'default', 'href' => app_route('app.cro.templates'), 'active' => request()->routeIs('app.cro.templates')],
        ],
    ];
@endphp
<aside class="ob-sidebar">
    <nav class="ob-subnav" aria-label="CRO">
        <a class="ob-side-top" href="{{ app_route('app.cro.overview') }}" @if (request()->routeIs('app.cro.overview')) aria-current="page" @endif>
            <x-app.icon name="grid" size="sm" tone="default"/><span>CRO overview</span>
        </a>
        @foreach ($groups as $heading => $items)
            <p class="ob-side-head">{{ $heading }}</p>
            @foreach ($items as $item)
                <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif>
                    <x-app.icon :name="$item['icon']" size="sm" :tone="$item['tone']"/><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
</aside>
<script>
    // Narrow screens show the menu as a strip: bring the current page into view.
    if (window.matchMedia('(max-width: 900px)').matches) {
        document.querySelector('.ob-subnav [aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }
</script>
