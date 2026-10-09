@extends('admin.layout')
@section('title', $post->exists ? $post->title : 'New article')
@section('content')
@php($live = $post->exists && $post->isLive())
<p class="ad-crumbs"><a href="{{ route('admin.blog') }}">Blog</a> / {{ $post->exists ? $post->title : 'New article' }}</p>
<div class="ad-head">
    <div>
        <h1>{{ $post->exists ? $post->title : 'New article' }}</h1>
        @if ($post->exists)
            <p>
                @if ($live)<a href="{{ $post->url() }}" target="_blank" rel="noopener">/blog/{{ $post->slug }} ↗</a> · Live since {{ $post->published_at->toFormattedDateString() }}
                @elseif ($post->status === 'published')Scheduled for {{ $post->published_at->toFormattedDateString() }}
                @else Draft: hidden from the website @endif
                · {{ $post->readingMinutes() }} min read
            </p>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="ad-note warn">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}" class="ad-form">
    @csrf
    <div class="ad-legal">
        <div>
            <section class="ad-card">
                <div class="ad-fields">
                    <label style="grid-column:1/-1">Title<input name="title" value="{{ old('title', $post->title) }}" required maxlength="160"></label>
                    <label style="grid-column:1/-1">Address<span class="ad-input-prefix"><span>/blog/</span><input name="slug" value="{{ old('slug', $post->slug) }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="120" placeholder="made from the title"></span><small>Lowercase letters, numbers and dashes. Changing it breaks links to the old address.</small></label>
                    <label style="grid-column:1/-1">Summary<input name="excerpt" value="{{ old('excerpt', $post->excerpt) }}" maxlength="300"><small>Shown on the blog list and under the title.</small></label>
                </div>
                <label style="margin-top:14px">Article (Markdown)
                    <textarea name="body" class="ad-code" rows="34" required>{{ old('body', $post->body) }}</textarea>
                    <small><code>## Heading</code> starts a section (and an "On this page" link) · <code>**bold**</code> · <code>- item</code> · <code>1. step</code> · <code>[text](https://…)</code> · tables with <code>| a | b |</code></small>
                </label>
            </section>
        </div>

        <aside>
            <section class="ad-card">
                <h2>Publish</h2>
                <label>Publish date (UTC)<input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"><small>Empty publishes now. A future date schedules the article.</small></label>
                <label class="ad-check" style="margin-top:12px"><input type="checkbox" name="featured" value="1" @checked(old('featured', $post->featured))><span>Feature at the top of the blog<small>The newest featured article leads the list.</small></span></label>
                <div class="ad-stack" style="margin-top:14px">
                    @if ($post->status === 'published')
                        <button class="ad-btn primary" type="submit" name="intent" value="save">Save changes</button>
                        <button class="ad-btn" type="submit" name="intent" value="unpublish" onclick="return confirm('Unpublish this article? It disappears from the blog until you publish it again.')">Unpublish</button>
                    @else
                        <button class="ad-btn primary" type="submit" name="intent" value="publish">Publish</button>
                        <button class="ad-btn" type="submit" name="intent" value="save">Save draft</button>
                    @endif
                    <button class="ad-btn" type="submit" formaction="{{ route('admin.blog.preview') }}" formtarget="_blank" formnovalidate>Preview ↗</button>
                </div>
            </section>

            <section class="ad-card">
                <h2>Details</h2>
                <label>Topic<input name="category" value="{{ old('category', $post->category) }}" maxlength="40" list="blog-topics"><small>Shoppers can filter the blog by topic.</small></label>
                <datalist id="blog-topics">@foreach (\App\Models\BlogPost::CATEGORIES as $cat)<option value="{{ $cat }}">@endforeach</datalist>
                <label style="margin-top:12px">Related feature
                    <select name="feature"><option value="">None</option>@foreach ($features as $slug => $name)<option value="{{ $slug }}" @selected(old('feature', $post->feature) === $slug)>{{ $name }}</option>@endforeach</select>
                    <small>Adds a link to the feature page at the end of the article.</small>
                </label>
            </section>

            <section class="ad-card">
                <h2>Search engines</h2>
                <label>SEO title<input name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="160"><small>Empty uses the title. Best under 60 characters.</small></label>
                <label style="margin-top:12px">Meta description<textarea name="seo_description" rows="3" maxlength="300">{{ old('seo_description', $post->seo_description) }}</textarea><small>Empty uses the summary. Best under 160 characters.</small></label>
            </section>
        </aside>
    </div>
</form>

@if ($post->exists)
    <form method="POST" action="{{ route('admin.blog.delete', $post->id) }}" style="margin-top:16px" onsubmit="return confirm('Delete this article for good? This can\'t be undone.')">
        @csrf
        <button class="ad-btn danger" type="submit">Delete article</button>
    </form>
@endif
@endsection
