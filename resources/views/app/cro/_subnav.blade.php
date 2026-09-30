{{-- CRO sub-menu: every conversion feature in one place. --}}
@php
    $feature = request()->route('feature');
    $items = [
        ['CRO overview', app_route('app.cro.overview'), request()->routeIs('app.cro.overview')],
        ['Bundles', app_route('app.bundles.index'), request()->routeIs('app.bundles.*')],
        ['Progressive gifts', app_route('app.gifts.index'), request()->routeIs('app.gifts.*')],
    ];
    foreach (\App\Experiences\Registry::features() as $key => $f) {
        if (! isset($f['module'])) {
            $items[] = [$f['label'], app_route('app.features.show', ['feature' => $key]), $feature === $key];
        }
    }
    $items[] = ['All offers', app_route('app.cro.experiences.index'), request()->routeIs('app.cro.experiences.*')];
    $items[] = ['Templates', app_route('app.cro.templates'), request()->routeIs('app.cro.templates')];
@endphp
<nav class="ob-subnav" aria-label="CRO">
    <span class="ob-subnav-label">CRO</span>
    @foreach ($items as [$label, $href, $on])
        <a href="{{ $href }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
