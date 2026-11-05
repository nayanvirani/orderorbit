@extends('admin.layout')
@section('title', 'Database backups')
@section('content')
@if ($running)<meta http-equiv="refresh" content="5">@endif
<div class="ad-head">
    <div><h1>Database backups</h1><p>A full copy of the database as a compressed SQL file. Make one when you want it, download it, and delete old ones to free space. Nothing runs automatically.</p></div>
    <div class="ad-actions">
        <form method="POST" action="{{ route('admin.backups.create') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Starting…'">
            @csrf<button class="ad-btn primary" type="submit" @disabled($running)>{{ $running ? 'Backing up…' : 'Back up now' }}</button>
        </form>
    </div>
</div>

@if ($error && ! $running)<div class="ad-flash bad"><span>The last backup failed: {{ $error }}</span></div>@endif

@unless ($persistent)
    {{-- One span: .ad-flash is a flex row and would split the sentence into columns. --}}
    <div class="ad-flash bad"><span>No backup volume yet, so files here are lost on the next deploy. In Railway, open the <b>orderorbit</b> service › Settings › Volumes and add a volume with mount path <code>/app/storage/backups</code>. Until then, download each backup right after making it.</span></div>
@endunless

<section class="ad-card flush">
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>File</th><th>Made</th><th>Size</th><th></th></tr></thead>
            <tbody>
                @if ($running)
                    <tr>
                        <td><code>{{ $running['name'] }}</code></td>
                        <td>Started {{ $running['started']->diffForHumans() }}</td>
                        <td>{{ \App\Services\Backups\DatabaseBackup::size($running['size']) }} so far</td>
                        <td><span class="ad-badge">Backing up…</span> <span class="ad-muted ad-small">The site keeps working while this runs.</span></td>
                    </tr>
                @endif
                @forelse ($backups as $b)
                    <tr>
                        <td><code>{{ $b['name'] }}</code></td>
                        <td>{{ $b['at']->format('j M Y, H:i') }} UTC<div class="ad-muted">{{ $b['at']->diffForHumans() }}</div></td>
                        <td>{{ \App\Services\Backups\DatabaseBackup::size($b['size']) }}</td>
                        <td class="ad-actions">
                            <a class="ad-btn" href="{{ route('admin.backups.download', $b['name']) }}">Download</a>
                            <form method="POST" action="{{ route('admin.backups.delete', $b['name']) }}" onsubmit="return confirm('Delete {{ $b['name'] }}? This can\'t be undone.')">
                                @csrf<button class="ad-btn" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty @unless ($running)<tr><td colspan="4" class="ad-empty">No backups yet. Click Back up now.</td></tr>@endunless @endforelse
            </tbody>
        </table>
    </div>
</section>
<p class="ad-muted ad-small">
    {{ count($backups) }} {{ \Illuminate\Support\Str::plural('backup', count($backups)) }}, {{ \App\Services\Backups\DatabaseBackup::size(array_sum(array_column($backups, 'size'))) }} in total
    @if ($free !== null) · {{ \App\Services\Backups\DatabaseBackup::size($free) }} free on this disk @endif.
    Customers' names, emails and order details are encrypted in the database, so they stay encrypted in these files; restore with the same APP_KEY to read them.
    Downloads and deletions are logged. Restore with: <code>gunzip -c FILE.sql.gz | psql DATABASE_URL</code>
</p>
@endsection
