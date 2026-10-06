@extends('admin.layout')
@section('title', 'Crawlers & SEO')
@section('content')
@php($searchOpen = \App\Support\Crawlers::searchOpen($settings))
<div class="ad-head">
    <div>
        <h1>Crawlers & SEO</h1>
        <p>Choose which bots may crawl the public website. robots.txt, the pages' robots tags and server blocking follow these settings at once.</p>
    </div>
    <div class="ad-inline">
        <span class="ad-badge {{ $searchOpen ? 'ok' : 'warn' }}">{{ $searchOpen ? 'Open to search engines' : 'Hidden from search engines' }}</span>
        <a class="ad-btn" href="{{ route('site.robots') }}" target="_blank" rel="noopener">View robots.txt</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.crawlers.update') }}" class="ad-form" data-crawlers>
    @csrf
    @foreach ($groups as $key => $group)
        @php($bots = array_keys($group['bots']))
        @php($blockedCount = count(array_filter($bots, fn ($b) => ! empty($settings['blocked'][$b]))))
        <section class="ad-card" data-group="{{ $key }}">
            <header>
                <div>
                    <h2>{{ $group['label'] }} <span class="ad-badge {{ $blockedCount === count($bots) ? 'bad' : ($blockedCount ? 'warn' : 'ok') }}" data-count>{{ $blockedCount === count($bots) ? 'All blocked' : ($blockedCount ? $blockedCount.' of '.count($bots).' blocked' : 'All allowed') }}</span></h2>
                    <p class="ad-muted" style="margin:4px 0 0">{{ $group['help'] }}</p>
                </div>
                <div class="ad-inline">
                    <button type="button" class="ad-btn" data-all="block">Block all</button>
                    <button type="button" class="ad-btn" data-all="allow">Allow all</button>
                </div>
            </header>
            <div class="cr-bots">
                @foreach ($bots as $bot)
                    <label class="ad-check cr-bot"><input type="checkbox" name="blocked[]" value="{{ $bot }}" @checked(! empty($settings['blocked'][$bot]))><span>{{ $bot }}<small>{{ implode(', ', $group['bots'][$bot]) }}</small></span></label>
                @endforeach
            </div>
            <p class="ad-muted" style="margin:10px 0 0">Ticked = blocked.@if (($group['robots'] ?? true) === false) These tools don't read robots.txt; the server refuses them when blocking is enforced.@endif</p>
        </section>
    @endforeach

    <section class="ad-card">
        <h2>Your own rules</h2>
        <div class="ad-fields">
            <label>Extra blocked user agents<textarea name="custom_blocked" rows="4" placeholder="BadBot&#10;SomeCrawler">{{ implode("\n", $settings['custom_blocked']) }}</textarea><small>One per line. Any user agent containing the text is refused (and listed in robots.txt).</small></label>
            <label>Always allowed user agents<textarea name="custom_allowed" rows="4" placeholder="UptimeRobot">{{ implode("\n", $settings['custom_allowed']) }}</textarea><small>One per line. Wins over every block, e.g. your uptime monitor.</small></label>
            <label>Paths hidden from every crawler<textarea name="paths" rows="4">{{ implode("\n", $settings['paths']) }}</textarea><small>One per line, starting with /. Used in robots.txt.</small></label>
        </div>
    </section>

    <section class="ad-card">
        <h2>Enforcement</h2>
        <div class="ad-fields">
            <label class="ad-check"><input type="hidden" name="enforce" value="0"><input type="checkbox" name="enforce" value="1" @checked($settings['enforce'])><span>Refuse blocked bots on the server<small>Blocked bots get "403 Forbidden" even if they ignore robots.txt. Off: robots.txt and tags only.</small></span></label>
            <label class="ad-check"><input type="hidden" name="noai" value="0"><input type="checkbox" name="noai" value="1" @checked($settings['noai'])><span>Tag pages "noai, noimageai"<small>Asks AI tools not to use the content or images.</small></span></label>
        </div>
        <p class="ad-muted" style="margin:12px 0 0">Pages are tagged now: <code>{{ $tag }}</code>. They're "noindex" while every search engine is blocked.</p>
    </section>

    <section class="ad-card">
        <h2>robots.txt now</h2>
        <pre class="cr-robots">{{ $robots }}</pre>
    </section>

    <div class="ad-savebar">
        <button class="ad-btn primary" type="submit">Save crawler settings</button>
    </div>
</form>
<form method="POST" action="{{ route('admin.crawlers.reset') }}" onsubmit="return confirm('Reset every crawler setting to the defaults?')" style="margin-top:10px">
    @csrf
    <button class="ad-btn" type="submit">Reset to defaults</button>
</form>

<style>
    .ad-inline { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .cr-bots { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px 14px; }
    .cr-bot { padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; }
    .cr-bot:has(input:checked) { background: var(--bad-soft); border-color: transparent; }
    .cr-bot small, .ad-check small { display: block; color: var(--muted); font-weight: 400; font-size: 12px; margin-top: 2px; }
    .cr-robots { max-height: 360px; overflow: auto; margin: 0; padding: 12px 14px; border-radius: 9px; background: var(--soft); border: 1px solid var(--line); font: 12px/1.6 ui-monospace, Menlo, monospace; white-space: pre-wrap; }
</style>
<script>
    document.querySelectorAll('[data-crawlers] [data-group]').forEach((group) => {
        const boxes = [...group.querySelectorAll('input[name="blocked[]"]')];
        const count = group.querySelector('[data-count]');
        const refresh = () => {
            const n = boxes.filter((b) => b.checked).length;
            count.textContent = n === boxes.length ? 'All blocked' : n ? `${n} of ${boxes.length} blocked` : 'All allowed';
            count.className = 'ad-badge ' + (n === boxes.length ? 'bad' : n ? 'warn' : 'ok');
        };
        group.querySelectorAll('[data-all]').forEach((btn) => btn.addEventListener('click', () => { boxes.forEach((b) => { b.checked = btn.dataset.all === 'block'; }); refresh(); }));
        boxes.forEach((b) => b.addEventListener('change', refresh));
    });
</script>
@endsection
