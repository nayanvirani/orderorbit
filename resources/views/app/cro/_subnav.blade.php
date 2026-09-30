{{-- CRO sub-menu: every conversion feature in one place. --}}
@php
    $feature = request()->route('feature');
    $items = [
        ['CRO overview', app_route('app.cro.overview'), request()->routeIs('app.cro.overview')],
        ['Bundles', app_route('app.bundles.index'), request()->routeIs('app.bundles.*'), 'bundles'],
        ['Progressive gifts', app_route('app.gifts.index'), request()->routeIs('app.gifts.*'), 'gifts'],
    ];
    foreach (\App\Experiences\Registry::features() as $key => $f) {
        if (! isset($f['module'])) {
            $items[] = [$f['label'], app_route('app.features.show', ['feature' => $key]), $feature === $key, $f['tone'] ?? null];
        }
    }
    $items[] = ['All offers', app_route('app.cro.experiences.index'), request()->routeIs('app.cro.experiences.*')];
    $items[] = ['Templates', app_route('app.cro.templates'), request()->routeIs('app.cro.templates')];
@endphp
<nav class="ob-subnav" aria-label="CRO">
    <span class="ob-subnav-label">CRO</span>
    @foreach ($items as $item)
        <a href="{{ $item[1] }}" @if ($item[2]) aria-current="page" @endif @isset($item[3]) class="t-{{ $item[3] }}" @endisset>@isset($item[3])<i></i>@endisset{{ $item[0] }}</a>
    @endforeach
</nav>
