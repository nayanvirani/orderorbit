<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Growvia · Coming soon</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    @include('site.partials.theme')
    <style>
        :root { --ink: var(--c-text); --paper: var(--c-bg); --muted: var(--c-muted); --line: var(--c-line); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { display: grid; min-height: 100vh; place-items: center; padding: 24px 16px; background: var(--paper); color: var(--ink); font: var(--fs-body)/1.55 var(--f-body), system-ui, sans-serif; overflow-x: hidden; }
        main { position: relative; width: min(560px, 100%); text-align: center; }
        .mark { display: block; width: 88px; height: 88px; margin: 0 auto 28px; border-radius: 20px; box-shadow: 0 14px 30px -12px rgba(59, 31, 184, .55); }
        .eyebrow { font: 500 11px/1 var(--f-label), system-ui, sans-serif; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 14px 0 12px; color: var(--c-heading); font: var(--fw-heading) clamp(40px, 9vw, 64px)/1 var(--f-heading), system-ui, sans-serif; letter-spacing: -.01em; }
        h1 .hl, h1 em { color: var(--c-accent); font-style: normal; text-decoration: underline; text-decoration-thickness: .05em; text-underline-offset: .12em; }
        p { margin: 0 auto; max-width: 440px; color: var(--muted); }
        details { margin-top: 40px; }
        summary { display: inline-block; cursor: pointer; font: 500 12px/1 var(--f-label), system-ui, sans-serif; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); list-style: none; }
        summary::-webkit-details-marker { display: none; }
        form { display: flex; gap: 8px; justify-content: center; margin-top: 14px; flex-wrap: wrap; }
        input { flex: 1 1 200px; max-width: 260px; font: inherit; padding: 10px 14px; border: 1px solid var(--c-line-strong); border-radius: var(--radius-btn); }
        input:focus { outline: 2px solid var(--ink); outline-offset: 2px; }
        button { font: 600 14px/1 var(--f-body), sans-serif; padding: 12px 20px; border: 0; border-radius: var(--radius-btn); background: var(--c-accent); color: var(--c-on-accent); cursor: pointer; }
        .error { margin-top: 12px; color: var(--c-danger); font-size: 14px; }
        footer { margin-top: 48px; font-size: 13px; color: var(--muted); }
        footer a { color: inherit; }
    </style>
</head>
<body>
<main>
    <img class="mark" src="/brand/growvia-icon.svg" alt="" width="88" height="88">
    @php($cs = \App\Support\SiteContent::page('coming_soon'))
    <span class="eyebrow">{{ site_md($cs['eyebrow']) }}</span>
    <h1>{{ site_md($cs['title']) }}</h1>
    <p>{{ site_md($cs['text']) }}</p>

    <details @if ($failed) open @endif>
        <summary>{{ $cs['owner'] }}</summary>
        <form method="POST" action="{{ route('site.unlock') }}">
            @csrf
            <label for="password" style="position:absolute;left:-9999px">{{ $cs['password'] }}</label>
            <input id="password" type="password" name="password" placeholder="{{ $cs['password'] }}" autocomplete="current-password" required @if ($failed) autofocus @endif>
            <button type="submit">{{ $cs['enter'] }}</button>
        </form>
        @if ($failed)<p class="error" role="alert">{{ $cs['wrong'] }}</p>@endif
    </details>

    <footer>&copy; {{ date('Y') }} Growvia · <a href="{{ route('site.privacy') }}">Privacy</a> · <a href="{{ route('site.terms') }}">Terms</a> · <a href="{{ route('site.legal.index') }}">All policies</a></footer>
</main>
</body>
</html>
