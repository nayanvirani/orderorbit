@extends('admin.layout')
@section('title', 'Message from '.$m->name)
@section('content')
@php use App\Models\ContactSubmission; @endphp
<p class="ad-crumbs"><a href="{{ route('admin.contact') }}">Contact inbox</a> / {{ $m->name }}</p>
<div class="ad-head"><div><h1>{{ $m->topic }} · {{ $m->name }}</h1><p>{{ $m->email }} · received {{ $m->created_at->toDayDateTimeString() }} · <span class="ad-badge">{{ ContactSubmission::STATUSES[$m->status] ?? $m->status }}</span></p></div></div>
@if (session('error'))<div class="ad-flash bad">{{ session('error') }}</div>@endif
<div class="ad-grid-2">
    <section class="ad-card ad-thread">
        <h2>Message</h2>
        <div class="ad-msg merchant"><div>{!! nl2br(e($m->message)) !!}</div></div>
        @if ($m->reply)
            <div class="ad-msg team"><div class="ad-muted"><b>Our reply</b> · {{ $m->replier?->name ?? $m->replier?->email }} · {{ $m->replied_at?->toDayDateTimeString() }}</div><div>{!! nl2br(e($m->reply)) !!}</div></div>
        @endif
        <form method="POST" action="{{ route('admin.contact.reply', $m->id) }}" class="ad-form">
            @csrf
            <label>{{ $m->reply ? 'Send another reply' : 'Reply' }} to {{ $m->email }}<textarea name="body" rows="8" required>{{ old('body') }}</textarea></label>
            <button class="ad-btn primary" type="submit">Send reply</button>
        </form>
    </section>
    <section class="ad-card">
        <h2>Details</h2>
        <p>
            <b>{{ $m->name }}</b><br>{{ $m->email }}<br>
            @if ($m->company){{ $m->company }}<br>@endif
            @if ($m->store)Store: <a href="{{ route('admin.store', $m->store_id) }}">{{ $m->store->shop_domain }}</a> · {{ ucfirst($m->store->effectivePlan()) }}@elseif ($m->store_url)Store URL: {{ $m->store_url }} <span class="ad-muted">(not installed)</span>@endif
        </p>
        <form method="POST" action="{{ route('admin.contact.status', $m->id) }}" class="ad-form">
            @csrf
            <label>Status<select name="status">@foreach (ContactSubmission::STATUSES as $k => $l)<option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option>@endforeach</select></label>
            <button class="ad-btn" type="submit">Update</button>
        </form>
        @if ($earlier->isNotEmpty())
            <h2 style="margin-top:18px">Earlier messages from {{ $m->email }}</h2>
            @foreach ($earlier as $e)<p><a href="{{ route('admin.contact.show', $e->id) }}">{{ $e->topic }} · {{ $e->created_at->toFormattedDateString() }}</a><br><span class="ad-muted">{{ \Illuminate\Support\Str::limit($e->message, 80) }}</span></p>@endforeach
        @endif
    </section>
</div>
@endsection
