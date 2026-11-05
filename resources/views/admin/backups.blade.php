@extends('admin.layout')
@section('title', 'Database backups')
@section('content')
@if ($running)<meta http-equiv="refresh" content="5">@endif
<div class="ad-head">
    <div><h1>Database backups</h1><p>A full copy of the database as a compressed SQL file, kept in a private bucket so deploys never remove it. Make one when you want it, download it, and delete old ones to free space. Nothing runs automatically.</p></div>
    <div class="ad-actions">
        <form method="POST" action="{{ route('admin.backups.create') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Starting…'">
            @csrf<button class="ad-btn primary" type="submit" @disabled($running || ! $configured)>{{ $running ? 'Backing up…' : 'Back up now' }}</button>
        </form>
    </div>
</div>

{{-- Messages in one span: .ad-flash is a flex row and would split a sentence into columns. --}}
@unless ($configured)
    <div class="ad-flash bad"><span>The backups bucket isn't connected yet. In Railway, open the bucket and connect it to the <b>orderorbit</b> service (it adds the bucket's variables), then reload this page.</span></div>
@endunless
@if ($error && ! $running)<div class="ad-flash bad"><span>{{ $error }}</span></div>@endif

<section class="ad-card flush">
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>File</th><th>Made</th><th>Size</th><th></th></tr></thead>
            <tbody>
                @if ($running)
                    <tr>
                        <td><code>{{ $running['name'] }}</code></td>
                        <td>Started {{ $running['started']->diffForHumans() }}</td>
                        <td>—</td>
                        <td><span class="ad-badge">Backing up…</span> <span class="ad-muted ad-small">The site keeps working while this runs.</span></td>
                    </tr>
                @endif
                @forelse ($backups as $b)
                    <tr>
                        <td><code>{{ $b['name'] }}</code></td>
                        <td>{{ $b['at']->format('j M Y, H:i') }} UTC<div class="ad-muted">{{ $b['at']->diffForHumans() }}</div></td>
                        <td>{{ \App\Services\Backups\DatabaseBackup::size($b['size']) }}</td>
                        <td>
                            <div class="ad-actions" style="justify-content: flex-start">
                                <a class="ad-btn" href="{{ route('admin.backups.download', $b['name']) }}">Download</a>
                                <form method="POST" action="{{ route('admin.backups.delete', $b['name']) }}" onsubmit="return confirm('Delete this backup from the bucket? This cannot be undone.')">
                                    @csrf<button class="ad-btn" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    @unless ($running)<tr><td colspan="4" class="ad-empty">No backups yet. Click Back up now.</td></tr>@endunless
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<p class="ad-muted ad-small">
    {{ count($backups) }} {{ \Illuminate\Support\Str::plural('backup', count($backups)) }}, {{ \App\Services\Backups\DatabaseBackup::size(array_sum(array_column($backups, 'size'))) }} in the bucket.
    Customers' names, emails and order details are encrypted in the database, so they stay encrypted in these files; restore with the same APP_KEY to read them.
    Downloads and deletions are logged. Restore with: <code>gunzip -c FILE.sql.gz | psql DATABASE_URL</code>
</p>
@endsection
