@extends('layouts.site')

@section('title', $guide['title'].' Guide | OrderOrbit Space Docs')
@section('description', $guide['summary'])

@php
    $md = fn ($t) => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($t));
    $keys = array_keys($guides);
    $i = array_search($slug, $keys, true);
    $prev = $i > 0 ? $keys[$i - 1] : null;
    $next = $i < count($keys) - 1 ? $keys[$i + 1] : null;
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<div class="mn docs">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker"><a href="{{ route('site.docs.index') }}">Docs</a> · {{ $guide['title'] }}</span>
            <h1>{{ $guide['title'] }}</h1>
            <p class="mn-lead">{{ $guide['summary'] }}</p>
        </div>
    </section>

    <div class="docs-layout wrap">
        <aside class="docs-toc" aria-label="On this page">
            <p class="mn-group">On this page</p>
            <ol>@foreach ($guide['sections'] as $id => [$title])<li><a href="#{{ $id }}">{{ $title }}</a></li>@endforeach</ol>
            <p class="mn-group" style="margin-top:24px">All guides</p>
            <ul class="docs-all">@foreach ($guides as $s => $g)<li><a href="{{ route('site.docs', $s) }}" @if ($s === $slug) aria-current="page" @endif>{{ $g['title'] }}</a></li>@endforeach</ul>
        </aside>

        <article class="docs-body">
            @foreach ($guide['sections'] as $id => [$title, $blocks])
                <section id="{{ $id }}">
                    <h2>{{ $title }}</h2>
                    @foreach ($blocks as $block)
                        @switch($block[0])
                            @case('p')<p>{!! $md($block[1]) !!}</p>@break
                            @case('note')<p class="docs-note">{!! $md($block[1]) !!}</p>@break
                            @case('code')<pre class="code"><code>{{ $block[1] }}</code></pre>@break
                            @case('list')<ul class="docs-list">@foreach ($block[1] as $item)<li>{!! $md($item) !!}</li>@endforeach</ul>@break
                            @case('steps')
                                @foreach ($block[1] as $n => [$stepTitle, $text])
                                    <div class="docs-step"><span class="docs-num">{{ $n + 1 }}</span><div><h3>{{ $stepTitle }}</h3><p>{!! $md($text) !!}</p></div></div>
                                @endforeach
                                @break
                            @case('table')
                                <div class="docs-table"><table>
                                    <thead><tr>@foreach ($block[1] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                                    <tbody>@foreach ($block[2] as $row)<tr>@foreach ($row as $cell)<td>{!! $md($cell) !!}</td>@endforeach</tr>@endforeach</tbody>
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
</div>
@endsection
