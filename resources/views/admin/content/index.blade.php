@extends('admin.layout')
@section('title', 'Website content')
@section('content')
<div class="ad-head">
    <div><h1>Website content</h1><p>Every heading, paragraph, button, list and question on the public website. Edits go live as soon as you save; reset brings back the built-in text. Colours and fonts are in <a href="{{ route('admin.website') }}">Website design</a>, plans and prices in <a href="{{ route('admin.plans') }}">Plans &amp; features</a>, policies in <a href="{{ route('admin.legal') }}">Legal &amp; policies</a>.</p></div>
    <div class="ad-actions"><a class="ad-btn" href="{{ route('site.home') }}" target="_blank" rel="noopener">View website ↗</a></div>
</div>

@foreach ($catalogue as $group => $items)
    <section class="ad-card flush">
        <header><h2>{{ $group }}</h2><span class="ad-muted">{{ count($items) }}</span></header>
        <table class="ad-table">
            <thead><tr><th>Name</th><th>Where</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($items as $key => [$title, $where])
                    <tr>
                        <td><a href="{{ route('admin.content.edit', $key) }}"><b>{{ $title }}</b></a></td>
                        <td class="ad-muted ad-small">@if (str_starts_with((string) $where, '/'))<a href="{{ url($where) }}" target="_blank" rel="noopener">{{ $where }} ↗</a>@else{{ $where }}@endif</td>
                        <td>@if (isset($edited[$key]))<span class="ad-badge ok">Edited {{ \Illuminate\Support\Carbon::parse($edited[$key])->diffForHumans() }}</span>@else<span class="ad-badge">Built-in text</span>@endif</td>
                        <td style="text-align:right"><a class="ad-btn small" href="{{ route('admin.content.edit', $key) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endforeach
@endsection
