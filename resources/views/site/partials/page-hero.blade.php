{{-- Page header: $c with eyebrow, title, lead; optional $crumbs [[label, url|null]], $center, $dark. --}}
<section class="page-hero {{ ($center ?? false) ? 'center' : '' }} {{ ($dark ?? false) ? 'dark' : '' }}">
    <div class="wrap">
        @isset($crumbs)
            <nav class="crumbs" aria-label="Breadcrumb">
                @foreach ($crumbs as [$label, $url])
                    @if ($url)<a href="{{ $url }}">{{ $label }}</a><span aria-hidden="true">/</span>@else<span>{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endisset
        @if (! empty($c['eyebrow']))<span class="eyebrow">{{ site_md($c['eyebrow'], $vars ?? []) }}</span>@endif
        <h1>{{ site_md($c['title'] ?? $c['h1'] ?? '', $vars ?? []) }}</h1>
        @if (! empty($c['lead']))<p class="lead">{{ site_md($c['lead'], $vars ?? []) }}</p>@endif
    </div>
</section>
