@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('contact'))
@php($f = $c['form'])

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="section" style="padding-top:80px">
    <div class="wrap split top">
        <div class="stack lg" style="flex:1 1 440px">
            <span class="eyebrow">{{ $c['eyebrow'] }}</span>
            <h1>{{ site_md($c['title']) }}</h1>
            <p class="lead" style="font-size:19px">{{ site_md($c['lead']) }}</p>
            <div class="stack" style="gap:12px;margin-top:8px">
                @foreach ($c['links'] as $link)
                    <a class="link-row" href="{{ site_url($link['href']) }}"><span class="icon-tile"><x-icon :name="['help', 'book', 'layers', 'message'][$loop->index % 4]"/></span><span><b style="font-size:17px">{{ $link['title'] }}</b><br><span class="card-text">{{ site_md($link['text']) }}</span></span><span class="arrow" aria-hidden="true">→</span></a>
                @endforeach
                <div class="link-row ink"><span class="icon-tile" style="background:var(--c-dark-surface);color:var(--c-dark-accent)"><x-icon name="mail"/></span><span><b style="font-size:17px">{{ $c['merchant']['title'] }}</b><br><span class="card-text">{{ site_md($c['merchant']['text']) }}</span></span></div>
            </div>
        </div>

        <div class="form-card" style="flex:1 1 560px">
            <h2 style="font-size:26px">{{ $f['title'] }}</h2>
            @if (session('contact_sent'))<div class="alert ok" role="status">{{ site_md($f['sent']) }}</div>@endif
            @if (session('contact_failed'))<div class="alert bad" role="alert">{{ site_md($f['failed']) }}</div>@endif
            <form method="POST" action="{{ route('site.contact.submit') }}" data-event-form="contact_submitted" novalidate class="stack" style="gap:20px">
                @csrf
                <div class="form-grid">
                    <label class="fld">{{ $f['name'] }}<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld">{{ $f['email'] }}<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld">{{ $f['company'] }}<input name="company" value="{{ old('company') }}" autocomplete="organization"></label>
                    <label class="fld">{{ $f['store_url'] }}<input name="store_url" value="{{ old('store_url') }}" placeholder="{{ $f['store_placeholder'] }}">@error('store_url')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld full">{{ $f['topic'] }}
                        <select name="topic" required>
                            @foreach ($f['topics'] as $value => $label)<option value="{{ $value }}" @selected(old('topic') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="fld full">{{ $f['message'] }}<textarea name="message" required>{{ old('message') }}</textarea>@error('message')<span class="err">{{ $message }}</span>@enderror</label>
                    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                </div>
                <div class="row"><button class="btn primary" type="submit" data-event="contact_submitted">{{ $f['send'] }}</button></div>
                <span class="small dim">{{ site_md($f['privacy']) }}</span>
            </form>
        </div>
    </div>
</section>
@endsection
