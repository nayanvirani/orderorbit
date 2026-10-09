@extends('admin.layout')
@section('title', 'Blog')
@section('content')
@php($labels = ['published' => 'Live', 'scheduled' => 'Scheduled', 'draft' => 'Draft'])
<div class="ad-head">
    <div><h1>Blog</h1><p>Articles on the website's blog. Write in Markdown, preview, then publish now or on a date.</p></div>
    <div class="ad-actions"><a class="ad-btn" href="{{ route('site.blog') }}" target="_blank" rel="noopener">View blog ↗</a><a class="ad-btn primary" href="{{ route('admin.blog.create') }}">New article</a></div>
</div>

<nav class="ad-tabs" aria-label="Filter by status">
    <a href="{{ route('admin.blog') }}" @if (! $status) aria-current="page" @endif>All ({{ $total }})</a>
    @foreach ($labels as $key => $label)
        <a href="{{ route('admin.blog', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
</nav>

<section class="ad-card flush">
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Article</th><th>Status</th><th>Topic</th><th>Publish date</th><th>Last edited</th></tr></thead>
            <tbody>
                @forelse ($posts as $p)
                    @php($s = $state($p))
                    <tr>
                        <td><a class="ad-strong" href="{{ route('admin.blog.edit', $p->id) }}">{{ $p->title }}</a><div class="ad-muted">/blog/{{ $p->slug }}</div></td>
                        <td><span class="ad-badge {{ ['published' => 'ok', 'scheduled' => 'warn', 'draft' => ''][$s] }}">{{ $labels[$s] }}</span>@if ($p->featured)<span class="ad-badge">Featured</span>@endif</td>
                        <td>{{ $p->category ?: '—' }}</td>
                        <td>{{ $p->published_at?->toFormattedDateString() ?? '—' }}</td>
                        <td>{{ $p->updated_at?->diffForHumans() }}<div class="ad-muted">{{ $p->updated_by }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ad-muted">No articles here yet. <a href="{{ route('admin.blog.create') }}">Write the first one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
