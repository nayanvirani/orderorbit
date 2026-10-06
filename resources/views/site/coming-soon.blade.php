<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>OrderOrbit Space · Coming soon</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #0a0a0a; --paper: #ffffff; --muted: #6b6b6b; --line: #e5e5e5; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { display: grid; min-height: 100vh; place-items: center; padding: 24px 16px; background: var(--paper); color: var(--ink); font: 16px/1.55 Roboto, system-ui, sans-serif; overflow-x: hidden; }
        main { position: relative; width: min(560px, 100%); text-align: center; }
        .orbit { position: relative; width: 132px; height: 132px; margin: 0 auto 28px; }
        .orbit span { position: absolute; inset: 0; border: 1px solid var(--line); border-radius: 50%; }
        .orbit span:nth-child(2) { inset: 22px; border-color: #cfcfcf; }
        .orbit b { position: absolute; top: 50%; left: 50%; width: 30px; height: 30px; margin: -15px 0 0 -15px; border-radius: 50%; background: var(--ink); }
        .orbit i { position: absolute; top: -5px; left: 50%; width: 10px; height: 10px; margin-left: -5px; border-radius: 50%; background: var(--ink); transform-origin: 5px 71px; animation: spin 9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .orbit i { animation: none; } }
        .eyebrow { font: 500 11px/1 Roboto, system-ui, sans-serif; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 14px 0 12px; font: 400 clamp(40px, 9vw, 64px)/1 Roboto, system-ui, sans-serif; letter-spacing: -.01em; }
        h1 em { font-style: italic; text-decoration: underline; text-decoration-thickness: .05em; text-underline-offset: .12em; }
        p { margin: 0 auto; max-width: 440px; color: var(--muted); }
        details { margin-top: 40px; }
        summary { display: inline-block; cursor: pointer; font: 500 12px/1 Roboto, system-ui, sans-serif; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); list-style: none; }
        summary::-webkit-details-marker { display: none; }
        form { display: flex; gap: 8px; justify-content: center; margin-top: 14px; flex-wrap: wrap; }
        input { flex: 1 1 200px; max-width: 260px; font: inherit; padding: 10px 14px; border: 1px solid #bdbdbd; border-radius: 999px; }
        input:focus { outline: 2px solid var(--ink); outline-offset: 2px; }
        button { font: 600 14px/1 Roboto, sans-serif; padding: 12px 20px; border: 0; border-radius: 999px; background: var(--ink); color: #fff; cursor: pointer; }
        .error { margin-top: 12px; color: #b42318; font-size: 14px; }
        footer { margin-top: 48px; font-size: 13px; color: var(--muted); }
        footer a { color: inherit; }
    </style>
</head>
<body>
<main>
    <div class="orbit" aria-hidden="true"><span></span><span></span><b></b><i></i></div>
    <span class="eyebrow">Shopify CRO · checkout · customer experience</span>
    <h1>OrderOrbit Space is <em>coming soon.</em></h1>
    <p>Bundles, progressive gifts and checkout widgets for Shopify stores, all in one app. We're putting on the finishing touches.</p>

    <details @if ($failed) open @endif>
        <summary>Owner access</summary>
        <form method="POST" action="{{ route('site.unlock') }}">
            @csrf
            <label for="password" style="position:absolute;left:-9999px">Password</label>
            <input id="password" type="password" name="password" placeholder="Password" autocomplete="current-password" required @if ($failed) autofocus @endif>
            <button type="submit">Enter</button>
        </form>
        @if ($failed)<p class="error" role="alert">That password isn't right.</p>@endif
    </details>

    <footer>&copy; {{ date('Y') }} OrderOrbit Space · <a href="{{ route('site.privacy') }}">Privacy</a> · <a href="{{ route('site.terms') }}">Terms</a> · <a href="{{ route('site.legal.index') }}">All policies</a></footer>
</main>
</body>
</html>
