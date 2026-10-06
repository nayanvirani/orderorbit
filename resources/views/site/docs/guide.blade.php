@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('docs'))
@php($keys = array_keys($guides))
@php($i = array_search($slug, $keys, true))
@php($prev = $i > 0 ? $keys[$i - 1] : null)
@php($next = $i < count($keys) - 1 ? $keys[$i + 1] : null)

@section('title', \App\Support\SiteContent::plain($guide['title']).' Guide | OrderOrbit Space Docs')
@section('description', \App\Support\SiteContent::plain($guide['summary']))

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<section class="page-hero">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.docs.index') }}">{{ $c['eyebrow'] }}</a><span aria-hidden="true">/</span><span>{{ $guide['title'] }}</span></nav>
        <h1 style="font-size:clamp(36px,4.4vw,56px)">{{ site_md($guide['h1'] ?? $guide['title']) }}</h1>
        <p class="lead">{{ site_md($guide['lead'] ?? $guide['summary']) }}</p>
        @if (! empty($guide['meta']))<p class="small dim">{{ site_md($guide['meta']) }}</p>@endif
    </div>
</section>

<div class="docs-layout wrap">
    <aside class="docs-toc" aria-label="{{ $c['on_this_page'] }}">
        <p class="docs-rail-title">{{ $c['on_this_page'] }}</p>
        <ol>@foreach ($guide['sections'] as $id => $section)<li><a href="#{{ $id }}" data-toc>{{ $section[0] ?? '' }}</a></li>@endforeach</ol>
        <p class="docs-rail-title" style="margin-top:24px">{{ $c['all_guides'] }}</p>
        <ul class="docs-all">@foreach ($guides as $s => $g)<li><a href="{{ route('site.docs', $s) }}" @if ($s === $slug) aria-current="page" @endif>{{ $g['title'] }}</a></li>@endforeach</ul>
    </aside>

    <article class="docs-body">
        @foreach ($guide['sections'] as $id => $section)
            <section id="{{ $id }}">
                <h2>{{ site_md($section[0] ?? '') }}</h2>
                @foreach ($section[1] ?? [] as $block)
                    @switch($block[0] ?? '')
                        @case('p')<p>{{ site_md($block[1] ?? '') }}</p>@break
                        @case('h3')<h3>{{ site_md($block[1] ?? '') }}</h3>@break
                        @case('note')<p class="docs-note">{{ site_md($block[1] ?? '') }}</p>@break
                        @case('code')<pre class="code"><code>{{ $block[1] ?? '' }}</code></pre>@break
                        @case('list')<ul class="docs-list">@foreach ((array) ($block[1] ?? []) as $item)<li>{{ site_md(is_array($item) ? implode(' ', $item) : $item) }}</li>@endforeach</ul>@break
                        @case('faq')@include('site.partials.faq', ['faqs' => (array) ($block[1] ?? []), 'openFirst' => false])@break
                        @case('steps')
                            @foreach ((array) ($block[1] ?? []) as $n => $step)
                                <div class="docs-step"><span class="docs-num">{{ $n + 1 }}</span><div><h3>{{ site_md($step[0] ?? '') }}</h3><p>{{ site_md($step[1] ?? '') }}</p></div></div>
                            @endforeach
                            @break
                        @case('table')
                            <div class="docs-table"><table>
                                <thead><tr>@foreach ((array) ($block[1] ?? []) as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                                <tbody>@foreach ((array) ($block[2] ?? []) as $row)<tr>@foreach ((array) $row as $cell)<td>{{ site_md((string) $cell) }}</td>@endforeach</tr>@endforeach</tbody>
                            </table></div>
                            @break
                    @endswitch
                @endforeach
            </section>
        @endforeach
        <nav class="docs-pager">
            @if ($prev)<a href="{{ route('site.docs', $prev) }}">← {{ $guides[$prev]['title'] }}</a>@else<span></span>@endif
            @if ($next)<a href="{{ route('site.docs', $next) }}">{{ $guides[$next]['title'] }} →</a>@endif
        </nav>
    </article>
</div>

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
